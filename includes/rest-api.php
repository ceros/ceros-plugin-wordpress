<?php
/**
 * REST API routes and response handling.
 *
 * Registers the WordPress REST API endpoints that proxy requests
 * to the external Ceros API and handles response formatting.
 *
 * @package ceros
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper to retrieve the stored API key.
 *
 * Uses the Ceros_Encryption class which handles:
 * - wp-config.php constant (CEROS_API_KEY)
 * - Encrypted database storage
 * - Legacy plain text migration
 *
 * @return string The decrypted API key or empty string.
 */
function ceros_get_api_key() {
	return Ceros_Encryption::get_api_key();
}

/**
 * Permission callback for Ceros REST routes.
 * Requires a user who can edit posts (i.e. editors, admins).
 *
 * @return bool
 */
function ceros_rest_permission_check() {
	return current_user_can( 'edit_posts' );
}

/**
 * Register custom REST API routes for the Ceros plugin.
 */
function ceros_register_rest_routes() {
	register_rest_route(
		CEROS_REST_NAMESPACE,
		'/current-account',
		[
			'methods'             => 'GET',
			'callback'            => 'ceros_rest_get_current_account',
			'permission_callback' => 'ceros_rest_permission_check',
		]
	);

	register_rest_route(
		CEROS_REST_NAMESPACE,
		'/folder-tree/(?P<account_resource_id>' . CEROS_RESOURCE_ID_PATTERN . ')',
		[
			'methods'             => 'GET',
			'callback'            => 'ceros_rest_get_folder_tree',
			'permission_callback' => 'ceros_rest_permission_check',
			'args'                => [
				'account_resource_id' => [
					'required' => true,
					'type'     => 'string',
				],
			],
		]
	);

	register_rest_route(
		CEROS_REST_NAMESPACE,
		'/folder/(?P<resource_id>' . CEROS_RESOURCE_ID_PATTERN . ')/experiences',
		[
			'methods'             => 'GET',
			'callback'            => 'ceros_rest_get_experiences',
			'permission_callback' => 'ceros_rest_permission_check',
			'args'                => [
				'resource_id' => [
					'required' => true,
					'type'     => 'string',
				],
			],
		]
	);

	register_rest_route(
		CEROS_REST_NAMESPACE,
		'/test-connection',
		[
			'methods'             => 'POST',
			'callback'            => 'ceros_rest_test_connection',
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'args'                => [
				'environment' => [
					'required' => false,
					'type'     => 'string',
				],
				'staging_url' => [
					'required' => false,
					'type'     => 'string',
				],
				'api_key'     => [
					'required' => false,
					'type'     => 'string',
				],
			],
		]
	);

	register_rest_route(
		CEROS_REST_NAMESPACE,
		'/experiences/(?P<resource_id>' . CEROS_RESOURCE_ID_PATTERN . ')/embed-codes',
		[
			'methods'             => 'GET',
			'callback'            => 'ceros_rest_get_embed_codes',
			'permission_callback' => 'ceros_rest_permission_check',
			'args'                => [
				'resource_id' => [
					'required' => true,
					'type'     => 'string',
				],
			],
		]
	);
}
add_action( 'rest_api_init', 'ceros_register_rest_routes' );

/**
 * Handle API response and return appropriate REST response.
 *
 * @param array|WP_Error $result The API result.
 * @return WP_REST_Response The REST response.
 */
function ceros_handle_api_response( $result ) {
	if ( is_wp_error( $result ) ) {
		$error_message = $result->get_error_message();
		$error_code    = $result->get_error_code();

		// Preserve the original, explicit API key / config errors so the
		// editor can reliably detect them — they are already user-friendly.
		if ( in_array( $error_code, [ 'ceros_api_key_missing', 'ceros_api_url_missing' ], true ) ) {
			$friendly_message = $error_message;
		} else {
			$friendly_message = ceros_format_error(
				$error_message,
				ceros_get_friendly_error_message( $error_message )
			);
		}

		return new WP_REST_Response(
			[
				'error'      => $friendly_message,
				'error_code' => $error_code,
			],
			400
		);
	}

	// Check for 403 Forbidden response which typically means invalid API key.
	if ( isset( $result['code'] ) && $result['code'] === 403 &&
		 isset( $result['body']['message'] ) && $result['body']['message'] === 'Forbidden resource' ) {
		return new WP_REST_Response(
			[
				'code'  => 403,
				'body'  => [ 'message' => 'Forbidden resource' ],
				'error' => ceros_format_error(
					__( '403 Forbidden — the Ceros API rejected the request as "Forbidden resource".', 'ceros' ),
					__( 'Your API key appears to be invalid. Please confirm that it is correct in the Ceros settings.', 'ceros' )
				),
			],
			403
		);
	}

	// Check for other HTTP error responses (4xx and 5xx).
	if ( isset( $result['code'] ) && $result['code'] >= 400 ) {
		$api_message   = isset( $result['body']['message'] ) ? $result['body']['message'] : 'Unknown error';
		$technical_msg = sprintf( 'Ceros API error (%d): %s', $result['code'], $api_message );

		return new WP_REST_Response(
			[
				'code'  => $result['code'],
				'body'  => $result['body'],
				'error' => ceros_format_error(
					$technical_msg,
					__( 'The Ceros API returned an error. Please try again or check the Ceros settings.', 'ceros' )
				),
			],
			$result['code']
		);
	}

	return rest_ensure_response( $result );
}

/**
 * REST callback: Get current account.
 *
 * @return WP_REST_Response
 */
function ceros_rest_get_current_account() {
	return ceros_handle_api_response( Ceros_API::instance()->get_current_account() );
}

/**
 * REST callback: Get folder tree.
 *
 * @param WP_REST_Request $request The REST request instance.
 * @return WP_REST_Response
 */
function ceros_rest_get_folder_tree( WP_REST_Request $request ) {
	return ceros_handle_api_response(
		Ceros_API::instance()->get_folder_tree( $request->get_param( 'account_resource_id' ) )
	);
}

/**
 * REST callback: Get experiences for a folder.
 *
 * @param WP_REST_Request $request The REST request instance.
 * @return WP_REST_Response
 */
function ceros_rest_get_experiences( WP_REST_Request $request ) {
	return ceros_handle_api_response(
		Ceros_API::instance()->get_experiences( $request->get_param( 'resource_id' ) )
	);
}

/**
 * REST callback: Get embed codes for an experience.
 *
 * Sanitizes embed codes before returning to prevent XSS vulnerabilities.
 *
 * @param WP_REST_Request $request The REST request instance.
 * @return WP_REST_Response
 */
function ceros_rest_get_embed_codes( WP_REST_Request $request ) {
	$result = Ceros_API::instance()->get_embed_codes( $request->get_param( 'resource_id' ) );

	// Sanitize embed codes in the response body before returning.
	if ( ! is_wp_error( $result ) && isset( $result['body'] ) && is_array( $result['body'] ) ) {
		$result['body'] = ceros_sanitize_embed_codes_array( $result['body'] );
	}

	return ceros_handle_api_response( $result );
}

/**
 * REST callback: Test the Ceros API connection.
 *
 * Accepts optional `environment`, `staging_url`, and `api_key` params.
 * When `api_key` is supplied it is tested in place of the stored key,
 * allowing users to validate a key before saving it.
 *
 * @param WP_REST_Request $request The REST request instance.
 * @return WP_REST_Response
 */
function ceros_rest_test_connection( WP_REST_Request $request ) {
	$override_key = (string) $request->get_param( 'api_key' );
	$api_key      = '' !== $override_key ? $override_key : ceros_get_api_key();

	if ( empty( $api_key ) ) {
		return new WP_REST_Response(
			[ 'message' => __( 'No API key is configured. Please enter or save an API key first.', 'ceros' ) ],
			400
		);
	}

	$environment = (string) $request->get_param( 'environment' );
	if ( ! in_array( $environment, [ CEROS_ENV_PRODUCTION, CEROS_ENV_STAGING ], true ) ) {
		$environment = CEROS_ENV_PRODUCTION;
	}

	if ( CEROS_ENV_STAGING === $environment ) {
		$staging_url = esc_url_raw( (string) $request->get_param( 'staging_url' ) );

		if ( empty( $staging_url ) ) {
			return new WP_REST_Response(
				[ 'message' => __( 'Staging URL is required.', 'ceros' ) ],
				400
			);
		}

		if ( 'https' !== wp_parse_url( $staging_url, PHP_URL_SCHEME ) ) {
			return new WP_REST_Response(
				[ 'message' => __( 'The staging URL must use HTTPS.', 'ceros' ) ],
				400
			);
		}

		if ( ! ceros_is_public_host( wp_parse_url( $staging_url, PHP_URL_HOST ) ) ) {
			return new WP_REST_Response(
				[ 'message' => __( 'The staging URL resolves to a private or loopback address and is not allowed.', 'ceros' ) ],
				400
			);
		}

		$base_url = untrailingslashit( $staging_url );
	} else {
		$base_url = CEROS_PRODUCTION_API_URL;
	}

	$response = wp_remote_get(
		$base_url . CEROS_ENDPOINT_CURRENT_ACCOUNT,
		[
			'headers' => ceros_get_api_headers( $api_key ),
			'timeout' => CEROS_API_REQUEST_TIMEOUT,
		]
	);

	if ( is_wp_error( $response ) ) {
		return new WP_REST_Response(
			[
				'message' => ceros_format_error(
					$response->get_error_message(),
					__( 'Could not connect to the Ceros API. Please try again.', 'ceros' )
				),
			],
			502
		);
	}

	$code = wp_remote_retrieve_response_code( $response );

	if ( $code >= 200 && $code < 300 ) {
		return new WP_REST_Response(
			[ 'message' => __( 'Connection successful.', 'ceros' ) ],
			200
		);
	}

	$body          = wp_remote_retrieve_body( $response );
	$technical_msg = sprintf( 'HTTP %d — %s', $code, $body );

	return new WP_REST_Response(
		[
			'message' => ceros_format_error(
				$technical_msg,
				__( 'Connection test failed. Please check the URL and API key.', 'ceros' )
			),
		],
		$code
	);
}
