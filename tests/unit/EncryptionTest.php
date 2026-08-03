<?php
/**
 * Tests for Ceros_Encryption, which protects the API key at rest.
 *
 * The crypto is pure PHP + libsodium — get_encryption_key() derives from the
 * LOGGED_IN_KEY / LOGGED_IN_SALT constants rather than wp_salt() — so it is
 * testable with no WordPress. Only the public wrappers (get_api_key,
 * save_api_key) touch the options table, and those belong in an integration
 * suite.
 *
 * That leaves the interesting methods private, so they are reached by
 * reflection. Testing private surface is a trade-off taken deliberately here:
 * these are authenticated-encryption invariants, and the alternative is not
 * covering them at all.
 *
 * @package ceros
 */

use PHPUnit\Framework\TestCase;

/**
 * @covers Ceros_Encryption
 */
final class EncryptionTest extends TestCase {

	/**
	 * Call a private static method on Ceros_Encryption.
	 *
	 * @param string $method Method name.
	 * @param array  $args   Arguments.
	 * @return mixed
	 */
	private function call( $method, array $args = [] ) {
		$ref = new ReflectionMethod( 'Ceros_Encryption', $method );
		$ref->setAccessible( true );

		return $ref->invokeArgs( null, $args );
	}

	public function test_sodium_is_available_in_this_environment() {
		// The rest of this file is meaningless if it is not: every method under
		// test short-circuits when sodium is missing.
		$this->assertTrue( $this->call( 'is_sodium_available' ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
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
	 *
	 * @param string $plaintext Value to round-trip.
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

		// A fresh random nonce per call is what makes secretbox safe to reuse
		// with one key. A fixed nonce would make these identical.
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

		// Flip a bit in the ciphertext, past the nonce. Poly1305 authentication
		// must reject it rather than return anything at all.
		$offset             = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + 2;
		$decoded[ $offset ] = chr( ord( $decoded[ $offset ] ) ^ 0x01 );

		$this->assertSame( '', $this->call( 'decrypt', [ base64_encode( $decoded ) ] ) );
	}

	public function test_tampered_nonce_does_not_decrypt() {
		$decoded = base64_decode( $this->call( 'encrypt', [ 'secret-value' ] ), true );

		$decoded[0] = chr( ord( $decoded[0] ) ^ 0x01 );

		$this->assertSame( '', $this->call( 'decrypt', [ base64_encode( $decoded ) ] ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
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
	 *
	 * @param string $value Stored value that cannot be decrypted.
	 */
	public function test_decrypt_returns_empty_string_for_unusable_input( $value ) {
		$this->assertSame( '', $this->call( 'decrypt', [ $value ] ) );
	}

	public function test_is_encrypted_recognises_its_own_ciphertext() {
		$encrypted = $this->call( 'encrypt', [ 'sk-live-abcdef' ] );

		$this->assertTrue( $this->call( 'is_encrypted', [ $encrypted ] ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function not_encrypted_values() {
		return [
			// A raw key contains characters outside the base64 alphabet.
			'raw api key'    => [ 'sk-live-0123456789' ],
			'empty string'   => [ '' ],
			'short base64'   => [ base64_encode( 'abc' ) ],
			'one byte short' => [ base64_encode( str_repeat( 'x', SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES - 1 ) ) ],
		];
	}

	/**
	 * @dataProvider not_encrypted_values
	 *
	 * @param string $value Value that must not be treated as ciphertext.
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

		// A generichash of the salts, not the salts themselves.
		$this->assertNotSame( LOGGED_IN_KEY . LOGGED_IN_SALT, $key );
		$this->assertStringNotContainsString( LOGGED_IN_KEY, $key );
	}
}
