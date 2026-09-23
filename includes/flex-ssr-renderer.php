<?php
/**
 * Flex SSR (Beta) renderer.
 *
 * Server-side renders a Flex experience from its public manifest: emits the
 * pre-rendered `html-body`, the SSR delivery-mode styles/scripts, and the
 * customer head scripts, then ships the `flex-ssr.js` runtime to hydrate. This
 * is the WordPress analogue of the Ceros AEM connector's "Fetch" mode
 * (ManifestRenderer + DeepLinkResolver).
 *
 * Manifest content is fetched over https from an SSRF-validated host and output
 * largely verbatim — the experience body is arbitrary markup (SVG, custom
 * elements) that an HTML allow-list would corrupt, so we trust the Ceros CDN
 * the same way the AEM connector does.
 *
 * @package ceros
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render a Flex experience server-side from its manifest URL.
 *
 * @param string $manifest_url        The experience's manifest URL (stored on the block).
 * @param bool   $include_custom_html Whether to append the experience's authored
 *                                    custom Body HTML. Defaults true, matching the
 *                                    block attribute default.
 * @return string Rendered HTML, or '' when the manifest could not be fetched
 *                (the caller should fall back to another delivery mode).
 */
function ceros_render_flex_ssr( $manifest_url, $include_custom_html = true ) {
	$manifest = ceros_fetch_flex_manifest( $manifest_url );
	if ( is_wp_error( $manifest ) || ! is_array( $manifest ) ) {
		return '';
	}

	// Follow a deep link (?cer_<slug>=<page>) to the requested page when present.
	$resolved = ceros_flex_ssr_resolve_page( $manifest, $manifest_url );

	return ceros_flex_ssr_render_manifest( $resolved['manifest'], $resolved['url'], $include_custom_html );
}

/**
 * Render a parsed manifest to SSR HTML.
 *
 * Shared by the live path (after fetching) and the Store path (after reading a
 * locally-persisted, URL-rewritten manifest), so both emit identical markup.
 *
 * @param array  $manifest            The parsed manifest (URLs may be remote or local).
 * @param string $served_url          The manifest URL to advertise on the wrapper for
 *                                    the SPA router (deep-link nav). May be ''.
 * @param bool   $include_custom_html Whether to append the experience's authored
 *                                    custom Body HTML.
 * @return string Rendered HTML, or '' when there is nothing renderable.
 */
function ceros_flex_ssr_render_manifest( $manifest, $served_url, $include_custom_html = true ) {
	if ( ! is_array( $manifest ) ) {
		return '';
	}

	$html_body = ceros_flex_ssr_html_body( $manifest );
	$ssr       = isset( $manifest['deliveryModes']['ssr'] ) && is_array( $manifest['deliveryModes']['ssr'] )
		? $manifest['deliveryModes']['ssr']
		: [];

	// Nothing renderable (e.g. SSR delivery mode not published for this experience).
	if ( '' === $html_body && empty( $ssr ) ) {
		return '';
	}

	$styles       = ceros_flex_ssr_styles( $manifest, $ssr );
	$head_scripts = ceros_flex_ssr_head_scripts( $manifest );
	$body_scripts = ceros_flex_ssr_body_scripts( $ssr );

	$wrapper_attrs = '';
	if ( '' !== (string) $served_url ) {
		$wrapper_attrs = ' data-flex-manifest-url="' . esc_url( $served_url ) . '"';
	}

	$content = '<div class="ceros-block__flex-ssr"' . $wrapper_attrs . '>'
		. $html_body
		. '</div>';

	// Appended after the experience markup and the hydration runtime, and
	// emitted verbatim so any <script> in it runs as authored.
	$custom_body = $include_custom_html ? ceros_flex_ssr_custom_body_html( $manifest ) : '';

	if ( ceros_flex_ssr_import_map_needs_own_tag() ) {
		return ceros_flex_ssr_import_map_tag( $manifest ) . $styles . $head_scripts . $content . $body_scripts . $custom_body;
	}

	ceros_flex_ssr_register_import_map( $manifest );
	ceros_flex_ssr_carry_import_map_integrity( ceros_flex_ssr_import_map( $manifest ) );

	if ( 'wp_head' === ceros_flex_ssr_import_map_hook() ) {
		return $styles . $head_scripts . $content . $body_scripts . $custom_body;
	}

	$split = ceros_flex_ssr_split_deferred_scripts( [ $styles, $head_scripts, $content, $body_scripts, $custom_body ] );
	ceros_flex_ssr_print_after_import_map( $split['scripts'] );

	return $split['html'];
}

/**
 * Split out the scripts that must follow WordPress's `wp_footer` import map,
 * leaving an inert placeholder for each.
 *
 * Firefox refuses an import map once a module has started loading. Moved: module
 * scripts, inline scripts that load a module, and, to keep their order, deferred
 * scripts after a module and parse-time scripts after an inline loader in the
 * same block.
 *
 * @param string[] $pieces The block's markup, in order.
 * @return array { @type string $html, @type string[] $scripts }
 */
function ceros_flex_ssr_split_deferred_scripts( $pieces ) {
	static $after_module = false;

	$html          = '';
	$scripts       = [];
	$after_loading = false;

	foreach ( $pieces as $piece ) {
		if ( false === stripos( $piece, '<script' ) ) {
			$html .= $piece;
			continue;
		}

		$moved     = false;
		$skips     = [];
		$processor = new WP_HTML_Tag_Processor( $piece );
		while ( $processor->next_tag( [ 'tag_closers' => 'visit' ] ) ) {
			$tag = $processor->get_tag();

			if ( 'SCRIPT' !== $tag ) {
				$skips = ceros_flex_ssr_track_context( $skips, $processor );
				continue;
			}
			if ( $processor->is_tag_closer() || ceros_flex_ssr_in_skipped_context( $skips ) || ! ceros_flex_ssr_is_javascript( $processor ) ) {
				continue;
			}

			$timing = ceros_flex_ssr_script_timing( $processor );
			if ( 'module' === $timing ) {
				$after_module = true;
			} elseif ( 'parse' === $timing && ceros_flex_ssr_calls_import( $processor ) ) {
				$after_loading = true;
			} elseif ( ! ( 'defer' === $timing && $after_module ) && ! ( 'parse' === $timing && $after_loading ) ) {
				continue;
			}

			// The placeholder keeps the attributes for lookups by id, under an inert
			// type that consent managers do not re-enable as they do text/plain.
			// Only the placeholder keeps the id.
			$attributes = [];
			foreach ( (array) $processor->get_attribute_names_with_prefix( '' ) as $name ) {
				if ( 'id' !== $name ) {
					$attributes[ $name ] = $processor->get_attribute( $name );
				}
			}
			$scripts[] = ceros_flex_ssr_rebuild_script( $attributes, $processor->get_modifiable_text() );

			$processor->set_attribute( 'type', 'application/x-ceros-moved' );
			$processor->set_modifiable_text( '' );
			$moved = true;
		}

		$html .= $moved ? $processor->get_updated_html() : $piece;
	}

	return [
		'html'    => $html,
		'scripts' => $scripts,
	];
}

/**
 * Track the open elements that decide whether a script runs as HTML: inert ones
 * (template, unless a declarative shadow root; noscript), SVG and MathML, and
 * their HTML integration points.
 *
 * @param array                 $open      [ tag, kind ] pairs, outermost first.
 * @param WP_HTML_Tag_Processor $processor A processor on a tag other than SCRIPT.
 * @return array The updated pairs.
 */
function ceros_flex_ssr_track_context( $open, $processor ) {
	static $breakout = null;
	static $html_end = null;
	if ( null === $breakout ) {
		// HTML start tags that end SVG or MathML content.
		$breakout = array_flip( [ 'B', 'BIG', 'BLOCKQUOTE', 'BODY', 'BR', 'CENTER', 'CODE', 'DD', 'DIV', 'DL', 'DT', 'EM', 'EMBED', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'HEAD', 'HR', 'I', 'IMG', 'LI', 'LISTING', 'MENU', 'META', 'NOBR', 'OL', 'P', 'PRE', 'RUBY', 'S', 'SMALL', 'SPAN', 'STRONG', 'STRIKE', 'SUB', 'SUP', 'TABLE', 'TT', 'U', 'UL', 'VAR' ] );
		// End tags of HTML-only elements, which end SVG or MathML content opened
		// inside them.
		$html_end = $breakout + array_flip( [ 'ARTICLE', 'ASIDE', 'BUTTON', 'DETAILS', 'FIGCAPTION', 'FIGURE', 'FOOTER', 'FORM', 'HEADER', 'LABEL', 'MAIN', 'NAV', 'SECTION', 'SUMMARY', 'TBODY', 'TD', 'TFOOT', 'TH', 'THEAD', 'TR' ] );
	}

	$tag    = $processor->get_tag();
	$closer = $processor->is_tag_closer();
	$last   = end( $open );
	$space  = false !== $last && 'foreign' === $last[1] ? ( 'SVG' === $last[0] ? 'SVG' : 'MATH' ) : 'HTML';

	if ( 'HTML' !== $space ) {
		$font_breaks = 'FONT' === $tag && ! $closer && ( null !== $processor->get_attribute( 'color' ) || null !== $processor->get_attribute( 'face' ) || null !== $processor->get_attribute( 'size' ) );
		if ( $font_breaks || ( $closer ? isset( $html_end[ $tag ] ) : isset( $breakout[ $tag ] ) ) ) {
			while ( ! empty( $open ) && 'foreign' === end( $open )[1] ) {
				array_pop( $open );
			}
			return $open;
		}
	}

	if ( $closer ) {
		// Pop to the matching opener, as a browser does.
		$matches = array_keys( array_column( $open, 0 ), $tag, true );
		return empty( $matches ) ? $open : array_slice( $open, 0, end( $matches ) );
	}

	$self_closing = $processor->has_self_closing_flag();
	if ( 'SVG' === $tag || 'MATH' === $tag ) {
		if ( ! $self_closing ) {
			$open[] = [ $tag, 'foreign' ];
		}
	} elseif ( 'SVG' === $space && ( 'FOREIGNOBJECT' === $tag || 'DESC' === $tag ) ) {
		if ( ! $self_closing ) {
			$open[] = [ $tag, 'html' ];
		}
	} elseif ( 'MATH' === $space && in_array( $tag, [ 'MI', 'MO', 'MN', 'MS', 'MTEXT' ], true ) ) {
		if ( ! $self_closing ) {
			$open[] = [ $tag, 'html' ];
		}
	} elseif ( 'MATH' === $space && 'ANNOTATION-XML' === $tag ) {
		if ( ! $self_closing ) {
			$encoding = $processor->get_attribute( 'encoding' );
			$html     = is_string( $encoding ) && in_array( strtolower( $encoding ), [ 'text/html', 'application/xhtml+xml' ], true );
			$open[]   = [ $tag, $html ? 'html' : 'foreign' ];
		}
	} elseif ( 'HTML' === $space && ( 'TEMPLATE' === $tag || 'NOSCRIPT' === $tag ) ) {
		$mode   = 'TEMPLATE' === $tag ? $processor->get_attribute( 'shadowrootmode' ) : null;
		$live   = is_string( $mode ) && in_array( strtolower( $mode ), [ 'open', 'closed' ], true );
		$open[] = [ $tag, $live ? 'html' : 'inert' ];
	}

	return $open;
}

/**
 * Whether a script at this point is inert, or an SVG or MathML one.
 *
 * @param array $open [ tag, kind ] pairs from ceros_flex_ssr_track_context().
 * @return bool
 */
function ceros_flex_ssr_in_skipped_context( $open ) {
	$kinds = array_column( $open, 1 );

	return in_array( 'inert', $kinds, true ) || 'foreign' === end( $kinds );
}

/**
 * Rebuild a script tag from decoded attributes and text, applying WordPress's
 * script-attribute filters.
 *
 * Not `wp_get_script_tag()`: newer WordPress sanitizes `src` there, dropping
 * `data:` URLs.
 *
 * @param array  $attributes Attribute name => decoded value, or true for a bare one.
 * @param string $text       The script's text.
 * @return string The <script> tag.
 */
function ceros_flex_ssr_rebuild_script( $attributes, $text ) {
	// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's filters, so nonce plugins see these tags.
	$attributes = isset( $attributes['src'] )
		? apply_filters( 'wp_script_attributes', $attributes )
		: apply_filters( 'wp_inline_script_attributes', $attributes, $text );
	// phpcs:enable

	$tag = '<script';
	foreach ( $attributes as $name => $value ) {
		if ( true === $value ) {
			$tag .= ' ' . $name;
		} elseif ( false !== $value && null !== $value ) {
			$tag .= ' ' . $name . '="' . htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) . '"';
		}
	}

	return $tag . '>' . $text . '</script>' . "\n";
}

/**
 * When the script runs: 'module', 'defer' (classic with `defer`), 'async', or
 * 'parse' (when the parser reaches it).
 *
 * @param WP_HTML_Tag_Processor $processor A processor on a SCRIPT tag.
 * @return string
 */
function ceros_flex_ssr_script_timing( $processor ) {
	if ( 'module' === ceros_flex_ssr_script_type( $processor ) ) {
		return 'module';
	}
	if ( null === $processor->get_attribute( 'src' ) ) {
		return 'parse';
	}
	if ( null !== $processor->get_attribute( 'async' ) ) {
		return 'async';
	}

	return null !== $processor->get_attribute( 'defer' ) ? 'defer' : 'parse';
}

/**
 * Whether the inline script loads a module: it calls `import()` or sets a
 * script's type to `module`.
 *
 * @param WP_HTML_Tag_Processor $processor A processor on a SCRIPT tag.
 * @return bool
 */
function ceros_flex_ssr_calls_import( $processor ) {
	return null === $processor->get_attribute( 'src' )
		&& 1 === preg_match( '/(?<![\w$.])import\s*\(|\.type\s*=\s*[\'"]module[\'"]|setAttribute\(\s*[\'"]type[\'"]\s*,\s*[\'"]module[\'"]/', $processor->get_modifiable_text() );
}

/**
 * Whether a browser runs the script: a module, or a classic script of a
 * JavaScript MIME type.
 *
 * @param WP_HTML_Tag_Processor $processor A processor on a SCRIPT tag.
 * @return bool
 */
function ceros_flex_ssr_is_javascript( $processor ) {
	$type = ceros_flex_ssr_script_type( $processor );

	return '' === $type
		|| 'module' === $type
		|| 1 === preg_match( '#^(?:(?:text|application)/(?:x-)?(?:java|ecma)script|text/javascript1\.[0-5]|text/(?:jscript|livescript))$#', $type );
}

/**
 * The script's `type`, lowercased, without parameters.
 *
 * @param WP_HTML_Tag_Processor $processor A processor on a SCRIPT tag.
 * @return string
 */
function ceros_flex_ssr_script_type( $processor ) {
	$type = $processor->get_attribute( 'type' );
	if ( ! is_string( $type ) ) {
		return '';
	}

	$parts = explode( ';', $type );
	return strtolower( trim( $parts[0] ) );
}

/**
 * Queue scripts to print on `wp_footer` straight after WordPress's import map.
 *
 * @param string[] $scripts The script tags.
 * @return void
 */
function ceros_flex_ssr_print_after_import_map( $scripts ) {
	static $queued = '';
	static $hooked = false;

	$queued .= implode( '', $scripts );
	if ( $hooked || '' === $queued ) {
		return;
	}
	$hooked = true;

	add_action(
		'wp_footer',
		static function () use ( &$queued ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- moved script tags, attributes re-encoded.
			echo $queued;
			$queued = '';
		},
		(int) ceros_flex_ssr_import_map_priority() + 1
	);
}

/**
 * Carry an experience's SRI hashes into WordPress's import map, which holds
 * only `imports`.
 *
 * @param array $map The experience's import map.
 * @return void
 */
function ceros_flex_ssr_carry_import_map_integrity( $map ) {
	static $hooked = false;

	$integrity = ceros_flex_ssr_import_map_integrity( $map );
	if ( $hooked || empty( $integrity ) ) {
		return;
	}
	$hooked = true;

	$hook     = ceros_flex_ssr_import_map_hook();
	$priority = (int) ceros_flex_ssr_import_map_priority();
	add_action( $hook, 'ceros_flex_ssr_buffer_import_map', $priority - 1 );
	add_action( $hook, 'ceros_flex_ssr_print_buffered_import_map', $priority );
}

/**
 * The URL => hash entries carried so far; the first hash for a URL wins.
 *
 * @param array|null $map An import map to add, or null to only read.
 * @return array
 */
function ceros_flex_ssr_import_map_integrity( $map = null ) {
	static $integrity = [];

	if ( is_array( $map ) && isset( $map['integrity'] ) && is_array( $map['integrity'] ) ) {
		foreach ( $map['integrity'] as $url => $hash ) {
			if ( is_string( $url ) && '' !== $url && is_string( $hash ) && '' !== $hash && ! isset( $integrity[ $url ] ) ) {
				$integrity[ $url ] = $hash;
			}
		}
	}

	return $integrity;
}

/**
 * Buffer the output around WordPress's import map.
 *
 * @return void
 */
function ceros_flex_ssr_buffer_import_map() {
	ob_start();
	ceros_flex_ssr_import_map_buffer_level( ob_get_level() );
}

/**
 * Level of the output buffer around WordPress's import map, or 0 when none is
 * open.
 *
 * @param int|null $set The new level, or null to only read.
 * @return int
 */
function ceros_flex_ssr_import_map_buffer_level( $set = null ) {
	static $level = 0;

	if ( null !== $set ) {
		$level = $set;
	}
	return $level;
}

/**
 * Print the buffered output with the carried hashes merged into WordPress's
 * import map.
 *
 * @return void
 */
function ceros_flex_ssr_print_buffered_import_map() {
	$level = ceros_flex_ssr_import_map_buffer_level();
	ceros_flex_ssr_import_map_buffer_level( 0 );

	// A newer buffer on top is not ours to close; ours then flushes unchanged.
	if ( 0 === $level || ob_get_level() !== $level ) {
		return;
	}

	$html      = (string) ob_get_clean();
	$processor = new WP_HTML_Tag_Processor( $html );

	while ( $processor->next_tag( 'SCRIPT' ) ) {
		if ( 'wp-importmap' !== $processor->get_attribute( 'id' ) ) {
			continue;
		}
		// As objects, so an empty `{}` re-encodes as `{}`, not `[]`.
		$map = json_decode( $processor->get_modifiable_text() );
		if ( $map instanceof stdClass ) {
			$existing       = isset( $map->integrity ) && $map->integrity instanceof stdClass ? (array) $map->integrity : [];
			$map->integrity = (object) ( $existing + ceros_flex_ssr_import_map_integrity() );
			$processor->set_modifiable_text( (string) wp_json_encode( $map, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ) );
			$html = $processor->get_updated_html();
		}
		break;
	}

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress's own output, with its import map re-encoded.
	echo $html;
}

/**
 * Resolve the manifest/page to render, honouring the inline SPA router's
 * deep-link query param (`?cer_<experienceSlug>=<pageSlug>`, with the
 * `?cer_<accountSlug>__<experienceSlug>` collision fallback).
 *
 * @param array  $manifest     The primary manifest.
 * @param string $manifest_url The primary manifest URL.
 * @return array { @type array $manifest, @type string $url }
 */
function ceros_flex_ssr_resolve_page( $manifest, $manifest_url ) {
	$default = [
		'manifest' => $manifest,
		'url'      => $manifest_url,
	];

	$requested = ceros_flex_ssr_requested_slug( $manifest );
	if ( null === $requested ) {
		return $default;
	}

	$experience   = isset( $manifest['experience'] ) ? $manifest['experience'] : [];
	$current_slug = isset( $experience['pageSlug'] ) ? $experience['pageSlug'] : '';
	if ( $requested === $current_slug ) {
		return $default;
	}

	foreach ( ( isset( $manifest['pages'] ) ? $manifest['pages'] : [] ) as $page ) {
		if ( ! is_array( $page ) || ( isset( $page['slug'] ) ? $page['slug'] : '' ) !== $requested ) {
			continue;
		}
		if ( ! empty( $page['current'] ) ) {
			return $default;
		}
		$page_url = isset( $page['manifestUrl'] ) ? $page['manifestUrl'] : '';
		if ( '' === $page_url ) {
			return $default;
		}
		$page_manifest = ceros_fetch_flex_manifest( $page_url );
		if ( is_wp_error( $page_manifest ) || ! is_array( $page_manifest ) ) {
			return $default;
		}
		return [
			'manifest' => $page_manifest,
			'url'      => $page_url,
		];
	}

	return $default;
}

/**
 * Read the page slug requested by the deep-link query param for this experience.
 *
 * @param array $manifest The manifest.
 * @return string|null The requested slug, or null when none is present.
 */
function ceros_flex_ssr_requested_slug( $manifest ) {
	$experience = isset( $manifest['experience'] ) ? $manifest['experience'] : [];
	$slug       = isset( $experience['slug'] ) ? $experience['slug'] : '';
	if ( '' === $slug ) {
		return null;
	}

	$value = ceros_flex_ssr_query_param( 'cer_' . $slug );

	if ( '' === $value && ! empty( $experience['accountSlug'] ) ) {
		$value = ceros_flex_ssr_query_param( 'cer_' . $experience['accountSlug'] . '__' . $slug );
	}

	return '' === $value ? null : $value;
}

/**
 * Read and sanitize a query-string parameter from the current request.
 *
 * @param string $key The parameter name.
 * @return string The sanitized value, or '' when absent.
 */
function ceros_flex_ssr_query_param( $key ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation param, no state change.
	if ( ! isset( $_GET[ $key ] ) ) {
		return '';
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
}

/**
 * Extract the raw pre-rendered experience body from the `html-body` asset.
 *
 * @param array $manifest The manifest.
 * @return string The html-body content, or '' when absent.
 */
function ceros_flex_ssr_html_body( $manifest ) {
	foreach ( ( isset( $manifest['assets'] ) ? $manifest['assets'] : [] ) as $asset ) {
		if ( is_array( $asset ) && ( isset( $asset['type'] ) ? $asset['type'] : '' ) === 'html-body' ) {
			$src = isset( $asset['src'] ) ? $asset['src'] : [];
			return isset( $src['content'] ) && is_string( $src['content'] ) ? $src['content'] : '';
		}
	}
	return '';
}

/**
 * Extract the experience's authored custom Body HTML.
 *
 * Lives in `displayMetadata` rather than `assets[]`. This is whatever the
 * author entered in the experience's Custom HTML settings.
 *
 * @param array $manifest The manifest.
 * @return string The custom body HTML, or '' when absent.
 */
function ceros_flex_ssr_custom_body_html( $manifest ) {
	$display = isset( $manifest['displayMetadata'] ) ? $manifest['displayMetadata'] : [];
	$html    = isset( $display['customBodyHtml'] ) ? $display['customBodyHtml'] : '';
	return is_string( $html ) ? $html : '';
}

/**
 * The experience's import map, or [] when the manifest carries none.
 *
 * SSR deliveries are not served the manifest's import map, so the page prints it.
 * An empty map is dropped, since Firefox honours only a page's first map.
 *
 * @param array $manifest The manifest.
 * @return array The import map, or [] when none should be emitted.
 */
function ceros_flex_ssr_import_map( $manifest ) {
	$map     = isset( $manifest['importMap'] ) ? $manifest['importMap'] : [];
	$imports = isset( $map['imports'] ) ? $map['imports'] : [];
	if ( ! is_array( $imports ) || empty( $imports ) ) {
		return [];
	}

	// An entry that is not a specifier/URL pair of strings would make the whole
	// map invalid, so drop it rather than pass it on.
	$clean = [];
	foreach ( $imports as $specifier => $url ) {
		if ( is_string( $specifier ) && '' !== $specifier && is_string( $url ) && '' !== $url ) {
			$clean[ $specifier ] = $url;
		}
	}
	if ( empty( $clean ) ) {
		return [];
	}

	$map['imports'] = $clean;
	if ( isset( $map['integrity'] ) && ! is_array( $map['integrity'] ) ) {
		unset( $map['integrity'] );
	}

	return $map;
}

/**
 * Whether this render has to print an import map of its own rather than adding
 * its specifiers to the one WordPress prints.
 *
 * @return bool
 */
function ceros_flex_ssr_import_map_needs_own_tag() {
	if ( wp_is_rest_endpoint() || wp_doing_ajax() || is_embed() || is_feed() || ceros_flex_ssr_import_map_printed() ) {
		return true;
	}

	// Classic themes render the body between `wp_head` and `wp_footer`; a render
	// outside that may never reach a footer.
	return 'wp_footer' === ceros_flex_ssr_import_map_hook() && ( ! did_action( 'wp_head' ) || doing_action( 'wp_head' ) );
}

/**
 * The hook WordPress prints its import map on, or '' when it is unhooked.
 *
 * @return string
 */
function ceros_flex_ssr_import_map_hook() {
	foreach ( [ 'wp_head', 'wp_footer' ] as $hook ) {
		if ( false !== has_action( $hook, [ wp_script_modules(), 'print_import_map' ] ) ) {
			return $hook;
		}
	}
	return '';
}

/**
 * The priority WordPress prints its import map at, or false when it is unhooked.
 *
 * @return int|false
 */
function ceros_flex_ssr_import_map_priority() {
	$hook = ceros_flex_ssr_import_map_hook();
	return '' === $hook ? false : has_action( $hook, [ wp_script_modules(), 'print_import_map' ] );
}

/**
 * Whether it is too late to add specifiers to WordPress's import map.
 *
 * @param bool $mark True to record that WordPress has printed it.
 * @return bool
 */
function ceros_flex_ssr_import_map_printed( $mark = false ) {
	static $printed = false;

	if ( $mark ) {
		$printed = true;
	}

	$hook = ceros_flex_ssr_import_map_hook();
	return $printed || '' === $hook || ( did_action( $hook ) && ! doing_action( $hook ) );
}

add_action( 'after_setup_theme', 'ceros_flex_ssr_watch_import_map', 11 );

/**
 * Mark WordPress's import map as printed, right after it prints. Registered
 * after core's own `after_setup_theme` hook, so it runs after core's callback at
 * the same priority.
 *
 * @return void
 */
function ceros_flex_ssr_watch_import_map() {
	$priority = ceros_flex_ssr_import_map_priority();
	if ( false !== $priority ) {
		add_action( ceros_flex_ssr_import_map_hook(), 'ceros_flex_ssr_mark_import_map_printed', $priority );
	}
}

/**
 * Mark WordPress's import map as printed.
 *
 * @return void
 */
function ceros_flex_ssr_mark_import_map_printed() {
	ceros_flex_ssr_import_map_printed( true );
}

/**
 * Register the experience's import map with WordPress.
 *
 * Firefox honours only a page's first import map, so the specifiers join
 * WordPress's. WordPress maps an enqueued module's dependencies, so they ride on
 * an empty anchor module.
 *
 * @param array $manifest The manifest.
 * @return void
 */
function ceros_flex_ssr_register_import_map( $manifest ) {
	static $carried = [];
	static $anchors = 0;

	$map = ceros_flex_ssr_import_map( $manifest );
	if ( empty( $map ) ) {
		return;
	}

	foreach ( $map['imports'] as $specifier => $url ) {
		// A null version leaves the manifest's URL untouched. A specifier
		// already registered by an earlier block keeps its first URL.
		wp_register_script_module( $specifier, $url, [], null );
	}

	// An anchor's dependencies are fixed once it is registered, so a block
	// naming specifiers no earlier one carried gets an anchor of its own.
	$fresh = array_values( array_diff( array_keys( $map['imports'] ), $carried ) );
	if ( empty( $fresh ) ) {
		return;
	}

	++$anchors;
	$carried = array_merge( $carried, $fresh );

	// Dynamic, so WordPress maps them without emitting a modulepreload for each.
	$dependencies = [];
	foreach ( $fresh as $specifier ) {
		$dependencies[] = [
			'id'     => $specifier,
			'import' => 'dynamic',
		];
	}

	wp_enqueue_script_module(
		'ceros-flex-import-map-' . $anchors,
		plugin_dir_url( CEROS_PLUGIN_FILE ) . 'public/import-map-anchor.js',
		$dependencies,
		null
	);
}

/**
 * Build a standalone `<script type="importmap">` tag, or '' when none is needed.
 *
 * @param array $manifest The manifest.
 * @return string The <script> tag, or ''.
 */
function ceros_flex_ssr_import_map_tag( $manifest ) {
	$map = ceros_flex_ssr_import_map( $manifest );
	if ( empty( $map ) ) {
		return '';
	}

	$json = wp_json_encode( $map );
	if ( ! is_string( $json ) ) {
		return '';
	}

	// Escape "<" so no URL in the map can close the script element it sits in
	// ("</script>", "<!--"). \u003c is valid JSON and parses back to "<", so
	// the map the browser reads is unchanged.
	return '<script type="importmap">' . str_replace( '<', '\\u003c', $json ) . '</script>' . "\n";
}

/**
 * Build the <link> tags for SSR styles, with webfonts prepended so they preload
 * ahead of the component styles (matching the AEM connector ordering).
 *
 * @param array $manifest The manifest.
 * @param array $ssr      The `deliveryModes.ssr` object.
 * @return string Concatenated <link> tags.
 */
function ceros_flex_ssr_styles( $manifest, $ssr ) {
	$assets = isset( $manifest['assets'] ) ? $manifest['assets'] : [];

	// Webfonts first, so faces are registered before the content renders. A
	// webfont asset is either an external stylesheet (Google Fonts → <link>) or
	// an inline @font-face block for a custom font (→ <style>); emit both.
	$out = ceros_flex_ssr_asset_styles( $assets, 'webfont' );

	// Shared SSR delivery styles (components.css, reset.css …).
	foreach ( ( isset( $ssr['styles'] ) ? $ssr['styles'] : [] ) as $style ) {
		if ( is_array( $style ) && ! empty( $style['url'] ) ) {
			$out .= ceros_flex_ssr_style_tag( $style['url'], isset( $style['integrity'] ) ? $style['integrity'] : '' );
		}
	}

	// Per-experience style assets (brand-kit overrides) after the shared styles
	// so they win the cascade.
	$out .= ceros_flex_ssr_asset_styles( $assets, 'style' );

	return $out;
}

/**
 * Emit the styles for every asset of a given type: an external `src.url` as a
 * <link>, an inline `src.content` as a verbatim <style> block.
 *
 * @param array  $assets The manifest `assets[]`.
 * @param string $type   The asset type to emit (`webfont` or `style`).
 * @return string Concatenated <link>/<style> tags.
 */
function ceros_flex_ssr_asset_styles( $assets, $type ) {
	$out = '';
	foreach ( $assets as $asset ) {
		if ( ! is_array( $asset ) || ( isset( $asset['type'] ) ? $asset['type'] : '' ) !== $type ) {
			continue;
		}
		$src = isset( $asset['src'] ) ? $asset['src'] : [];
		if ( ! empty( $src['url'] ) ) {
			$out .= ceros_flex_ssr_style_tag( $src['url'], isset( $src['integrity'] ) ? $src['integrity'] : '' );
		} elseif ( ! empty( $src['content'] ) && is_string( $src['content'] ) ) {
			// Inline @font-face / style block — output verbatim (trusted, from
			// the SSRF-validated manifest host, same posture as the html-body).
			$out .= '<style>' . $src['content'] . '</style>' . "\n";
		}
	}
	return $out;
}

/**
 * Build a single stylesheet <link> tag.
 *
 * @param string $url       The stylesheet URL.
 * @param string $integrity Optional SRI hash.
 * @return string The <link> tag.
 */
function ceros_flex_ssr_style_tag( $url, $integrity ) {
	// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- same as the script tags below: a manifest-supplied stylesheet emitted inline with the SSR body.
	$tag = '<link rel="stylesheet" href="' . esc_url( $url ) . '"';
	if ( ! empty( $integrity ) ) {
		$tag .= ' integrity="' . esc_attr( $integrity ) . '"';
	}
	if ( preg_match( '#^https?://#i', $url ) ) {
		$tag .= ' crossorigin="anonymous"';
	}
	return $tag . ' />' . "\n";
}

/**
 * Build the head <script> tags lifted from customer HTML (`assets[]` of type
 * `script`).
 *
 * @param array $manifest The manifest.
 * @return string Concatenated <script> tags.
 */
function ceros_flex_ssr_head_scripts( $manifest ) {
	$out = '';
	foreach ( ( isset( $manifest['assets'] ) ? $manifest['assets'] : [] ) as $asset ) {
		if ( ! is_array( $asset ) || ( isset( $asset['type'] ) ? $asset['type'] : '' ) !== 'script' ) {
			continue;
		}
		$src  = isset( $asset['src'] ) ? $asset['src'] : [];
		$meta = isset( $asset['metadata'] ) ? $asset['metadata'] : [];

		$out .= ceros_flex_ssr_script_tag(
			[
				'inline'       => ( isset( $src['type'] ) ? $src['type'] : '' ) === 'inline',
				'content'      => isset( $src['content'] ) ? $src['content'] : '',
				'type'         => isset( $src['mimeType'] ) ? $src['mimeType'] : '',
				'url'          => isset( $src['url'] ) ? $src['url'] : '',
				// Per the manifest contract, customer scripts default to ES modules.
				'module'       => array_key_exists( 'module', $meta ) ? (bool) $meta['module'] : true,
				'loadStrategy' => isset( $meta['loadStrategy'] ) ? $meta['loadStrategy'] : 'defer',
				'integrity'    => isset( $src['integrity'] ) ? $src['integrity'] : '',
				'id'           => isset( $asset['name'] ) ? $asset['name'] : '',
			]
		);
	}
	return $out;
}

/**
 * Build the body <script> tags from the SSR delivery mode (the flex-ssr.js
 * hydration runtime).
 *
 * @param array $ssr The `deliveryModes.ssr` object.
 * @return string Concatenated <script> tags.
 */
function ceros_flex_ssr_body_scripts( $ssr ) {
	$out = '';
	foreach ( ( isset( $ssr['scripts'] ) ? $ssr['scripts'] : [] ) as $script ) {
		if ( ! is_array( $script ) || empty( $script['url'] ) ) {
			continue;
		}
		$out .= ceros_flex_ssr_script_tag(
			[
				'url'          => $script['url'],
				'module'       => ! empty( $script['module'] ),
				'loadStrategy' => isset( $script['loadStrategy'] ) ? $script['loadStrategy'] : 'defer',
				'integrity'    => isset( $script['integrity'] ) ? $script['integrity'] : '',
			]
		);
	}
	return $out;
}

/**
 * Build a single <script> tag from a descriptor.
 *
 * Inline script content is emitted verbatim (same trust posture as the html
 * body); external scripts carry SRI + crossorigin when an integrity hash is
 * present.
 *
 * @param array $args Script descriptor (inline/content/type/url/module/loadStrategy/integrity/id).
 * @return string The <script> tag.
 */
function ceros_flex_ssr_script_tag( $args ) {
	if ( ! empty( $args['inline'] ) ) {
		$id   = ! empty( $args['id'] ) ? ' id="' . esc_attr( $args['id'] ) . '"' : '';
		$type = ! empty( $args['type'] ) ? ' type="' . esc_attr( $args['type'] ) . '"' : '';
		return '<script' . $id . $type . '>' . $args['content'] . '</script>' . "\n";
	}

	if ( empty( $args['url'] ) ) {
		return '';
	}

	// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- builds a <script> tag for a manifest-supplied runtime, emitted inline with the SSR body; there is no enqueue phase at block-render time.
	$tag = '<script src="' . esc_url( $args['url'] ) . '"';
	if ( ! empty( $args['module'] ) ) {
		$tag .= ' type="module"';
	}
	$tag .= ( 'async' === ( isset( $args['loadStrategy'] ) ? $args['loadStrategy'] : 'defer' ) ) ? ' async' : ' defer';
	if ( ! empty( $args['integrity'] ) ) {
		$tag .= ' integrity="' . esc_attr( $args['integrity'] ) . '" crossorigin="anonymous"';
	}
	if ( ! empty( $args['id'] ) ) {
		$tag .= ' id="' . esc_attr( $args['id'] ) . '"';
	}
	return $tag . '></script>' . "\n";
}
