<?php
/**
 * Tests for ceros_is_ceros_owned_url(): every manifest and script origin the
 * plugin injects must pass it. A substring match instead of an exact suffix
 * match would let an attacker-controlled host through.
 *
 * @package ceros
 */

use PHPUnit\Framework\TestCase;

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
			'host with port'    => [ 'https://ceros.com:8443/exp' ],
			// The real origin is ceros.com.
			'userinfo prefix'   => [ 'https://evil.com@ceros.com/exp' ],
		];
	}

	/**
	 * @dataProvider accepted_urls
	 */
	public function test_accepts_ceros_owned_https_urls( $url ) {
		$this->assertTrue( ceros_is_ceros_owned_url( $url ) );
	}

	public function rejected_urls() {
		return [
			// An allowlisted domain appearing anywhere but the end.
			'domain mid-host'     => [ 'https://ceros.com.evil.com' ],
			'dev domain mid-host' => [ 'https://cerosdev.com.attacker.net' ],
			// Ends with the domain, but with no dot boundary.
			'no dot boundary'     => [ 'https://evilceros.com' ],
			'no dot before .site' => [ 'https://xceros.site' ],
			'truncated tld'       => [ 'https://ceros.co' ],
			'userinfo confusion'  => [ 'https://ceros.com@evil.com/exp' ],
			'trailing dot host'   => [ 'https://ceros.com./exp' ],
			'plain http'          => [ 'http://ceros.com' ],
			'protocol relative'   => [ '//ceros.com' ],
			'no scheme'           => [ 'ceros.com' ],
		];
	}

	/**
	 * @dataProvider rejected_urls
	 */
	public function test_rejects_non_ceros_or_insecure_urls( $url ) {
		$this->assertFalse( ceros_is_ceros_owned_url( $url ) );
	}
}
