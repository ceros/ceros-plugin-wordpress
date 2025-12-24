<?php
/**
 * Plugin Name:       Ceros
 * Description:       Ceros API integration plugin
 * Version: 		  0.27.0
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
function ceros_block_init() {
	/**
	 * Registers the block(s) metadata from the `blocks-manifest.php` file.
	 * Added to WordPress 6.7 to improve the performance of block type registration.
	 *
	 * @see https://make.wordpress.org/core/2024/10/17/new-block-type-registration-apis-to-improve-performance-in-wordpress-6-7/
	 */
	if ( function_exists( 'wp_register_block_metadata_collection' ) ) {
		wp_register_block_metadata_collection( __DIR__ . '/build', __DIR__ . '/build/blocks-manifest.php' );
	}

	/**
	 * Registers the block type(s) in the `blocks-manifest.php` file.
	 *
	 * @see https://developer.wordpress.org/reference/functions/register_block_type/
	 */
	$manifest_data = require __DIR__ . '/build/blocks-manifest.php';
	foreach ( array_keys( $manifest_data ) as $block_type ) {
		if ( $block_type === 'ceros' ) {
			register_block_type( __DIR__ . "/build/{$block_type}", [
				'render_callback' => 'ceros_render_block',
			] );
		} else {
			register_block_type( __DIR__ . "/build/{$block_type}" );
		}
	}
}

/**
 * Get the Ceros asset version for cache busting.
 *
 * @return string Version string based on file modification time.
 */
function ceros_get_asset_version() {
	$css_file = __DIR__ . '/build/ceros/index.css';
	return file_exists( $css_file ) ? (string) filemtime( $css_file ) : '0.1.0';
}

/**
 * Add cache busting to Ceros CSS files.
 * Uses style_loader_src filter as a fallback to ensure version is applied.
 */
function ceros_add_cache_busting_to_css() {
	$version = ceros_get_asset_version();

	add_filter( 'style_loader_src', function( $src, $handle ) use ( $version ) {
		// Only target Ceros CSS files
		if ( strpos( $src, '/ceros/' ) === false || strpos( $src, '.css' ) === false ) {
			return $src;
		}

		// Parse URL to check/replace version parameter
		$parsed = wp_parse_url( $src );
		$query_params = [];

		if ( ! empty( $parsed['query'] ) ) {
			parse_str( $parsed['query'], $query_params );
		}

		// Set or replace the version parameter (prevents duplicates)
		$query_params['ver'] = $version;

		// Rebuild the URL
		$base_url = $parsed['scheme'] . '://' . $parsed['host'];
		if ( ! empty( $parsed['port'] ) ) {
			$base_url .= ':' . $parsed['port'];
		}
		$base_url .= $parsed['path'];

		return $base_url . '?' . http_build_query( $query_params );
	}, 10, 2 );
}
add_action( 'init', 'ceros_add_cache_busting_to_css' );

/**
 * Modify block registration to include proper versioning for cache busting.
 */
function ceros_modify_block_registration() {
	$version = ceros_get_asset_version();

	add_filter( 'block_type_metadata', function( $metadata ) use ( $version ) {
		if ( isset( $metadata['name'] ) && $metadata['name'] === 'create-block/ceros' ) {
			$metadata['version'] = $version;
		}
		return $metadata;
	}, 10, 1 );
}
add_action( 'init', 'ceros_modify_block_registration' );

add_action( 'init', 'ceros_block_init' );

/**
 * -----------------------------------------------------------------------------
 * Ceros Plugin Enhancements
 * -----------------------------------------------------------------------------
 * 1. Options page for storing the Ceros API key.
 * 2. Loader for API client class – provides a single point for all external
 *    requests (admin + frontend).
 * -----------------------------------------------------------------------------
 */

// Define plugin file constant for use in helper functions.
if ( ! defined( 'CEROS_PLUGIN_FILE' ) ) {
	define( 'CEROS_PLUGIN_FILE', __FILE__ );
}

// Load helper classes – keep all includes contained inside the plugin.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-ceros-encryption.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-ceros-api.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/functions.php';

/**
 * Register plugin settings.
 *
 * Stores the API key encrypted in the WordPress options table using the
 * Ceros_Encryption class. Supports wp-config.php constant as alternative.
 */
function ceros_register_settings() {
	// Register API key setting with custom sanitize callback that encrypts.
	register_setting(
		'ceros_settings_group',
		'ceros_api_key',
		[
			'type'              => 'string',
			'sanitize_callback' => 'ceros_sanitize_and_encrypt_api_key',
			'default'           => '',
		]
	);

	register_setting(
		'ceros_settings_group',
		'ceros_api_environment',
		[
			'type'              => 'string',
			'sanitize_callback' => 'ceros_sanitize_api_environment',
			'default'           => 'production',
		]
	);
}
add_action( 'admin_init', 'ceros_register_settings' );

/**
 * Sanitize and encrypt the API key before saving.
 *
 * The actual storage is handled by the Ceros_Encryption class.
 * We return an empty string since the encrypted value is stored
 * in a separate option (ceros_api_key_encrypted).
 *
 * @param string $value The submitted API key.
 * @return string Empty string (actual storage handled by encryption class).
 */
function ceros_sanitize_and_encrypt_api_key( $value ) {
	// If the value is the masked placeholder, don't update (user didn't change it).
	if ( preg_match( '/^•+$/', $value ) ) {
		// Return empty to prevent overwriting with masked value.
		// The existing encrypted key remains untouched.
		return '';
	}

	$value = sanitize_text_field( $value );

	// Save via encryption class (handles empty values too).
	Ceros_Encryption::save_api_key( $value );

	// Return empty - we use separate encrypted storage.
	return '';
}

/**
 * Sanitize the API environment setting.
 *
 * @param string $value The value to sanitize.
 * @return string The sanitized value.
 */
function ceros_sanitize_api_environment( $value ) {
	$valid_environments = [ 'production', 'staging' ];
	return in_array( $value, $valid_environments, true ) ? $value : 'production';
}

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
 * Add Settings link to plugin action links on the plugins page.
 *
 * @param array $links Existing plugin action links.
 * @return array Modified plugin action links.
 */
function ceros_add_plugin_action_links( $links ) {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'options-general.php?page=ceros_settings' ) ),
		__( 'Settings', 'ceros' )
	);
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( CEROS_PLUGIN_FILE ), 'ceros_add_plugin_action_links' );

/**
 * Render the markup for the options page.
 */
function ceros_render_options_page() {
	// Capability check.
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Check if API key is configured and how.
	$is_configured    = Ceros_Encryption::is_configured();
	$using_constant   = Ceros_Encryption::is_using_constant();

	// Display value: masked if configured, empty if not.
	$display_value = $is_configured ? '••••••••••••••••' : '';
	$placeholder   = $is_configured
		? __( 'Key saved (enter new key to replace)', 'ceros' )
		: __( 'Enter your API key', 'ceros' );
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
						<?php if ( $using_constant ) : ?>
							<input type="text" value="<?php esc_attr_e( 'Defined in wp-config.php', 'ceros' ); ?>" class="regular-text" disabled />
							<p class="description">
								<?php esc_html_e( 'Your API key is defined using the CEROS_API_KEY constant in wp-config.php.', 'ceros' ); ?>
							</p>
						<?php else : ?>
							<input
								name="ceros_api_key"
								type="password"
								id="ceros_api_key"
								value="<?php echo esc_attr( $display_value ); ?>"
								placeholder="<?php echo esc_attr( $placeholder ); ?>"
								class="regular-text"
								autocomplete="new-password"
							/>
							<p class="description">
								<?php esc_html_e( 'Enter your Ceros API key. The key is stored securely using encryption.', 'ceros' ); ?>
							</p>
							<?php if ( $is_configured ) : ?>
								<p class="description">
									<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
									<?php esc_html_e( 'API key is configured and encrypted.', 'ceros' ); ?>
								</p>
							<?php endif; ?>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="ceros_api_environment"><?php esc_html_e( 'API Environment', 'ceros' ); ?></label>
					</th>
					<td>
						<?php $current_environment = get_option( 'ceros_api_environment', 'production' ); ?>
						<select name="ceros_api_environment" id="ceros_api_environment">
							<option value="production" <?php selected( $current_environment, 'production' ); ?>><?php esc_html_e( 'Production', 'ceros' ); ?></option>
							<option value="staging" <?php selected( $current_environment, 'staging' ); ?>><?php esc_html_e( 'Staging', 'ceros' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Select the Ceros API environment to use.', 'ceros' ); ?></p>
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
 * Uses the Ceros_Encryption class which handles:
 * - wp-config.php constant (CEROS_API_KEY)
 * - Encrypted database storage
 * - Legacy plain text migration
 *
 * @return string The decrypted API key or empty string.
 */
function ceros_get_api_key() {
	return Ceros_Encryption::get_api_key();
}

/**
 * Permission callback for Ceros REST routes.
 * Requires a user who can edit posts (i.e. editors, admins).
 *
 * @return bool
 */
function ceros_rest_permission_check() {
	return current_user_can( 'edit_posts' );
}

/**
 * Register custom REST API routes for the Ceros plugin.
 */
// Load render callback for block
require_once plugin_dir_path( __FILE__ ) . 'build/ceros/render.php';

function ceros_register_rest_routes() {
	register_rest_route(
		'ceros/v1',
		'/current-account',
		[
			'methods'             => 'GET',
			'callback'            => 'ceros_rest_get_current_account',
			'permission_callback' => 'ceros_rest_permission_check',
		]
	);

	register_rest_route(
		'ceros/v1',
		'/folder-tree/(?P<account_resource_id>[a-zA-Z0-9\-_]+)',
		[
			'methods'             => 'GET',
			'callback'            => 'ceros_rest_get_folder_tree',
			'permission_callback' => 'ceros_rest_permission_check',
			'args'                => [
				'account_resource_id' => [
					'required' => true,
					'type'     => 'string',
				],
			],
		]
	);

	register_rest_route(
		'ceros/v1',
		'/folder/(?P<resource_id>[a-zA-Z0-9\-_]+)/experiences',
		[
			'methods'             => 'GET',
			'callback'            => 'ceros_rest_get_experiences',
			'permission_callback' => 'ceros_rest_permission_check',
			'args'                => [
				'resource_id' => [
					'required' => true,
					'type'     => 'string',
				],
			],
		]
	);

	register_rest_route(
		'ceros/v1',
		'/experiences/(?P<resource_id>[a-zA-Z0-9\-_]+)/embed-codes',
		[
			'methods'             => 'GET',
			'callback'            => 'ceros_rest_get_embed_codes',
			'permission_callback' => 'ceros_rest_permission_check',
			'args'                => [
				'resource_id' => [
					'required' => true,
					'type'     => 'string',
				],
			],
		]
	);

	register_rest_route(
		'ceros/v1',
		'/api-key-status',
		[
			'methods'             => 'GET',
			'callback'            => 'ceros_rest_get_api_key_status',
			'permission_callback' => 'ceros_rest_permission_check',
		]
	);
}
add_action( 'rest_api_init', 'ceros_register_rest_routes' );

/**
 * Handle API response and return appropriate REST response.
 *
 * @param array|WP_Error $result The API result.
 * @return WP_REST_Response The REST response.
 */
function ceros_handle_api_response( $result ) {
	if ( is_wp_error( $result ) ) {
		$error_message = $result->get_error_message();
		$friendly_message = ceros_get_friendly_error_message( $error_message );
		
		return new WP_REST_Response(
			[ 'error' => $friendly_message ],
			400
		);
	}

	// Check for 403 Forbidden response which typically means invalid API key
	if ( isset( $result['code'] ) && $result['code'] === 403 &&
		 isset( $result['body']['message'] ) && $result['body']['message'] === 'Forbidden resource' ) {
		return new WP_REST_Response(
			[
				'code'  => 403,
				'body'  => [ 'message' => 'Forbidden resource' ],
				'error' => 'The API call was forbidden, which usually means your API key is invalid. Please confirm that your API key is correct.',
			],
			403
		);
	}

	// Check for other HTTP error responses (4xx and 5xx).
	if ( isset( $result['code'] ) && $result['code'] >= 400 ) {
		$error_message = isset( $result['body']['message'] ) ? $result['body']['message'] : 'An error occurred';

		return new WP_REST_Response(
			[
				'code'  => $result['code'],
				'body'  => $result['body'],
				'error' => sprintf( 'Ceros API error (%d): %s', $result['code'], $error_message ),
			],
			$result['code']
		);
	}

	return rest_ensure_response( $result );
}

/**
 * REST callback: Get current account.
 *
 * @return WP_REST_Response
 */
function ceros_rest_get_current_account() {
	return ceros_handle_api_response( Ceros_API::instance()->get_current_account() );
}

/**
 * REST callback: Get folder tree.
 *
 * @param WP_REST_Request $request The REST request instance.
 * @return WP_REST_Response
 */
function ceros_rest_get_folder_tree( WP_REST_Request $request ) {
	return ceros_handle_api_response(
		Ceros_API::instance()->get_folder_tree( $request->get_param( 'account_resource_id' ) )
	);
}

/**
 * REST callback: Get experiences for a folder.
 *
 * @param WP_REST_Request $request The REST request instance.
 * @return WP_REST_Response
 */
function ceros_rest_get_experiences( WP_REST_Request $request ) {
	return ceros_handle_api_response(
		Ceros_API::instance()->get_experiences( $request->get_param( 'resource_id' ) )
	);
}

/**
 * REST callback: Get embed codes for an experience.
 *
 * Sanitizes embed codes before returning to prevent XSS vulnerabilities.
 *
 * @param WP_REST_Request $request The REST request instance.
 * @return WP_REST_Response
 */
function ceros_rest_get_embed_codes( WP_REST_Request $request ) {
	$result = Ceros_API::instance()->get_embed_codes( $request->get_param( 'resource_id' ) );

	// Sanitize embed codes in the response body before returning.
	if ( ! is_wp_error( $result ) && isset( $result['body'] ) && is_array( $result['body'] ) ) {
		$result['body'] = ceros_sanitize_embed_codes_array( $result['body'] );
	}

	return ceros_handle_api_response( $result );
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
	
	return rest_ensure_response( [
		'configured' => $is_configured,
		'message'    => $is_configured
			? __( 'Ceros API key is configured.', 'ceros' )
			: __( 'Ceros API key is not set. Please add it in the Ceros settings first.', 'ceros' ),
	] );
}

// -----------------------------------------------------------------------------
// End of file
// -----------------------------------------------------------------------------
