<?php
/**
 * Plugin Name:       Ceros
 * Description:       Ceros API integration plugin
 * Version:           0.32.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            Ceros.com
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
	define( 'CEROS_API_VERSION', '2026-08-06-09-00' );
}
if ( ! defined( 'CEROS_PLUGIN_VERSION' ) ) {
	// Kept in step with the Version header above by tools/check-versions.sh.
	define( 'CEROS_PLUGIN_VERSION', '0.32.0' );
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
// Public (API-key-less) experience resolution.
if ( ! defined( 'CEROS_FLEX_ASSETS_BASE' ) ) {
	// CDN that serves the Flex host-page runtime scripts (embed.v1.js, flex-client.js).
	define( 'CEROS_FLEX_ASSETS_BASE', 'https://assets.ceros.site' );
}
if ( ! defined( 'CEROS_LEGACY_VIEW_HOST' ) ) {
	// Default host for legacy Studio experiences / the scroll-proxy script.
	define( 'CEROS_LEGACY_VIEW_HOST', 'view.ceros.com' );
}
if ( ! defined( 'CEROS_MANIFEST_FILENAME' ) ) {
	// Public, unauthenticated Flex Inline manifest filename. The major version in
	// the filename tracks the manifest schema major: v1 as of Early Access.
	define( 'CEROS_MANIFEST_FILENAME', 'manifest.v1.json' );
}

// Core classes.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-ceros-encryption.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-ceros-api.php';

// Helper functions (must load before settings/rest/ajax which depend on them).
require_once plugin_dir_path( __FILE__ ) . 'includes/functions.php';

// Flex manifest fetching (shared by the public-URL resolver and SSR renderer).
require_once plugin_dir_path( __FILE__ ) . 'includes/flex-manifest.php';

// Public (API-key-less) experience URL resolver — detects Flex vs legacy and
// builds embed codes from a pasted public experience URL. Relies on
// ceros_is_public_host() (settings.php) at request time.
require_once plugin_dir_path( __FILE__ ) . 'includes/public-url-resolver.php';

// Flex SSR (Beta) renderer — server-side fetches the manifest and renders the
// experience's pre-rendered HTML body, with deep-link support.
require_once plugin_dir_path( __FILE__ ) . 'includes/flex-ssr-renderer.php';

// Flex SSR "Store" mode — downloads manifest + assets into uploads for fully
// local, zero-CDN rendering.
require_once plugin_dir_path( __FILE__ ) . 'includes/flex-store.php';

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
