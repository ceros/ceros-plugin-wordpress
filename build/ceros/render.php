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

	$missing_experience_markup = '<div class="ceros-missing-experience" style="font-family: sans-serif;background-color:#000;color:#fff;min-height:700px;display:flex;align-items:center;justify-content:center;text-align:center;padding:2rem;">
			<div>
				<h2 style="font-size:2.5rem;margin:0 0 1rem;font-weight:600;">' . esc_html__( 'Experience not found', 'ceros' ) . '</h2>
				<p style="margin:0;max-width:640px;">' . esc_html__( "The folder / experience can't be found, possibly indicating that it's been deleted in Ceros admin.", 'ceros' ) . '</p>
			</div>
		</div>';

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
