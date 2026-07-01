<?php
/**
 * Public (API-key-less) experience URL resolver.
 *
 * Lets an editor paste a public Ceros experience URL and have the plugin
 * figure out — without an API key — whether it is a Flex experience or a
 * legacy Studio experience, and build the matching embed codes.
 *
 * SECURITY INVARIANT: every script/manifest URL this flow injects into a post
 * must originate from a Ceros-owned TLD (`ceros_is_ceros_owned_url()`). Because
 * the resolved snippets carry third-party-controlled <script> tags, keying
 * authenticity off a per-host probe (oEmbed, manifest shape) is spoofable — the
 * experience's own host serves the proof. Pinning every injected origin to a
 * Ceros TLD means only genuine Ceros content can render, so the flow is safe at
 * the `edit_posts` capability without an `unfiltered_html` gate.
 *
 * Flex detection: a vanity-domain experience advertises its (Ceros-hosted)
 * manifest via an `x-flex-manifest` response header; a Ceros-hosted experience
 * exposes the manifest at `<experience>/CEROS_MANIFEST_FILENAME`. Either way the
 * manifest URL must pass the Ceros-TLD whitelist before we fetch it. A 200 +
 * valid manifest means Flex; otherwise a Ceros-hosted URL is treated as legacy
 * Studio and anything else is rejected. The embed snippets are built to match the
 * authenticated embed-codes endpoint byte-for-byte (Flex) or the classic
 * scroll-proxy embed (legacy).
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
 *     @type string $manifestUrl         The Ceros-TLD manifest URL (Flex), '' for Studio.
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

	$experience_url  = ceros_derive_experience_url( $raw_url );
	$pasted_is_ceros = ceros_is_ceros_owned_url( $raw_url );

	// Catch obviously-wrong editor/preview URLs up front with an actionable hint.
	// These all live on Ceros-owned hosts, so without this they'd fall through to a
	// broken embed: the manifest probe 404s and the flow silently emits a legacy
	// scroll-proxy embed pointing at an editor/preview page.
	if ( $pasted_is_ceros ) {
		$non_publish = ceros_detect_non_publish_url( $raw_url );
		if ( '' !== $non_publish ) {
			return new WP_Error( 'ceros_url_not_publish', $non_publish );
		}
	}

	// HEAD the pasted URL BEFORE we trust anything it serves. We need its response
	// headers to discover a vanity-domain Flex experience (via `x-flex-manifest`),
	// and a transport failure (TLS/DNS/timeout) means we genuinely couldn't verify
	// the experience — so we surface that rather than guessing what it is.
	$head = wp_remote_head(
		$raw_url,
		[
			'timeout'     => CEROS_API_REQUEST_TIMEOUT,
			'redirection' => 0,
		]
	);
	if ( is_wp_error( $head ) ) {
		return new WP_Error(
			'ceros_detect_failed',
			sprintf(
				/* translators: %s: underlying fetch error message. */
				__( 'Could not reach the experience to verify it: %s', 'ceros' ),
				$head->get_error_message()
			)
		);
	}

	// Determine which manifest URL (if any) to probe:
	//  - an `x-flex-manifest` response header points at the (vanity-aware) manifest;
	//  - else, a Ceros-owned host exposes it at `<experience>/CEROS_MANIFEST_FILENAME`;
	//  - else there is no manifest we are willing to trust.
	$header_manifest = trim( (string) wp_remote_retrieve_header( $head, 'x-flex-manifest' ) );
	if ( '' !== $header_manifest ) {
		$manifest_url = $header_manifest;
	} elseif ( $pasted_is_ceros ) {
		$manifest_url = ceros_build_manifest_url( $raw_url );
	} else {
		$manifest_url = '';
	}

	// SECURITY INVARIANT: we only ever fetch a manifest — and later inject the
	// scripts it references — when that manifest is served from a Ceros-owned TLD.
	// A spoofed `x-flex-manifest` header pointing anywhere else is rejected here.
	if ( '' !== $manifest_url ) {
		if ( ! ceros_is_ceros_owned_url( $manifest_url ) ) {
			return new WP_Error(
				'ceros_manifest_untrusted',
				__( 'This experience reported a manifest hosted outside Ceros, so it can’t be trusted.', 'ceros' )
			);
		}

		$manifest = ceros_fetch_flex_manifest( $manifest_url );
		if ( ! is_wp_error( $manifest ) && is_array( $manifest ) ) {
			return array_merge(
				[
					'isFlex'      => true,
					'viewUrl'     => $experience_url,
					// Persisted on the block so the SSR delivery mode can re-fetch
					// the manifest server-side at render time.
					'manifestUrl' => $manifest_url,
				],
				ceros_build_flex_embed_codes( $experience_url, $manifest_url, $manifest )
			);
		}

		// The manifest URL was advertised via the header — the experience
		// positively identified itself as Flex — but the manifest didn't load.
		// Surface that failure rather than misclassifying it below as "not a Ceros
		// experience". (A *constructed* Ceros-host URL failing is the normal Studio
		// case, so it falls through to the Studio embed instead.)
		if ( '' !== $header_manifest ) {
			return new WP_Error(
				'ceros_manifest_unavailable',
				__( 'This is a Ceros Flex experience, but its manifest couldn’t be loaded. Please try again in a moment.', 'ceros' )
			);
		}
	}

	// No Flex manifest. Fall back to a legacy Studio embed — but only for a
	// Ceros-owned host, so the scroll-proxy <script> we inject is always served from
	// a Ceros TLD.
	if ( $pasted_is_ceros ) {
		return array_merge(
			[
				'isFlex'      => false,
				'viewUrl'     => $experience_url,
				// Studio experiences have no Flex manifest to re-fetch for SSR.
				'manifestUrl' => '',
			],
			ceros_build_legacy_embed_codes( $experience_url )
		);
	}

	// A non-Ceros host that gave no Flex signal. Vanity-domain Flex experiences
	// resolve above (via the x-flex-manifest header); reaching here means we can't
	// safely identify or embed this URL without authenticated API access.
	return new WP_Error(
		'ceros_not_ceros',
		__( 'This URL isn’t on a recognized Ceros domain and didn’t identify itself as a Ceros experience. To embed it, add a Ceros API key and use Browse.', 'ceros' )
	);
}

/**
 * Whether a URL is served from a Ceros-owned TLD over https.
 *
 * The security gate for the keyless paste flow: every manifest/script origin we
 * inject must pass this check. A host qualifies only if the URL is https and the
 * host exactly equals — or is an exact dotted subdomain of — one of the
 * Ceros-owned domains. Matching is exact-suffix (no substring `strpos`) so
 * look-alikes like `evilceros.com` or `ceros.com.evil.com` are rejected.
 *
 * Covers `view.ceros.com` (Studio) and the Flex TLDs (`*.ceros.site`,
 * `*.cerosdev.site`, `*.cerosstage.site`).
 *
 * @param string $url The URL to check.
 * @return bool True when the URL is https and on a Ceros-owned TLD.
 */
function ceros_is_ceros_owned_url( $url ) {
	$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
	if ( 'https' !== $scheme ) {
		return false;
	}

	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	if ( '' === $host ) {
		return false;
	}

	// Studio/view lives on the `.com` family (prod `ceros.com`, dev `cerosdev.com`,
	// stage `cerosstage.com`); Flex assets/players on the `.site` family.
	$domains = [
		'ceros.com',
		'cerosdev.com',
		'cerosstage.com',
		'ceros.site',
		'cerosdev.site',
		'cerosstage.site',
	];
	foreach ( $domains as $domain ) {
		if ( $host === $domain ) {
			return true;
		}

		$suffix = '.' . $domain;
		$len    = strlen( $suffix );
		if ( strlen( $host ) > $len && substr( $host, -$len ) === $suffix ) {
			return true;
		}
	}

	return false;
}

/**
 * Detect a non-publish Ceros URL — a Studio/Flex editor or preview URL — so the
 * editor can be told to paste the *published* experience URL instead.
 *
 * These shapes all live on Ceros-owned hosts, so without this check they slip
 * past the whitelist and fall through to a broken embed (the manifest probe 404s
 * and the flow silently emits a legacy scroll-proxy embed pointing at an
 * editor/preview page). Recognised shapes (prod + non-prod):
 *
 *   Preview (Flex):   <account>.preview.<domain>/<exp>/<page>
 *   Preview (Studio): <account>.preview.<domain>/<exp>/page/<page-id>
 *   Editor (Flex):    flex.<domain>/edit/<page-id>
 *   Editor (Studio):  admin.<domain>/account/<acct>/studio/experience/<exp-id>
 *
 * Detection keys off host labels (`preview`, `flex`, `admin`) plus an editor
 * path marker, which stay stable across environments (`latest.dev.flex.…`,
 * `latest.admin.…`, `…preview.latest.…`).
 *
 * @param string $url The pasted URL (already validated as https + public host).
 * @return string A warning message when the URL is an editor/preview URL, else ''.
 */
function ceros_detect_non_publish_url( $url ) {
	$host   = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	$path   = strtolower( (string) wp_parse_url( $url, PHP_URL_PATH ) );
	$labels = explode( '.', $host );

	// Preview hosts (both Flex and Studio) carry a dedicated `preview` label.
	if ( in_array( 'preview', $labels, true ) ) {
		return __( 'That looks like a Ceros preview URL. Open the experience and copy its published URL, then paste that here.', 'ceros' );
	}

	// Flex editor: flex.<domain>/edit/<page-id>.
	if ( in_array( 'flex', $labels, true ) && preg_match( '#^/edit(?:/|$)#', $path ) ) {
		return __( 'That looks like a Ceros Flex editor URL. Publish the experience, then paste its published URL here.', 'ceros' );
	}

	// Studio editor: admin.<domain>/…/studio/experience/<exp-id>.
	if ( in_array( 'admin', $labels, true ) && false !== strpos( $path, '/studio/' ) ) {
		return __( 'That looks like a Ceros Studio editor URL. Publish the experience, then paste its published URL here.', 'ceros' );
	}

	return '';
}

/**
 * Derive the canonical experience (view) URL from a pasted URL.
 *
 * Strips any trailing `manifest.v<n>.json`, a trailing slash, and the query
 * string / fragment so the result is a clean experience root URL.
 *
 * @param string $url The pasted URL.
 * @return string The canonical experience URL, or '' when the input has no
 *                scheme/host (an invalid URL no experience can be derived from).
 */
function ceros_derive_experience_url( $url ) {
	$url   = trim( (string) $url );
	$parts = wp_parse_url( $url );

	if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
		return '';
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
 * (minus query/fragment); otherwise the configured manifest filename
 * (`CEROS_MANIFEST_FILENAME`) is appended to the derived experience URL.
 *
 * @param string $url The pasted URL.
 * @return string The manifest URL, or '' when no experience URL can be derived.
 */
function ceros_build_manifest_url( $url ) {
	$url = trim( (string) $url );

	if ( preg_match( '#manifest(\.v[0-9.]+)?\.json(?:$|[?\#])#i', $url ) ) {
		$cut = strpbrk( $url, '?#' );
		return false === $cut ? $url : substr( $url, 0, strlen( $url ) - strlen( $cut ) );
	}

	$experience_url = ceros_derive_experience_url( $url );
	return '' === $experience_url ? '' : $experience_url . '/' . CEROS_MANIFEST_FILENAME;
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
 * Render the Flex Inline (iframeless) snippet at request time from the manifest.
 *
 * The inline runtime is emitted as a freshly-generated <script> during render
 * rather than persisted on the block: hosts that disable the `unfiltered_html`
 * capability (e.g. WordPress.com) strip <script> tags out of stored post
 * content on save, which neuters a persisted embed snippet (the src URL is left
 * behind as bare text and auto-linked into an <a>). Generating the snippet here
 * mirrors the SSR renderer, whose scripts survive for exactly this reason.
 *
 * The flex-client URL is read from the manifest's `deliveryModes.inline` block
 * (so vanity domains and non-prod environments resolve correctly), falling back
 * to the production Flex assets CDN.
 *
 * @param string $manifest_url The manifest URL persisted on the block.
 * @return string The inline embed snippet, or '' on any failure.
 */
function ceros_render_flex_inline( $manifest_url ) {
	$manifest_url = trim( (string) $manifest_url );
	if ( '' === $manifest_url ) {
		return '';
	}

	$manifest = ceros_fetch_flex_manifest( $manifest_url );
	if ( is_wp_error( $manifest ) || ! is_array( $manifest ) ) {
		return '';
	}

	$delivery = ( isset( $manifest['deliveryModes'] ) && is_array( $manifest['deliveryModes'] ) )
		? $manifest['deliveryModes']
		: [];

	$flex_client = ceros_first_script_url( isset( $delivery['inline'] ) ? $delivery['inline'] : [] );
	if ( '' === $flex_client ) {
		$flex_client = CEROS_FLEX_ASSETS_BASE . '/js/flex-client.js';
	}

	return ceros_build_flex_inline_snippet( $manifest_url, $flex_client );
}

/**
 * Render the Flex iframe embed snippet at request time from the manifest.
 *
 * Like the inline and SSR paths, the iframe runtime (`embed.v1.js`) is emitted
 * as a freshly-generated <script> during render rather than persisted on the
 * block, so it survives on hosts that strip <script> from stored content on
 * save (e.g. WordPress.com, which disables `unfiltered_html`).
 *
 * This is Flex-only: a Flex block always carries a manifest URL, whereas the
 * legacy Studio (scroll-proxy) embed does not, so callers gate on that.
 *
 * @param string $manifest_url The manifest URL persisted on the block.
 * @param string $height       'auto' (full height) or e.g. '800px' (scrollable).
 * @return string The iframe embed snippet, or '' on any failure.
 */
function ceros_render_flex_iframe( $manifest_url, $height ) {
	$manifest_url = trim( (string) $manifest_url );
	if ( '' === $manifest_url ) {
		return '';
	}

	$experience_url = ceros_derive_experience_url( $manifest_url );
	if ( '' === $experience_url ) {
		return '';
	}

	$manifest = ceros_fetch_flex_manifest( $manifest_url );
	if ( is_wp_error( $manifest ) || ! is_array( $manifest ) ) {
		return '';
	}

	$delivery = ( isset( $manifest['deliveryModes'] ) && is_array( $manifest['deliveryModes'] ) )
		? $manifest['deliveryModes']
		: [];

	$embed_script = ceros_first_script_url( isset( $delivery['iframe'] ) ? $delivery['iframe'] : [] );
	if ( '' === $embed_script ) {
		$embed_script = CEROS_FLEX_ASSETS_BASE . '/js/embed.v1.js';
	}

	return ceros_build_flex_iframe_snippet( $experience_url, $embed_script, $height );
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
