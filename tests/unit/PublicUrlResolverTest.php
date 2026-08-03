<?php
/**
 * Tests for the URL-shaping helpers in includes/public-url-resolver.php.
 *
 * The snippet builders are absent on purpose: escaping is what makes them
 * correct, so they belong in an integration suite. See tests/README.md.
 *
 * @package ceros
 */

use PHPUnit\Framework\TestCase;

final class PublicUrlResolverTest extends TestCase {

	public function non_publish_urls() {
		return [
			'preview'               => [ 'https://acct.preview.ceros.site/exp/page', 'preview' ],
			'flex editor'           => [ 'https://flex.ceros.com/edit/123', 'flex editor' ],
			'flex editor bare edit' => [ 'https://flex.ceros.com/edit', 'flex editor' ],
			'studio editor'         => [ 'https://admin.ceros.com/account/a1/studio/experience/7', 'studio editor' ],
			// Host and path are both lowercased.
			'uppercase throughout'  => [ 'HTTPS://FLEX.CEROS.COM/EDIT/1', 'flex editor' ],
		];
	}

	/**
	 * @dataProvider non_publish_urls
	 */
	public function test_flags_editor_and_preview_urls( $url, $expected ) {
		$message = ceros_detect_non_publish_url( $url );

		$this->assertNotSame( '', $message );
		$this->assertStringContainsStringIgnoringCase( $expected, $message );
	}

	/**
	 * The near-misses matter: the host label must match exactly and the editor
	 * path marker must be anchored.
	 */
	public function publish_urls() {
		return [
			'published'              => [ 'https://view.ceros.com/acct/exp' ],
			'flex host, other path'  => [ 'https://flex.ceros.com/something' ],
			'flex host, edit inside' => [ 'https://flex.ceros.com/x/edit/1' ],
			'admin host, other path' => [ 'https://admin.ceros.com/account/a1/billing' ],
			'previewish label'       => [ 'https://previews.ceros.site/exp' ],
		];
	}

	/**
	 * @dataProvider publish_urls
	 */
	public function test_allows_published_urls( $url ) {
		$this->assertSame( '', ceros_detect_non_publish_url( $url ) );
	}

	public function experience_urls() {
		return [
			'strips manifest file'    => [ 'https://a.ceros.site/exp/manifest.v1.json', 'https://a.ceros.site/exp' ],
			'strips dotted version'   => [ 'https://a.ceros.site/exp/manifest.v1.2.json', 'https://a.ceros.site/exp' ],
			'strips unversioned man.' => [ 'https://a.ceros.site/exp/manifest.json', 'https://a.ceros.site/exp' ],
			'strips trailing slash'   => [ 'https://a.ceros.site/exp/', 'https://a.ceros.site/exp' ],
			'strips query'            => [ 'https://a.ceros.site/exp?x=1', 'https://a.ceros.site/exp' ],
			'strips fragment'         => [ 'https://a.ceros.site/exp#page', 'https://a.ceros.site/exp' ],
			'keeps port'              => [ 'https://a.ceros.site:8443/exp', 'https://a.ceros.site:8443/exp' ],
			// Userinfo is dropped by the rebuild.
			'drops userinfo'          => [ 'https://user:pass@a.ceros.site/exp', 'https://a.ceros.site/exp' ],
			'bare host'               => [ 'https://a.ceros.site', 'https://a.ceros.site' ],
			'no scheme or host'       => [ 'not-a-url', '' ],
		];
	}

	/**
	 * @dataProvider experience_urls
	 */
	public function test_derives_canonical_experience_url( $url, $expected ) {
		$this->assertSame( $expected, ceros_derive_experience_url( $url ) );
	}

	public function manifest_urls() {
		return [
			'appends filename'    => [ 'https://a.ceros.site/exp', 'https://a.ceros.site/exp/manifest.v1.json' ],
			'already a manifest'  => [ 'https://a.ceros.site/exp/manifest.v1.json', 'https://a.ceros.site/exp/manifest.v1.json' ],
			'manifest with query' => [ 'https://a.ceros.site/exp/manifest.v1.json?v=2', 'https://a.ceros.site/exp/manifest.v1.json' ],
			'manifest with frag'  => [ 'https://a.ceros.site/exp/manifest.v1.json#x', 'https://a.ceros.site/exp/manifest.v1.json' ],
			'not a url'           => [ 'not-a-url', '' ],
		];
	}

	/**
	 * @dataProvider manifest_urls
	 */
	public function test_builds_manifest_url( $url, $expected ) {
		$this->assertSame( $expected, ceros_build_manifest_url( $url ) );
	}

	public function test_building_a_manifest_url_is_idempotent() {
		$once = ceros_build_manifest_url( 'https://a.ceros.site/exp' );

		$this->assertSame( $once, ceros_build_manifest_url( $once ) );
	}

	public function test_first_script_url_returns_first_https_entry() {
		$mode = [
			'scripts' => [
				[ 'url' => 'https://assets.ceros.site/js/a.js' ],
				[ 'url' => 'https://assets.ceros.site/js/b.js' ],
			],
		];

		$this->assertSame( 'https://assets.ceros.site/js/a.js', ceros_first_script_url( $mode ) );
	}

	public function test_first_script_url_skips_non_https_entries() {
		$mode = [
			'scripts' => [
				[ 'url' => 'http://insecure.example/js/a.js' ],
				[ 'noturl' => 'x' ],
				[ 'url' => 'https://assets.ceros.site/js/good.js' ],
			],
		];

		$this->assertSame( 'https://assets.ceros.site/js/good.js', ceros_first_script_url( $mode ) );
	}

	public function empty_script_modes() {
		return [
			'not an array'      => [ 'nope' ],
			'no scripts key'    => [ [ 'other' => [] ] ],
			'scripts not array' => [ [ 'scripts' => 'nope' ] ],
			'only http'         => [ [ 'scripts' => [ [ 'url' => 'http://x.example/a.js' ] ] ] ],
			'url not a string'  => [ [ 'scripts' => [ [ 'url' => 123 ] ] ] ],
			// The https:// check is case-sensitive, so this falls back to the CDN.
			'uppercase scheme'  => [ [ 'scripts' => [ [ 'url' => 'HTTPS://x.ceros.site/a.js' ] ] ] ],
		];
	}

	/**
	 * @dataProvider empty_script_modes
	 */
	public function test_first_script_url_returns_empty_when_nothing_usable( $mode ) {
		$this->assertSame( '', ceros_first_script_url( $mode ) );
	}

	public function test_delivery_script_url_prefers_manifest_entry() {
		$manifest = [
			'deliveryModes' => [
				'inline' => [ 'scripts' => [ [ 'url' => 'https://vanity.ceros.site/js/custom.js' ] ] ],
			],
		];

		$this->assertSame(
			'https://vanity.ceros.site/js/custom.js',
			ceros_flex_delivery_script_url( $manifest, 'inline', 'flex-client.js' )
		);
	}

	public function manifests_without_delivery_script() {
		return [
			'no deliveryModes'   => [ [] ],
			'wrong mode'         => [ [ 'deliveryModes' => [ 'iframe' => [ 'scripts' => [ [ 'url' => 'https://x.ceros.site/a.js' ] ] ] ] ] ],
			'mode without shape' => [ [ 'deliveryModes' => [ 'inline' => [] ] ] ],
		];
	}

	/**
	 * @dataProvider manifests_without_delivery_script
	 */
	public function test_delivery_script_url_falls_back_to_cdn( $manifest ) {
		$this->assertSame(
			CEROS_FLEX_ASSETS_BASE . '/js/flex-client.js',
			ceros_flex_delivery_script_url( $manifest, 'inline', 'flex-client.js' )
		);
	}
}
