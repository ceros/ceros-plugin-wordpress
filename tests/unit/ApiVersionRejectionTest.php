<?php
/**
 * Tests for the API-version rejection path.
 *
 * The API rejects an unknown version with a 400 whose body message is "Invalid
 * API version". Every other non-2xx on the settings save means a bad key, and the
 * two are indistinguishable from the status alone.
 *
 * @package ceros
 */

use PHPUnit\Framework\TestCase;

final class ApiVersionRejectionTest extends TestCase {

	public function rejections() {
		return [
			// What the API sends.
			'json body'   => [ 400, '{"message":"Invalid API version","statusCode":400}' ],
			// Error envelopes vary, so this matches the message, not the shape.
			'nested body' => [ 400, '{"error":{"message":"Invalid API version"}}' ],
			// Capitalisation is not worth depending on across releases.
			'lowercased'  => [ 400, '{"message":"invalid api version"}' ],
		];
	}

	/**
	 * @dataProvider rejections
	 */
	public function test_detects_a_version_rejection( $code, $body ) {
		$this->assertTrue( ceros_is_api_version_rejection( $code, $body ) );
	}

	public function non_rejections() {
		return [
			// The bad-key case has to keep reading as a bad key.
			'forbidden'         => [ 403, '{"message":"Forbidden resource"}' ],
			// A 400 about something else is not a version problem.
			'other bad request' => [ 400, '{"message":"pageSize must not exceed 1000"}' ],
			// A 5xx mentioning the phrase is still not a version rejection.
			'server error'      => [ 500, '{"message":"Invalid API version"}' ],
			'empty body'        => [ 400, '' ],
			'success'           => [ 200, '{"accountResourceId":"abc"}' ],
		];
	}

	/**
	 * @dataProvider non_rejections
	 */
	public function test_leaves_other_failures_alone( $code, $body ) {
		$this->assertFalse( ceros_is_api_version_rejection( $code, $body ) );
	}

	public function test_message_names_the_pin_and_the_plugin_version() {
		// The point is that the reader can act without a support round trip,
		// which needs both numbers in the text.
		$message = ceros_api_version_rejection_message();
		$this->assertStringContainsString( CEROS_API_VERSION, $message );
		$this->assertStringContainsString( CEROS_PLUGIN_VERSION, $message );
	}

	public function test_message_says_to_update_the_plugin() {
		$this->assertStringContainsString( 'update', strtolower( ceros_api_version_rejection_message() ) );
	}

	public function test_reports_a_version_rejection_as_an_unsupported_version() {
		// The call sites only forward what this returns, so this is where the
		// choice between the two failures is actually asserted.
		$failure = ceros_api_failure_report( 400, '{"message":"Invalid API version"}' );

		$this->assertSame( 'ceros_api_version_unsupported', $failure['error_code'] );
		$this->assertSame( ceros_api_version_rejection_message(), $failure['message'] );
	}

	public function test_reports_any_other_failure_as_a_bad_key() {
		$failure = ceros_api_failure_report( 403, '{"message":"Forbidden resource"}' );

		$this->assertSame( 'ceros_api_key_invalid', $failure['error_code'] );
		$this->assertStringContainsString( 'API key could not be verified', $failure['message'] );
	}

	/**
	 * @dataProvider rejections
	 */
	public function test_never_pairs_a_version_rejection_with_the_key_message( $code, $body ) {
		// A swap between the two branches would leave both assertions above
		// passing individually while reporting the wrong thing.
		$failure = ceros_api_failure_report( $code, $body );

		$this->assertStringNotContainsString( 'API key', $failure['message'] );
		$this->assertNotSame( 'ceros_api_key_invalid', $failure['error_code'] );
	}

	/**
	 * @dataProvider non_rejections
	 */
	public function test_never_pairs_an_ordinary_failure_with_the_upgrade_message( $code, $body ) {
		$failure = ceros_api_failure_report( $code, $body );

		$this->assertStringNotContainsString( 'too old', $failure['message'] );
		$this->assertNotSame( 'ceros_api_version_unsupported', $failure['error_code'] );
	}

	public function test_message_does_not_blame_the_api_key() {
		// If the phrase comes back, the mapping has regressed into the bad-key
		// message.
		$this->assertStringNotContainsString( 'api key', strtolower( ceros_api_version_rejection_message() ) );
	}
}
