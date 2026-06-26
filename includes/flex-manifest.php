<?php
/**
 * Flex manifest fetching.
 *
 * Shared helper for fetching and validating a public, unauthenticated Ceros Flex
 * manifest (`manifest.v1.json`). Used by both the public-URL resolver (to detect
 * Flex vs legacy) and the SSR renderer (to server-render an experience).
 *
 * The manifest is fetched fresh on every call — manifests change when an author
 * republishes, and Ceros serves them with edge caching, so the plugin does not
 * add its own cache layer here. (A future "Store" mode will persist a snapshot
 * on the block instead.)
 *
 * @package ceros
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetch and minimally validate a public Flex manifest.
 *
 * Enforces an https URL pointing at a publicly-routable host and never follows
 * redirects (SSRF posture, matching the Ceros AEM connector).
 *
 * @param string $manifest_url The manifest URL.
 * @return array|WP_Error The decoded manifest on success, WP_Error otherwise.
 */
function ceros_fetch_flex_manifest( $manifest_url ) {
	$manifest_url = trim( (string) $manifest_url );

	if ( '' === $manifest_url ) {
		return new WP_Error( 'ceros_manifest_url_required', __( 'Manifest URL is required.', 'ceros' ) );
	}

	if ( 'https' !== strtolower( (string) wp_parse_url( $manifest_url, PHP_URL_SCHEME ) ) ) {
		return new WP_Error( 'ceros_manifest_scheme', __( 'Manifest URL must use https.', 'ceros' ) );
	}

	$host = wp_parse_url( $manifest_url, PHP_URL_HOST );
	if ( empty( $host ) || ! ceros_is_public_host( $host ) ) {
		return new WP_Error( 'ceros_manifest_host', __( 'Manifest host is not publicly reachable.', 'ceros' ) );
	}

	$response = wp_remote_get(
		$manifest_url,
		[
			'timeout'     => CEROS_API_REQUEST_TIMEOUT,
			// Do not follow redirects: a redirect could bounce an allowed URL into
			// an internal target (SSRF). A published manifest is served with a 200.
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
