<?php
/**
 * Plugin Name:       Ceros
 * Description:       Ceros API integration POC plugin
 * Version:           0.15.0
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
/**
 * Registers the block using a `blocks-manifest.php` file, which improves the performance of block type registration.
 * Behind the scenes, it also registers all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://make.wordpress.org/core/2025/03/13/more-efficient-block-type-registration-in-6-8/
 * @see https://make.wordpress.org/core/2024/10/17/new-block-type-registration-apis-to-improve-performance-in-wordpress-6-7/
 */
function create_block_ceros_block_init() {
	file_put_contents( '/tmp/ceros_debug.log', 'Init function called at ' . date('Y-m-d H:i:s') . "\n", FILE_APPEND );
	/**
	 * Registers the block(s) metadata from the `blocks-manifest.php` and registers the block type(s)
	 * based on the registered block metadata.
	 * Added in WordPress 6.8 to simplify the block metadata registration process added in WordPress 6.7.
	 *
	 * @see https://make.wordpress.org/core/2025/03/13/more-efficient-block-type-registration-in-6-8/
	 */
	// Skip automatic registration - force manual registration with render callback
	file_put_contents( '/tmp/ceros_debug.log', 'Skipping automatic registration, using manual registration' . "\n", FILE_APPEND );

	/**
	 * Registers the block(s) metadata from the `blocks-manifest.php` file.
	 * Added to WordPress 6.7 to improve the performance of block type registration.
	 *
	 * @see https://make.wordpress.org/core/2024/10/17/new-block-type-registration-apis-to-improve-performance-in-wordpress-6-7/
	 */
	if ( function_exists( 'wp_register_block_metadata_collection' ) ) {
		file_put_contents( '/tmp/ceros_debug.log', 'Using wp_register_block_metadata_collection' . "\n", FILE_APPEND );
		wp_register_block_metadata_collection( __DIR__ . '/build', __DIR__ . '/build/blocks-manifest.php' );
	} else {
		file_put_contents( '/tmp/ceros_debug.log', 'wp_register_block_metadata_collection not available, using manual registration' . "\n", FILE_APPEND );
	}
	/**
	 * Registers the block type(s) in the `blocks-manifest.php` file.
	 *
	 * @see https://developer.wordpress.org/reference/functions/register_block_type/
	 */
	$manifest_data = require __DIR__ . '/build/blocks-manifest.php';
	file_put_contents( '/tmp/ceros_debug.log', 'Manifest loaded with blocks: ' . implode(', ', array_keys($manifest_data)) . "\n", FILE_APPEND );
	foreach ( array_keys( $manifest_data ) as $block_type ) {
		file_put_contents( '/tmp/ceros_debug.log', 'Processing block type: ' . $block_type . "\n", FILE_APPEND );
		// Add render callback for our Ceros block
		if ( $block_type === 'ceros' ) {
			file_put_contents( '/tmp/ceros_debug.log', 'Registering Ceros block with render callback at ' . date('Y-m-d H:i:s') . "\n", FILE_APPEND );
			$result = register_block_type( __DIR__ . "/build/{$block_type}", array(
				'render_callback' => 'render_create_block_ceros',
			) );
			if ( $result ) {
				file_put_contents( '/tmp/ceros_debug.log', 'Block registered successfully: ' . $result->name . "\n", FILE_APPEND );
			} else {
				file_put_contents( '/tmp/ceros_debug.log', 'Block registration failed' . "\n", FILE_APPEND );
			}
		} else {
			register_block_type( __DIR__ . "/build/{$block_type}" );
		}
	}
}

/**
 * Add cache busting to Ceros CSS files
 * This ensures that CSS updates are immediately reflected in the browser
 */
function ceros_add_cache_busting_to_css() {
	// Get the asset version from the index.asset.php file
	$asset_file = __DIR__ . '/build/ceros/index.asset.php';
	$version = '0.1.0'; // Default version
	if ( file_exists( $asset_file ) ) {
		$asset_data = require $asset_file;
		$version = isset( $asset_data['version'] ) ? $asset_data['version'] : filemtime( __DIR__ . '/build/ceros/index.css' );
	} else {
		// Fallback to file modification time
		$version = filemtime( __DIR__ . '/build/ceros/index.css' );
	}

	// Add filter to modify the CSS URL to include version parameter
	add_filter( 'style_loader_src', function( $src, $handle ) use ( $version ) {
		// Check if this is a Ceros CSS file
		if ( strpos( $src, 'ceros' ) !== false && strpos( $src, '.css' ) !== false ) {
			// Add version parameter to the URL
			$separator = strpos( $src, '?' ) !== false ? '&' : '?';
			$src .= $separator . 'ver=' . $version;
		}
		return $src;
	}, 10, 2 );
}
add_action( 'init', 'ceros_add_cache_busting_to_css' );

/**
 * Modify block registration to include proper versioning for cache busting
 */
function ceros_modify_block_registration() {
	// Get the asset version
	$asset_file = __DIR__ . '/build/ceros/index.asset.php';
	$version = '0.1.0'; // Default version
	if ( file_exists( $asset_file ) ) {
		$asset_data = require $asset_file;
		$version = isset( $asset_data['version'] ) ? $asset_data['version'] : filemtime( __DIR__ . '/build/ceros/index.css' );
	} else {
		$version = filemtime( __DIR__ . '/build/ceros/index.css' );
	}

	// Add filter to modify the block.json data
	add_filter( 'block_type_metadata', function( $metadata ) use ( $version ) {
		if ( isset( $metadata['name'] ) && $metadata['name'] === 'create-block/ceros' ) {
			// Add version to the metadata
			$metadata['version'] = $version;
		}
		return $metadata;
	}, 10, 1 );
}
add_action( 'init', 'ceros_modify_block_registration' );

/**
 * Force cache busting by adding file modification time to CSS URLs
 * This ensures CSS updates are loaded when the file changes
 */
function ceros_force_cache_busting() {
	// Add filter to append file modification time to CSS URLs
	add_filter( 'style_loader_src', function( $src, $handle ) {
		// Check if this is a Ceros CSS file
		if ( strpos( $src, 'ceros' ) !== false && strpos( $src, '.css' ) !== false ) {
			// Get file modification time for cache busting
			$css_file = __DIR__ . '/build/ceros/index.css';
			$file_time = file_exists( $css_file ) ? filemtime( $css_file ) : time();
			
			// Add file modification time parameter to force cache busting
			$separator = strpos( $src, '?' ) !== false ? '&' : '?';
			$src .= $separator . 'ver=' . $file_time;
		}
		return $src;
	}, 10, 2 );
}
add_action( 'init', 'ceros_force_cache_busting' );

/**
 * Manual cache busting - you can update this constant to force cache refresh
 * Uncomment the line below and change the version number when you update CSS
 */
// define( 'CEROS_CSS_VERSION', '1.0.1' );

/**
 * Alternative manual cache busting function
 * Uncomment this function and comment out the automatic cache busting above
 * if you prefer manual control over cache busting
 */
/*
function ceros_manual_cache_busting() {
	if ( defined( 'CEROS_CSS_VERSION' ) ) {
		add_filter( 'style_loader_src', function( $src, $handle ) {
			if ( strpos( $src, 'ceros' ) !== false && strpos( $src, '.css' ) !== false ) {
				$separator = strpos( $src, '?' ) !== false ? '&' : '?';
				$src .= $separator . 'ver=' . CEROS_CSS_VERSION;
			}
			return $src;
		}, 10, 2 );
	}
}
add_action( 'init', 'ceros_manual_cache_busting' );
*/

add_action( 'init', 'create_block_ceros_block_init' );

/**
 * -----------------------------------------------------------------------------
 * Ceros Plugin Enhancements
 * -----------------------------------------------------------------------------
 * 1. Options page for storing the Ceros API key.
 * 2. Loader for API client class – provides a single point for all external
 *    requests (admin + frontend).
 * -----------------------------------------------------------------------------
 */

// Load helper classes – keep all includes contained inside the plugin.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-ceros-api.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/functions.php';

/**
 * Register plugin settings.
 *
 * Stores the API key in the WordPress options table. Sanitisation keeps things
 * safe and simple.
 */
function ceros_register_settings() {
	register_setting(
		'ceros_settings_group', // Option group.
		'ceros_api_key',        // Option name.
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);
}
add_action( 'admin_init', 'ceros_register_settings' );

/**
 * Add the Ceros settings page under the standard "Settings" menu.
 */
function ceros_add_options_page() {
	add_options_page(
		__( 'Ceros Settings', 'ceros' ),
		__( 'Ceros', 'ceros' ),
		'manage_options',
		'ceros_settings',
		'ceros_render_options_page'
	);
}
add_action( 'admin_menu', 'ceros_add_options_page' );

/**
 * Render the markup for the options page.
 */
function ceros_render_options_page() {
	// Capability check.
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Ceros Settings', 'ceros' ); ?></h1>

		<form action="options.php" method="post">
			<?php
			settings_fields( 'ceros_settings_group' );
			?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="ceros_api_key"><?php esc_html_e( 'API Key', 'ceros' ); ?></label>
					</th>
					<td>
						<input name="ceros_api_key" type="password" id="ceros_api_key" value="<?php echo esc_attr( get_option( 'ceros_api_key', '' ) ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Enter your Ceros API key.', 'ceros' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/**
 * Helper to retrieve the stored API key.
 *
 * @return string
 */
function ceros_get_api_key() {
	return get_option( 'ceros_api_key', '' );
}

/**
 * Register custom REST API routes for the Ceros plugin.
 */
// Load render callback for block
require_once plugin_dir_path( __FILE__ ) . 'src/ceros/render.php';

function ceros_register_rest_routes() {
	register_rest_route(
		'ceros/v1',
		'/current-account',
		array(
			'methods'             => 'GET',
			'callback'            => 'ceros_rest_get_current_account',
			'permission_callback' => function () {
				// Require a user who can edit posts (i.e. editors, admins) in the editor.
				return current_user_can( 'edit_posts' );
			},
		)
	);

	register_rest_route(
		'ceros/v1',
		'/folder-tree/(?P<account_resource_id>[a-zA-Z0-9\-_]+)',
		array(
			'methods'             => 'GET',
			'callback'            => 'ceros_rest_get_folder_tree',
			'permission_callback' => function () {
				// Require a user who can edit posts (i.e. editors, admins) in the editor.
				return current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'account_resource_id' => array(
					'required' => true,
					'type'     => 'string',
				),
			),
		)
	);

	register_rest_route(
		'ceros/v1',
		'/folder/(?P<resource_id>[a-zA-Z0-9\-_]+)/experiences',
		array(
			'methods'             => 'GET',
			'callback'            => 'ceros_rest_get_experiences',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'resource_id' => array(
					'required' => true,
					'type'     => 'string',
				),
			),
		)
	);

	register_rest_route(
		'ceros/v1',
		'/experiences/(?P<resource_id>[a-zA-Z0-9\-_]+)/embed-codes',
		array(
			'methods'             => 'GET',
			'callback'            => 'ceros_rest_get_embed_codes',
			'permission_callback' => function () {
				// Require a user who can edit posts (i.e. editors, admins) in the editor.
				return current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'resource_id' => array(
					'required' => true,
					'type'     => 'string',
				),
			),
		)
	);

	register_rest_route(
		'ceros/v1',
		'/api-key-status',
		array(
			'methods'             => 'GET',
			'callback'            => 'ceros_rest_get_api_key_status',
			'permission_callback' => function () {
				// Require a user who can edit posts (i.e. editors, admins) in the editor.
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'rest_api_init', 'ceros_register_rest_routes' );

/**
 * Helper function to check for 403 Forbidden API responses and return appropriate error.
 *
 * @param array $result The API result array.
 * @return WP_REST_Response|null Returns WP_REST_Response for 403 errors, null otherwise.
 */
function ceros_check_forbidden_response( $result ) {
	if ( isset( $result['code'] ) && $result['code'] === 403 && 
		 isset( $result['body']['message'] ) && $result['body']['message'] === 'Forbidden resource' ) {
		return new WP_REST_Response(
			array( 
				'code' => 403,
				'body' => array( 'message' => 'Forbidden resource' ),
				'error' => 'The API call was forbidden, which usually means your API key is invalid. Please confirm that your API key is correct.'
			),
			403
		);
	}
	return null;
}

/**
 * REST callback that proxies to the Ceros API client.
 *
 * @param WP_REST_Request $request The REST request instance.
 *
 * @return WP_REST_Response|WP_Error
 */
function ceros_rest_get_current_account( WP_REST_Request $request ) {
	$result = Ceros_API::instance()->get_current_account();

	if ( is_wp_error( $result ) ) {
		return new WP_REST_Response(
			array( 'error' => $result->get_error_message() ),
			400
		);
	}

	// Check for 403 Forbidden response which typically means invalid API key
	$forbidden_response = ceros_check_forbidden_response( $result );
	if ( $forbidden_response ) {
		return $forbidden_response;
	}

	return rest_ensure_response( $result );
}

/**
 * REST callback that proxies to the Ceros API client for folder tree.
 *
 * @param WP_REST_Request $request The REST request instance.
 *
 * @return WP_REST_Response|WP_Error
 */
function ceros_rest_get_folder_tree( WP_REST_Request $request ) {
	$account_resource_id = $request->get_param( 'account_resource_id' );
	$result = Ceros_API::instance()->get_folder_tree( $account_resource_id );

	if ( is_wp_error( $result ) ) {
		return new WP_REST_Response(
			array( 'error' => $result->get_error_message() ),
			400
		);
	}

	// Check for 403 Forbidden response which typically means invalid API key
	$forbidden_response = ceros_check_forbidden_response( $result );
	if ( $forbidden_response ) {
		return $forbidden_response;
	}

	return rest_ensure_response( $result );
}

/**
 * REST callback that proxies to the Ceros API client for experiences.
 *
 * @param WP_REST_Request $request The REST request instance.
 *
 * @return WP_REST_Response|WP_Error
 */
function ceros_rest_get_experiences( WP_REST_Request $request ) {
	$resource_id = $request->get_param( 'resource_id' );
	$result = Ceros_API::instance()->get_experiences( $resource_id );

	if ( is_wp_error( $result ) ) {
		return new WP_REST_Response(
			array( 'error' => $result->get_error_message() ),
			400
		);
	}

	// Check for 403 Forbidden response which typically means invalid API key
	$forbidden_response = ceros_check_forbidden_response( $result );
	if ( $forbidden_response ) {
		return $forbidden_response;
	}

	return rest_ensure_response( $result );
}

/**
 * REST callback that proxies to the Ceros API client for embed codes.
 *
 * @param WP_REST_Request $request The REST request instance.
 *
 * @return WP_REST_Response|WP_Error
 */
function ceros_rest_get_embed_codes( WP_REST_Request $request ) {
	$resource_id = $request->get_param( 'resource_id' );
	$result      = Ceros_API::instance()->get_embed_codes( $resource_id );

	if ( is_wp_error( $result ) ) {
		return new WP_REST_Response(
			array( 'error' => $result->get_error_message() ),
			400
		);
	}

	// Check for 403 Forbidden response which typically means invalid API key
	$forbidden_response = ceros_check_forbidden_response( $result );
	if ( $forbidden_response ) {
		return $forbidden_response;
	}

	return rest_ensure_response( $result );
}

/**
 * REST callback to check API key configuration status.
 *
 * @param WP_REST_Request $request The REST request instance.
 *
 * @return WP_REST_Response
 */
function ceros_rest_get_api_key_status( WP_REST_Request $request ) {
	$is_configured = ceros_is_api_configured();
	
	return rest_ensure_response( array(
		'configured' => $is_configured,
		'message' => $is_configured ? 
			__( 'Ceros API key is configured.', 'ceros' ) : 
			__( 'Ceros API key is not set. Please add it in the Ceros settings first.', 'ceros' )
	) );
}

// -----------------------------------------------------------------------------
// End of file
// -----------------------------------------------------------------------------
