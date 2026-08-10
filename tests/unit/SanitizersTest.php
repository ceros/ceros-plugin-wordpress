<?php
/**
 * Tests for the pure input sanitizers. ceros_sanitize_resource_id() validates
 * resource IDs arriving on REST routes, so what it rejects is the point.
 *
 * The sanitizers wrapping wp_kses and esc_url_raw are deferred; see
 * tests/README.md.
 *
 * @package ceros
 */

use PHPUnit\Framework\TestCase;

final class SanitizersTest extends TestCase {

	public function valid_resource_ids() {
		return [
			'alphanumeric'     => [ 'abc123', 'abc123' ],
			'with hyphens'     => [ 'exp-123-abc', 'exp-123-abc' ],
			'with underscores' => [ 'exp_123_abc', 'exp_123_abc' ],
			'mixed case'       => [ 'AbC123', 'AbC123' ],
			'single char'      => [ 'a', 'a' ],
			'trims whitespace' => [ '  abc123  ', 'abc123' ],
			'trims newline'    => [ "abc123\n", 'abc123' ],
			// trim()'s character list includes NUL, so a trailing one is
			// stripped rather than refused. An inner NUL still fails the regex.
			'trims nul'        => [ 'abc123' . chr( 0 ), 'abc123' ],
		];
	}

	/**
	 * @dataProvider valid_resource_ids
	 */
	public function test_accepts_valid_resource_ids( $input, $expected ) {
		$this->assertSame( $expected, ceros_sanitize_resource_id( $input ) );
	}

	public function invalid_resource_ids() {
		return [
			'empty string'    => [ '' ],
			'only whitespace' => [ '   ' ],
			'null'            => [ null ],
			'zero'            => [ 0 ],
			'array'           => [ [ 'abc' ] ],
			'integer'         => [ 123 ],
			// Refused, not stripped.
			'path traversal'  => [ '../secrets' ],
			'slash'           => [ 'abc/123' ],
			'inner space'     => [ 'abc 123' ],
			'query string'    => [ 'abc?x=1' ],
			'angle brackets'  => [ '<script>' ],
			'percent encoded' => [ 'abc%2f123' ],
			// chr() rather than "\0", which PHP reads as an octal escape when
			// digits follow — "abc\0123" is abc + LF + "3", i.e. the case below.
			'nul byte'        => [ 'abc' . chr( 0 ) . '123' ],
			'newline inside'  => [ "abc\n123" ],
		];
	}

	/**
	 * @dataProvider invalid_resource_ids
	 */
	public function test_rejects_invalid_resource_ids( $input ) {
		$this->assertFalse( ceros_sanitize_resource_id( $input ) );
	}

	public function test_api_environment_accepts_known_values() {
		$this->assertSame( CEROS_ENV_PRODUCTION, ceros_sanitize_api_environment( CEROS_ENV_PRODUCTION ) );
		$this->assertSame( CEROS_ENV_STAGING, ceros_sanitize_api_environment( CEROS_ENV_STAGING ) );
	}

	public function unknown_environments() {
		return [
			'unknown string' => [ 'nonsense' ],
			'empty string'   => [ '' ],
			'null'           => [ null ],
			// in_array is strict.
			'boolean true'   => [ true ],
			'wrong case'     => [ 'Production' ],
		];
	}

	/**
	 * @dataProvider unknown_environments
	 */
	public function test_api_environment_falls_back_to_production( $value ) {
		$this->assertSame( CEROS_ENV_PRODUCTION, ceros_sanitize_api_environment( $value ) );
	}
}
