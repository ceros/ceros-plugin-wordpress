<?php
/**
 * Ceros API Client
 *
 * Provides a centralised way to communicate with the Ceros REST API from both
 * admin-side JavaScript (via custom REST endpoints) and PHP templates on the
 * front-end.
 *
 * @package ceros
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client for the Ceros REST API.
 *
 * A singleton wrapper around the authenticated Ceros endpoints the plugin
 * consumes (current account, folder tree, experiences, embed codes). Each
 * method returns either `[ 'code' => int, 'body' => array ]` or a WP_Error,
 * which the REST layer maps onto an HTTP response.
 */
class Ceros_API {

	/**
	 * Holds the singleton instance.
	 *
	 * @var Ceros_API|null
	 */
	protected static $instance = null;

	/**
	 * Retrieve the class instance.
	 *
	 * @return Ceros_API
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Get the API base URL based on the selected environment.
	 *
	 * @return string The API base URL.
	 */
	private function get_api_base_url() {
		return ceros_get_api_base_url();
	}

	/**
	 * Make an authenticated GET request to the Ceros POC API.
	 *
	 * @param string $endpoint The API endpoint path (e.g., '/accounts/current-account').
	 *
	 * @return array|WP_Error Returns ['code' => int, 'body' => array] on success.
	 */
	private function make_authenticated_request( $endpoint ) {
		$api_key = ceros_get_api_key();

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'ceros_api_key_missing',
				__( 'Ceros API key is not set. Please add it in the Ceros settings first.', 'ceros' )
			);
		}

		$base_url = $this->get_api_base_url();

		if ( empty( $base_url ) ) {
			return new WP_Error(
				'ceros_api_url_missing',
				__( 'Staging API URL is not configured. Please set it in the Ceros settings.', 'ceros' )
			);
		}

		$url      = $base_url . $endpoint;
		$response = wp_remote_get(
			$url,
			[
				'headers' => ceros_get_api_headers( $api_key ),
				'timeout' => CEROS_API_REQUEST_TIMEOUT,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		// Normalise non-JSON / scalar bodies to an array so downstream code can
		// safely access keys without tripping undefined-index warnings.
		if ( ! is_array( $data ) ) {
			$data = [];
		}

		return [
			'code' => $code,
			'body' => $data,
		];
	}

	/**
	 * Fetch the current account details from the Ceros POC API.
	 *
	 * Endpoint: /accounts/current-account
	 * Auth:     Bearer Token (value = saved API key)
	 *
	 * @return array|WP_Error
	 */
	public function get_current_account() {
		return $this->make_authenticated_request( CEROS_ENDPOINT_CURRENT_ACCOUNT );
	}

	/**
	 * Fetch the folder tree for a specific account from the Ceros POC API.
	 *
	 * Endpoint: /accounts/{accountResourceID}/folder-tree
	 * Auth:     Bearer Token (value = saved API key)
	 *
	 * @param string $account_resource_id The account resource ID.
	 *
	 * @return array|WP_Error
	 */
	public function get_folder_tree( $account_resource_id ) {
		$account_resource_id = ceros_sanitize_resource_id( $account_resource_id );

		if ( false === $account_resource_id ) {
			return new WP_Error(
				'ceros_account_resource_id_invalid',
				__( 'Account resource ID is missing or invalid.', 'ceros' )
			);
		}

		$result    = $this->make_authenticated_request( '/accounts/' . $account_resource_id . '/folder-tree' );
		$resources = $result['body']['resources'];
		// Only filter successful responses that actually contain a folder list.
		// Skip WP_Error, non-2xx responses, and non-list bodies (e.g. `{"message": "..."}`
		// error payloads) so their structure is preserved for the error handler downstream.
		$body_is_list = is_array( $resources ?? null )
			&& ( [] === $resources || array_keys( $resources ) === range( 0, count( $resources ) - 1 ) );
		if (
			! is_wp_error( $result ) &&
			isset( $result['code'] ) && $result['code'] >= 200 && $result['code'] < 300 &&
			$body_is_list
		) {
			$resources      = array_values(
				array_filter(
					$resources,
					function ( $item ) {
						if ( ! is_array( $item ) || ! isset( $item['name'] ) ) {
							return true;
						}

						// Still hide "Account Templates", but allow "Flex Experiences".
						return ! in_array( $item['name'], [ 'Account Templates' ], true );
					}
				)
			);
			$result['body'] = $resources;
		}

		return $result;
	}

	/**
	 * Fetch experiences for a specific folder from the Ceros POC API.
	 *
	 * Endpoint: /folder/{resourceId}/experiences
	 * Auth:     Bearer Token (value = saved API key)
	 *
	 * @param string $resource_id The folder resource ID.
	 *
	 * @return array|WP_Error
	 */
	public function get_experiences( $resource_id ) {
		$resource_id = ceros_sanitize_resource_id( $resource_id );

		if ( false === $resource_id ) {
			return new WP_Error(
				'ceros_resource_id_invalid',
				__( 'Resource ID is missing or invalid.', 'ceros' )
			);
		}

		$result = $this->make_authenticated_request( '/folders/' . $resource_id . '/experiences?pageSize=1000&filter=published' );

		// Filter out invalid experiences on the backend to reduce data sent to frontend.
		if ( ! is_wp_error( $result ) && isset( $result['body'] ) ) {
			$experiences = $result['body'];

			// Handle different response structures.
			if ( isset( $experiences['items'] ) && is_array( $experiences['items'] ) ) {
				$experiences = $experiences['items'];
			} elseif ( isset( $experiences['resources'] ) && is_array( $experiences['resources'] ) ) {
				$experiences = $experiences['resources'];
			} elseif ( ! is_array( $experiences ) ) {
				$experiences = [];
			}

			// Filter out experiences that shouldn't be shown.
			$valid_experiences = array_filter(
				$experiences,
				function ( $exp ) {
					// Only include published, non-template, non-password-protected, non-SSO experiences.
					// Flex Experiences are now allowed.
					return isset( $exp['status'] ) && 'published' === $exp['status'] &&
						isset( $exp['isTemplate'] ) && false === $exp['isTemplate'] &&
						isset( $exp['isPasswordProtected'] ) && false === $exp['isPasswordProtected'] &&
						isset( $exp['isSSOProtected'] ) && false === $exp['isSSOProtected'];
				}
			);

			// Re-index array to ensure sequential keys.
			$result['body'] = array_values( $valid_experiences );
		}

		return $result;
	}

	/**
	 * Fetch embed codes for a specific experience from the Ceros POC API.
	 *
	 * Endpoint: /experiences/{resourceId}/embed-codes
	 * Auth:     Bearer Token (value = saved API key)
	 *
	 * @param string $resource_id Experience resource ID.
	 *
	 * @return array|WP_Error
	 */
	public function get_embed_codes( $resource_id ) {
		$resource_id = ceros_sanitize_resource_id( $resource_id );

		if ( false === $resource_id ) {
			return new WP_Error(
				'ceros_resource_id_invalid',
				__( 'Resource ID is missing or invalid.', 'ceros' )
			);
		}

		return $this->make_authenticated_request( '/experiences/' . $resource_id . '/embed-codes' );
	}
}
