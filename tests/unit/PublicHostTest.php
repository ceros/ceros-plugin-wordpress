<?php
/**
 * Tests for ceros_is_public_host(), the SSRF guard on user-supplied URLs.
 *
 * IP literals only: the function reaches gethostbyname() for anything else,
 * which would put DNS in the pre-push path. Range classification is filter_var's
 * job, so these pin the flag choice rather than PHP's range tables.
 *
 * @package ceros
 */

use PHPUnit\Framework\TestCase;

/**
 * @covers ::ceros_is_public_host
 */
final class PublicHostTest extends TestCase {

	public function public_ips() {
		return [
			'public ipv4'       => [ '8.8.8.8' ],
			'public ipv6'       => [ '2606:4700:4700::1111' ],
			// 172.16.0.0/12 ends at 172.31.255.255.
			'just past rfc1918' => [ '172.32.0.1' ],
		];
	}

	/**
	 * @dataProvider public_ips
	 */
	public function test_accepts_publicly_routable_ips( $host ) {
		$this->assertTrue( ceros_is_public_host( $host ) );
	}

	public function blocked_ips() {
		return [
			// Drops if NO_PRIV_RANGE is ever removed.
			'rfc1918 172'         => [ '172.16.0.1' ],
			// Drops if NO_RES_RANGE is ever removed.
			'ipv4 loopback'       => [ '127.0.0.1' ],
			// Cloud instance-metadata address.
			'link local metadata' => [ '169.254.169.254' ],
		];
	}

	/**
	 * @dataProvider blocked_ips
	 */
	public function test_rejects_private_and_reserved_ips( $host ) {
		$this->assertFalse( ceros_is_public_host( $host ) );
	}

	public function empty_hosts() {
		return [
			'empty string' => [ '' ],
			// empty('0') is true in PHP.
			'zero string'  => [ '0' ],
		];
	}

	/**
	 * @dataProvider empty_hosts
	 */
	public function test_rejects_empty_hosts( $host ) {
		$this->assertFalse( ceros_is_public_host( $host ) );
	}

	/**
	 * Ranges PHP does not classify as reserved, so they pass the guard today.
	 * Recorded, not endorsed; closing either needs an explicit range check.
	 */
	public function ranges_php_treats_as_public() {
		return [
			'cgnat'          => [ '100.64.0.1' ],
			'ipv4 multicast' => [ '224.0.0.1' ],
		];
	}

	/**
	 * @dataProvider ranges_php_treats_as_public
	 */
	public function test_documents_ranges_php_does_not_treat_as_reserved( $host ) {
		$this->assertTrue(
			ceros_is_public_host( $host ),
			'If this now fails, the SSRF guard got stricter — update the test and note the fix.'
		);
	}
}
