<?php
/**
 * Tests for ceros_get_allowed_embed_html(), the allowlist handed to wp_kses
 * when an embed code is saved.
 *
 * This is pure data, but it is load-bearing data: anything missing here is
 * stripped from the snippet on save, which is how an embed ends up rendering
 * dead. The cases below are the attributes that carry a specific feature, so a
 * tidy-up that drops one fails here rather than in production.
 *
 * How wp_kses interprets the list is a separate question and belongs to the
 * integration suite; see tests/README.md.
 *
 * @package ceros
 */

use PHPUnit\Framework\TestCase;

final class AllowedEmbedHtmlTest extends TestCase {

	/**
	 * The allowlist under test.
	 *
	 * @var array
	 */
	private $allowed;

	protected function setUp(): void {
		$this->allowed = ceros_get_allowed_embed_html();
	}

	public function test_returns_a_tag_keyed_array() {
		$this->assertIsArray( $this->allowed );
		$this->assertNotEmpty( $this->allowed );
	}

	public function embed_tags() {
		return [
			// The iframe embeds, and the script the legacy scroll-proxy needs.
			'iframe' => [ 'iframe' ],
			'script' => [ 'script' ],
			// Flex Inline renders into a div and styles it inline.
			'div'    => [ 'div' ],
			'style'  => [ 'style' ],
		];
	}

	/**
	 * @dataProvider embed_tags
	 */
	public function test_allows_the_tags_the_embeds_are_built_from( $tag ) {
		$this->assertArrayHasKey( $tag, $this->allowed );
	}

	public function load_bearing_attributes() {
		return [
			// Without these the Flex Inline snippet survives as an inert div:
			// view.js has no marker to find and no manifest to fetch.
			'flex inline marker'     => [ 'div', 'data-flex-inline' ],
			'flex manifest url'      => [ 'div', 'data-flex-manifest-url' ],
			// The iframe snippets size themselves from these.
			'aspect ratio'           => [ 'div', 'data-aspectratio' ],
			'mobile aspect ratio'    => [ 'div', 'data-mobile-aspectratio' ],
			// A legacy Studio scroll-proxy embed sets scrolling="no", and its
			// script is configured by data-ceros-origin-domains.
			'iframe scrolling'       => [ 'iframe', 'scrolling' ],
			'iframe src'             => [ 'iframe', 'src' ],
			'script src'             => [ 'script', 'src' ],
			'script data attributes' => [ 'script', 'data-*' ],
			// Blanket data-* on the div, so a new marker attribute needs no
			// change here.
			'div data attributes'    => [ 'div', 'data-*' ],
		];
	}

	/**
	 * @dataProvider load_bearing_attributes
	 */
	public function test_allows_attributes_the_embeds_depend_on( $tag, $attribute ) {
		$this->assertArrayHasKey( $tag, $this->allowed );
		$this->assertArrayHasKey( $attribute, $this->allowed[ $tag ] );
		$this->assertTrue( $this->allowed[ $tag ][ $attribute ] );
	}

	public function test_omits_event_handler_attributes() {
		// wp_kses strips on* regardless, but an allowlist that named one would
		// be a clear mistake worth catching here.
		foreach ( $this->allowed as $tag => $attributes ) {
			foreach ( array_keys( $attributes ) as $attribute ) {
				$this->assertStringStartsNotWith(
					'on',
					$attribute,
					"Tag <$tag> allows the event handler attribute $attribute"
				);
			}
		}
	}

	public function test_allows_no_tag_outside_the_embed_vocabulary() {
		// Pins the list closed: adding a tag is a deliberate decision, and
		// form or object tags in particular have no place in an embed snippet.
		// Compared as a set — the declaration order carries no meaning.
		$expected = [ 'a', 'div', 'iframe', 'img', 'p', 'script', 'span', 'style' ];
		$actual   = array_keys( $this->allowed );
		sort( $actual );

		$this->assertSame(
			$expected,
			$actual,
			'The allowed tag list changed; confirm the addition is intended.'
		);
	}
}
