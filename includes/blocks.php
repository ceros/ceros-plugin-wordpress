<?php
/**
 * Block registration and asset management.
 *
 * Handles Gutenberg block registration, asset versioning,
 * and CSS cache busting for the Ceros plugin.
 *
 * @package ceros
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the block using a `blocks-manifest.php` file, which improves the performance of block type registration.
 * Behind the scenes, it also registers all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://make.wordpress.org/core/2025/03/13/more-efficient-block-type-registration-in-6-8/
 * @see https://make.wordpress.org/core/2024/10/17/new-block-type-registration-apis-to-improve-performance-in-wordpress-6-7/
 */
function ceros_block_init() {
	$plugin_dir = plugin_dir_path( CEROS_PLUGIN_FILE );

	/**
	 * Registers the block(s) metadata from the `blocks-manifest.php` file.
	 * Added to WordPress 6.7 to improve the performance of block type registration.
	 *
	 * @see https://make.wordpress.org/core/2024/10/17/new-block-type-registration-apis-to-improve-performance-in-wordpress-6-7/
	 */
	if ( function_exists( 'wp_register_block_metadata_collection' ) ) {
		wp_register_block_metadata_collection( $plugin_dir . 'build', $plugin_dir . 'build/blocks-manifest.php' );
	}

	/**
	 * Registers the block type(s) in the `blocks-manifest.php` file.
	 *
	 * @see https://developer.wordpress.org/reference/functions/register_block_type/
	 */
	$manifest_data = require $plugin_dir . 'build/blocks-manifest.php';
	foreach ( array_keys( $manifest_data ) as $block_type ) {
		if ( 'ceros' === $block_type ) {
			register_block_type(
				$plugin_dir . "build/{$block_type}",
				[
					'render_callback' => 'ceros_render_block',
				]
			);
		} else {
			register_block_type( $plugin_dir . "build/{$block_type}" );
		}
	}
}
add_action( 'init', 'ceros_block_init' );

/**
 * Get the Ceros asset version for cache busting.
 *
 * @return string Version string based on file modification time.
 */
function ceros_get_asset_version() {
	$css_file = plugin_dir_path( CEROS_PLUGIN_FILE ) . 'build/ceros/index.css';
	return file_exists( $css_file ) ? (string) filemtime( $css_file ) : '0.1.0';
}

/**
 * Add cache busting to Ceros CSS files.
 * Uses style_loader_src filter as a fallback to ensure version is applied.
 */
function ceros_add_cache_busting_to_css() {
	$version = ceros_get_asset_version();

	add_filter(
		'style_loader_src',
		function ( $src ) use ( $version ) {
			// Only target Ceros CSS files.
			if ( strpos( $src, '/ceros/' ) === false || strpos( $src, '.css' ) === false ) {
				return $src;
			}

			// Parse URL to check/replace version parameter.
			$parsed       = wp_parse_url( $src );
			$query_params = [];

			if ( ! empty( $parsed['query'] ) ) {
				parse_str( $parsed['query'], $query_params );
			}

			// Set or replace the version parameter (prevents duplicates).
			$query_params['ver'] = $version;

			// Rebuild the URL.
			$base_url = $parsed['scheme'] . '://' . $parsed['host'];
			if ( ! empty( $parsed['port'] ) ) {
				$base_url .= ':' . $parsed['port'];
			}
			$base_url .= $parsed['path'];

			return $base_url . '?' . http_build_query( $query_params );
		},
		10,
		1
	);
}
add_action( 'init', 'ceros_add_cache_busting_to_css' );

/**
 * Modify block registration to include proper versioning for cache busting.
 */
function ceros_modify_block_registration() {
	$version = ceros_get_asset_version();

	add_filter(
		'block_type_metadata',
		function ( $metadata ) use ( $version ) {
			if ( isset( $metadata['name'] ) && 'create-block/ceros' === $metadata['name'] ) {
				$metadata['version'] = $version;
			}
			return $metadata;
		},
		10,
		1
	);
}
add_action( 'init', 'ceros_modify_block_registration' );
