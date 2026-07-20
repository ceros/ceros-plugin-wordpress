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
	$plugin_data = get_file_data( CEROS_PLUGIN_FILE, [ 'Version' => 'Version' ] );
	return $plugin_data['Version'] ?? '0.31.0';
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

	// Basic validation for Ceros resource ID format (alphanumeric with hyphens and underscores)
	if ( ! preg_match( '/^' . CEROS_RESOURCE_ID_PATTERN . '$/', $resource_id ) ) {
		return false;
	}

	return $resource_id;
}

/**
 * Check if the Ceros API is properly configured.
 *
 * Uses the Ceros_Encryption class which checks both
 * wp-config.php constant and encrypted database storage.
 *
 * @return bool True if API is configured, false otherwise.
 */
function ceros_is_api_configured() {
	return Ceros_Encryption::is_configured();
}

/**
 * Build the standard headers for outgoing Ceros API requests.
 *
 * Includes authentication, content negotiation, and identification
 * headers so the Ceros API can identify the plugin and version.
 *
 * @param string $api_key The API key (Bearer token).
 * @return array Associative array of HTTP headers.
 */
function ceros_get_api_headers( $api_key ) {
	return [
		'Authorization'       => 'Bearer ' . $api_key,
		'Accept'              => 'application/json',
		'X-Ceros-Api-Version' => CEROS_API_VERSION,
		'X-Ceros-Plugin'      => CEROS_PLUGIN_IDENTIFIER,
	];
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
		[ 'wp-api-fetch', 'wp-components', 'wp-element' ],
		ceros_get_version(),
		true
	);

	wp_enqueue_style(
		'ceros-admin',
		ceros_get_plugin_url() . 'build/ceros/index.css',
		[ 'wp-components' ],
		ceros_get_version()
	);

	// Localize script with API data
	wp_localize_script( 'ceros-admin', 'cerosAdmin', [
		'apiUrl'          => rest_url( CEROS_REST_NAMESPACE . '/' ),
		'nonce'           => wp_create_nonce( 'wp_rest' ),
		'isApiConfigured' => ceros_is_api_configured(),
		'settingsUrl'     => admin_url( 'options-general.php?page=ceros_settings' ),
	] );
}

/**
 * Get user-friendly error messages for API connection errors.
 *
 * Maps technical cURL errors to user-friendly messages that are easy to customize.
 * To change these messages, modify the array returned by this function or use the
 * 'ceros_api_error_messages' filter.
 *
 * @param string $error_message The original error message from WP_Error.
 * @return string User-friendly error message.
 */
function ceros_get_friendly_error_message( $error_message ) {
	// Default error messages - easy to modify
	$error_messages = [
		// cURL error 6: Could not resolve host
		'could not resolve host' => __( 'Unable to connect to the Ceros API. Please check your internet connection and try again. If the problem persists, the Ceros API server may be temporarily unavailable.', 'ceros' ),

		// cURL error 7: Failed to connect
		'failed to connect' => __( 'Failed to connect to the Ceros API. Please check your internet connection and try again.', 'ceros' ),

		// cURL error 28: Operation timeout
		'timeout' => __( 'The connection to the Ceros API timed out. Please try again in a moment.', 'ceros' ),

		// Generic connection errors
		'curl error' => __( 'Unable to connect to the Ceros API. Please check your internet connection and try again.', 'ceros' ),
	];

	// Allow filtering of error messages for easy customization
	$error_messages = apply_filters( 'ceros_api_error_messages', $error_messages );

	// Convert error message to lowercase for case-insensitive matching
	$error_lower = strtolower( $error_message );

	// Check for specific error patterns
	foreach ( $error_messages as $pattern => $friendly_message ) {
		if ( strpos( $error_lower, $pattern ) !== false ) {
			return $friendly_message;
		}
	}

	// Default fallback message if no pattern matches
	return __( 'An error occurred while connecting to the Ceros API. Please try again later or contact support if the problem persists.', 'ceros' );
}

/**
 * Format an error message based on the active environment.
 *
 * - Staging: returns the full technical message (useful for debugging).
 * - Production: logs the technical message to error_log and returns a
 *   user-friendly message instead.
 *
 * Both modes prefix the message with the environment name.
 *
 * @param string $technical_message The full technical error detail.
 * @param string $friendly_message  Optional user-friendly message for production.
 *                                  Falls back to $technical_message if empty.
 * @return string The formatted message to display.
 */
function ceros_format_error( $technical_message, $friendly_message = '' ) {
	$environment = get_option( 'ceros_api_environment', 'production' );
	$env_label   = ucfirst( $environment );

	if ( 'staging' === $environment ) {
		/* translators: 1: environment label, 2: error message */
		return sprintf( __( '[%1$s] %2$s', 'ceros' ), $env_label, $technical_message );
	}

	// Production: log the technical detail, but rate-limit to avoid filling the
	// error log when a key is expired or Ceros is having an outage. We key the
	// transient on a hash of the message so different errors still surface.
	$log_key = 'ceros_err_' . md5( $technical_message );
	if ( false === get_transient( $log_key ) ) {
		error_log( sprintf( 'Ceros [%s]: %s', $env_label, $technical_message ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional production logging.
		set_transient( $log_key, 1, 5 * MINUTE_IN_SECONDS );
	}

	$display = ! empty( $friendly_message ) ? $friendly_message : $technical_message;

	/* translators: 1: environment label, 2: error message */
	return sprintf( __( '[%1$s] %2$s', 'ceros' ), $env_label, $display );
}

/**
 * Enqueue block editor assets and localize data for blocks
 */
function ceros_enqueue_block_editor_assets() {
	// Localize script data for block editor
	wp_localize_script(
		'create-block-ceros-editor-script',
		'cerosBlockData',
		[
			'settingsUrl'     => admin_url( 'options-general.php?page=ceros_settings' ),
			'apiUrl'          => rest_url( CEROS_REST_NAMESPACE . '/' ),
			'nonce'           => wp_create_nonce( 'wp_rest' ),
			// When false, the editor disables "Browse Experiences" and offers the
			// paste-a-public-URL flow instead.
			'isApiConfigured' => ceros_is_api_configured(),
		]
	);
}
add_action( 'enqueue_block_editor_assets', 'ceros_enqueue_block_editor_assets' );

// Hook admin assets.
add_action( 'admin_enqueue_scripts', 'ceros_enqueue_admin_assets' );

/**
 * Get allowed HTML tags and attributes for Ceros embed codes.
 *
 * Ceros embeds typically contain:
 * - <div> containers with data attributes
 * - <script> tags for the Ceros SDK
 * - <iframe> for some embed types
 * - <style> for inline styling
 *
 * @return array Allowed HTML tags and attributes for wp_kses().
 */
function ceros_get_allowed_embed_html() {
	$allowed_html = [
		'div'    => [
			'id'                      => true,
			'class'                   => true,
			'style'                   => true,
			'data-*'                  => true, // Allow all data attributes
			'data-aspectratio'        => true,
			'data-mobile-aspectratio' => true,
			// Flex Inline (iframeless) marker attributes.
			'data-flex-inline'        => true,
			'data-flex-manifest-url'  => true,
		],
		'span'   => [
			'id'    => true,
			'class' => true,
			'style' => true,
		],
		'iframe' => [
			'id'              => true,
			'class'           => true,
			'src'             => true,
			'width'           => true,
			'height'          => true,
			'style'           => true,
			'frameborder'     => true,
			'allowfullscreen' => true,
			'allow'           => true,
			'loading'         => true,
			'title'           => true,
			// Legacy Studio (scroll-proxy) embeds set scrolling="no" on the iframe.
			'scrolling'       => true,
		],
		'script' => [
			'id'      => true,
			'src'     => true,
			'async'   => true,
			'defer'   => true,
			'type'    => true,
			// The legacy scroll-proxy script carries data-ceros-origin-domains.
			'data-*'  => true,
		],
		'style'  => [
			'type' => true,
		],
		'a'      => [
			'href'   => true,
			'target' => true,
			'rel'    => true,
			'class'  => true,
			'id'     => true,
		],
		'p'      => [
			'class' => true,
			'style' => true,
		],
		'img'    => [
			'src'    => true,
			'alt'    => true,
			'width'  => true,
			'height' => true,
			'class'  => true,
			'style'  => true,
		],
	];

	/**
	 * Filter the allowed HTML tags for Ceros embed codes.
	 *
	 * @param array $allowed_html Array of allowed HTML tags and attributes.
	 */
	return apply_filters( 'ceros_allowed_embed_html', $allowed_html );
}

/**
 * Sanitize a Ceros embed code.
 *
 * Uses wp_kses() with a custom allow-list of HTML tags and attributes
 * that are necessary for Ceros embeds to function properly.
 *
 * @param string $embed_code The raw embed code from the Ceros API.
 * @return string The sanitized embed code.
 */
function ceros_sanitize_embed_code( $embed_code ) {
	if ( empty( $embed_code ) || ! is_string( $embed_code ) ) {
		return '';
	}

	// Get allowed HTML tags and attributes.
	$allowed_html = ceros_get_allowed_embed_html();

	// Sanitize using wp_kses with our allow-list.
	$sanitized = wp_kses( $embed_code, $allowed_html );

	/**
	 * Filter the sanitized embed code.
	 *
	 * @param string $sanitized   The sanitized embed code.
	 * @param string $embed_code  The original embed code.
	 */
	return apply_filters( 'ceros_sanitized_embed_code', $sanitized, $embed_code );
}

/**
 * Sanitize an array of embed codes (iframe full-height/scrollable + Flex Inline).
 *
 * The Ceros embed-codes endpoint returns `inlineEmbedCode` (the iframeless Flex
 * Inline snippet) only for Flex experiences, alongside the iframe variants. All
 * three are HTML snippets and must be sanitized before reaching the editor or
 * the frontend.
 *
 * @param array $embed_codes Array with 'fullHeightEmbedCode', 'scrollableEmbedCode', and/or 'inlineEmbedCode'.
 * @return array Sanitized embed codes array.
 */
function ceros_sanitize_embed_codes_array( $embed_codes ) {
	if ( ! is_array( $embed_codes ) ) {
		return [];
	}

	$sanitized = [];

	$html_keys = [ 'fullHeightEmbedCode', 'scrollableEmbedCode', 'inlineEmbedCode' ];
	foreach ( $html_keys as $key ) {
		if ( isset( $embed_codes[ $key ] ) ) {
			$sanitized[ $key ] = ceros_sanitize_embed_code( $embed_codes[ $key ] );
		}
	}

	// Pass through any other keys unchanged (like metadata).
	foreach ( $embed_codes as $key => $value ) {
		if ( ! isset( $sanitized[ $key ] ) ) {
			$sanitized[ $key ] = $value;
		}
	}

	return $sanitized;
}

/**
 * Extract the Flex manifest URL from a Flex Inline snippet's
 * `data-flex-manifest-url` attribute.
 *
 * Must run on the RAW snippet (before `ceros_sanitize_embed_code`): wp_kses
 * normalizes entities in attribute values (e.g. `&` becomes `&#038;`), which
 * would corrupt a manifest URL carrying query parameters. Extracting here — and
 * decoding any entities the API itself included — yields a real, fetchable URL,
 * matching the `manifestUrl` the paste-a-public-URL flow returns.
 *
 * @param string $inline_embed_code The raw Flex Inline snippet from the API.
 * @return string The manifest URL (esc_url_raw'd), or '' when not present.
 */
function ceros_manifest_url_from_inline( $inline_embed_code ) {
	if ( ! is_string( $inline_embed_code ) || '' === $inline_embed_code ) {
		return '';
	}

	// Accept single- or double-quoted attribute values.
	if ( preg_match( '/data-flex-manifest-url=(["\'])(.*?)\1/i', $inline_embed_code, $matches ) ) {
		return esc_url_raw( html_entity_decode( $matches[2], ENT_QUOTES ) );
	}

	return '';
}
