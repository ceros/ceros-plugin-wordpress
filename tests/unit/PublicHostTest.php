<?php
/**
 * Tests for ceros_is_public_host(), the SSRF guard on user-supplied URLs.
 *
 * Every case here is an IP literal on purpose. The function only calls
 * gethostbyname() when the input is not already an IP, so sticking to literals
 * keeps this suite free of DNS — which is what lets the pre-push hook run it.
 *
 * @package ceros
 */

use PHPUnit\Framework\TestCase;

/**
 * @covers ::ceros_is_public_host
 */
final class PublicHostTest extends TestCase {

	/**
	 * @return array<string, array{string}>
	 */
	public function public_ips() {
		return [
			'google dns'        => [ '8.8.8.8' ],
			'cloudflare dns'    => [ '1.1.1.1' ],
			'public ipv6'       => [ '2606:4700:4700::1111' ],
			// 172.16.0.0/12 ends at 172.31.255.255, so this one is public.
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

	/**
	 * @return array<string, array{string}>
	 */
	public function blocked_ips() {
		return [
			'ipv4 loopback'       => [ '127.0.0.1' ],
			'ipv6 loopback'       => [ '::1' ],
			'rfc1918 ten'         => [ '10.0.0.1' ],
			'rfc1918 ten top'     => [ '10.255.255.255' ],
			'rfc1918 172'         => [ '172.16.0.1' ],
			'rfc1918 192'         => [ '192.168.1.1' ],
			'ipv6 unique local'   => [ 'fc00::1' ],
			// The cloud instance-metadata address, the classic SSRF target.
			'link local metadata' => [ '169.254.169.254' ],
			'unspecified'         => [ '0.0.0.0' ],
			'broadcast'           => [ '255.255.255.255' ],
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

	/**
	 * @return array<string, array{mixed}>
	 */
	public function empty_hosts() {
		return [
			'empty string' => [ '' ],
			'null'         => [ null ],
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
	 * Ranges PHP's FILTER_FLAG_NO_RES_RANGE does not cover, so they currently
	 * pass the guard.
	 *
	 * This test records today's behaviour rather than endorsing it — without it,
	 * a future change here would go unnoticed. Both are arguably gaps worth
	 * closing with an explicit range check:
	 *
	 *   - 100.64.0.0/10  carrier-grade NAT (RFC 6598)
	 *   - 224.0.0.0/4    IPv4 multicast
	 *
	 * @return array<string, array{string}>
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
