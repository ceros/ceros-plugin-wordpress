<?php
/**
 * Pins the wp_parse_url() stand-in in tests/bootstrap.php, which every URL test
 * rests on. These cannot prove faithfulness to core — only the integration suite
 * can — but they make a change to the shim fail here rather than silently change
 * what other tests mean.
 *
 * @package ceros
 */

use PHPUnit\Framework\TestCase;

final class BootstrapShimTest extends TestCase {

	public function test_splits_an_absolute_url_into_all_components() {
		$this->assertSame(
			[
				'scheme'   => 'https',
				'host'     => 'a.ceros.site',
				'port'     => 8443,
				'path'     => '/exp',
				'query'    => 'x=1',
				'fragment' => 'f',
			],
			wp_parse_url( 'https://a.ceros.site:8443/exp?x=1#f' )
		);
	}

	/**
	 * parse_url() alone misreads these; core uses a placeholder scheme.
	 */
	public function test_drops_scheme_for_a_protocol_relative_url() {
		$this->assertSame(
			[
				'host' => 'a.ceros.site',
				'path' => '/exp',
			],
			wp_parse_url( '//a.ceros.site/exp' )
		);
	}

	public function test_drops_scheme_and_host_for_a_root_relative_url() {
		$this->assertSame( [ 'path' => '/exp/page' ], wp_parse_url( '/exp/page' ) );
	}

	/**
	 * Why https://ceros.com@evil.com is rejected by the owned-URL gate.
	 */
	public function test_keeps_userinfo_out_of_the_host() {
		$parts = wp_parse_url( 'https://ceros.com@evil.com/x' );

		$this->assertSame( 'evil.com', $parts['host'] );
		$this->assertSame( 'ceros.com', $parts['user'] );
	}

	public function url_components() {
		return [
			'scheme'   => [ PHP_URL_SCHEME, 'https' ],
			'host'     => [ PHP_URL_HOST, 'a.ceros.site' ],
			'port'     => [ PHP_URL_PORT, 8443 ],
			'path'     => [ PHP_URL_PATH, '/exp' ],
			'query'    => [ PHP_URL_QUERY, 'x=1' ],
			'fragment' => [ PHP_URL_FRAGMENT, 'f' ],
		];
	}

	/**
	 * @dataProvider url_components
	 */
	public function test_returns_a_single_requested_component( $component, $expected ) {
		$this->assertSame( $expected, wp_parse_url( 'https://a.ceros.site:8443/exp?x=1#f', $component ) );
	}

	public function test_returns_null_for_a_component_that_is_absent() {
		$this->assertNull( wp_parse_url( 'https://a.ceros.site/exp', PHP_URL_PORT ) );
	}

	public function test_returns_false_for_an_unparseable_url() {
		$this->assertFalse( wp_parse_url( 'http://:80' ) );
	}

	public function test_handles_an_empty_string() {
		$this->assertSame( [ 'path' => '' ], wp_parse_url( '' ) );
		$this->assertNull( wp_parse_url( '', PHP_URL_SCHEME ) );
	}

	public function test_translation_shim_returns_its_input() {
		$this->assertSame( 'Some message.', __( 'Some message.', 'ceros' ) );
	}
}
