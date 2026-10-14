<?php
/**
 * Tests for the Ceros analytics decision in includes/flex-analytics.php: whether
 * a block tracks, which blocks are Flex, the attribute it writes, and how the
 * editor preview is recognised. ceros_flex_stamp_analytics() needs
 * WP_HTML_Tag_Processor, and the is_preview() / is_customize_preview() halves of
 * ceros_is_preview_render() need WordPress's query and Customizer, so those are
 * deferred; see tests/README.md. With neither function loaded,
 * ceros_is_preview_render() reads only the editor-preview flag, which is what
 * the flag tests below rely on.
 *
 * @package ceros
 */

use PHPUnit\Framework\TestCase;

final class FlexAnalyticsTest extends TestCase {

	protected function tearDown(): void {
		// The flag is a static that lives for the whole PHP process.
		ceros_flex_editor_preview_render( false );
	}

	/**
	 * A stand-in for WP_REST_Request: the hooks read only its route.
	 *
	 * @param string $route The request's route.
	 * @return object
	 */
	private static function request( $route ) {
		return new class( $route ) {
			/**
			 * The request's route.
			 *
			 * @var string
			 */
			private $route;

			public function __construct( $route ) {
				$this->route = $route;
			}

			public function get_route() {
				return $this->route;
			}
		};
	}

	private static function dispatch_before( $route ) {
		return ceros_flex_flag_editor_preview( 'response', [], self::request( $route ) );
	}

	private static function dispatch_after( $route ) {
		return ceros_flex_unflag_editor_preview( 'response', [], self::request( $route ) );
	}

	public function test_tracks_by_default() {
		$this->assertTrue( ceros_flex_tracking_enabled( [], false ) );
	}

	public function test_tracks_when_the_toggle_is_on() {
		$this->assertTrue( ceros_flex_tracking_enabled( [ 'cerosAnalytics' => true ], false ) );
	}

	public function test_does_not_track_when_the_toggle_is_off() {
		$this->assertFalse( ceros_flex_tracking_enabled( [ 'cerosAnalytics' => false ], false ) );
	}

	/**
	 * @return array<string, array{0: array}>
	 */
	public static function any_toggle_state() {
		return [
			'default' => [ [] ],
			'on'      => [ [ 'cerosAnalytics' => true ] ],
			'off'     => [ [ 'cerosAnalytics' => false ] ],
		];
	}

	/**
	 * @dataProvider any_toggle_state
	 */
	public function test_never_tracks_a_preview( $attributes ) {
		$this->assertFalse( ceros_flex_tracking_enabled( $attributes, true ) );
	}

	public function test_writes_enabled_when_tracking() {
		$this->assertSame( 'enabled', ceros_flex_analytics_value( true ) );
	}

	/**
	 * `disabled` is the one value every delivery reads as off: the iframe embed
	 * reports for anything else, including no attribute at all.
	 */
	public function test_writes_disabled_when_not_tracking() {
		$this->assertSame( 'disabled', ceros_flex_analytics_value( false ) );
	}

	public function test_attribute_is_ready_to_append_to_a_tag() {
		$this->assertSame( ' data-ceros-analytics="enabled"', ceros_flex_analytics_attribute( true ) );
		$this->assertSame( ' data-ceros-analytics="disabled"', ceros_flex_analytics_attribute( false ) );
	}

	/**
	 * @return array<string, array{0: array, 1: bool}>
	 */
	public static function flex_detection_cases() {
		return [
			'manifest url'                => [ [ 'manifestUrl' => 'https://a.ceros.site/x/manifest.v1.json' ], true ],
			'inline snippet, no manifest' => [ [ 'inlineEmbedCode' => '<div data-flex-inline></div>' ], true ],
			'legacy Studio'               => [
				[
					'experienceUrl'       => 'https://view.ceros.com/a/b',
					'fullHeightEmbedCode' => '<iframe></iframe>',
				],
				false,
			],
			'empty manifest and inline'   => [
				[
					'manifestUrl'     => '',
					'inlineEmbedCode' => '',
				],
				false,
			],
			'no attributes'               => [ [], false ],
		];
	}

	/**
	 * Blocks saved before the manifest URL was stored carry only the Inline
	 * snippet; the sidebar offers them the toggle, so render must honour it.
	 *
	 * @dataProvider flex_detection_cases
	 */
	public function test_detects_flex_blocks( $attributes, $expected ) {
		$this->assertSame( $expected, ceros_is_flex_block( $attributes ) );
	}

	public function test_the_block_renderer_for_this_block_is_a_preview() {
		self::dispatch_before( '/wp/v2/block-renderer/create-block/ceros' );
		$this->assertTrue( ceros_is_preview_render() );
	}

	public function test_a_trailing_slash_on_the_route_is_still_this_block() {
		self::dispatch_before( '/wp/v2/block-renderer/create-block/ceros/' );
		$this->assertTrue( ceros_is_preview_render() );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function other_routes() {
		return [
			'another block sharing the prefix' => [ '/wp/v2/block-renderer/create-block/ceros-gallery' ],
			'another block'                    => [ '/wp/v2/block-renderer/core/archives' ],
			'a headless read of a post'        => [ '/wp/v2/posts/42' ],
			'this plugin\'s own routes'        => [ '/ceros/v1/manifest-meta' ],
		];
	}

	/**
	 * @dataProvider other_routes
	 */
	public function test_other_routes_are_not_a_preview( $route ) {
		self::dispatch_before( $route );
		$this->assertFalse( ceros_is_preview_render() );
	}

	/**
	 * A block-renderer request dispatched in-process while building a real page
	 * must not leave tracking off for the blocks rendered after it.
	 */
	public function test_the_preview_ends_with_the_block_renderer_dispatch() {
		self::dispatch_before( '/wp/v2/block-renderer/create-block/ceros' );
		self::dispatch_after( '/wp/v2/block-renderer/create-block/ceros' );
		$this->assertFalse( ceros_is_preview_render() );
	}

	public function test_another_dispatch_ending_does_not_end_the_preview() {
		self::dispatch_before( '/wp/v2/block-renderer/create-block/ceros' );
		self::dispatch_after( '/wp/v2/posts/42' );
		$this->assertTrue( ceros_is_preview_render() );
	}

	public function test_the_hooks_return_the_response_unchanged() {
		$this->assertSame( 'response', self::dispatch_before( '/wp/v2/block-renderer/create-block/ceros' ) );
		$this->assertSame( 'response', self::dispatch_after( '/wp/v2/block-renderer/create-block/ceros' ) );
	}
}
