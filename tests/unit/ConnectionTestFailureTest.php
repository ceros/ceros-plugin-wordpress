<?php
/**
 * Tests for the connection test's failure report.
 *
 * The connection test reaches the API with the pinned version in its headers, so
 * a stale plugin fails it. Reporting that as a bad key or URL sends the reader
 * to check two things that are both correct.
 *
 * @package ceros
 */

use PHPUnit\Framework\TestCase;

final class ConnectionTestFailureTest extends TestCase {

	public function rejections() {
		return [
			'json body'   => [ 400, '{"message":"Invalid API version","statusCode":400}' ],
			'nested body' => [ 400, '{"error":{"message":"Invalid API version"}}' ],
			'lowercased'  => [ 400, '{"message":"invalid api version"}' ],
		];
	}

	public function non_rejections() {
		return [
			// A reachable staging URL answering 404 is the case the generic
			// advice names the URL for.
			'wrong url'         => [ 404, '{"message":"Not Found"}' ],
			'forbidden'         => [ 403, '{"message":"Forbidden resource"}' ],
			'other bad request' => [ 400, '{"message":"pageSize must not exceed 1000"}' ],
			'server error'      => [ 500, '{"message":"Invalid API version"}' ],
			'empty body'        => [ 400, '' ],
		];
	}

	/**
	 * @dataProvider rejections
	 */
	public function test_reports_a_version_rejection_as_an_unsupported_version( $code, $body ) {
		$failure = ceros_connection_test_failure( $code, $body );
		$this->assertSame( 'ceros_api_version_unsupported', $failure['error_code'] );
	}

	/**
	 * The regression this exists for: the generic advice used to answer every
	 * non-2xx, including this one.
	 *
	 * @dataProvider rejections
	 */
	public function test_never_sends_a_stale_plugin_to_check_the_key_or_url( $code, $body ) {
		$message = strtolower( ceros_connection_test_failure( $code, $body )['message'] );
		$this->assertStringNotContainsString( 'api key', $message );
		$this->assertStringNotContainsString( 'url', $message );
	}

	/**
	 * @dataProvider rejections
	 */
	public function test_tells_a_stale_plugin_to_update( $code, $body ) {
		$message = ceros_connection_test_failure( $code, $body )['message'];
		$this->assertStringContainsString( CEROS_API_VERSION, $message );
		$this->assertStringContainsString( 'update', strtolower( $message ) );
	}

	/**
	 * @dataProvider non_rejections
	 */
	public function test_reports_any_other_failure_as_a_failed_connection_test( $code, $body ) {
		$failure = ceros_connection_test_failure( $code, $body );
		$this->assertSame( 'ceros_connection_test_failed', $failure['error_code'] );
	}

	/**
	 * The generic advice has to keep naming both, because this flow exercises a
	 * staging URL the caller just typed as well as the key.
	 *
	 * @dataProvider non_rejections
	 */
	public function test_generic_advice_names_the_url_and_the_key( $code, $body ) {
		$message = strtolower( ceros_connection_test_failure( $code, $body )['message'] );
		$this->assertStringContainsString( 'url', $message );
		$this->assertStringContainsString( 'api key', $message );
	}

	/**
	 * The settings save reports the same rejection as a key problem, because
	 * that flow has no URL to blame. Drops if the two reports are merged.
	 */
	public function test_differs_from_the_settings_save_report_on_a_plain_failure() {
		$connection = ceros_connection_test_failure( 403, '{"message":"Forbidden resource"}' );
		$settings   = ceros_api_failure_report( 403, '{"message":"Forbidden resource"}' );
		$this->assertNotSame( $settings['error_code'], $connection['error_code'] );
	}
}
