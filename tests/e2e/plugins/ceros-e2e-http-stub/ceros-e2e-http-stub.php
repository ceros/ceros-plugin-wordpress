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
		$stubs = get_option( 'ceros_e2e_http_stubs', [] );
		if ( ! isset( $stubs[ $url ] ) ) {
			return $pre;
		}

		$stub = $stubs[ $url ];
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
