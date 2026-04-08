<?php
/**
 * Settings page, registration, and sanitization.
 *
 * Handles the Ceros settings page under Settings > Ceros,
 * API key validation, environment configuration, and the
 * plugin action links on the Plugins page.
 *
 * @package ceros
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
			'default'           => CEROS_ENV_PRODUCTION,
		]
	);

	register_setting(
		'ceros_settings_group',
		'ceros_staging_api_url',
		[
			'type'              => 'string',
			'sanitize_callback' => 'ceros_sanitize_staging_api_url',
			'default'           => '',
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
	$value = sanitize_text_field( $value );

	// Empty field means the user didn't enter a new key — preserve the existing one.
	// To clear the key, use the "Remove API Key" button.
	if ( empty( $value ) ) {
		return '';
	}

	// Validate the key against the Ceros API before saving.
	// Use submitted form values since environment/URL may have changed in this same request.
	$environment = isset( $_POST['ceros_api_environment'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by options.php
		? sanitize_text_field( wp_unslash( $_POST['ceros_api_environment'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		: get_option( 'ceros_api_environment', CEROS_ENV_PRODUCTION );

	$staging_url = isset( $_POST['ceros_staging_api_url'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by options.php
		? esc_url_raw( wp_unslash( $_POST['ceros_staging_api_url'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		: null;

	$base_url = ceros_get_api_base_url( $environment, $staging_url );

	if ( empty( $base_url ) ) {
		add_settings_error(
			'ceros_api_key',
			'ceros_staging_url_required',
			__( 'A staging API URL is required when using the staging environment. The key was not saved.', 'ceros' ),
			'error'
		);
		return '';
	}

	$response = wp_remote_get(
		$base_url . CEROS_ENDPOINT_CURRENT_ACCOUNT,
		[
			'headers' => ceros_get_api_headers( $value ),
			'timeout' => CEROS_API_REQUEST_TIMEOUT,
		]
	);

	if ( is_wp_error( $response ) ) {
		add_settings_error(
			'ceros_api_key',
			'ceros_api_key_connection_failed',
			ceros_format_error(
				$response->get_error_message(),
				__( 'Could not connect to the Ceros API. The key was not saved.', 'ceros' )
			),
			'error'
		);
		return '';
	}

	$code = wp_remote_retrieve_response_code( $response );

	if ( $code < 200 || $code >= 300 ) {
		$body          = wp_remote_retrieve_body( $response );
		$technical_msg = sprintf( 'HTTP %d — %s', $code, $body );
		add_settings_error(
			'ceros_api_key',
			'ceros_api_key_invalid',
			ceros_format_error(
				$technical_msg,
				__( 'The API key could not be verified. Please check that the key is correct and try again.', 'ceros' )
			),
			'error'
		);
		return '';
	}

	// Key is valid — save it encrypted for the selected environment.
	$saved = Ceros_Encryption::save_api_key( $value, $environment );

	if ( ! $saved ) {
		add_settings_error(
			'ceros_api_key',
			'ceros_api_key_encryption_failed',
			__( 'The API key could not be encrypted. Please ensure the PHP Sodium extension is enabled.', 'ceros' ),
			'error'
		);
		return '';
	}

	add_settings_error(
		'ceros_api_key',
		'ceros_api_key_valid',
		__( 'API key verified and saved successfully.', 'ceros' ),
		'success'
	);

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
	$valid_environments = [ CEROS_ENV_PRODUCTION, CEROS_ENV_STAGING ];
	return in_array( $value, $valid_environments, true ) ? $value : CEROS_ENV_PRODUCTION;
}

/**
 * Sanitize the staging API URL.
 *
 * Validates the URL format and enforces HTTPS.
 *
 * @param string $value The submitted URL.
 * @return string The sanitized URL, or empty string if invalid.
 */
function ceros_sanitize_staging_api_url( $value ) {
	$value = trim( sanitize_text_field( $value ) );

	if ( empty( $value ) ) {
		return '';
	}

	// Remove trailing slashes for consistency.
	$value = untrailingslashit( $value );

	// Validate URL format.
	if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
		add_settings_error(
			'ceros_staging_api_url',
			'ceros_staging_url_invalid',
			__( 'The staging API URL is not a valid URL.', 'ceros' ),
			'error'
		);
		return get_option( 'ceros_staging_api_url', '' );
	}

	// Enforce HTTPS.
	$scheme = wp_parse_url( $value, PHP_URL_SCHEME );
	if ( 'https' !== $scheme ) {
		add_settings_error(
			'ceros_staging_api_url',
			'ceros_staging_url_not_https',
			__( 'The staging API URL must use HTTPS.', 'ceros' ),
			'error'
		);
		return get_option( 'ceros_staging_api_url', '' );
	}

	// Basic SSRF guard: reject loopback, link-local, and RFC1918 private ranges
	// so an admin can't (accidentally or otherwise) point the plugin at an
	// internal service. Hostnames are resolved via gethostbyname to catch
	// "my-internal-host" pointing at 10.x etc.
	if ( ! ceros_is_public_host( wp_parse_url( $value, PHP_URL_HOST ) ) ) {
		add_settings_error(
			'ceros_staging_api_url',
			'ceros_staging_url_private',
			__( 'The staging API URL resolves to a private or loopback address and is not allowed.', 'ceros' ),
			'error'
		);
		return get_option( 'ceros_staging_api_url', '' );
	}

	return esc_url_raw( $value );
}

/**
 * Check whether a host resolves to a publicly-routable IP address.
 *
 * Rejects loopback, link-local, and RFC1918 private ranges. Used as a basic
 * SSRF guard on user-supplied URLs.
 *
 * @param string|null $host Hostname or IP.
 * @return bool True if the host appears to be publicly routable.
 */
function ceros_is_public_host( $host ) {
	if ( empty( $host ) ) {
		return false;
	}

	// Resolve hostnames to an IP. If resolution fails, gethostbyname returns
	// the original string unchanged — treat that as untrusted.
	$ip = filter_var( $host, FILTER_VALIDATE_IP ) ? $host : gethostbyname( $host );
	if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
		return false;
	}

	// FILTER_FLAG_NO_PRIV_RANGE rejects RFC1918 / ULA; NO_RES_RANGE rejects
	// loopback, link-local, multicast, unspecified, etc.
	return (bool) filter_var(
		$ip,
		FILTER_VALIDATE_IP,
		FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
	);
}

/**
 * Get the Ceros API base URL for the current environment.
 *
 * Centralises the logic so it can be used by both the API class and
 * the settings validation without duplicating URL resolution.
 *
 * @param string|null $environment Optional override (e.g. from $_POST during save).
 * @param string|null $staging_url Optional override for the staging URL.
 * @return string The API base URL.
 */
function ceros_get_api_base_url( $environment = null, $staging_url = null ) {
	if ( null === $environment ) {
		$environment = get_option( 'ceros_api_environment', CEROS_ENV_PRODUCTION );
	}

	if ( CEROS_ENV_STAGING === $environment ) {
		if ( null === $staging_url ) {
			$staging_url = get_option( 'ceros_staging_api_url', '' );
		}

		return ! empty( $staging_url ) ? untrailingslashit( $staging_url ) : '';
	}

	return CEROS_PRODUCTION_API_URL;
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

	// Never put the key (or a mask) in the value — an empty field means "keep existing key".
	$placeholder = $is_configured
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
								value=""
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
									<?php esc_html_e( 'API key is verified, configured, and encrypted.', 'ceros' ); ?>
									<button type="button" class="button-link" id="ceros-remove-api-key" style="color: #b32d2e; margin-left: 8px;">
										<?php esc_html_e( 'Remove API Key', 'ceros' ); ?>
									</button>
								</p>
							<?php endif; ?>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<?php
			$current_environment = get_option( 'ceros_api_environment', CEROS_ENV_PRODUCTION );
			$staging_url         = get_option( 'ceros_staging_api_url', '' );
			// Auto-expand if the user is already on a non-default environment so they can see the active config.
			$details_open = ( CEROS_ENV_STAGING === $current_environment );
			?>
			<details class="ceros-advanced-settings" style="margin-top: 1em;" <?php echo $details_open ? 'open' : ''; ?>>
				<summary style="cursor: pointer; font-weight: 600; padding: 4px 0;">
					<?php esc_html_e( 'Advanced: API Environment', 'ceros' ); ?>
				</summary>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="ceros_api_environment"><?php esc_html_e( 'API Environment', 'ceros' ); ?></label>
						</th>
						<td>
							<select name="ceros_api_environment" id="ceros_api_environment">
								<option value="<?php echo esc_attr( CEROS_ENV_PRODUCTION ); ?>" <?php selected( $current_environment, CEROS_ENV_PRODUCTION ); ?>><?php esc_html_e( 'Production', 'ceros' ); ?></option>
								<option value="<?php echo esc_attr( CEROS_ENV_STAGING ); ?>" <?php selected( $current_environment, CEROS_ENV_STAGING ); ?>><?php esc_html_e( 'Staging', 'ceros' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Select the Ceros API environment to use.', 'ceros' ); ?></p>
						</td>
					</tr>
					<tr id="ceros-staging-url-row" style="<?php echo CEROS_ENV_STAGING === $current_environment ? '' : 'display: none;'; ?>">
						<th scope="row">
							<label for="ceros_staging_api_url"><?php esc_html_e( 'Staging API URL', 'ceros' ); ?></label>
						</th>
						<td>
							<input
								name="ceros_staging_api_url"
								type="url"
								id="ceros_staging_api_url"
								value="<?php echo esc_attr( $staging_url ); ?>"
								placeholder="https://api-staging.example.com"
								class="regular-text"
							/>
							<p class="description"><?php esc_html_e( 'Enter the staging API base URL (must use HTTPS).', 'ceros' ); ?></p>
						</td>
					</tr>
				</table>
			</details>

			<?php if ( $is_configured ) : ?>
				<p>
					<?php
					// When the key is defined via wp-config.php constant there is no input field,
					// so the button can be enabled immediately. Otherwise require the user to type
					// a new key first — Test Connection only tests what's in the input field.
					$test_btn_disabled = $using_constant ? '' : 'disabled';
					?>
					<button type="button" class="button button-secondary" id="ceros-test-connection" <?php echo esc_attr( $test_btn_disabled ); ?>>
						<?php esc_html_e( 'Test Connection', 'ceros' ); ?>
					</button>
					<span id="ceros-test-result" style="margin-left: 8px;">
						<?php if ( ! $using_constant ) : ?>
							<em><?php esc_html_e( 'Enter an API key above to test the connection.', 'ceros' ); ?></em>
						<?php endif; ?>
					</span>
				</p>
			<?php endif; ?>

			<?php submit_button(); ?>
		</form>
	</div>

	<script>
	(function() {
		var envSelect = document.getElementById( 'ceros_api_environment' );
		var stagingRow = document.getElementById( 'ceros-staging-url-row' );

		var savedEnvironment = envSelect ? envSelect.value : '';

		if ( envSelect && stagingRow ) {
			envSelect.addEventListener( 'change', function() {
				stagingRow.style.display = this.value === 'staging' ? '' : 'none';

				// Disable Test Connection when environment changes (unsaved).
				var testBtn  = document.getElementById( 'ceros-test-connection' );
				var resultEl = document.getElementById( 'ceros-test-result' );
				if ( testBtn ) {
					var changed = this.value !== savedEnvironment;
					testBtn.disabled = changed;
					if ( changed && resultEl ) {
						resultEl.innerHTML = '<em><?php echo esc_js( __( 'Save changes first to test the new environment.', 'ceros' ) ); ?></em>';
					} else if ( resultEl ) {
						resultEl.innerHTML = '';
					}
				}
			});
		}


		// Enable Test Connection only while the user has typed something in the API key field.
		// Test Connection tests the value in the input; with no input there is nothing to test.
		var apiKeyInput = document.getElementById( 'ceros_api_key' );
		if ( apiKeyInput ) {
			apiKeyInput.addEventListener( 'input', function() {
				var testBtn  = document.getElementById( 'ceros-test-connection' );
				var resultEl = document.getElementById( 'ceros-test-result' );
				if ( ! testBtn ) {
					return;
				}
				var hasKey = this.value.length > 0;
				testBtn.disabled = ! hasKey;
				if ( resultEl ) {
					resultEl.innerHTML = hasKey
						? ''
						: '<em><?php echo esc_js( __( 'Enter an API key above to test the connection.', 'ceros' ) ); ?></em>';
				}
			});
		}

		var removeBtn = document.getElementById( 'ceros-remove-api-key' );
		if ( removeBtn ) {
			removeBtn.addEventListener( 'click', function() {
				if ( ! confirm( '<?php echo esc_js( __( 'Are you sure you want to remove the API key?', 'ceros' ) ); ?>' ) ) {
					return;
				}

				var formData = new FormData();
				formData.append( 'action', 'ceros_remove_api_key' );
				formData.append( '_ajax_nonce', '<?php echo esc_js( wp_create_nonce( 'ceros_remove_api_key' ) ); ?>' );

				fetch( ajaxurl, {
					method: 'POST',
					credentials: 'same-origin',
					body: formData,
				})
				.then( function( response ) { return response.json(); } )
				.then( function( data ) {
					if ( data.success ) {
						window.location.reload();
					}
				});
			});
		}

		var testBtn = document.getElementById( 'ceros-test-connection' );
		if ( testBtn ) {
			testBtn.addEventListener( 'click', function() {
				var resultEl    = document.getElementById( 'ceros-test-result' );
				var environment = envSelect ? envSelect.value : 'production';
				var urlInput    = document.getElementById( 'ceros_staging_api_url' );
				var stagingUrl  = urlInput ? urlInput.value.trim() : '';

				if ( environment === 'staging' && ! stagingUrl ) {
					resultEl.innerHTML = '<span style="color: #d63638;"><?php echo esc_js( __( 'Please enter a staging URL first.', 'ceros' ) ); ?></span>';
					return;
				}

				testBtn.disabled = true;
				resultEl.innerHTML = '<span class="spinner is-active" style="float: none; margin: 0;"></span>';

				var payload = { environment: environment };
				if ( environment === 'staging' ) {
					payload.staging_url = stagingUrl;
				}

				// If the user has typed a new API key in the input, test that value
				// instead of the currently-stored key.
				var apiKeyField = document.getElementById( 'ceros_api_key' );
				if ( apiKeyField && apiKeyField.value.length > 0 ) {
					payload.api_key = apiKeyField.value;
				}

				fetch( '<?php echo esc_js( rest_url( CEROS_REST_NAMESPACE . '/test-connection' ) ); ?>', {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>',
					},
					body: JSON.stringify( payload ),
				})
				.then( function( response ) {
					return response.json().then( function( data ) {
						return { ok: response.ok, data: data };
					});
				})
				.then( function( result ) {
					var message = ( result.data && result.data.message ) ? result.data.message : '';
					if ( result.ok ) {
						resultEl.innerHTML = '<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> ' + message;
					} else {
						resultEl.innerHTML = '<span class="dashicons dashicons-warning" style="color: #d63638;"></span> ' + message;
					}
				})
				.catch( function() {
					resultEl.innerHTML = '<span class="dashicons dashicons-warning" style="color: #d63638;"></span> <?php echo esc_js( __( 'Request failed. Please try again.', 'ceros' ) ); ?>';
				})
				.finally( function() {
					testBtn.disabled = false;
				});
			});
		}
	})();
	</script>
	<?php
}
