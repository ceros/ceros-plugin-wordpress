<?php
/**
 * AJAX handlers for the Ceros settings page.
 *
 * Provides the Test Connection and Remove API Key actions
 * used by the inline JavaScript on the settings page.
 *
 * @package ceros
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX handler: Remove the stored API key.
 */
function ceros_ajax_remove_api_key() {
	check_ajax_referer( 'ceros_remove_api_key' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => __( 'You do not have permission to perform this action.', 'ceros' ) ] );
	}

	Ceros_Encryption::save_api_key( '' );

	wp_send_json_success( [ 'message' => __( 'API key removed.', 'ceros' ) ] );
}
add_action( 'wp_ajax_ceros_remove_api_key', 'ceros_ajax_remove_api_key' );
