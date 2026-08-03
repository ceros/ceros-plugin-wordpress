<?php
/**
 * Tests for Ceros_Encryption, which protects the API key at rest.
 *
 * The crypto is pure PHP + libsodium: get_encryption_key() derives from the
 * LOGGED_IN_* constants, not wp_salt(). The public wrappers read the options
 * table, so the methods worth testing are private and reached by reflection.
 *
 * @package ceros
 */

use PHPUnit\Framework\TestCase;

final class EncryptionTest extends TestCase {

	private function call( $method, array $args = [] ) {
		$ref = new ReflectionMethod( 'Ceros_Encryption', $method );
		$ref->setAccessible( true );

		return $ref->invokeArgs( null, $args );
	}

	public function test_sodium_is_available_in_this_environment() {
		// Every method under test short-circuits when sodium is missing.
		$this->assertTrue( $this->call( 'is_sodium_available' ) );
	}

	public function plaintexts() {
		return [
			'typical api key' => [ 'sk-live-0123456789abcdefghijklmnop' ],
			'single char'     => [ 'x' ],
			'unicode'         => [ 'clé-🔑-ünïcodé' ],
			'long'            => [ str_repeat( 'a', 4096 ) ],
			'whitespace'      => [ "  padded  \n" ],
		];
	}

	/**
	 * @dataProvider plaintexts
	 */
	public function test_round_trips_plaintext( $plaintext ) {
		$encrypted = $this->call( 'encrypt', [ $plaintext ] );

		$this->assertIsString( $encrypted );
		$this->assertNotSame( $plaintext, $encrypted );
		$this->assertSame( $plaintext, $this->call( 'decrypt', [ $encrypted ] ) );
	}

	public function test_same_plaintext_encrypts_differently_each_time() {
		$a = $this->call( 'encrypt', [ 'same-value' ] );
		$b = $this->call( 'encrypt', [ 'same-value' ] );

		// A fixed nonce would make these identical.
		$this->assertNotSame( $a, $b );
		$this->assertSame( 'same-value', $this->call( 'decrypt', [ $a ] ) );
		$this->assertSame( 'same-value', $this->call( 'decrypt', [ $b ] ) );
	}

	public function test_encrypt_output_carries_a_nonce_of_the_expected_length() {
		$decoded = base64_decode( $this->call( 'encrypt', [ 'value' ] ), true );

		$this->assertIsString( $decoded );
		$this->assertGreaterThan(
			SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES,
			strlen( $decoded )
		);
	}

	public function test_tampered_ciphertext_does_not_decrypt() {
		$decoded = base64_decode( $this->call( 'encrypt', [ 'secret-value' ] ), true );

		$offset             = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + 2;
		$decoded[ $offset ] = chr( ord( $decoded[ $offset ] ) ^ 0x01 );

		$this->assertSame( '', $this->call( 'decrypt', [ base64_encode( $decoded ) ] ) );
	}

	public function test_tampered_nonce_does_not_decrypt() {
		$decoded = base64_decode( $this->call( 'encrypt', [ 'secret-value' ] ), true );

		$decoded[0] = chr( ord( $decoded[0] ) ^ 0x01 );

		$this->assertSame( '', $this->call( 'decrypt', [ base64_encode( $decoded ) ] ) );
	}

	public function undecryptable_values() {
		return [
			'empty string'      => [ '' ],
			'not base64'        => [ 'sk-live-not-base64!!' ],
			'base64 too short'  => [ base64_encode( 'short' ) ],
			// Exactly one byte below nonce + MAC + 1.
			'one byte short'    => [ base64_encode( str_repeat( 'x', SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES ) ) ],
			'valid b64 garbage' => [ base64_encode( str_repeat( 'x', 128 ) ) ],
		];
	}

	/**
	 * @dataProvider undecryptable_values
	 */
	public function test_decrypt_returns_empty_string_for_unusable_input( $value ) {
		$this->assertSame( '', $this->call( 'decrypt', [ $value ] ) );
	}

	public function test_is_encrypted_recognises_its_own_ciphertext() {
		$encrypted = $this->call( 'encrypt', [ 'sk-live-abcdef' ] );

		$this->assertTrue( $this->call( 'is_encrypted', [ $encrypted ] ) );
	}

	public function not_encrypted_values() {
		return [
			// Outside the base64 alphabet.
			'raw api key'    => [ 'sk-live-0123456789' ],
			'empty string'   => [ '' ],
			'short base64'   => [ base64_encode( 'abc' ) ],
			'one byte short' => [ base64_encode( str_repeat( 'x', SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES - 1 ) ) ],
		];
	}

	/**
	 * @dataProvider not_encrypted_values
	 */
	public function test_is_encrypted_rejects_values_it_did_not_produce( $value ) {
		$this->assertFalse( $this->call( 'is_encrypted', [ $value ] ) );
	}

	public function test_key_is_derived_deterministically_at_the_expected_length() {
		$first  = $this->call( 'get_encryption_key' );
		$second = $this->call( 'get_encryption_key' );

		$this->assertSame( SODIUM_CRYPTO_SECRETBOX_KEYBYTES, strlen( $first ) );
		$this->assertSame( bin2hex( $first ), bin2hex( $second ) );
	}

	public function test_key_is_not_the_raw_salt() {
		$key = $this->call( 'get_encryption_key' );

		$this->assertNotSame( LOGGED_IN_KEY . LOGGED_IN_SALT, $key );
		$this->assertStringNotContainsString( LOGGED_IN_KEY, $key );
	}
}
