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

class Ceros_API {

	/**
	 * The base URL for the Ceros Production API.
	 *
	 * @var string
	 */
	private const API_BASE_URL_PRODUCTION = 'https://rest.ceros.com';

	/**
	 * The base URL for the Ceros Staging API.
	 *
	 * @var string
	 */
	private const API_BASE_URL_STAGING = 'https://api-wordpresspoc.dev.flex.cerosdev.com';

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
		$environment = get_option( 'ceros_api_environment', 'production' );

		if ( 'staging' === $environment ) {
			return self::API_BASE_URL_STAGING;
		}

		return self::API_BASE_URL_PRODUCTION;
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

		$url      = $this->get_api_base_url() . $endpoint;
		$response = wp_remote_get(
			$url,
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
					'Accept'        => 'application/json',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

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
		return $this->make_authenticated_request( '/accounts/current-account' );
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

		$result = $this->make_authenticated_request( '/accounts/' . $account_resource_id . '/folder-tree' );

		// Filter out unwanted elements.
		if ( ! is_wp_error( $result ) && is_array( $result['body'] ) ) {
			$result['body'] = array_values( array_filter( $result['body'], function( $item ) {
				if ( ! is_array( $item ) || ! isset( $item['name'] ) ) {
					return true;
				}
				return ! in_array( $item['name'], [ 'Flex Experiences', 'Account Templates' ], true );
			} ) );
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

		$result = $this->make_authenticated_request( '/folder/' . $resource_id . '/experiences' );

		// Filter out invalid experiences on the backend to reduce data sent to frontend
		if ( ! is_wp_error( $result ) && isset( $result['body'] ) ) {
			$experiences = $result['body'];

			// Handle different response structures
			if ( isset( $experiences['items'] ) && is_array( $experiences['items'] ) ) {
				$experiences = $experiences['items'];
			} elseif ( isset( $experiences['data'] ) && is_array( $experiences['data'] ) ) {
				$experiences = $experiences['data'];
			} elseif ( ! is_array( $experiences ) ) {
				$experiences = [];
			}

			// Filter out experiences that shouldn't be shown
			$valid_experiences = array_filter( $experiences, function( $exp ) {
				// Only include published, non-template, non-flex, non-password-protected, non-SSO experiences
				return isset( $exp['status'] ) && $exp['status'] === 'published' &&
				       isset( $exp['isTemplate'] ) && $exp['isTemplate'] === false &&
				       isset( $exp['isFlexExperience'] ) && $exp['isFlexExperience'] === false &&
				       isset( $exp['isPasswordProtected'] ) && $exp['isPasswordProtected'] === false &&
				       isset( $exp['isSSOProtected'] ) && $exp['isSSOProtected'] === false;
			} );

			// Re-index array to ensure sequential keys
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
