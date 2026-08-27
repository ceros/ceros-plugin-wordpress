<?php
/**
 * Bootstrap for the unit suite. Loads plugin files with no WordPress present.
 *
 * Only hook registration, exact PHP equivalents and translation passthrough may
 * be shimmed here. Escaping, sanitizing, HTTP and options must not be — see
 * tests/README.md for the rule and the reasoning.
 *
 * @package ceros
 */

// Every plugin file guards on ABSPATH and would silently exit without it.
define( 'ABSPATH', __DIR__ . '/' );

define( 'CEROS_PLUGIN_FILE', dirname( __DIR__ ) . '/ceros.php' );
define( 'CEROS_API_REQUEST_TIMEOUT', 15 );
define( 'CEROS_RESOURCE_ID_PATTERN', '[a-zA-Z0-9\-_]+' );
define( 'CEROS_ENV_PRODUCTION', 'production' );
define( 'CEROS_ENV_STAGING', 'staging' );
define( 'CEROS_PRODUCTION_API_URL', 'https://rest.ceros.com' );
define( 'CEROS_FLEX_ASSETS_BASE', 'https://assets.ceros.site' );
define( 'CEROS_LEGACY_VIEW_HOST', 'view.ceros.com' );
define( 'CEROS_MANIFEST_FILENAME', 'manifest.v1.json' );

// Ceros_Encryption derives its key from these constants, not wp_salt().
define( 'LOGGED_IN_KEY', 'unit-test-logged-in-key-0123456789abcdef' );
define( 'LOGGED_IN_SALT', 'unit-test-logged-in-salt-fedcba9876543210' );

/**
 * Mirrors core: a placeholder scheme/host makes protocol- and root-relative
 * URLs parse correctly, then is removed. For absolute URLs this is parse_url().
 */
function wp_parse_url( $url, $component = -1 ) {
	$to_unset = [];
	$url      = (string) $url;

	if ( '//' === substr( $url, 0, 2 ) ) {
		$to_unset[] = 'scheme';
		$url        = 'placeholder:' . $url;
	} elseif ( '/' === substr( $url, 0, 1 ) ) {
		$to_unset[] = 'scheme';
		$to_unset[] = 'host';
		$url        = 'placeholder://placeholder' . $url;
	}

	$parts = parse_url( $url );

	if ( false === $parts ) {
		return $parts;
	}

	foreach ( $to_unset as $key ) {
		unset( $parts[ $key ] );
	}

	if ( -1 === $component ) {
		return $parts;
	}

	$key = [
		PHP_URL_SCHEME   => 'scheme',
		PHP_URL_HOST     => 'host',
		PHP_URL_PORT     => 'port',
		PHP_URL_USER     => 'user',
		PHP_URL_PASS     => 'pass',
		PHP_URL_PATH     => 'path',
		PHP_URL_QUERY    => 'query',
		PHP_URL_FRAGMENT => 'fragment',
	];

	if ( isset( $key[ $component ] ) && isset( $parts[ $key[ $component ] ] ) ) {
		return $parts[ $key[ $component ] ];
	}

	return null;
}

/**
 * WordPress returns the string unchanged with no textdomain loaded.
 */
function __( $text, $domain = 'default' ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.stringFound
	return $text;
}

/**
 * No-op: nothing under test observes the hook registry.
 */
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	return true;
}

/**
 * No-op: nothing under test observes the hook registry.
 */
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	return true;
}

/**
 * Passthrough: with no filters added, WordPress returns the value unchanged,
 * and the no-op add_filter() above means none ever are. Same reasoning as the
 * __() shim — this is what core does, not a stand-in for what core does.
 */
function apply_filters( $hook, $value ) {
	return $value;
}

/**
 * Only used at file scope to build a filter name.
 */
function plugin_basename( $file ) {
	return basename( dirname( $file ) ) . '/' . basename( $file );
}

require_once dirname( __DIR__ ) . '/includes/class-ceros-encryption.php';
require_once dirname( __DIR__ ) . '/includes/public-url-resolver.php';
require_once dirname( __DIR__ ) . '/includes/flex-store.php';
require_once dirname( __DIR__ ) . '/includes/flex-ssr-renderer.php';
// functions.php and settings.php register hooks at file scope, which is the
// reason add_action/add_filter/plugin_basename are shimmed above.
require_once dirname( __DIR__ ) . '/includes/functions.php';
require_once dirname( __DIR__ ) . '/includes/settings.php';
