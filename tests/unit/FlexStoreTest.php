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
	 * Paths that must be rejected outright.
	 *
	 * @return array<string, array{string}>
	 */
	public function unsafe_paths() {
		return [
			'parent traversal'      => [ '../etc/passwd' ],
			'traversal mid path'    => [ 'assets/../../etc/passwd' ],
			'traversal after strip' => [ '/../secrets' ],
			'single dot segment'    => [ 'assets/./app.js' ],
			'bare dot'              => [ '.' ],
			'bare double dot'       => [ '..' ],
			'empty segment'         => [ 'assets//app.js' ],
			'trailing slash'        => [ 'assets/app.js/' ],
			'backslash traversal'   => [ 'assets\\..\\app.js' ],
			'lone backslash'        => [ 'assets\\app.js' ],
			'nul byte'              => [ "assets/app\0.js" ],
			'newline'               => [ "assets/app\n.js" ],
			'tab'                   => [ "assets/app\t.js" ],
			'delete char'           => [ "assets/app\x7f.js" ],
			'empty string'          => [ '' ],
			'only a slash'          => [ '/' ],
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
	 * Paths that are legitimate, including the awkward-but-valid filenames the
	 * function deliberately allows.
	 *
	 * @return array<string, array{string, string}>
	 */
	public function safe_paths() {
		return [
			'simple nested'     => [ 'assets/app.js', 'assets/app.js' ],
			'strips leading /'  => [ '/assets/app.js', 'assets/app.js' ],
			'deeply nested'     => [ 'a/b/c/d/e.css', 'a/b/c/d/e.css' ],
			'single file'       => [ 'index.html', 'index.html' ],
			'spaces'            => [ 'my assets/my file.js', 'my assets/my file.js' ],
			'parens and commas' => [ 'assets/file (1),v2.js', 'assets/file (1),v2.js' ],
			'unicode'           => [ 'assets/ünïcodé.js', 'assets/ünïcodé.js' ],
			'dot in filename'   => [ 'assets/app.min.js', 'assets/app.min.js' ],
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

	/**
	 * @return array<string, array{string, string, bool}>
	 */
	public function suffixes() {
		return [
			'matching suffix'   => [ 'foobar', 'bar', true ],
			'whole string'      => [ 'foobar', 'foobar', true ],
			'empty needle'      => [ 'foobar', '', true ],
			'both empty'        => [ '', '', true ],
			'prefix not suffix' => [ 'foobar', 'foo', false ],
			'needle longer'     => [ 'ab', 'abc', false ],
			'empty haystack'    => [ '', 'a', false ],
			'case sensitive'    => [ 'foobar', 'BAR', false ],
			'domain suffix'     => [ 'cdn.ceros.site', '.ceros.site', true ],
			'domain lookalike'  => [ 'evilceros.site', '.ceros.site', false ],
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

	/**
	 * @return array<string, array{array}>
	 */
	public function manifests_without_slug() {
		return [
			'empty manifest'       => [ [] ],
			'no pages'             => [ [ 'experience' => [] ] ],
			'no current page'      => [ [ 'pages' => [ [ 'slug' => 'a' ] ] ] ],
			'current without slug' => [ [ 'pages' => [ [ 'current' => true ] ] ] ],
			'empty page slug'      => [
				[
					'pages' => [
						[
							'current' => true,
							'slug'    => '',
						],
					],
				],
			],
			'pages not arrays'     => [ [ 'pages' => [ 'nope' ] ] ],
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
