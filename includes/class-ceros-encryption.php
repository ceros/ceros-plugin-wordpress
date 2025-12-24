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
	 * Option name for the encrypted API key.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'ceros_api_key_encrypted';

	/**
	 * Option name for the legacy plain text key (for migration).
	 *
	 * @var string
	 */
	const LEGACY_OPTION_NAME = 'ceros_api_key';

	/**
	 * Get the API key (decrypted).
	 *
	 * Priority:
	 * 1. wp-config.php constant (CEROS_API_KEY)
	 * 2. Encrypted value in database
	 * 3. Legacy plain text value (triggers migration)
	 *
	 * @return string The API key or empty string.
	 */
	public static function get_api_key() {
		// Priority 1: Check for wp-config.php constant.
		if ( defined( 'CEROS_API_KEY' ) && CEROS_API_KEY ) {
			return CEROS_API_KEY;
		}

		// Priority 2: Check for encrypted key.
		$encrypted = get_option( self::OPTION_NAME, '' );
		if ( ! empty( $encrypted ) ) {
			$decrypted = self::decrypt( $encrypted );
			if ( ! empty( $decrypted ) ) {
				return $decrypted;
			}
			// Decryption failed - key may be corrupted or salts changed.
			// Clear the invalid encrypted key.
			delete_option( self::OPTION_NAME );
		}

		// Priority 3: Check for legacy plain text key and migrate.
		$legacy_key = get_option( self::LEGACY_OPTION_NAME, '' );
		if ( ! empty( $legacy_key ) && ! self::is_encrypted( $legacy_key ) ) {
			// Migrate to encrypted storage.
			self::save_api_key( $legacy_key );
			// Remove legacy option.
			delete_option( self::LEGACY_OPTION_NAME );
			return $legacy_key;
		}

		return '';
	}

	/**
	 * Save the API key (encrypted).
	 *
	 * @param string $key The plain text API key.
	 * @return bool True on success, false on failure.
	 */
	public static function save_api_key( $key ) {
		// Sanitize input.
		$key = sanitize_text_field( $key );

		if ( empty( $key ) ) {
			delete_option( self::OPTION_NAME );
			delete_option( self::LEGACY_OPTION_NAME );
			return true;
		}

		$encrypted = self::encrypt( $key );
		if ( false === $encrypted ) {
			// Fallback to plain text if encryption fails.
			return update_option( self::LEGACY_OPTION_NAME, $key );
		}

		// Save encrypted and clean up legacy.
		$result = update_option( self::OPTION_NAME, $encrypted );
		delete_option( self::LEGACY_OPTION_NAME );
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
	 */
	private static function get_encryption_key() {
		$salt = '';

		if ( defined( 'LOGGED_IN_KEY' ) ) {
			$salt .= LOGGED_IN_KEY;
		}
		if ( defined( 'LOGGED_IN_SALT' ) ) {
			$salt .= LOGGED_IN_SALT;
		}

		// Fallback if salts are not defined (shouldn't happen in production).
		if ( empty( $salt ) ) {
			$salt = 'ceros-fallback-' . get_site_url();
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

		$decoded = base64_decode( $value, true );
		if ( false === $decoded ) {
			return false;
		}

		// Check if it's long enough to be an encrypted value.
		$min_length = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES;
		return strlen( $decoded ) >= $min_length;
	}

	/**
	 * Check if API key is configured (either via constant or database).
	 *
	 * @return bool
	 */
	public static function is_configured() {
		return ! empty( self::get_api_key() );
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
