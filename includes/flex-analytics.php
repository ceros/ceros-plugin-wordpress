<?php
/**
 * Ceros analytics tracking for Flex embeds.
 *
 * Every Flex delivery reads one host-markup attribute, `data-ceros-analytics`,
 * but reads its absence differently: the iframe embed (`embed.v1.js`) reports
 * unless the attribute says `disabled`, while Inline (`flex-client.js`) and SSR
 * (`flex-ssr.js`) report only when it says `enabled`. The block always writes
 * the attribute out, so a block's tracking choice never depends on which
 * default its delivery mode happens to have.
 *
 * The attribute only switches reporting on or off. Inline and SSR load the
 * reporting bundle from the URL the manifest names, and only for an account
 * whose Ceros analytics toggle is on; nothing here can make an embed report
 * more than its manifest allows.
 *
 * Editor and post previews never track: an author checking their own page is
 * not an audience.
 *
 * @package ceros
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether a block should report to Ceros analytics.
 *
 * @param array $attributes Block attributes.
 * @param bool  $is_preview Whether this render is a preview (see
 *                          ceros_is_preview_render()).
 * @return bool True to report.
 */
function ceros_flex_tracking_enabled( $attributes, $is_preview ) {
	if ( $is_preview ) {
		return false;
	}

	// Defaults on: WordPress merges the block.json default in before render, so
	// a block saved before the toggle existed arrives as true. Compared against
	// false for the same reason as includeCustomHtml in render.php.
	return false !== ( $attributes['cerosAnalytics'] ?? true );
}

/**
 * The `data-ceros-analytics` value for a tracking choice.
 *
 * @param bool $tracking Whether the embed should report.
 * @return string 'enabled' or 'disabled'.
 */
function ceros_flex_analytics_value( $tracking ) {
	return $tracking ? 'enabled' : 'disabled';
}

/**
 * The `data-ceros-analytics` attribute, with a leading space, for building
 * embed markup. The value is one of two fixed strings, so it needs no escaping.
 *
 * @param bool $tracking Whether the embed should report.
 * @return string E.g. ` data-ceros-analytics="enabled"`.
 */
function ceros_flex_analytics_attribute( $tracking ) {
	return ' data-ceros-analytics="' . ceros_flex_analytics_value( $tracking ) . '"';
}

/**
 * Set `data-ceros-analytics` on every Flex embed container in some markup: the
 * iframe embed's `[data-ceros-experience]` and Inline's `[data-flex-inline]`.
 *
 * For an embed code persisted on the block, which render.php echoes when the
 * manifest can't be fetched. A container that already carries the attribute is
 * overwritten, so the block's choice wins over whatever was saved. Markup with
 * no Flex container (a legacy Studio embed) is returned unchanged.
 *
 * @param string $html     Embed markup.
 * @param bool   $tracking Whether the embed should report.
 * @return string The markup with the attribute set.
 */
function ceros_flex_stamp_analytics( $html, $tracking ) {
	if ( '' === (string) $html || ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
		return $html;
	}

	$processor = new WP_HTML_Tag_Processor( $html );
	while ( $processor->next_tag() ) {
		if ( null !== $processor->get_attribute( 'data-ceros-experience' )
			|| null !== $processor->get_attribute( 'data-flex-inline' ) ) {
			$processor->set_attribute( 'data-ceros-analytics', ceros_flex_analytics_value( $tracking ) );
		}
	}

	return $processor->get_updated_html();
}

/**
 * Remember, for the rest of the request, that the block is rendering for the
 * editor preview. Called with no argument it only reads.
 *
 * @param bool $mark True to mark this request as an editor preview.
 * @return bool Whether it is one.
 */
function ceros_flex_editor_preview_render( $mark = false ) {
	static $is_editor_preview = false;
	if ( $mark ) {
		$is_editor_preview = true;
	}
	return $is_editor_preview;
}

/**
 * Mark a request to the block renderer for this block as an editor preview.
 *
 * The editor previews every Flex block through `/wp/v2/block-renderer` (see
 * src/ceros/components/ssr-preview.js). Matched on that route rather than on
 * REST_REQUEST, which is also true when a headless front end reads a post's
 * rendered content for real visitors.
 *
 * @param mixed           $response The response so far, returned unchanged.
 * @param array           $handler  The route handler.
 * @param WP_REST_Request $request  The request.
 * @return mixed The response, unchanged.
 */
function ceros_flex_flag_editor_preview( $response, $handler, $request ) {
	if ( 0 === strpos( (string) $request->get_route(), '/wp/v2/block-renderer/create-block/ceros' ) ) {
		ceros_flex_editor_preview_render( true );
	}
	return $response;
}
add_filter( 'rest_request_before_callbacks', 'ceros_flex_flag_editor_preview', 10, 3 );

/**
 * Whether the block is rendering for a preview: the editor's in-block preview,
 * or WordPress's post preview (`?preview=true`).
 *
 * @return bool True for a preview.
 */
function ceros_is_preview_render() {
	return ceros_flex_editor_preview_render() || ( function_exists( 'is_preview' ) && is_preview() );
}
