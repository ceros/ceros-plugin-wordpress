<?php
/**
 * Plugin Name: Ceros API stub (e2e fixture)
 * Description: Returns 403 for every server-side Ceros request, reproducing a rejected API key without swapping the real one.
 *
 * Lives only inside the wp-env container; installed and removed by rig/mode.sh.
 *
 * Intercepts server-side calls only. A front-end iframe is fetched by the
 * browser and bypasses this, so the stub can never prove an embed renders.
 *
 * @package Ceros
 */

add_filter(
	'pre_http_request',
	function ( $preempt, $args, $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $host ) {
			return $preempt;
		}

		// The domain families the plugin itself allows, in includes/flex-store.php
		// and includes/public-url-resolver.php.
		$is_ceros = (bool) preg_match(
			'/(^|\.)(ceros\.com|cerosdev\.com|cerosstage\.com|ceros\.site|cerosdev\.site|cerosstage\.site)$/i',
			$host
		);
		if ( ! $is_ceros ) {
			return $preempt;
		}

		return array(
			'headers'  => array( 'content-type' => 'application/json' ),
			'body'     => wp_json_encode( array( 'message' => 'Forbidden resource' ) ),
			'response' => array(
				'code'    => 403,
				'message' => 'Forbidden',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	},
	10,
	3
);
