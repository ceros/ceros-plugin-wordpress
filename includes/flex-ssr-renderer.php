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
 * @param string $manifest_url The experience's manifest URL (stored on the block).
 * @return string Rendered HTML, or '' when the manifest could not be fetched
 *                (the caller should fall back to another delivery mode).
 */
function ceros_render_flex_ssr( $manifest_url ) {
	$manifest = ceros_fetch_flex_manifest( $manifest_url );
	if ( is_wp_error( $manifest ) || ! is_array( $manifest ) ) {
		return '';
	}

	// Follow a deep link (?cer_<slug>=<page>) to the requested page when present.
	$resolved     = ceros_flex_ssr_resolve_page( $manifest, $manifest_url );
	$manifest     = $resolved['manifest'];
	$served_url   = $resolved['url'];

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

	$content = '<div class="ceros-block__flex-ssr" data-flex-manifest-url="' . esc_url( $served_url ) . '">'
		. $html_body
		. '</div>';

	return ceros_flex_ssr_style_reset() . $styles . $head_scripts . $content . $body_scripts;
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
 * Build the <link> tags for SSR styles, with webfonts prepended so they preload
 * ahead of the component styles (matching the AEM connector ordering).
 *
 * @param array $manifest The manifest.
 * @param array $ssr      The `deliveryModes.ssr` object.
 * @return string Concatenated <link> tags.
 */
function ceros_flex_ssr_styles( $manifest, $ssr ) {
	$out = '';

	foreach ( ( isset( $manifest['assets'] ) ? $manifest['assets'] : [] ) as $asset ) {
		if ( is_array( $asset ) && ( isset( $asset['type'] ) ? $asset['type'] : '' ) === 'webfont' ) {
			$src = isset( $asset['src'] ) ? $asset['src'] : [];
			if ( ! empty( $src['url'] ) ) {
				$out .= ceros_flex_ssr_style_tag( $src['url'], isset( $src['integrity'] ) ? $src['integrity'] : '' );
			}
		}
	}

	foreach ( ( isset( $ssr['styles'] ) ? $ssr['styles'] : [] ) as $style ) {
		if ( is_array( $style ) && ! empty( $style['url'] ) ) {
			$out .= ceros_flex_ssr_style_tag( $style['url'], isset( $style['integrity'] ) ? $style['integrity'] : '' );
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

/**
 * Emit the inline style reset (once per request) that keeps the SSR container
 * and the Ceros viewer from collapsing to zero height inside a host page.
 *
 * @return string A <style> block the first time it is called, '' afterwards.
 */
function ceros_flex_ssr_style_reset() {
	static $emitted = false;
	if ( $emitted ) {
		return '';
	}
	$emitted = true;

	return '<style>'
		. '.ceros-block__flex-ssr,'
		. '.ceros-block__flex-ssr .cml-experience-viewer,'
		. '.ceros-block__flex-ssr .cml-experience-viewer--window,'
		. '.ceros-block__flex-ssr #experience-canvas-container{'
		. 'height:auto!important;min-height:0!important;max-height:none!important;overflow:visible!important;}'
		. '</style>' . "\n";
}
