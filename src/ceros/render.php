<?php
/**
 * Server-side rendering for the Ceros block.
 *
 * @package Ceros
 */

/**
 * Render the Ceros block on the frontend.
 *
 * Sanitizes embed codes before output to prevent XSS vulnerabilities.
 *
 * @param array $attributes Block attributes including embed codes.
 * @return string HTML output for the block.
 */
function ceros_render_block( $attributes ) {
	// Determine if any embed codes are available.
	$has_full   = ! empty( $attributes['fullHeightEmbedCode'] );
	$has_scroll = ! empty( $attributes['scrollableEmbedCode'] );

	$delivery_mode = $attributes['deliveryMode'] ?? 'iframe';

	$missing_experience_markup = '<div class="ceros-missing-experience" style="font-family: sans-serif;background-color:#000;color:#fff;min-height:700px;display:flex;align-items:center;justify-content:center;text-align:center;padding:2rem;">
			<div>
				<h2 style="font-size:2.5rem;margin:0 0 1rem;font-weight:600;">' . esc_html__( 'Experience not found', 'ceros' ) . '</h2>
				<p style="margin:0;max-width:640px;">' . esc_html__( "The folder / experience can't be found, possibly indicating that it's been deleted in Ceros admin.", 'ceros' ) . '</p>
			</div>
		</div>';

	// Iframeless (Flex Inline) delivery: regenerate the snippet (the
	// `<div data-flex-inline>` marker + flex-client.js runtime) from the manifest
	// at render time rather than echoing the persisted `inlineEmbedCode`. Hosts
	// that disable `unfiltered_html` (e.g. WordPress.com) strip <script> tags out
	// of stored post content on save, so a persisted snippet loses its runtime;
	// a render-time <script> survives, matching the SSR path. On any failure we
	// fall through to the iframe embed below.
	if ( 'inline' === $delivery_mode && ! empty( $attributes['manifestUrl'] )
		&& function_exists( 'ceros_render_flex_inline' ) ) {
		$inline_html = ceros_render_flex_inline( $attributes['manifestUrl'] );
		if ( '' !== $inline_html ) {
			return $inline_html;
		}
	}

	// Flex SSR (Beta) delivery. On any failure we fall through to the iframe
	// embed below.
	if ( 'ssr' === $delivery_mode ) {
		// Store mode: render fully from the locally-persisted bundle (no CDN).
		if ( ! empty( $attributes['storedIndexPath'] ) && function_exists( 'ceros_render_flex_ssr_stored' ) ) {
			$stored_html = ceros_render_flex_ssr_stored( $attributes['storedIndexPath'] );
			if ( '' !== $stored_html ) {
				return $stored_html;
			}
		}

		// Live mode: fetch the manifest server-side and render inline.
		if ( ! empty( $attributes['manifestUrl'] ) && function_exists( 'ceros_render_flex_ssr' ) ) {
			$ssr_html = ceros_render_flex_ssr( $attributes['manifestUrl'] );
			if ( '' !== $ssr_html ) {
				return $ssr_html;
			}
		}
	}

	// Flex iframe delivery: regenerate the iframe snippet at render time. The
	// stored embed code's <script> is stripped on save by hosts without the
	// `unfiltered_html` capability (e.g. WordPress.com), same as inline/SSR. A
	// Flex block always carries a manifest URL — legacy Studio (scroll-proxy)
	// embeds never do, so those fall through to the stored embed code below.
	// This also serves as the WP.com-safe fallback when inline/SSR above fail.
	if ( ! empty( $attributes['manifestUrl'] ) && function_exists( 'ceros_render_flex_iframe' ) ) {
		$selected    = $attributes['selectedOption'] ?? 'full';
		$height      = ( 'scroll' === $selected ) ? '800px' : 'auto';
		$flex_iframe = ceros_render_flex_iframe( $attributes['manifestUrl'], $height );
		if ( '' !== $flex_iframe ) {
			return $flex_iframe;
		}
	}

	// Legacy Studio (scroll-proxy) delivery: rebuild the embed fresh at render
	// time from the stored experience URL, for the same reason as the Flex paths
	// above — the persisted embed's <script> (and iframe) don't survive on hosts
	// that strip them on save (e.g. WordPress.com). Only Studio blocks carry an
	// experienceUrl without a manifestUrl. SECURITY: re-validate the host is
	// Ceros-owned before we emit a `<script src="https://{host}/scroll-proxy…">`,
	// so a tampered attribute can't inject a third-party script.
	if ( empty( $attributes['manifestUrl'] ) && ! empty( $attributes['experienceUrl'] )
		&& function_exists( 'ceros_build_legacy_embed_codes' )
		&& function_exists( 'ceros_is_ceros_owned_url' )
		&& ceros_is_ceros_owned_url( $attributes['experienceUrl'] ) ) {
		$codes    = ceros_build_legacy_embed_codes( $attributes['experienceUrl'] );
		$selected = $attributes['selectedOption'] ?? 'full';
		$studio   = ( 'scroll' === $selected && ! empty( $codes['scrollableEmbedCode'] ) )
			? $codes['scrollableEmbedCode']
			: $codes['fullHeightEmbedCode'];
		if ( '' !== $studio ) {
			return ceros_sanitize_embed_code( $studio );
		}
	}

	// Show the "experience not found" message when there are no embed codes saved for this block.
	if ( ! $has_full && ! $has_scroll ) {
		return $missing_experience_markup;
	}

	$selected = $attributes['selectedOption'] ?? 'full';

	// Determine which embed code to output based on selection.
	$embed_code = '';
	if ( 'scroll' === $selected && $has_scroll ) {
		$embed_code = $attributes['scrollableEmbedCode'];
	} elseif ( $has_full ) {
		$embed_code = $attributes['fullHeightEmbedCode'];
	}

	// Sanitize the embed code before output to prevent XSS.
	if ( ! empty( $embed_code ) ) {
		return ceros_sanitize_embed_code( $embed_code );
	}

	// Final fallback - should not normally reach here.
	return $missing_experience_markup;
}
