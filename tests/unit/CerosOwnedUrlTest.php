<?php
/**
 * Tests for ceros_is_ceros_owned_url().
 *
 * This is the security gate for the keyless paste flow: every manifest and
 * script origin the plugin injects into a post must pass it. The interesting
 * cases are the look-alikes, because a substring match instead of an exact
 * suffix match would let an attacker-controlled host through.
 *
 * @package ceros
 */

use PHPUnit\Framework\TestCase;

/**
 * @covers ::ceros_is_ceros_owned_url
 */
final class CerosOwnedUrlTest extends TestCase {

	/**
	 * URLs that must be accepted.
	 *
	 * @return array<string, array{string}>
	 */
	public function accepted_urls() {
		return [
			'exact prod .com'     => [ 'https://ceros.com' ],
			'exact prod .site'    => [ 'https://ceros.site' ],
			'exact dev .com'      => [ 'https://cerosdev.com' ],
			'exact stage .com'    => [ 'https://cerosstage.com' ],
			'exact dev .site'     => [ 'https://cerosdev.site' ],
			'exact stage .site'   => [ 'https://cerosstage.site' ],
			'studio view host'    => [ 'https://view.ceros.com' ],
			'flex assets host'    => [ 'https://assets.ceros.site' ],
			'nested subdomain'    => [ 'https://a.b.c.ceros.site' ],
			'uppercase scheme'    => [ 'HTTPS://VIEW.CEROS.COM' ],
			'path and query'      => [ 'https://ceros.com/acct/exp?x=1#frag' ],
			'non-prod flex label' => [ 'https://latest.dev.flex.cerosdev.com' ],
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

	/**
	 * URLs that must be rejected. The look-alikes are the point of the test.
	 *
	 * @return array<string, array{string}>
	 */
	public function rejected_urls() {
		return [
			// A substring check would wrongly accept these three.
			'domain as prefix of another' => [ 'https://ceros.com.evil.com' ],
			'flex domain as prefix'       => [ 'https://ceros.site.evil.com' ],
			'suffix without dot boundary' => [ 'https://evilceros.com' ],
			'no dot before site domain'   => [ 'https://xceros.site' ],
			'lookalike bare domain'       => [ 'https://notceros.com' ],
			'truncated tld'               => [ 'https://ceros.co' ],
			'dev domain as prefix'        => [ 'https://cerosdev.com.attacker.net' ],
			// Scheme must be https: the injected origin has to be authenticated.
			'plain http'                  => [ 'http://ceros.com' ],
			'ftp'                         => [ 'ftp://ceros.com' ],
			'protocol relative'           => [ '//ceros.com' ],
			'empty string'                => [ '' ],
			'not a url'                   => [ 'not a url' ],
			'host only'                   => [ 'ceros.com' ],
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
