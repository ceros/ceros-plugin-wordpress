<?php
/**
 * Tests for the pure helpers in includes/flex-store.php.
 *
 * ceros_store_safe_rel_path() is the important one: paths come from a manifest
 * rewrite map and are joined onto the uploads directory, so a traversal that
 * slips through writes outside the intended tree.
 *
 * @package ceros
 */

use PHPUnit\Framework\TestCase;

final class FlexStoreTest extends TestCase {

	/**
	 * One row per rejection branch.
	 */
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
	 *
	 * @param string $path Path under test.
	 */
	public function test_rejects_unsafe_relative_paths( $path ) {
		$this->assertSame( '', ceros_store_safe_rel_path( $path ) );
	}

	/**
	 * The awkward-but-valid filenames matter here: the function deliberately
	 * allows a permissive alphabet rather than restricting to a safe set.
	 */
	public function safe_paths() {
		return [
			'nested'            => [ 'assets/app.min.js', 'assets/app.min.js' ],
			'strips leading /'  => [ '/assets/app.js', 'assets/app.js' ],
			// ltrim strips every leading slash, so this stays relative rather
			// than escaping to an absolute path.
			'many leading /'    => [ '//etc/passwd', 'etc/passwd' ],
			'spaces'            => [ 'my assets/my file.js', 'my assets/my file.js' ],
			'parens and commas' => [ 'assets/file (1),v2.js', 'assets/file (1),v2.js' ],
			'unicode'           => [ 'assets/ünïcodé.js', 'assets/ünïcodé.js' ],
			'leading dot file'  => [ 'assets/.keep', 'assets/.keep' ],
		];
	}

	/**
	 * @dataProvider safe_paths
	 *
	 * @param string $path     Path under test.
	 * @param string $expected Expected normalised path.
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
	 *
	 * @param string $haystack Haystack.
	 * @param string $needle   Needle.
	 * @param bool   $expected Expected result.
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
	 *
	 * @param array $manifest Manifest under test.
	 */
	public function test_primary_slug_defaults_to_index( $manifest ) {
		$this->assertSame( 'index', ceros_store_primary_slug( $manifest ) );
	}
}
