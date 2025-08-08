<?php
/**
 * Ceros Plugin Functions
 *
 * Common utility functions and helper methods for the Ceros plugin.
 *
 * @package ceros
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the plugin version.
 *
 * @return string The plugin version.
 */
function ceros_get_version() {
	$plugin_data = get_file_data( CEROS_PLUGIN_FILE, array( 'Version' => 'Version' ) );
	return $plugin_data['Version'] ?? '0.1.0';
}

/**
 * Get the plugin directory URL.
 *
 * @return string The plugin directory URL.
 */
function ceros_get_plugin_url() {
	return plugin_dir_url( CEROS_PLUGIN_FILE );
}

/**
 * Get the plugin directory path.
 *
 * @return string The plugin directory path.
 */
function ceros_get_plugin_path() {
	return plugin_dir_path( CEROS_PLUGIN_FILE );
}

/**
 * Check if the current user can manage Ceros settings.
 *
 * @return bool True if user can manage settings, false otherwise.
 */
function ceros_can_manage_settings() {
	return current_user_can( 'manage_options' );
}

/**
 * Sanitize and validate a Ceros resource ID.
 *
 * @param string $resource_id The resource ID to sanitize.
 * @return string|false The sanitized resource ID or false if invalid.
 */
function ceros_sanitize_resource_id( $resource_id ) {
	if ( empty( $resource_id ) || ! is_string( $resource_id ) ) {
		return false;
	}

	// Remove any whitespace and validate format
	$resource_id = trim( $resource_id );
	
	// Basic validation for Ceros resource ID format (alphanumeric with hyphens)
	if ( ! preg_match( '/^[a-zA-Z0-9\-]+$/', $resource_id ) ) {
		return false;
	}

	return $resource_id;
}

/**
 * Get a formatted error message for display.
 *
 * @param string $error_code The error code.
 * @param string $default_message The default message if no specific message is found.
 * @return string The formatted error message.
 */
function ceros_get_error_message( $error_code, $default_message = '' ) {
	$error_messages = array(
		'ceros_api_key_missing'     => __( 'Ceros API key is not set. Please add it in the Ceros settings first.', 'ceros' ),
		'ceros_account_resource_id_missing' => __( 'Account resource ID is required.', 'ceros' ),
		'ceros_resource_id_missing' => __( 'Resource ID is required.', 'ceros' ),
		'ceros_api_error'           => __( 'An error occurred while communicating with the Ceros API.', 'ceros' ),
		'ceros_invalid_response'    => __( 'Invalid response received from Ceros API.', 'ceros' ),
	);

	return isset( $error_messages[ $error_code ] ) ? $error_messages[ $error_code ] : $default_message;
}

/**
 * Log debug information if debugging is enabled.
 *
 * @param mixed $data The data to log.
 * @param string $context Optional context for the log entry.
 */
function ceros_debug_log( $data, $context = '' ) {
	if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
		return;
	}

	$log_entry = array(
		'timestamp' => current_time( 'mysql' ),
		'context'   => $context,
		'data'      => $data,
	);

	error_log( '[Ceros Debug] ' . print_r( $log_entry, true ) );
}

/**
 * Check if the Ceros API is properly configured.
 *
 * @return bool True if API is configured, false otherwise.
 */
function ceros_is_api_configured() {
	$api_key = ceros_get_api_key();
	return ! empty( $api_key );
}

/**
 * Get the current account resource ID from settings.
 *
 * @return string|false The account resource ID or false if not set.
 */
function ceros_get_account_resource_id() {
	$account_resource_id = get_option( 'ceros_account_resource_id', '' );
	return ! empty( $account_resource_id ) ? $account_resource_id : false;
}

/**
 * Format a Ceros experience for display.
 *
 * @param array $experience The experience data from the API.
 * @return array The formatted experience data.
 */
function ceros_format_experience( $experience ) {
	if ( ! is_array( $experience ) ) {
		return array();
	}

	return array(
		'resourceId'    => sanitize_text_field( $experience['resourceId'] ?? '' ),
		'name'          => sanitize_text_field( $experience['name'] ?? '' ),
		'description'   => sanitize_textarea_field( $experience['description'] ?? '' ),
		'thumbnailUrl'  => esc_url_raw( $experience['thumbnailUrl'] ?? '' ),
		'createdAt'     => sanitize_text_field( $experience['createdAt'] ?? '' ),
		'updatedAt'     => sanitize_text_field( $experience['updatedAt'] ?? '' ),
		'status'        => sanitize_text_field( $experience['status'] ?? '' ),
	);
}

/**
 * Get the embed code for a specific experience.
 *
 * @param string $experience_id The experience resource ID.
 * @param string $embed_type The type of embed code (default: 'responsive').
 * @return string|WP_Error The embed code or WP_Error on failure.
 */
function ceros_get_experience_embed_code( $experience_id, $embed_type = 'responsive' ) {
	if ( ! ceros_is_api_configured() ) {
		return new WP_Error( 'ceros_api_not_configured', __( 'Ceros API is not configured.', 'ceros' ) );
	}

	$api = Ceros_API::instance();
	$response = $api->get_embed_codes( $experience_id );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	if ( ! isset( $response['body'] ) || ! is_array( $response['body'] ) ) {
		return new WP_Error( 'ceros_invalid_response', __( 'Invalid response from Ceros API.', 'ceros' ) );
	}

	// Find the embed code for the specified type
	foreach ( $response['body'] as $embed_code ) {
		if ( isset( $embed_code['type'] ) && $embed_code['type'] === $embed_type ) {
			return $embed_code['code'] ?? '';
		}
	}

	// Fallback to first available embed code
	if ( ! empty( $response['body'] ) ) {
		return $response['body'][0]['code'] ?? '';
	}

	return new WP_Error( 'ceros_no_embed_code', __( 'No embed code found for this experience.', 'ceros' ) );
}

/**
 * Enqueue Ceros admin scripts and styles.
 */
function ceros_enqueue_admin_assets() {
	if ( ! ceros_can_manage_settings() ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || strpos( $screen->id, 'ceros' ) === false ) {
		return;
	}

	wp_enqueue_script(
		'ceros-admin',
		ceros_get_plugin_url() . 'build/ceros/index.js',
		array( 'wp-api-fetch', 'wp-components', 'wp-element' ),
		ceros_get_version(),
		true
	);

	wp_enqueue_style(
		'ceros-admin',
		ceros_get_plugin_url() . 'build/ceros/index.css',
		array( 'wp-components' ),
		ceros_get_version()
	);

	// Localize script with API data
	wp_localize_script( 'ceros-admin', 'cerosAdmin', array(
		'apiUrl'     => rest_url( 'ceros/v1/' ),
		'nonce'      => wp_create_nonce( 'wp_rest' ),
		'isConfigured' => ceros_is_api_configured(),
		'settingsUrl' => admin_url( 'options-general.php?page=ceros_settings' ),
	) );
}

/**
 * Enqueue block editor assets and localize data for blocks
 */
function ceros_enqueue_block_editor_assets() {
	// Localize script data for block editor
	wp_localize_script(
		'create-block-ceros-editor-script',
		'cerosBlockData',
		array(
			'settingsUrl' => admin_url( 'options-general.php?page=ceros_settings' ),
			'apiUrl'      => rest_url( 'ceros/v1/' ),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
		)
	);
}
add_action( 'enqueue_block_editor_assets', 'ceros_enqueue_block_editor_assets' );

/**
 * Initialize the functions file.
 */
function ceros_init_functions() {
	// Define plugin file constant if not already defined
	if ( ! defined( 'CEROS_PLUGIN_FILE' ) ) {
		define( 'CEROS_PLUGIN_FILE', __FILE__ );
	}

	// Hook admin assets
	add_action( 'admin_enqueue_scripts', 'ceros_enqueue_admin_assets' );
}

// Initialize functions
ceros_init_functions(); 
