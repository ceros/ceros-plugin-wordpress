<?php
/**
 * Tests for ceros_is_public_host(), the SSRF guard on user-supplied URLs.
 *
 * Every case is an IP literal on purpose: the function only reaches
 * gethostbyname() when the input is not already an IP, so literals keep this
 * suite free of DNS, which is what lets the pre-push hook run it.
 *
 * The function delegates range classification to filter_var(), so the cases
 * here pin what this plugin actually decides — that both NO_PRIV_RANGE and
 * NO_RES_RANGE are applied — rather than re-testing PHP's range tables.
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
			// 172.16.0.0/12 ends at 172.31.255.255, so this one is public.
			// Paired with 'rfc1918 172' below, this pins the boundary.
			'just past rfc1918' => [ '172.32.0.1' ],
		];
	}

	/**
	 * @dataProvider public_ips
	 *
	 * @param string $host IP under test.
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
			// The cloud instance-metadata address, the classic SSRF target.
			'link local metadata' => [ '169.254.169.254' ],
		];
	}

	/**
	 * @dataProvider blocked_ips
	 *
	 * @param string $host IP under test.
	 */
	public function test_rejects_private_and_reserved_ips( $host ) {
		$this->assertFalse( ceros_is_public_host( $host ) );
	}

	public function empty_hosts() {
		return [
			'empty string' => [ '' ],
			// empty('0') is true in PHP, so this takes the same early return.
			'zero string'  => [ '0' ],
		];
	}

	/**
	 * @dataProvider empty_hosts
	 *
	 * @param mixed $host Host under test.
	 */
	public function test_rejects_empty_hosts( $host ) {
		$this->assertFalse( ceros_is_public_host( $host ) );
	}

	/**
	 * Ranges PHP does not classify as reserved, so they pass the guard today:
	 * 100.64.0.0/10 (carrier-grade NAT, RFC 6598) and 224.0.0.0/4 (multicast).
	 *
	 * Recorded rather than endorsed — closing either needs an explicit range
	 * check, and without this a change would go unnoticed.
	 */
	public function ranges_php_treats_as_public() {
		return [
			'cgnat'          => [ '100.64.0.1' ],
			'ipv4 multicast' => [ '224.0.0.1' ],
		];
	}

	/**
	 * @dataProvider ranges_php_treats_as_public
	 *
	 * @param string $host IP under test.
	 */
	public function test_documents_ranges_php_does_not_treat_as_reserved( $host ) {
		$this->assertTrue(
			ceros_is_public_host( $host ),
			'If this now fails, the SSRF guard got stricter — update the test and note the fix.'
		);
	}
}
