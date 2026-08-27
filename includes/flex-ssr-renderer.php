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
 *                                    custom Body HTML (and the import map its
 *                                    module scripts need).
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

	// A page joins the import map WordPress already prints, which under a block
	// theme is in the head. A classic theme prints it below the content, after
	// the module scripts, and the block renderer behind the editor preview emits
	// no page head at all; both take an inline map instead, which has to precede
	// those scripts.
	$import_map = '';
	if ( wp_is_rest_endpoint() || ! wp_is_block_theme() ) {
		$import_map = ceros_flex_ssr_import_map_tag( $manifest, $custom_body );
	} else {
		ceros_flex_ssr_register_import_map( $manifest, $custom_body );
	}

	return $import_map . $styles . $head_scripts . $content . $body_scripts . $custom_body;
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
 * The experience's import map, or [] when the page needs none.
 *
 * Custom body HTML may import a module by bare specifier, which only resolves
 * against an import map. The manifest carries one; SSR deliveries are not
 * served it automatically, so the consumer supplies it.
 *
 * Returned verbatim, and only when the custom body HTML names one of its
 * specifiers, so a page that needs no map is left alone.
 *
 * @param array  $manifest         The manifest.
 * @param string $custom_body_html The custom body HTML about to be emitted.
 * @return array The import map, or [] when none should be emitted.
 */
function ceros_flex_ssr_import_map( $manifest, $custom_body_html ) {
	if ( ! is_string( $custom_body_html ) || '' === $custom_body_html ) {
		return [];
	}

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
	$used = false;
	foreach ( array_keys( $clean ) as $specifier ) {
		if ( false !== strpos( $custom_body_html, $specifier ) ) {
			$used = true;
			break;
		}
	}
	if ( ! $used ) {
		return [];
	}

	$map['imports'] = $clean;
	if ( isset( $map['integrity'] ) && ! is_array( $map['integrity'] ) ) {
		unset( $map['integrity'] );
	}

	return $map;
}

/**
 * Register the experience's import map with WordPress.
 *
 * A document may hold only one import map and WordPress prints its own in the
 * head under a block theme, so there the specifiers are added to that one rather
 * than emitted separately. A module reaches it by being declared a dependency of
 * an enqueued script.
 *
 * @param array  $manifest         The manifest.
 * @param string $custom_body_html The custom body HTML about to be emitted.
 * @return void
 */
function ceros_flex_ssr_register_import_map( $manifest, $custom_body_html ) {
	static $specifiers = [];

	$map = ceros_flex_ssr_import_map( $manifest, $custom_body_html );
	if ( empty( $map ) ) {
		return;
	}

	foreach ( $map['imports'] as $specifier => $url ) {
		// A null version leaves the manifest's URL untouched. A specifier
		// already registered by an earlier block keeps its first URL.
		wp_register_script_module( $specifier, $url, [], null );
		$specifiers[ $specifier ] = true;
	}

	// Carries the declaration and prints nothing of its own.
	if ( ! wp_script_is( 'ceros-flex-import-map', 'registered' ) ) {
		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- no src, so nothing is fetched or cached.
		wp_register_script( 'ceros-flex-import-map', false, [], null, true );
		wp_enqueue_script( 'ceros-flex-import-map' );
	}

	// Replaces rather than appends, so every specifier seen so far is re-sent.
	wp_scripts()->add_data( 'ceros-flex-import-map', 'module_dependencies', array_keys( $specifiers ) );
}

/**
 * Build a standalone `<script type="importmap">` tag, or '' when none is needed.
 *
 * For renders where WordPress's own map lands after the module scripts or is not
 * printed at all. Each block emits its own, immediately ahead of the modules
 * that read it, so a page carrying several of them resolves all of their
 * specifiers.
 *
 * @param array  $manifest         The manifest.
 * @param string $custom_body_html The custom body HTML about to be emitted.
 * @return string The <script> tag, or ''.
 */
function ceros_flex_ssr_import_map_tag( $manifest, $custom_body_html ) {
	$map = ceros_flex_ssr_import_map( $manifest, $custom_body_html );
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
