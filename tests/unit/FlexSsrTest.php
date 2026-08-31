<?php
/**
 * Tests for ceros_flex_ssr_html_body(), ceros_flex_ssr_custom_body_html() and
 * ceros_flex_ssr_import_map() — the helpers in includes/flex-ssr-renderer.php
 * that reach neither escaping nor the request superglobals. The rest are
 * deferred; see tests/README.md.
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
	 * A manifest whose import map names the SDK the custom body HTML imports.
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
		$html = '<script type="module">import { connect } from \'@ceros/flex-experience-sdk\'</script>';

		$map = ceros_flex_ssr_import_map( $this->sdk_manifest(), $html );

		// Verbatim: the SRI section rides along, so the module keeps its
		// integrity guarantee rather than being rebuilt without one, and every
		// entry is carried rather than only the one that matched.
		$this->assertSame( $this->sdk_manifest()['importMap'], $map );
	}

	public function test_import_map_is_returned_for_a_non_sdk_specifier_too() {
		$html = '<script type="module">import \'@ceros/flex-runtime/hls\'</script>';

		$this->assertNotEmpty( ceros_flex_ssr_import_map( $this->sdk_manifest(), $html ) );
	}

	public function test_no_import_map_when_the_custom_html_imports_nothing_from_it() {
		// A document may hold a single import map, so one is emitted solely when
		// the injected HTML actually names a specifier it declares.
		$this->assertSame(
			[],
			ceros_flex_ssr_import_map( $this->sdk_manifest(), '<script>track()</script>' )
		);
	}

	public function test_no_import_map_when_the_custom_html_is_suppressed() {
		// The toggle is off, so nothing is injected and nothing needs resolving.
		$this->assertSame( [], ceros_flex_ssr_import_map( $this->sdk_manifest(), '' ) );
	}

	public function manifests_without_a_usable_import_map() {
		return [
			'no importMap key'    => [ [] ],
			'importMap not array' => [ [ 'importMap' => 'nope' ] ],
			'no imports key'      => [ [ 'importMap' => [ 'integrity' => [] ] ] ],
			'imports not array'   => [ [ 'importMap' => [ 'imports' => 'nope' ] ] ],
			'imports empty'       => [ [ 'importMap' => [ 'imports' => [] ] ] ],
		];
	}

	/**
	 * @dataProvider manifests_without_a_usable_import_map
	 */
	public function test_no_import_map_when_the_manifest_has_none( $manifest ) {
		// Experiences published before Ceros added the field.
		$html = '<script type="module">import \'@ceros/flex-experience-sdk\'</script>';

		$this->assertSame( [], ceros_flex_ssr_import_map( $manifest, $html ) );
	}

	public function test_no_import_map_when_the_custom_body_html_is_not_a_string() {
		$this->assertSame( [], ceros_flex_ssr_import_map( $this->sdk_manifest(), [ 'not', 'a', 'string' ] ) );
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

		$map = ceros_flex_ssr_import_map( $manifest, "import '@ceros/flex-experience-sdk'" );

		$this->assertSame(
			[ '@ceros/flex-experience-sdk' => 'https://assets.ceros.site/js/sdk.js' ],
			$map['imports']
		);
	}

	public function test_no_import_map_when_every_entry_is_malformed() {
		$manifest = [ 'importMap' => [ 'imports' => [ '@ceros/broken' => [ 'nested' ] ] ] ];

		$this->assertSame( [], ceros_flex_ssr_import_map( $manifest, "import '@ceros/broken'" ) );
	}

	public function test_a_non_array_integrity_section_is_dropped() {
		$manifest = [
			'importMap' => [
				'imports'   => [ '@ceros/flex-experience-sdk' => 'https://assets.ceros.site/js/sdk.js' ],
				'integrity' => 'nope',
			],
		];

		$map = ceros_flex_ssr_import_map( $manifest, "import '@ceros/flex-experience-sdk'" );

		$this->assertArrayNotHasKey( 'integrity', $map );
	}
}
