<?php
/**
 * Bootstrap for the unit suite. Loads plugin files with no WordPress present.
 *
 * The suite covers functions whose logic does not depend on WordPress
 * behaviour, so it runs in milliseconds with no Docker, database or network —
 * which is what makes it usable from the pre-push hook.
 *
 * WHAT MAY BE SHIMMED HERE, and nothing beyond it:
 *
 *   1. Hook registration (add_action/add_filter) as no-ops. Registering a hook
 *      cannot affect the return value of the functions under test.
 *   2. Helpers where WordPress is a thin wrapper over PHP and a faithful
 *      equivalent is exact, not approximate (wp_parse_url, plugin_basename).
 *   3. Translation passthrough (__), which is what WordPress itself returns
 *      when no textdomain is loaded.
 *
 * Escaping and sanitizing (esc_*, sanitize_*, wp_kses), HTTP (wp_remote_*),
 * options (get_option) and upload-dir helpers are deliberately NOT shimmed. A
 * stub for those would mean asserting against the stub instead of against
 * WordPress, so anything depending on them belongs in an integration suite
 * running against real WordPress. See tests/README.md.
 *
 * @package ceros
 */

// Every plugin file guards on ABSPATH and would silently exit without it.
define( 'ABSPATH', __DIR__ . '/' );

define( 'CEROS_PLUGIN_FILE', dirname( __DIR__ ) . '/ceros.php' );
define( 'CEROS_API_REQUEST_TIMEOUT', 15 );
define( 'CEROS_ENV_PRODUCTION', 'production' );
define( 'CEROS_ENV_STAGING', 'staging' );
define( 'CEROS_PRODUCTION_API_URL', 'https://rest.ceros.com' );
define( 'CEROS_FLEX_ASSETS_BASE', 'https://assets.ceros.site' );
define( 'CEROS_LEGACY_VIEW_HOST', 'view.ceros.com' );
define( 'CEROS_MANIFEST_FILENAME', 'manifest.v1.json' );

/**
 * Faithful stand-in for WordPress's wp_parse_url().
 *
 * Mirrors core: protocol-relative and root-relative URLs are parsed by giving
 * them a placeholder scheme/host which is then removed, because parse_url()
 * alone misreads them. For absolute URLs this is exactly parse_url().
 *
 * @param string $url       The URL to parse.
 * @param int    $component The specific component to retrieve.
 * @return mixed Parsed URL components, or false on failure.
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
 * Translation passthrough. WordPress returns the string unchanged when no
 * textdomain is loaded, so this is faithful rather than a stub.
 *
 * @param string $text   Text to translate.
 * @param string $domain Textdomain (unused).
 * @return string
 */
function __( $text, $domain = 'default' ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.stringFound
	return $text;
}

/**
 * No-op hook registration. Nothing under test observes the hook registry.
 *
 * @param string   $hook     Hook name.
 * @param callable $callback Callback.
 * @param int      $priority Priority.
 * @param int      $args     Accepted args.
 * @return true
 */
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	return true;
}

/**
 * No-op hook registration.
 *
 * @param string   $hook     Hook name.
 * @param callable $callback Callback.
 * @param int      $priority Priority.
 * @param int      $args     Accepted args.
 * @return true
 */
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	return true;
}

/**
 * Plugin basename. Only used here to build a filter name at file scope.
 *
 * @param string $file Plugin file path.
 * @return string
 */
function plugin_basename( $file ) {
	return basename( dirname( $file ) ) . '/' . basename( $file );
}

require_once dirname( __DIR__ ) . '/includes/public-url-resolver.php';
require_once dirname( __DIR__ ) . '/includes/flex-store.php';
// Loaded for ceros_is_public_host(); its file-scope hook calls are the reason
// add_action/add_filter/plugin_basename are shimmed above.
require_once dirname( __DIR__ ) . '/includes/settings.php';
