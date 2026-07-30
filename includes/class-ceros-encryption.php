<?php
/**
 * Ceros API Key Encryption
 *
 * Handles secure storage of the API key using Sodium encryption.
 * Provides automatic migration from plain text storage.
 *
 * @package ceros
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Ceros_Encryption
 *
 * Encrypts and decrypts the Ceros API key using PHP's Sodium extension.
 * Falls back to plain text storage if Sodium is unavailable.
 */
class Ceros_Encryption {

	/**
	 * Option name for the encrypted API key (production).
	 *
	 * @var string
	 */
	const OPTION_NAME = 'ceros_api_key_encrypted';

	/**
	 * Option name for the encrypted staging API key.
	 *
	 * @var string
	 */
	const STAGING_OPTION_NAME = 'ceros_api_key_encrypted_staging';

	/**
	 * Option name for the legacy plain text key (for migration).
	 *
	 * @var string
	 */
	const LEGACY_OPTION_NAME = 'ceros_api_key';

	/**
	 * Get the option name for the given environment.
	 *
	 * @param string $environment 'production' or 'staging'.
	 * @return string The option name.
	 */
	private static function option_name_for( $environment ) {
		return 'staging' === $environment ? self::STAGING_OPTION_NAME : self::OPTION_NAME;
	}

	/**
	 * Get the API key (decrypted) for the current or specified environment.
	 *
	 * Priority:
	 * 1. wp-config.php constant (CEROS_API_KEY) — applies to all environments
	 * 2. Encrypted value in database for the environment
	 * 3. Legacy plain text value (triggers migration to production key)
	 *
	 * @param string|null $environment Optional environment override ('production' or 'staging').
	 * @return string The API key or empty string.
	 */
	public static function get_api_key( $environment = null ) {
		// Priority 1: Check for wp-config.php constant.
		if ( defined( 'CEROS_API_KEY' ) && CEROS_API_KEY ) {
			return CEROS_API_KEY;
		}

		if ( null === $environment ) {
			$environment = get_option( 'ceros_api_environment', 'production' );
		}

		$option_name = self::option_name_for( $environment );

		// Priority 2: Check for encrypted key for this environment.
		$encrypted = get_option( $option_name, '' );
		if ( ! empty( $encrypted ) ) {
			$decrypted = self::decrypt( $encrypted );
			if ( ! empty( $decrypted ) ) {
				return $decrypted;
			}
			// Decryption failed - key may be corrupted or salts changed.
			delete_option( $option_name );
		}

		// Priority 3: Legacy migration (production only).
		// Plain text keys must never remain in the database.
		if ( 'production' === $environment ) {
			$legacy_key = get_option( self::LEGACY_OPTION_NAME, '' );
			if ( ! empty( $legacy_key ) && ! self::is_encrypted( $legacy_key ) ) {
				// Always delete the plain text key first.
				delete_option( self::LEGACY_OPTION_NAME );

				// Attempt to re-save it encrypted.
				self::save_api_key( $legacy_key, 'production' );

				return $legacy_key;
			}
		}

		return '';
	}

	/**
	 * Save the API key (encrypted) for the current or specified environment.
	 *
	 * @param string      $key         The plain text API key.
	 * @param string|null $environment Optional environment override ('production' or 'staging').
	 * @return bool True on success, false on failure.
	 */
	public static function save_api_key( $key, $environment = null ) {
		$key = sanitize_text_field( $key );

		if ( null === $environment ) {
			$environment = get_option( 'ceros_api_environment', 'production' );
		}

		$option_name = self::option_name_for( $environment );

		if ( empty( $key ) ) {
			delete_option( $option_name );
			if ( 'production' === $environment ) {
				delete_option( self::LEGACY_OPTION_NAME );
			}
			return true;
		}

		$encrypted = self::encrypt( $key );
		if ( false === $encrypted ) {
			// Encryption is required — never store in plain text.
			return false;
		}

		$result = update_option( $option_name, $encrypted );
		if ( 'production' === $environment ) {
			delete_option( self::LEGACY_OPTION_NAME );
		}
		return $result;
	}

	/**
	 * Encrypt a string using Sodium.
	 *
	 * @param string $plaintext The string to encrypt.
	 * @return string|false Base64 encoded encrypted string, or false on failure.
	 */
	private static function encrypt( $plaintext ) {
		if ( ! self::is_sodium_available() ) {
			return false;
		}

		try {
			$key   = self::get_encryption_key();
			$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

			$ciphertext = sodium_crypto_secretbox( $plaintext, $nonce, $key );

			// Prepend nonce to ciphertext for storage.
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- transport encoding for sodium ciphertext so it survives the options table, not obfuscation.
			$encoded = base64_encode( $nonce . $ciphertext );

			// Clear sensitive data from memory.
			sodium_memzero( $key );

			return $encoded;
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Decrypt a string using Sodium.
	 *
	 * @param string $encrypted Base64 encoded encrypted string.
	 * @return string The decrypted string, or empty string on failure.
	 */
	private static function decrypt( $encrypted ) {
		if ( ! self::is_sodium_available() ) {
			return '';
		}

		try {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- decoding our own sodium ciphertext, not obfuscation.
			$decoded = base64_decode( $encrypted, true );
			if ( false === $decoded ) {
				return '';
			}

			// Minimum length check (nonce + auth tag + at least 1 byte of ciphertext).
			$min_length = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES + 1;
			if ( strlen( $decoded ) < $min_length ) {
				return '';
			}

			$key        = self::get_encryption_key();
			$nonce      = substr( $decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$ciphertext = substr( $decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

			$plaintext = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );

			// Clear sensitive data from memory.
			sodium_memzero( $key );

			return false !== $plaintext ? $plaintext : '';
		} catch ( Exception $e ) {
			return '';
		}
	}

	/**
	 * Generate encryption key from WordPress salts.
	 *
	 * @return string 32-byte encryption key.
	 * @throws RuntimeException When LOGGED_IN_KEY and LOGGED_IN_SALT are both
	 *                          undefined, so no key can be derived. Failing
	 *                          closed is deliberate: the previous fallback
	 *                          derived a predictable key from the site URL.
	 */
	private static function get_encryption_key() {
		$salt = '';

		if ( defined( 'LOGGED_IN_KEY' ) ) {
			$salt .= LOGGED_IN_KEY;
		}
		if ( defined( 'LOGGED_IN_SALT' ) ) {
			$salt .= LOGGED_IN_SALT;
		}

		// Fail closed if the site's WordPress salts are missing. Previously we
		// fell back to a predictable derivation from the site URL, which would
		// let anyone who could read the encrypted option derive the key.
		if ( empty( $salt ) ) {
			throw new RuntimeException(
				'Ceros encryption requires LOGGED_IN_KEY and LOGGED_IN_SALT to be defined in wp-config.php.'
			);
		}

		// Derive a 32-byte key using Sodium's generic hash.
		return sodium_crypto_generichash( $salt, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}

	/**
	 * Check if Sodium extension is available.
	 *
	 * @return bool
	 */
	private static function is_sodium_available() {
		return function_exists( 'sodium_crypto_secretbox' ) &&
				function_exists( 'sodium_crypto_secretbox_open' ) &&
				function_exists( 'sodium_crypto_generichash' ) &&
				function_exists( 'sodium_memzero' );
	}

	/**
	 * Check if a value appears to be encrypted (base64 with correct length).
	 *
	 * @param string $value The value to check.
	 * @return bool
	 */
	private static function is_encrypted( $value ) {
		if ( ! self::is_sodium_available() ) {
			return false;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- length probe on our own stored ciphertext, not obfuscation.
		$decoded = base64_decode( $value, true );
		if ( false === $decoded ) {
			return false;
		}

		// Check if it's long enough to be an encrypted value.
		$min_length = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES;
		return strlen( $decoded ) >= $min_length;
	}

	/**
	 * Check if API key is configured for the current or specified environment.
	 *
	 * @param string|null $environment Optional environment override.
	 * @return bool
	 */
	public static function is_configured( $environment = null ) {
		return ! empty( self::get_api_key( $environment ) );
	}

	/**
	 * Check if the API key is defined via wp-config.php constant.
	 *
	 * @return bool
	 */
	public static function is_using_constant() {
		return defined( 'CEROS_API_KEY' ) && CEROS_API_KEY;
	}
}
