<?php
/**
 * Tests for ceros_flex_ssr_html_body(), ceros_flex_ssr_custom_body_html(),
 * ceros_flex_ssr_import_map(), ceros_flex_ssr_rebuild_script(),
 * ceros_flex_ssr_import_map_integrity() and ceros_flex_ssr_in_skipped_context() —
 * the helpers in includes/flex-ssr-renderer.php that reach neither escaping,
 * the request superglobals nor WordPress core. The rest are deferred; see
 * tests/README.md.
 *
 * @package ceros
 */

use PHPUnit\Framework\TestCase;

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

	public function test_returns_the_custom_body_html_from_display_metadata() {
		$manifest = [
			'displayMetadata' => [
				'mode'           => 'scale',
				'customBodyHtml' => '<script>window.sdkBoot=1</script>',
			],
		];

		$this->assertSame(
			'<script>window.sdkBoot=1</script>',
			ceros_flex_ssr_custom_body_html( $manifest )
		);
	}

	public function manifests_without_custom_body_html() {
		return [
			'empty manifest'            => [ [] ],
			'no displayMetadata key'    => [ [ 'assets' => [] ] ],
			'displayMetadata not array' => [ [ 'displayMetadata' => 'nope' ] ],
			'no customBodyHtml key'     => [ [ 'displayMetadata' => [ 'mode' => 'scale' ] ] ],
			'customBodyHtml null'       => [ [ 'displayMetadata' => [ 'customBodyHtml' => null ] ] ],
			'customBodyHtml not string' => [ [ 'displayMetadata' => [ 'customBodyHtml' => [ 1 ] ] ] ],
		];
	}

	/**
	 * @dataProvider manifests_without_custom_body_html
	 */
	public function test_returns_empty_string_when_there_is_no_custom_body_html( $manifest ) {
		$this->assertSame( '', ceros_flex_ssr_custom_body_html( $manifest ) );
	}

	public function test_custom_body_html_is_not_confused_with_the_html_body_asset() {
		// The two live in different places and must not cross-read.
		$manifest = [
			'assets'          => [
				[
					'type' => 'html-body',
					'src'  => [ 'content' => '<div id="ceros">hi</div>' ],
				],
			],
			'displayMetadata' => [ 'customBodyHtml' => '<script>boot()</script>' ],
		];

		$this->assertSame( '<div id="ceros">hi</div>', ceros_flex_ssr_html_body( $manifest ) );
		$this->assertSame( '<script>boot()</script>', ceros_flex_ssr_custom_body_html( $manifest ) );
	}

	/**
	 * A manifest carrying an import map, with SRI for one of its two modules.
	 *
	 * @return array
	 */
	private function sdk_manifest() {
		return [
			'importMap' => [
				'imports'   => [
					'@ceros/flex-experience-sdk' => 'https://assets.ceros.site/js/sdk.js',
					'@ceros/flex-runtime/hls'    => 'https://assets.ceros.site/js/hls.js',
				],
				'integrity' => [ 'https://assets.ceros.site/js/sdk.js' => 'sha384-abc' ],
			],
		];
	}

	public function test_import_map_is_returned_verbatim_including_integrity() {
		// The manifest alone decides the map: this fixture has no custom body
		// HTML, which is the case the video runtime needs, since nothing
		// authored ever names `@ceros/flex-runtime/hls`. The SRI section rides
		// along, so a caller printing the map whole keeps each module pinned.
		$map = ceros_flex_ssr_import_map( $this->sdk_manifest() );

		$this->assertSame( $this->sdk_manifest()['importMap'], $map );
	}

	public function manifests_without_a_usable_import_map() {
		return [
			'no importMap key'    => [ [] ],
			'importMap not array' => [ [ 'importMap' => 'nope' ] ],
			'no imports key'      => [ [ 'importMap' => [ 'integrity' => [] ] ] ],
			'imports not array'   => [ [ 'importMap' => [ 'imports' => 'nope' ] ] ],
			'imports empty'       => [ [ 'importMap' => [ 'imports' => [] ] ] ],
			'integrity only'      => [
				[
					'importMap' => [
						'imports'   => [],
						'integrity' => [ 'https://assets.ceros.site/js/sdk.js' => 'sha384-abc' ],
					],
				],
			],
		];
	}

	/**
	 * @dataProvider manifests_without_a_usable_import_map
	 */
	public function test_no_import_map_when_the_manifest_has_none( $manifest ) {
		// Experiences published before Ceros added the field.
		$this->assertSame( [], ceros_flex_ssr_import_map( $manifest ) );
	}

	public function test_import_entries_that_are_not_string_pairs_are_dropped() {
		// One malformed entry would otherwise invalidate the whole map.
		$manifest = [
			'importMap' => [
				'imports' => [
					'@ceros/flex-experience-sdk' => 'https://assets.ceros.site/js/sdk.js',
					'@ceros/broken'              => [ 'nested' ],
					'@ceros/empty'               => '',
				],
			],
		];

		$map = ceros_flex_ssr_import_map( $manifest );

		$this->assertSame(
			[ '@ceros/flex-experience-sdk' => 'https://assets.ceros.site/js/sdk.js' ],
			$map['imports']
		);
	}

	public function test_no_import_map_when_every_entry_is_malformed() {
		$manifest = [ 'importMap' => [ 'imports' => [ '@ceros/broken' => [ 'nested' ] ] ] ];

		$this->assertSame( [], ceros_flex_ssr_import_map( $manifest ) );
	}

	public function test_a_non_array_integrity_section_is_dropped() {
		$manifest = [
			'importMap' => [
				'imports'   => [ '@ceros/flex-experience-sdk' => 'https://assets.ceros.site/js/sdk.js' ],
				'integrity' => 'nope',
			],
		];

		$map = ceros_flex_ssr_import_map( $manifest );

		$this->assertArrayNotHasKey( 'integrity', $map );
	}

	public function test_a_rebuilt_script_round_trips_its_attribute_values() {
		$tag = ceros_flex_ssr_rebuild_script(
			[
				'type'        => 'module',
				'src'         => 'data:text/javascript,a&b',
				'data-config' => 'a&amp;b "q"',
				'defer'       => true,
				'hidden'      => false,
			],
			''
		);

		$this->assertSame(
			// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- expected markup, not output.
			'<script type="module" src="data:text/javascript,a&amp;b" data-config="a&amp;amp;b &quot;q&quot;" defer></script>' . "\n",
			$tag
		);
	}

	public function test_a_rebuilt_inline_script_keeps_its_text() {
		$this->assertSame(
			'<script type="module">import("@ceros/x");</script>' . "\n",
			ceros_flex_ssr_rebuild_script( [ 'type' => 'module' ], 'import("@ceros/x");' )
		);
	}

	public function test_carried_integrity_keeps_the_first_hash_for_a_url() {
		ceros_flex_ssr_import_map_integrity(
			[
				'integrity' => [
					'https://a.test/first.js' => 'sha384-first',
					''                        => 'sha384-no-url',
					'https://a.test/empty.js' => '',
				],
			]
		);
		$integrity = ceros_flex_ssr_import_map_integrity(
			[
				'integrity' => [
					'https://a.test/first.js'  => 'sha384-second',
					'https://a.test/second.js' => 'sha384-other',
				],
			]
		);

		$this->assertSame( 'sha384-first', $integrity['https://a.test/first.js'] );
		$this->assertSame( 'sha384-other', $integrity['https://a.test/second.js'] );
		$this->assertArrayNotHasKey( '', $integrity );
		$this->assertArrayNotHasKey( 'https://a.test/empty.js', $integrity );
	}

	public function contexts_for_a_script() {
		return [
			'top level'                     => [ [], false ],
			'inside a template'             => [ [ [ 'TEMPLATE', 'inert' ] ], true ],
			'inside svg'                    => [ [ [ 'SVG', 'foreign' ] ], true ],
			'inside svg foreignObject'      => [ [ [ 'SVG', 'foreign' ], [ 'FOREIGNOBJECT', 'html' ] ], false ],
			'svg inside foreignObject'      => [ [ [ 'SVG', 'foreign' ], [ 'FOREIGNOBJECT', 'html' ], [ 'SVG', 'foreign' ] ], true ],
			'foreignObject inside template' => [ [ [ 'TEMPLATE', 'inert' ], [ 'SVG', 'foreign' ], [ 'FOREIGNOBJECT', 'html' ] ], true ],
			'declarative shadow root'       => [ [ [ 'TEMPLATE', 'html' ] ], false ],
		];
	}

	/**
	 * @dataProvider contexts_for_a_script
	 */
	public function test_a_script_is_skipped_only_in_inert_or_foreign_content( $open, $skipped ) {
		$this->assertSame( $skipped, ceros_flex_ssr_in_skipped_context( $open ) );
	}
}
