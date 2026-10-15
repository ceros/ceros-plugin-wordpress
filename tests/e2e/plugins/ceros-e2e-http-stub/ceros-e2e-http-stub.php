<?php
/**
 * Plugin Name: Ceros e2e HTTP stub
 * Description: Test-only. Answers outbound requests to the URLs a test has stubbed; inert otherwise.
 *
 * @package ceros-e2e
 */

add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) {
		$stub = get_option( 'ceros_e2e_http_stub_' . md5( $url ) );
		if ( ! is_array( $stub ) ) {
			return $pre;
		}

		if ( isset( $stub['error'] ) ) {
			return new WP_Error( 'http_request_failed', $stub['error'] );
		}

		return [
			'headers'  => $stub['headers'] ?? [],
			'body'     => $stub['body'] ?? '',
			'response' => [
				'code'    => $stub['status'] ?? 200,
				'message' => '',
			],
			'cookies'  => [],
		];
	},
	10,
	3
);
