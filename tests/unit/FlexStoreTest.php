<?php
/**
 * Tests for the pure helpers in includes/flex-store.php.
 *
 * ceros_store_safe_rel_path() takes paths from a manifest rewrite map and they
 * are joined onto the uploads directory, so a traversal writes outside it.
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
}
