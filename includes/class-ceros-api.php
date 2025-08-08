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
	 * The base URL for the Ceros API.
	 *
	 * @var string
	 */
	protected $base_url = 'https://api.ceros.com';

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
	 * Make a GET request against the API.
	 *
	 * @param string $path   Endpoint path, e.g. "/v1/some/endpoint".
	 * @param array  $args   Extra wp_remote_get arguments.
	 *
	 * @return array|WP_Error Returns associative array on success, WP_Error on failure.
	 */
	public function get( $path, $args = array() ) {
		return $this->request( 'GET', $path, $args );
	}

	/**
	 * Perform the actual request using WordPress HTTP API.
	 *
	 * @param string $method HTTP method.
	 * @param string $path   Endpoint path.
	 * @param array  $args   Additional arguments for wp_remote_ functions.
	 *
	 * @return array|WP_Error
	 */
	protected function request( $method, $path, $args = array() ) {
		$endpoint = trailingslashit( $this->base_url ) . ltrim( $path, '/' );

		$defaults = array(
			'headers' => array(
				'x-api-key' => ceros_get_api_key(),
				'Accept'    => 'application/json',
			),
		);

		$args = wp_parse_args( $args, $defaults );

		$response = ( 'GET' === $method )
			? wp_remote_get( $endpoint, $args )
			: wp_remote_post( $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		return array(
			'code' => $code,
			'body' => $data,
		);
	}

	/**
	 * Example endpoint wrapper.
	 *
	 * Add concrete methods as needed so that block/editor code can fetch data
	 * without worrying about low-level request logic.
	 */
	public function get_example() {
		return $this->get( '/v1/example' );
	}

	/**
	 * Fetch the current account details from the Ceros POC API.
	 *
	 * Endpoint: https://api-wordpresspoc.dev.flex.cerosdev.com/accounts/current-account
	 * Auth:     Bearer Token (value = saved API key)
	 *
	 * @return array|WP_Error
	 */
	public function get_current_account() {
		$api_key = ceros_get_api_key();

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'ceros_api_key_missing',
				__( 'Ceros API key is not set. Please add it in the Ceros settings first.', 'ceros' )
			);
		}

		$url      = 'https://api-wordpresspoc.dev.flex.cerosdev.com/accounts/current-account';
		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Accept'        => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		return array(
			'code' => $code,
			'body' => $data,
		);
	}

	/**
	 * Fetch the folder tree for a specific account from the Ceros POC API.
	 *
	 * Endpoint: https://api-wordpresspoc.dev.flex.cerosdev.com/accounts/{accountResourceID}/folder-tree
	 * Auth:     Bearer Token (value = saved API key)
	 *
	 * @param string $account_resource_id The account resource ID.
	 *
	 * @return array|WP_Error
	 */
	public function get_folder_tree( $account_resource_id ) {
		$api_key = ceros_get_api_key();

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'ceros_api_key_missing',
				__( 'Ceros API key is not set. Please add it in the Ceros settings first.', 'ceros' )
			);
		}

		if ( empty( $account_resource_id ) ) {
			return new WP_Error(
				'ceros_account_resource_id_missing',
				__( 'Account resource ID is required to fetch folder tree.', 'ceros' )
			);
		}

		$url      = 'https://api-wordpresspoc.dev.flex.cerosdev.com/accounts/' . $account_resource_id . '/folder-tree';
		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Accept'        => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		// Filter out unwanted elements.
		if ( is_array( $data ) ) {
			$data = array_filter( $data, function( $item ) {
				if ( ! is_array( $item ) || ! isset( $item['name'] ) ) {
					return true;
				}
				
				return ! in_array( $item['name'], array( 'Flex Experiences', 'Account Templates' ), true );
			});
			
			// Re-index the array to maintain clean numeric indices.
			$data = array_values( $data );
		}

		return array(
			'code' => $code,
			'body' => $data,
		);
	}

	/**
	 * Fetch experiences for a specific folder from the Ceros POC API.
	 *
	 * Endpoint: https://api-wordpresspoc.dev.flex.cerosdev.com/folder/{resourceId}/experiences
	 * Auth:     Bearer Token (value = saved API key)
	 *
	 * @param string $resource_id The folder resource ID.
	 *
	 * @return array|WP_Error
	 */
	public function get_experiences( $resource_id ) {
		$api_key = ceros_get_api_key();

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'ceros_api_key_missing',
				__( 'Ceros API key is not set. Please add it in the Ceros settings first.', 'ceros' )
			);
		}

		if ( empty( $resource_id ) ) {
			return new WP_Error(
				'ceros_resource_id_missing',
				__( 'Resource ID is required to fetch experiences.', 'ceros' )
			);
		}

		$url      = 'https://api-wordpresspoc.dev.flex.cerosdev.com/folder/' . $resource_id . '/experiences';
		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Accept'        => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		return array(
			'code' => $code,
			'body' => $data,
		);
	}

	/**
	 * Fetch embed codes for a specific experience from the Ceros POC API.
	 *
	 * Endpoint: https://api-wordpresspoc.dev.flex.cerosdev.com/experiences/{resourceId}/embed-codes
	 *
	 * @param string $resource_id Experience resource ID.
	 *
	 * @return array|WP_Error
	 */
	public function get_embed_codes( $resource_id ) {
		$api_key = ceros_get_api_key();

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'ceros_api_key_missing',
				__( 'Ceros API key is not set. Please add it in the Ceros settings first.', 'ceros' )
			);
		}

		if ( empty( $resource_id ) ) {
			return new WP_Error(
				'ceros_resource_id_missing',
				__( 'Resource ID is required to fetch embed codes.', 'ceros' )
			);
		}

		$url      = 'https://api-wordpresspoc.dev.flex.cerosdev.com/experiences/' . $resource_id . '/embed-codes';
		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Accept'        => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		return array(
			'code' => $code,
			'body' => $data,
		);
	}
} 