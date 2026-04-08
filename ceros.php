<?php
/**
 * Plugin Name:       Ceros
 * Description:       Ceros API integration plugin
 * Version: 		  0.30.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            CopiaDigital.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ceros
 *
 * @package CreateBlock
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Plugin constants.
if ( ! defined( 'CEROS_PLUGIN_FILE' ) ) {
	define( 'CEROS_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'CEROS_API_VERSION' ) ) {
	define( 'CEROS_API_VERSION', '2025-12-10-09-11' );
}
if ( ! defined( 'CEROS_REST_NAMESPACE' ) ) {
	define( 'CEROS_REST_NAMESPACE', 'ceros/v1' );
}
if ( ! defined( 'CEROS_RESOURCE_ID_PATTERN' ) ) {
	// Used for both REST route regex and sanitizer validation.
	define( 'CEROS_RESOURCE_ID_PATTERN', '[a-zA-Z0-9\-_]+' );
}
if ( ! defined( 'CEROS_PRODUCTION_API_URL' ) ) {
	define( 'CEROS_PRODUCTION_API_URL', 'https://rest.ceros.com' );
}
if ( ! defined( 'CEROS_API_REQUEST_TIMEOUT' ) ) {
	define( 'CEROS_API_REQUEST_TIMEOUT', 15 );
}
if ( ! defined( 'CEROS_ENV_PRODUCTION' ) ) {
	define( 'CEROS_ENV_PRODUCTION', 'production' );
}
if ( ! defined( 'CEROS_ENV_STAGING' ) ) {
	define( 'CEROS_ENV_STAGING', 'staging' );
}
if ( ! defined( 'CEROS_ENDPOINT_CURRENT_ACCOUNT' ) ) {
	define( 'CEROS_ENDPOINT_CURRENT_ACCOUNT', '/accounts/current-account' );
}
if ( ! defined( 'CEROS_PLUGIN_IDENTIFIER' ) ) {
	define( 'CEROS_PLUGIN_IDENTIFIER', 'wordpress' );
}

// Core classes.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-ceros-encryption.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-ceros-api.php';

// Helper functions (must load before settings/rest/ajax which depend on them).
require_once plugin_dir_path( __FILE__ ) . 'includes/functions.php';

// Settings page, sanitization, and admin menu.
require_once plugin_dir_path( __FILE__ ) . 'includes/settings.php';

// REST API routes and response handling.
require_once plugin_dir_path( __FILE__ ) . 'includes/rest-api.php';

// AJAX handlers for the settings page.
require_once plugin_dir_path( __FILE__ ) . 'includes/ajax-handlers.php';

// Block registration and asset management.
require_once plugin_dir_path( __FILE__ ) . 'includes/blocks.php';

// Block render callback.
require_once plugin_dir_path( __FILE__ ) . 'build/ceros/render.php';
