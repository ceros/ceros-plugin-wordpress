<?php
/**
 * Tests for the pure input sanitizers.
 *
 * ceros_sanitize_resource_id() is the plugin's input boundary — it validates
 * resource IDs arriving on REST routes — so what it rejects matters more than
 * what it accepts.
 *
 * The sanitizers that wrap WordPress (ceros_sanitize_embed_code via wp_kses,
 * ceros_sanitize_staging_api_url via esc_url_raw and add_settings_error) are
 * deferred to an integration suite; see tests/README.md.
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
			// Surrounding whitespace is trimmed before validation.
			'trims whitespace' => [ '  abc123  ', 'abc123' ],
			'trims newline'    => [ "abc123\n", 'abc123' ],
		];
	}

	/**
	 * @dataProvider valid_resource_ids
	 *
	 * @param string $input    Raw resource ID.
	 * @param string $expected Expected sanitized value.
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
			// Anything outside [a-zA-Z0-9-_] must be refused rather than stripped.
			'path traversal'  => [ '../secrets' ],
			'slash'           => [ 'abc/123' ],
			'inner space'     => [ 'abc 123' ],
			'query string'    => [ 'abc?x=1' ],
			'angle brackets'  => [ '<script>' ],
			'percent encoded' => [ 'abc%2f123' ],
			'nul byte'        => [ "abc\0123" ],
			'newline inside'  => [ "abc\n123" ],
		];
	}

	/**
	 * @dataProvider invalid_resource_ids
	 *
	 * @param mixed $input Raw resource ID.
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
			// in_array is strict, so a truthy non-string must not slip through.
			'boolean true'   => [ true ],
			'wrong case'     => [ 'Production' ],
		];
	}

	/**
	 * @dataProvider unknown_environments
	 *
	 * @param mixed $value Submitted environment value.
	 */
	public function test_api_environment_falls_back_to_production( $value ) {
		$this->assertSame( CEROS_ENV_PRODUCTION, ceros_sanitize_api_environment( $value ) );
	}
}
