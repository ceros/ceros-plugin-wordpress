<?php
/**
 * Tests for ceros_get_friendly_error_message(), which turns a cURL/WP_Error
 * string into something an editor can act on.
 *
 * The matching is substring-based over an ordered map, so the two things that
 * can go wrong are a real transport error falling through to the generic
 * fallback, and the order changing so a broader pattern shadows a narrower one.
 *
 * @package ceros
 */

use PHPUnit\Framework\TestCase;

final class FriendlyErrorMessageTest extends TestCase {

	const CONNECTION = 'Unable to connect to the Ceros API. Please check your internet connection and try again. If the problem persists, the Ceros API server may be temporarily unavailable.';
	const FAILED     = 'Failed to connect to the Ceros API. Please check your internet connection and try again.';
	const TIMEOUT    = 'The connection to the Ceros API timed out. Please try again in a moment.';
	const GENERIC    = 'Unable to connect to the Ceros API. Please check your internet connection and try again.';
	const FALLBACK   = 'An error occurred while connecting to the Ceros API. Please try again later or contact support if the problem persists.';

	public function transport_errors() {
		return [
			// The messages WordPress surfaces from the matching cURL errors.
			'curl 6 host lookup' => [ 'cURL error 6: Could not resolve host: rest.ceros.com', self::CONNECTION ],
			'curl 7 connect'     => [ 'cURL error 7: Failed to connect to rest.ceros.com port 443', self::FAILED ],
			// 'timeout' matches on the word alone, without a cURL prefix.
			'bare timeout'       => [ 'Connection timeout', self::TIMEOUT ],
			// Falls through to the generic cURL branch: no other pattern hits.
			'other curl error'   => [ 'cURL error 35: SSL connect error', self::GENERIC ],
		];
	}

	/**
	 * @dataProvider transport_errors
	 */
	public function test_maps_transport_errors_to_advice( $error, $expected ) {
		$this->assertSame( $expected, ceros_get_friendly_error_message( $error ) );
	}

	public function unmatched_errors() {
		return [
			'http status'  => [ '401 Unauthorized' ],
			'api message'  => [ 'Invalid API key' ],
			'empty string' => [ '' ],
		];
	}

	/**
	 * @dataProvider unmatched_errors
	 */
	public function test_falls_back_when_nothing_matches( $error ) {
		$this->assertSame( self::FALLBACK, ceros_get_friendly_error_message( $error ) );
	}

	public function test_matches_case_insensitively() {
		// WordPress and cURL do not agree on capitalisation across versions, so
		// the lookup lowercases first.
		$this->assertSame(
			self::CONNECTION,
			ceros_get_friendly_error_message( 'COULD NOT RESOLVE HOST' )
		);
	}

	public function test_prefers_the_specific_host_error_over_the_generic_curl_one() {
		// 'could not resolve host' is listed before 'curl error', and a real
		// cURL 6 message contains both. Reordering the map would regress this
		// to the vaguer advice.
		$this->assertSame(
			self::CONNECTION,
			ceros_get_friendly_error_message( 'cURL error 6: Could not resolve host' )
		);
	}

	public function test_prefers_the_connect_error_over_the_generic_curl_one() {
		$this->assertSame(
			self::FAILED,
			ceros_get_friendly_error_message( 'cURL error 7: Failed to connect' )
		);
	}

	/**
	 * Documents a gap rather than endorsing it.
	 *
	 * The map's third entry is commented "cURL error 28: Operation timeout" but
	 * its pattern is the single word 'timeout', and cURL emits "Operation timed
	 * out after N milliseconds". The intended branch is therefore unreachable
	 * for the error it was written for, and a plugin timeout — the most likely
	 * failure of the three, given CEROS_API_REQUEST_TIMEOUT is 15s — gets the
	 * generic "check your internet connection" advice instead of "try again in
	 * a moment".
	 *
	 * Adding 'timed out' to the map fixes it and will fail this test, which is
	 * the intended signal to delete it.
	 */
	public function test_curl_28_does_not_reach_the_timeout_message() {
		$this->assertSame(
			self::GENERIC,
			ceros_get_friendly_error_message( 'cURL error 28: Operation timed out after 15001 milliseconds' )
		);
		$this->assertNotSame(
			self::TIMEOUT,
			ceros_get_friendly_error_message( 'cURL error 28: Operation timed out after 15001 milliseconds' )
		);
	}
}
