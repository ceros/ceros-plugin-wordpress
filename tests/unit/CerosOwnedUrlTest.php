<?php
/**
 * Tests for ceros_is_ceros_owned_url(), the security gate for the keyless paste
 * flow: every manifest and script origin the plugin injects must pass it.
 *
 * The look-alikes are the point. A substring match instead of an exact suffix
 * match would let an attacker-controlled host through, so both failure modes
 * are covered twice — they are the threat model, not redundancy.
 *
 * @package ceros
 */

use PHPUnit\Framework\TestCase;

/**
 * @covers ::ceros_is_ceros_owned_url
 */
final class CerosOwnedUrlTest extends TestCase {

	/**
	 * One row per allowlisted domain, so removing an entry fails a test.
	 */
	public function accepted_urls() {
		return [
			'exact prod .com'   => [ 'https://ceros.com' ],
			'exact prod .site'  => [ 'https://ceros.site' ],
			'exact dev .com'    => [ 'https://cerosdev.com' ],
			'exact stage .com'  => [ 'https://cerosstage.com' ],
			'exact dev .site'   => [ 'https://cerosdev.site' ],
			'exact stage .site' => [ 'https://cerosstage.site' ],
			'subdomain'         => [ 'https://view.ceros.com' ],
			'nested subdomain'  => [ 'https://a.b.c.ceros.site' ],
			'uppercase'         => [ 'HTTPS://VIEW.CEROS.COM' ],
			// The port is not part of the host, so it must not defeat the match.
			'host with port'    => [ 'https://ceros.com:8443/exp' ],
			// Userinfo is not the host: the real origin here IS ceros.com.
			'userinfo prefix'   => [ 'https://evil.com@ceros.com/exp' ],
		];
	}

	/**
	 * @dataProvider accepted_urls
	 *
	 * @param string $url URL under test.
	 */
	public function test_accepts_ceros_owned_https_urls( $url ) {
		$this->assertTrue( ceros_is_ceros_owned_url( $url ) );
	}

	public function rejected_urls() {
		return [
			// Mode 1: an allowlisted domain appearing anywhere but the end.
			'domain mid-host'     => [ 'https://ceros.com.evil.com' ],
			'dev domain mid-host' => [ 'https://cerosdev.com.attacker.net' ],
			// Mode 2: ends with the domain, but with no dot boundary before it.
			'no dot boundary'     => [ 'https://evilceros.com' ],
			'no dot before .site' => [ 'https://xceros.site' ],
			'truncated tld'       => [ 'https://ceros.co' ],
			// Reads as ceros.com to a human, but the host is evil.com.
			'userinfo confusion'  => [ 'https://ceros.com@evil.com/exp' ],
			// A trailing-dot FQDN is not treated as equivalent.
			'trailing dot host'   => [ 'https://ceros.com./exp' ],
			// Scheme must be https: the injected origin has to be authenticated.
			'plain http'          => [ 'http://ceros.com' ],
			'protocol relative'   => [ '//ceros.com' ],
			'no scheme'           => [ 'ceros.com' ],
		];
	}

	/**
	 * @dataProvider rejected_urls
	 *
	 * @param string $url URL under test.
	 */
	public function test_rejects_non_ceros_or_insecure_urls( $url ) {
		$this->assertFalse( ceros_is_ceros_owned_url( $url ) );
	}
}
