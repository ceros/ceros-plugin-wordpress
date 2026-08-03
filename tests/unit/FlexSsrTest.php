<?php
/**
 * Tests for ceros_flex_ssr_html_body(), the only helper in
 * includes/flex-ssr-renderer.php that reaches neither escaping nor the request
 * superglobals. The rest are deferred; see tests/README.md.
 *
 * @package ceros
 */

use PHPUnit\Framework\TestCase;

/**
 * @covers ::ceros_flex_ssr_html_body
 */
final class FlexSsrTest extends TestCase {

	public function test_returns_the_html_body_asset_content() {
		$manifest = [
			'assets' => [
				[ 'type' => 'stylesheet' ],
				[
					'type' => 'html-body',
					'src'  => [ 'content' => '<div id="ceros">hi</div>' ],
				],
			],
		];

		$this->assertSame( '<div id="ceros">hi</div>', ceros_flex_ssr_html_body( $manifest ) );
	}

	public function test_returns_the_first_html_body_asset() {
		$manifest = [
			'assets' => [
				[
					'type' => 'html-body',
					'src'  => [ 'content' => 'first' ],
				],
				[
					'type' => 'html-body',
					'src'  => [ 'content' => 'second' ],
				],
			],
		];

		$this->assertSame( 'first', ceros_flex_ssr_html_body( $manifest ) );
	}

	public function manifests_without_html_body() {
		return [
			'empty manifest'       => [ [] ],
			'no assets key'        => [ [ 'pages' => [] ] ],
			'no html-body type'    => [ [ 'assets' => [ [ 'type' => 'stylesheet' ] ] ] ],
			'asset not an array'   => [ [ 'assets' => [ 'nope' ] ] ],
			'missing type'         => [ [ 'assets' => [ [ 'src' => [ 'content' => 'x' ] ] ] ] ],
			'missing src'          => [ [ 'assets' => [ [ 'type' => 'html-body' ] ] ] ],
			'missing content'      => [
				[
					'assets' => [
						[
							'type' => 'html-body',
							'src'  => [],
						],
					],
				],
			],
			'content not a string' => [
				[
					'assets' => [
						[
							'type' => 'html-body',
							'src'  => [ 'content' => [ 1 ] ],
						],
					],
				],
			],
		];
	}

	/**
	 * @dataProvider manifests_without_html_body
	 */
	public function test_returns_empty_string_when_there_is_no_html_body( $manifest ) {
		$this->assertSame( '', ceros_flex_ssr_html_body( $manifest ) );
	}
}
