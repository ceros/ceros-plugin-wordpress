<?php
/**
 * Tests for the pure helpers in includes/flex-store.php.
 *
 * ceros_store_safe_rel_path() takes paths from a manifest rewrite map and they
 * are joined onto the uploads directory, so a traversal writes outside it.
 *
 * ceros_store_rewrite_import_map() is the pure half of the import-map mirroring:
 * the downloads live in ceros_store_localize_import_map(), which is deferred
 * because it makes HTTP requests. See tests/README.md.
 *
 * @package ceros
 */

use PHPUnit\Framework\TestCase;

final class FlexStoreTest extends TestCase {

	public function unsafe_paths() {
		return [
			'parent traversal'    => [ '../etc/passwd' ],
			'traversal mid path'  => [ 'assets/../../etc/passwd' ],
			'single dot segment'  => [ 'assets/./app.js' ],
			'empty segment'       => [ 'assets//app.js' ],
			'trailing slash'      => [ 'assets/app.js/' ],
			'backslash traversal' => [ 'assets\\..\\app.js' ],
			'control char'        => [ "assets/app\0.js" ],
			'empty string'        => [ '' ],
			'only a slash'        => [ '/' ],
		];
	}

	/**
	 * @dataProvider unsafe_paths
	 */
	public function test_rejects_unsafe_relative_paths( $path ) {
		$this->assertSame( '', ceros_store_safe_rel_path( $path ) );
	}

	/**
	 * The awkward-but-valid names matter: the alphabet is deliberately permissive.
	 */
	public function safe_paths() {
		return [
			'nested'            => [ 'assets/app.min.js', 'assets/app.min.js' ],
			'strips leading /'  => [ '/assets/app.js', 'assets/app.js' ],
			// ltrim strips every leading slash, so this stays relative.
			'many leading /'    => [ '//etc/passwd', 'etc/passwd' ],
			'spaces'            => [ 'my assets/my file.js', 'my assets/my file.js' ],
			'parens and commas' => [ 'assets/file (1),v2.js', 'assets/file (1),v2.js' ],
			'unicode'           => [ 'assets/ünïcodé.js', 'assets/ünïcodé.js' ],
			'leading dot file'  => [ 'assets/.keep', 'assets/.keep' ],
		];
	}

	/**
	 * @dataProvider safe_paths
	 */
	public function test_accepts_safe_relative_paths( $path, $expected ) {
		$this->assertSame( $expected, ceros_store_safe_rel_path( $path ) );
	}

	public function suffixes() {
		return [
			'matching suffix'   => [ 'foobar', 'bar', true ],
			'whole string'      => [ 'foobar', 'foobar', true ],
			'empty needle'      => [ 'foobar', '', true ],
			'prefix not suffix' => [ 'foobar', 'foo', false ],
			'case sensitive'    => [ 'foobar', 'BAR', false ],
			'needle longer'     => [ 'ab', 'abc', false ],
		];
	}

	/**
	 * @dataProvider suffixes
	 */
	public function test_str_ends_with( $haystack, $needle, $expected ) {
		$this->assertSame( $expected, ceros_str_ends_with( $haystack, $needle ) );
	}

	public function test_primary_slug_prefers_experience_page_slug() {
		$manifest = [
			'experience' => [ 'pageSlug' => 'chosen' ],
			'pages'      => [
				[
					'current' => true,
					'slug'    => 'ignored',
				],
			],
		];

		$this->assertSame( 'chosen', ceros_store_primary_slug( $manifest ) );
	}

	public function test_primary_slug_falls_back_to_current_page() {
		$manifest = [
			'pages' => [
				[ 'slug' => 'not-current' ],
				[
					'current' => true,
					'slug'    => 'current-page',
				],
			],
		];

		$this->assertSame( 'current-page', ceros_store_primary_slug( $manifest ) );
	}

	public function manifests_without_slug() {
		return [
			'empty manifest'  => [ [] ],
			'no current page' => [ [ 'pages' => [ [ 'slug' => 'a' ] ] ] ],
		];
	}

	/**
	 * @dataProvider manifests_without_slug
	 */
	public function test_primary_slug_defaults_to_index( $manifest ) {
		$this->assertSame( 'index', ceros_store_primary_slug( $manifest ) );
	}

	/**
	 * The map as it arrives from the `?baseUrl=` rewrite: still CDN URLs,
	 * because the server-side rewrite does not walk `importMap`.
	 *
	 * @return array
	 */
	private function cdn_manifest() {
		return [
			'importMap' => [
				'imports'   => [
					'@ceros/flex-experience-sdk' => 'https://assets.ceros.site/js/sdk.js',
					'@ceros/flex-runtime/hls'    => 'https://assets.ceros.site/js/hls.js',
				],
				'integrity' => [
					'https://assets.ceros.site/js/sdk.js' => 'sha384-abc',
					'https://assets.ceros.site/js/hls.js' => 'sha384-def',
				],
			],
		];
	}

	public function test_rewrites_mirrored_modules_to_the_local_base() {
		$out = ceros_store_rewrite_import_map(
			$this->cdn_manifest(),
			'https://site.test/uploads/ceros-flex/1/acme--exp/7',
			[
				'https://assets.ceros.site/js/sdk.js' => 'import-map/abc12345-sdk.js',
				'https://assets.ceros.site/js/hls.js' => 'import-map/def67890-hls.js',
			]
		);

		$this->assertSame(
			'https://site.test/uploads/ceros-flex/1/acme--exp/7/import-map/abc12345-sdk.js',
			$out['importMap']['imports']['@ceros/flex-experience-sdk']
		);
		$this->assertSame(
			'https://site.test/uploads/ceros-flex/1/acme--exp/7/import-map/def67890-hls.js',
			$out['importMap']['imports']['@ceros/flex-runtime/hls']
		);
	}

	public function test_rekeys_integrity_onto_the_local_url() {
		// integrity is keyed by URL, so leaving the old key behind silently
		// drops SRI for the mirrored copy.
		$out = ceros_store_rewrite_import_map(
			$this->cdn_manifest(),
			'https://site.test/u/v1',
			[ 'https://assets.ceros.site/js/sdk.js' => 'import-map/abc12345-sdk.js' ]
		);

		$integrity = $out['importMap']['integrity'];

		$this->assertSame( 'sha384-abc', $integrity['https://site.test/u/v1/import-map/abc12345-sdk.js'] );
		$this->assertArrayNotHasKey( 'https://assets.ceros.site/js/sdk.js', $integrity );
		// The module that was not mirrored keeps its original key.
		$this->assertSame( 'sha384-def', $integrity['https://assets.ceros.site/js/hls.js'] );
	}

	public function test_a_module_that_could_not_be_mirrored_keeps_its_cdn_url() {
		// Better a working CDN fetch than a local URL pointing at nothing.
		$out = ceros_store_rewrite_import_map(
			$this->cdn_manifest(),
			'https://site.test/u/v1',
			[ 'https://assets.ceros.site/js/sdk.js' => 'import-map/abc12345-sdk.js' ]
		);

		$this->assertSame(
			'https://assets.ceros.site/js/hls.js',
			$out['importMap']['imports']['@ceros/flex-runtime/hls']
		);
	}

	public function test_trailing_slash_on_the_base_does_not_double_up() {
		$out = ceros_store_rewrite_import_map(
			$this->cdn_manifest(),
			'https://site.test/u/v1/',
			[ 'https://assets.ceros.site/js/sdk.js' => 'import-map/abc12345-sdk.js' ]
		);

		$this->assertSame(
			'https://site.test/u/v1/import-map/abc12345-sdk.js',
			$out['importMap']['imports']['@ceros/flex-experience-sdk']
		);
	}

	public function untouched_manifests() {
		return [
			'nothing mirrored'  => [ [ 'importMap' => [ 'imports' => [ 'a' => 'https://x.test/a.js' ] ] ], [] ],
			'no importMap'      => [ [ 'assets' => [] ], [ 'https://x.test/a.js' => 'p' ] ],
			'importMap scalar'  => [ [ 'importMap' => 'nope' ], [ 'https://x.test/a.js' => 'p' ] ],
			'imports not array' => [ [ 'importMap' => [ 'imports' => 'nope' ] ], [ 'https://x.test/a.js' => 'p' ] ],
			'manifest scalar'   => [ 'nope', [ 'https://x.test/a.js' => 'p' ] ],
		];
	}

	/**
	 * @dataProvider untouched_manifests
	 */
	public function test_returns_the_manifest_unchanged( $manifest, $localized ) {
		$this->assertSame( $manifest, ceros_store_rewrite_import_map( $manifest, 'https://site.test/u', $localized ) );
	}

	public function test_import_map_paths_are_stable_and_url_safe() {
		$path = ceros_store_import_map_rel_path( 'https://assets.ceros.site/js/flex-experience-sdk.js' );

		$this->assertSame( $path, ceros_store_import_map_rel_path( 'https://assets.ceros.site/js/flex-experience-sdk.js' ) );
		// Generated rather than given, so it can go straight into a URL.
		$this->assertMatchesRegularExpression( '#^import-map/[0-9a-f]{8}-[A-Za-z0-9._-]+$#', $path );
		$this->assertStringEndsWith( '-flex-experience-sdk.js', $path );
	}

	public function test_import_map_paths_separate_modules_sharing_a_basename() {
		// Two runtimes both called index.js would otherwise overwrite each other.
		$this->assertNotSame(
			ceros_store_import_map_rel_path( 'https://assets.ceros.site/a/index.js' ),
			ceros_store_import_map_rel_path( 'https://assets.ceros.site/b/index.js' )
		);
	}

	public function awkward_module_urls() {
		return [
			'query string'   => [ 'https://assets.ceros.site/js/sdk.js?v=2' ],
			'no extension'   => [ 'https://assets.ceros.site/js/sdk' ],
			'trailing slash' => [ 'https://assets.ceros.site/js/' ],
			'root only'      => [ 'https://assets.ceros.site/' ],
			'spaces in name' => [ 'https://assets.ceros.site/js/my sdk.js' ],
			'dots only'      => [ 'https://assets.ceros.site/js/...' ],
		];
	}

	/**
	 * @dataProvider awkward_module_urls
	 */
	public function test_import_map_paths_stay_safe_for_awkward_urls( $url ) {
		$this->assertMatchesRegularExpression(
			'#^import-map/[0-9a-f]{8}-[A-Za-z0-9._-]+$#',
			ceros_store_import_map_rel_path( $url )
		);
	}

	public function test_import_map_path_is_empty_for_an_empty_url() {
		$this->assertSame( '', ceros_store_import_map_rel_path( '' ) );
	}

	public function test_import_map_path_bounds_the_generated_filename() {
		// Long remote names must not produce a path the filesystem rejects.
		$path = ceros_store_import_map_rel_path( 'https://assets.ceros.site/js/' . str_repeat( 'a', 300 ) . '.js' );

		preg_match( '#^import-map/[0-9a-f]{8}-(.+)$#', $path, $m );
		$this->assertNotEmpty( $m, $path );
		$this->assertLessThanOrEqual( 64, strlen( $m[1] ) );
	}

	public function test_import_map_path_keeps_the_end_of_a_long_name() {
		// The cap takes the tail, so the extension survives it.
		$path = ceros_store_import_map_rel_path( 'https://assets.ceros.site/js/' . str_repeat( 'a', 300 ) . '.chunk.js' );

		$this->assertStringEndsWith( '.chunk.js', $path );
	}

	public function names_needing_tidying() {
		// The last two names are over the 64-character cap, positioned so the
		// character the cut would expose sits exactly at it.
		$base   = 'https://assets.ceros.site/js/';
		$prefix = str_repeat( 'a', 10 );
		$suffix = str_repeat( 'b', 63 );

		return [
			'dots only'       => [ $base . '...' ],
			'dashes around'   => [ $base . '-x-' ],
			'leading dot'     => [ $base . '.hidden.' ],
			'dot at the cut'  => [ $base . $prefix . '.' . $suffix ],
			'dash at the cut' => [ $base . $prefix . '-' . $suffix ],
		];
	}

	/**
	 * @dataProvider names_needing_tidying
	 */
	public function test_import_map_path_has_no_leading_or_trailing_dot_or_dash( $url ) {
		preg_match( '#^import-map/[0-9a-f]{8}-(.+)$#', ceros_store_import_map_rel_path( $url ), $m );

		$this->assertNotEmpty( $m, $url );
		$this->assertSame( trim( $m[1], '.-' ), $m[1] );
		$this->assertLessThanOrEqual( 64, strlen( $m[1] ) );
	}

	public function test_two_specifiers_sharing_one_module_are_both_rewritten() {
		$manifest = [
			'importMap' => [
				'imports' => [
					'@ceros/a' => 'https://assets.ceros.site/js/shared.js',
					'@ceros/b' => 'https://assets.ceros.site/js/shared.js',
				],
			],
		];

		$out = ceros_store_rewrite_import_map(
			$manifest,
			'https://site.test/u/v1',
			[ 'https://assets.ceros.site/js/shared.js' => 'import-map/abc12345-shared.js' ]
		);

		$this->assertSame(
			'https://site.test/u/v1/import-map/abc12345-shared.js',
			$out['importMap']['imports']['@ceros/a']
		);
		$this->assertSame(
			'https://site.test/u/v1/import-map/abc12345-shared.js',
			$out['importMap']['imports']['@ceros/b']
		);
	}
}
