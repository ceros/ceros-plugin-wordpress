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
 * Previews never track: the editor's, WordPress's post preview and the
 * Customizer's. An author checking their own page is not an audience.
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
 * Whether a block embeds a Flex experience. Flex blocks carry a manifest URL,
 * except those saved before it was stored, which carry only the Inline snippet.
 * Legacy Studio blocks carry neither. Mirrors isFlex in sidebar-controls.js.
 *
 * @param array $attributes Block attributes.
 * @return bool True for a Flex block.
 */
function ceros_is_flex_block( $attributes ) {
	return ! empty( $attributes['manifestUrl'] ) || ! empty( $attributes['inlineEmbedCode'] );
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
	if ( '' === (string) $html ) {
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
 * Whether the block renderer is rendering this block for the editor preview.
 * Called with no argument it only reads; with a bool it sets the flag.
 *
 * @param bool|null $set True while an editor preview renders, false after.
 * @return bool Whether one is rendering.
 */
function ceros_flex_editor_preview_render( $set = null ) {
	static $is_editor_preview = false;
	if ( null !== $set ) {
		$is_editor_preview = (bool) $set;
	}
	return $is_editor_preview;
}

/**
 * Whether a REST request is the block renderer rendering this block.
 *
 * @param WP_REST_Request $request The request.
 * @return bool True for this block's editor preview.
 */
function ceros_flex_is_editor_preview_request( $request ) {
	return '/wp/v2/block-renderer/create-block/ceros' === rtrim( (string) $request->get_route(), '/' );
}

/**
 * Mark the block renderer's dispatch for this block as an editor preview.
 *
 * The editor previews every Flex block through `/wp/v2/block-renderer` (see
 * src/ceros/components/ssr-preview.js). Matched on that route rather than on
 * REST_REQUEST, which is also true when a headless front end reads a post's
 * rendered content for real visitors. The flag is cleared again when the
 * dispatch ends (ceros_flex_unflag_editor_preview()), so a block-renderer
 * request dispatched in-process while building a real page cannot turn
 * tracking off for the blocks rendered after it.
 *
 * @param mixed           $response The response so far, returned unchanged.
 * @param array           $handler  The route handler.
 * @param WP_REST_Request $request  The request.
 * @return mixed The response, unchanged.
 */
function ceros_flex_flag_editor_preview( $response, $handler, $request ) {
	if ( ceros_flex_is_editor_preview_request( $request ) ) {
		ceros_flex_editor_preview_render( true );
	}
	return $response;
}
add_filter( 'rest_request_before_callbacks', 'ceros_flex_flag_editor_preview', 10, 3 );

/**
 * Clear the editor-preview flag once the block renderer's dispatch ends.
 *
 * @param mixed           $response The response, returned unchanged.
 * @param array           $handler  The route handler.
 * @param WP_REST_Request $request  The request.
 * @return mixed The response, unchanged.
 */
function ceros_flex_unflag_editor_preview( $response, $handler, $request ) {
	if ( ceros_flex_is_editor_preview_request( $request ) ) {
		ceros_flex_editor_preview_render( false );
	}
	return $response;
}
add_filter( 'rest_request_after_callbacks', 'ceros_flex_unflag_editor_preview', 10, 3 );

/**
 * Whether the block is rendering for a preview: the editor's in-block preview,
 * WordPress's post preview (`?preview=true`), or the Customizer's.
 *
 * @return bool True for a preview.
 */
function ceros_is_preview_render() {
	return ceros_flex_editor_preview_render()
		|| ( function_exists( 'is_preview' ) && is_preview() )
		|| ( function_exists( 'is_customize_preview' ) && is_customize_preview() );
}
