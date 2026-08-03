<?php
/**
 * Tests for the URL-shaping helpers in includes/public-url-resolver.php.
 *
 * Covers the deterministic parts of the keyless resolve flow: recognising
 * editor/preview URLs, canonicalising an experience URL, building a manifest
 * URL, and picking a delivery-mode script out of a manifest.
 *
 * The snippet builders (ceros_build_flex_iframe_snippet and friends) are not
 * here on purpose — they depend on esc_url()/esc_attr(), and escaping is what
 * makes them correct, so they belong in an integration suite.
 *
 * @package ceros
 */

use PHPUnit\Framework\TestCase;

final class PublicUrlResolverTest extends TestCase {

	/**
	 * Editor and preview URLs, with the guidance each should produce.
	 *
	 * @return array<string, array{string, string}>
	 */
	public function non_publish_urls() {
		return [
			'flex preview'          => [ 'https://acct.preview.ceros.site/exp/page', 'preview' ],
			'studio preview'        => [ 'https://acct.preview.ceros.com/exp/page/12', 'preview' ],
			'non-prod preview'      => [ 'https://acct.preview.latest.cerosdev.site/exp/p', 'preview' ],
			'flex editor'           => [ 'https://flex.ceros.com/edit/123', 'flex editor' ],
			'flex editor bare edit' => [ 'https://flex.ceros.com/edit', 'flex editor' ],
			'non-prod flex editor'  => [ 'https://latest.dev.flex.cerosdev.com/edit/9', 'flex editor' ],
			'studio editor'         => [ 'https://admin.ceros.com/account/a1/studio/experience/7', 'studio editor' ],
			'non-prod studio'       => [ 'https://latest.admin.cerosdev.com/account/a/studio/experience/1', 'studio editor' ],
		];
	}

	/**
	 * @dataProvider non_publish_urls
	 *
	 * @param string $url      URL under test.
	 * @param string $expected Phrase the guidance should mention.
	 */
	public function test_flags_editor_and_preview_urls( $url, $expected ) {
		$message = ceros_detect_non_publish_url( $url );

		$this->assertNotSame( '', $message );
		$this->assertStringContainsStringIgnoringCase( $expected, $message );
	}

	/**
	 * Published URLs, and hosts that merely look like editor hosts.
	 *
	 * @return array<string, array{string}>
	 */
	public function publish_urls() {
		return [
			'studio published'       => [ 'https://view.ceros.com/acct/exp' ],
			'flex published'         => [ 'https://acct.ceros.site/exp' ],
			// The `flex`/`admin` labels alone are not enough; the path marker matters.
			'flex host, other path'  => [ 'https://flex.ceros.com/something' ],
			'flex host, edit inside' => [ 'https://flex.ceros.com/x/edit/1' ],
			'admin host, other path' => [ 'https://admin.ceros.com/account/a1/billing' ],
			'previewish word'        => [ 'https://previews.ceros.site/exp' ],
		];
	}

	/**
	 * @dataProvider publish_urls
	 *
	 * @param string $url URL under test.
	 */
	public function test_allows_published_urls( $url ) {
		$this->assertSame( '', ceros_detect_non_publish_url( $url ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public function experience_urls() {
		return [
			'strips manifest file'    => [ 'https://a.ceros.site/exp/manifest.v1.json', 'https://a.ceros.site/exp' ],
			'strips versioned man.'   => [ 'https://a.ceros.site/exp/manifest.v2.json', 'https://a.ceros.site/exp' ],
			'strips dotted version'   => [ 'https://a.ceros.site/exp/manifest.v1.2.json', 'https://a.ceros.site/exp' ],
			'strips unversioned man.' => [ 'https://a.ceros.site/exp/manifest.json', 'https://a.ceros.site/exp' ],
			'strips trailing slash'   => [ 'https://a.ceros.site/exp/', 'https://a.ceros.site/exp' ],
			'strips query'            => [ 'https://a.ceros.site/exp?x=1', 'https://a.ceros.site/exp' ],
			'strips fragment'         => [ 'https://a.ceros.site/exp#page', 'https://a.ceros.site/exp' ],
			'keeps port'              => [ 'https://a.ceros.site:8443/exp', 'https://a.ceros.site:8443/exp' ],
			'bare host'               => [ 'https://a.ceros.site', 'https://a.ceros.site' ],
			'no scheme or host'       => [ 'not-a-url', '' ],
			'root relative'           => [ '/exp/manifest.v1.json', '' ],
			'empty'                   => [ '', '' ],
		];
	}

	/**
	 * @dataProvider experience_urls
	 *
	 * @param string $url      URL under test.
	 * @param string $expected Expected canonical experience URL.
	 */
	public function test_derives_canonical_experience_url( $url, $expected ) {
		$this->assertSame( $expected, ceros_derive_experience_url( $url ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public function manifest_urls() {
		return [
			'appends filename'    => [ 'https://a.ceros.site/exp', 'https://a.ceros.site/exp/manifest.v1.json' ],
			'trailing slash'      => [ 'https://a.ceros.site/exp/', 'https://a.ceros.site/exp/manifest.v1.json' ],
			'already a manifest'  => [ 'https://a.ceros.site/exp/manifest.v1.json', 'https://a.ceros.site/exp/manifest.v1.json' ],
			'manifest with query' => [ 'https://a.ceros.site/exp/manifest.v1.json?v=2', 'https://a.ceros.site/exp/manifest.v1.json' ],
			'manifest with frag'  => [ 'https://a.ceros.site/exp/manifest.v1.json#x', 'https://a.ceros.site/exp/manifest.v1.json' ],
			'undelivered url'     => [ 'not-a-url', '' ],
		];
	}

	/**
	 * @dataProvider manifest_urls
	 *
	 * @param string $url      URL under test.
	 * @param string $expected Expected manifest URL.
	 */
	public function test_builds_manifest_url( $url, $expected ) {
		$this->assertSame( $expected, ceros_build_manifest_url( $url ) );
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

	/**
	 * @return array<string, array{mixed}>
	 */
	public function empty_script_modes() {
		return [
			'not an array'      => [ 'nope' ],
			'empty array'       => [ [] ],
			'scripts not array' => [ [ 'scripts' => 'nope' ] ],
			'no scripts key'    => [ [ 'other' => [] ] ],
			'only http'         => [ [ 'scripts' => [ [ 'url' => 'http://x.example/a.js' ] ] ] ],
		];
	}

	/**
	 * @dataProvider empty_script_modes
	 *
	 * @param mixed $mode Delivery-mode value under test.
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

	/**
	 * @return array<string, array{array}>
	 */
	public function manifests_without_delivery_script() {
		return [
			'no deliveryModes'   => [ [] ],
			'wrong mode'         => [ [ 'deliveryModes' => [ 'iframe' => [ 'scripts' => [ [ 'url' => 'https://x.ceros.site/a.js' ] ] ] ] ] ],
			'mode without shape' => [ [ 'deliveryModes' => [ 'inline' => [] ] ] ],
			'deliveryModes junk' => [ [ 'deliveryModes' => 'nope' ] ],
		];
	}

	/**
	 * @dataProvider manifests_without_delivery_script
	 *
	 * @param array $manifest Manifest under test.
	 */
	public function test_delivery_script_url_falls_back_to_cdn( $manifest ) {
		$this->assertSame(
			CEROS_FLEX_ASSETS_BASE . '/js/flex-client.js',
			ceros_flex_delivery_script_url( $manifest, 'inline', 'flex-client.js' )
		);
	}
}
