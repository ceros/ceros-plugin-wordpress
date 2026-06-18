<?php
/**
 * Public (API-key-less) experience URL resolver.
 *
 * Lets an editor paste a public Ceros experience URL and have the plugin
 * figure out — without an API key — whether it is a Flex experience or a
 * legacy Studio experience, and build the matching embed codes.
 *
 * Flex detection mirrors the Ceros AEM connector: fetch the public, unauthenticated
 * Flex manifest (`CEROS_MANIFEST_FILENAME`) for the experience. A 200 + valid manifest means Flex; any
 * other result is treated as a legacy Studio experience. The embed snippets are
 * built to match the authenticated embed-codes endpoint byte-for-byte (Flex) or
 * the classic scroll-proxy embed (legacy).
 *
 * @package ceros
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve a pasted public experience URL into embed codes.
 *
 * @param string $raw_url The URL pasted by the editor.
 * @return array|WP_Error {
 *     On success an array with:
 *     @type bool   $isFlex              Whether the experience is a Flex experience.
 *     @type string $viewUrl             The canonical experience (view) URL.
 *     @type string $fullHeightEmbedCode Iframe full-height snippet.
 *     @type string $scrollableEmbedCode Iframe scrollable snippet.
 *     @type string $inlineEmbedCode     Flex Inline (iframeless) snippet, '' for legacy.
 * }
 */
function ceros_resolve_public_experience_url( $raw_url ) {
	$raw_url = trim( (string) $raw_url );

	if ( '' === $raw_url ) {
		return new WP_Error( 'ceros_url_required', __( 'Please enter a Ceros experience URL.', 'ceros' ) );
	}

	// Enforce https + a publicly-routable host before making any outbound request
	// (basic SSRF guard, consistent with the connection-test endpoint).
	$scheme = strtolower( (string) wp_parse_url( $raw_url, PHP_URL_SCHEME ) );
	if ( 'https' !== $scheme ) {
		return new WP_Error( 'ceros_url_scheme', __( 'The experience URL must start with https://.', 'ceros' ) );
	}

	$host = wp_parse_url( $raw_url, PHP_URL_HOST );
	if ( empty( $host ) || ! ceros_is_public_host( $host ) ) {
		return new WP_Error(
			'ceros_url_host',
			__( 'The experience URL host is invalid or not publicly reachable.', 'ceros' )
		);
	}

	$experience_url = ceros_derive_experience_url( $raw_url );

	// Verify this is a genuine Ceros experience via its oEmbed endpoint BEFORE we
	// trust (and inject) anything it serves. Both Flex and Studio experiences
	// expose a Ceros-branded oEmbed; a 3rd-party page that merely mimics the Flex
	// manifest format will not — so we never key "it's a Ceros experience" off
	// the mere presence/absence of a manifest.
	$oembed = ceros_fetch_ceros_oembed( $experience_url );
	if ( is_wp_error( $oembed ) ) {
		// A transport failure (TLS/DNS/timeout) means we couldn't verify — surface
		// the real reason rather than guessing what the experience is.
		if ( 'ceros_oembed_unreachable' === $oembed->get_error_code() ) {
			return new WP_Error(
				'ceros_detect_failed',
				sprintf(
					/* translators: %s: underlying fetch error message. */
					__( 'Could not reach the experience to verify it: %s', 'ceros' ),
					$oembed->get_error_message()
				)
			);
		}
		// Reached the host, but it isn't a recognised Ceros experience.
		return new WP_Error(
			'ceros_not_ceros',
			__( 'This URL doesn’t appear to be a published Ceros experience. Please paste the public link to a Ceros experience.', 'ceros' )
		);
	}

	// Confirmed Ceros. A published Flex experience exposes the inline manifest; a
	// Studio experience does not — now a safe Flex-vs-Studio signal.
	$manifest_url = ceros_build_manifest_url( $raw_url );
	$manifest     = ceros_fetch_flex_manifest( $manifest_url );

	if ( ! is_wp_error( $manifest ) && is_array( $manifest ) ) {
		return array_merge(
			[
				'isFlex'  => true,
				'viewUrl' => $experience_url,
			],
			ceros_build_flex_embed_codes( $experience_url, $manifest_url, $manifest )
		);
	}

	// Ceros Studio experience. Prefer the canonical embed the oEmbed endpoint
	// returns; fall back to the constructed scroll-proxy snippet.
	$studio = ceros_build_legacy_embed_codes( $experience_url );
	if ( ! empty( $oembed['html'] ) && is_string( $oembed['html'] ) ) {
		$studio['fullHeightEmbedCode'] = $oembed['html'];
	}
	return array_merge(
		[
			'isFlex'  => false,
			'viewUrl' => $experience_url,
		],
		$studio
	);
}

/**
 * Fetch and validate the Ceros oEmbed for an experience URL.
 *
 * Hits the experience host's `/oembed` endpoint — served by Ceros for both Flex
 * and Studio experiences — and confirms the response is Ceros-branded
 * (`provider_name` "Ceros" / a ceros.com `provider_url`). This is the
 * authenticity gate: a page that merely mimics the Flex manifest format will not
 * return a Ceros oEmbed. (Not cryptographic proof against a host that fully
 * impersonates Ceros, but it stops manifest-shape look-alikes and accidental
 * non-Ceros matches.)
 *
 * @param string $experience_url The canonical experience URL.
 * @return array|WP_Error Decoded oEmbed on success; WP_Error otherwise — code
 *                        `ceros_oembed_unreachable` for transport failures, other
 *                        codes when the host responded but isn't a Ceros oEmbed.
 */
function ceros_fetch_ceros_oembed( $experience_url ) {
	$parts = wp_parse_url( $experience_url );
	if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
		return new WP_Error( 'ceros_oembed_url', __( 'Invalid experience URL.', 'ceros' ) );
	}

	$origin     = $parts['scheme'] . '://' . $parts['host'] . ( ! empty( $parts['port'] ) ? ':' . $parts['port'] : '' );
	$oembed_url = $origin . '/oembed?url=' . rawurlencode( $experience_url ) . '&format=json';

	$response = wp_remote_get(
		$oembed_url,
		[
			'timeout'     => CEROS_API_REQUEST_TIMEOUT,
			'redirection' => 0,
			'headers'     => [ 'Accept' => 'application/json' ],
		]
	);
	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'ceros_oembed_unreachable', $response->get_error_message() );
	}

	$code = wp_remote_retrieve_response_code( $response );
	if ( $code < 200 || $code >= 300 ) {
		return new WP_Error( 'ceros_oembed_http', sprintf( 'HTTP %d', $code ) );
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $data ) ) {
		return new WP_Error( 'ceros_oembed_invalid', __( 'oEmbed response was not valid JSON.', 'ceros' ) );
	}

	$provider_name = isset( $data['provider_name'] ) ? strtolower( trim( (string) $data['provider_name'] ) ) : '';
	$provider_host = isset( $data['provider_url'] ) ? strtolower( (string) wp_parse_url( $data['provider_url'], PHP_URL_HOST ) ) : '';
	$is_ceros      = 'ceros' === $provider_name
		|| 'ceros.com' === $provider_host
		|| ( strlen( $provider_host ) > 10 && '.ceros.com' === substr( $provider_host, -10 ) );

	if ( ! $is_ceros ) {
		return new WP_Error( 'ceros_oembed_provider', __( 'Response is not a Ceros oEmbed.', 'ceros' ) );
	}

	return $data;
}

/**
 * Derive the canonical experience (view) URL from a pasted URL.
 *
 * Strips any trailing `manifest.v<n>.json`, a trailing slash, and the query
 * string / fragment so the result is a clean experience root URL.
 *
 * @param string $url The pasted URL.
 * @return string The canonical experience URL.
 */
function ceros_derive_experience_url( $url ) {
	$url   = trim( (string) $url );
	$parts = wp_parse_url( $url );

	if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
		return $url;
	}

	$path = isset( $parts['path'] ) ? $parts['path'] : '';
	$path = preg_replace( '#/?manifest(\.v[0-9.]+)?\.json$#i', '', $path );
	$path = rtrim( (string) $path, '/' );

	$rebuilt = $parts['scheme'] . '://' . $parts['host'];
	if ( ! empty( $parts['port'] ) ) {
		$rebuilt .= ':' . $parts['port'];
	}
	$rebuilt .= $path;

	return $rebuilt;
}

/**
 * Build the manifest URL to probe for a pasted experience URL.
 *
 * If the pasted URL already points at a manifest file, that URL is used
 * (minus query/fragment); otherwise `manifest.v0.json` is appended to the
 * derived experience URL.
 *
 * @param string $url The pasted URL.
 * @return string The manifest URL.
 */
function ceros_build_manifest_url( $url ) {
	$url = trim( (string) $url );

	if ( preg_match( '#manifest(\.v[0-9.]+)?\.json(?:$|[?\#])#i', $url ) ) {
		$cut = strpbrk( $url, '?#' );
		return false === $cut ? $url : substr( $url, 0, strlen( $url ) - strlen( $cut ) );
	}

	return ceros_derive_experience_url( $url ) . '/' . CEROS_MANIFEST_FILENAME;
}

/**
 * Fetch and minimally validate a public Flex manifest.
 *
 * @param string $manifest_url The manifest URL.
 * @return array|WP_Error The decoded manifest on success, WP_Error otherwise.
 */
function ceros_fetch_flex_manifest( $manifest_url ) {
	$host = wp_parse_url( $manifest_url, PHP_URL_HOST );
	if ( empty( $host ) || ! ceros_is_public_host( $host ) ) {
		return new WP_Error( 'ceros_manifest_host', __( 'Manifest host is not publicly reachable.', 'ceros' ) );
	}

	$response = wp_remote_get(
		$manifest_url,
		[
			'timeout'     => CEROS_API_REQUEST_TIMEOUT,
			// Do not follow redirects: a redirect could bounce an allowed URL
			// into an internal target (SSRF), and a published manifest is served
			// directly with a 200.
			'redirection' => 0,
			'headers'     => [ 'Accept' => 'application/json' ],
		]
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	if ( $code < 200 || $code >= 300 ) {
		return new WP_Error( 'ceros_manifest_http', sprintf( 'HTTP %d', $code ) );
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $data ) ) {
		return new WP_Error( 'ceros_manifest_json', __( 'Manifest response was not valid JSON.', 'ceros' ) );
	}

	// Guard against treating arbitrary JSON as a Flex manifest: a real manifest
	// carries at least one of these top-level markers.
	if ( ! isset( $data['schemaVersion'] ) && ! isset( $data['deliveryModes'] ) && ! isset( $data['experience'] ) ) {
		return new WP_Error( 'ceros_manifest_shape', __( 'Response is not a Ceros manifest.', 'ceros' ) );
	}

	return $data;
}

/**
 * Build Flex embed codes from a manifest, matching the authenticated
 * embed-codes endpoint output.
 *
 * Script URLs are taken from the manifest's `deliveryModes` block when present
 * (so vanity domains and non-prod environments work), falling back to the
 * production Flex assets CDN.
 *
 * @param string $experience_url The canonical experience URL.
 * @param string $manifest_url   The manifest URL.
 * @param array  $manifest       The decoded manifest.
 * @return array Embed codes keyed by fullHeightEmbedCode/scrollableEmbedCode/inlineEmbedCode.
 */
function ceros_build_flex_embed_codes( $experience_url, $manifest_url, $manifest ) {
	$delivery = ( isset( $manifest['deliveryModes'] ) && is_array( $manifest['deliveryModes'] ) )
		? $manifest['deliveryModes']
		: [];

	$flex_client = ceros_first_script_url( isset( $delivery['inline'] ) ? $delivery['inline'] : [] );
	if ( '' === $flex_client ) {
		$flex_client = CEROS_FLEX_ASSETS_BASE . '/js/flex-client.js';
	}

	$embed_script = ceros_first_script_url( isset( $delivery['iframe'] ) ? $delivery['iframe'] : [] );
	if ( '' === $embed_script ) {
		$embed_script = CEROS_FLEX_ASSETS_BASE . '/js/embed.v1.js';
	}

	return [
		'fullHeightEmbedCode' => ceros_build_flex_iframe_snippet( $experience_url, $embed_script, 'auto' ),
		'scrollableEmbedCode' => ceros_build_flex_iframe_snippet( $experience_url, $embed_script, '800px' ),
		'inlineEmbedCode'     => ceros_build_flex_inline_snippet( $manifest_url, $flex_client ),
	];
}

/**
 * Pull the first absolute https script URL from a manifest delivery-mode entry.
 *
 * @param array $mode A `deliveryModes[mode]` object.
 * @return string The first https script URL, or '' when none.
 */
function ceros_first_script_url( $mode ) {
	if ( ! is_array( $mode ) || empty( $mode['scripts'] ) || ! is_array( $mode['scripts'] ) ) {
		return '';
	}

	foreach ( $mode['scripts'] as $script ) {
		if ( is_array( $script ) && ! empty( $script['url'] ) && is_string( $script['url'] )
			&& 0 === strpos( $script['url'], 'https://' ) ) {
			return $script['url'];
		}
	}

	return '';
}

/**
 * Build the Flex iframe snippet (matches buildFlexIframeSnippet in ceros-spark).
 *
 * @param string $experience_url   The experience URL.
 * @param string $embed_script_url The embed.v1.js URL.
 * @param string $height           'auto' (full height) or e.g. '800px' (scrollable).
 * @return string The iframe embed snippet.
 */
function ceros_build_flex_iframe_snippet( $experience_url, $embed_script_url, $height ) {
	return sprintf(
		'<div data-embed-width="100%%" data-embed-height="%s" data-ceros-experience="%s"></div>' . "\n" . '<script src="%s"></script>',
		esc_attr( $height ),
		esc_url( $experience_url ),
		esc_url( $embed_script_url )
	);
}

/**
 * Build the Flex Inline (iframeless) snippet (matches buildFlexInlineSnippet in ceros-spark).
 *
 * @param string $manifest_url The manifest URL.
 * @param string $script_url   The flex-client.js URL.
 * @return string The inline embed snippet.
 */
function ceros_build_flex_inline_snippet( $manifest_url, $script_url ) {
	return sprintf(
		'<div data-flex-inline data-flex-manifest-url="%s"></div>' . "\n" . '<script src="%s"></script>',
		esc_url( $manifest_url ),
		esc_url( $script_url )
	);
}

/**
 * Build legacy Studio embed codes (the classic scroll-proxy iframe) from a
 * public experience URL.
 *
 * Without the authenticated API / S3 data the experience's true aspect ratio is
 * unknown, so a 16:9 placeholder is used; the scroll-proxy script resizes the
 * full-height variant to the real content height at runtime.
 *
 * @param string $experience_url The experience URL.
 * @return array Embed codes (inlineEmbedCode is '' for legacy experiences).
 */
function ceros_build_legacy_embed_codes( $experience_url ) {
	$host = wp_parse_url( $experience_url, PHP_URL_HOST );
	$host = $host ? $host : CEROS_LEGACY_VIEW_HOST;

	$path     = (string) wp_parse_url( $experience_url, PHP_URL_PATH );
	$segments = array_values( array_filter( explode( '/', $path ) ) );
	$slug     = ! empty( $segments ) ? end( $segments ) : 'ceros-experience';

	// The scroll-proxy script is served from the same origin as the experience
	// so it also works behind legacy vanity domains.
	$script = sprintf(
		'<script type="text/javascript" src="%s" data-ceros-origin-domains="%s"></script>',
		esc_url( 'https://' . $host . '/scroll-proxy.min.js' ),
		esc_attr( $host )
	);

	$div_style = 'position:relative;width:auto;padding:0 0 56.25%;height:0;top:0;left:0;bottom:0;right:0;margin:0;border:0 none;';

	// Full height: non-scrolling iframe, auto-resized by scroll-proxy.
	$full_iframe_style = 'position:absolute;top:0;left:0;bottom:0;right:0;margin:0;padding:0;border:0 none;width:1px;min-width:100%;height:1px;min-height:100%;';
	$full              = sprintf(
		'<div style="%s" id="%s" data-aspectRatio="1.77777778"><iframe allowfullscreen src="%s" style="%s" frameborder="0" class="ceros-experience" scrolling="no"></iframe></div>%s',
		esc_attr( $div_style ),
		esc_attr( $slug ),
		esc_url( $experience_url ),
		esc_attr( $full_iframe_style ),
		$script
	);

	// Scrollable: experience scrolls within a fixed area.
	$scroll_iframe_style = 'position:absolute;top:0;left:0;bottom:0;right:0;margin:0;padding:0;border:0 none;width:100%;height:100%;';
	$scroll              = sprintf(
		'<div style="%s" id="%s" data-aspectRatio="1.77777778"><iframe allowfullscreen src="%s" style="%s" frameborder="0" class="ceros-experience"></iframe></div>%s',
		esc_attr( $div_style ),
		esc_attr( $slug ),
		esc_url( $experience_url ),
		esc_attr( $scroll_iframe_style ),
		$script
	);

	return [
		'fullHeightEmbedCode' => $full,
		'scrollableEmbedCode' => $scroll,
		'inlineEmbedCode'     => '',
	];
}
