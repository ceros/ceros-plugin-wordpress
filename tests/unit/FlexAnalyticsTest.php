<?php
/**
 * Tests for ceros_flex_tracking_enabled(), ceros_flex_analytics_value() and
 * ceros_flex_analytics_attribute() — the Ceros analytics decision in
 * includes/flex-analytics.php. ceros_flex_stamp_analytics() needs
 * WP_HTML_Tag_Processor and ceros_is_preview_render() needs is_preview(), so
 * both are deferred; see tests/README.md.
 *
 * @package ceros
 */

use PHPUnit\Framework\TestCase;

final class FlexAnalyticsTest extends TestCase {

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
}
