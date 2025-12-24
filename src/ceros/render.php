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
	// Check if API key is configured.
	if ( ! ceros_is_api_configured() ) {
		return '<div class="ceros-block-error" style="background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 0.5rem; padding: 1rem; text-align: center; margin: 1rem 0;">
			<div style="color: #dc2626; font-weight: 600; margin-bottom: 0.5rem;">' . esc_html__( 'Ceros API Key Required', 'ceros' ) . '</div>
			<div style="color: #991b1b;">' . esc_html__( 'The Ceros API key has not been configured. Please contact your site administrator to set up the Ceros integration.', 'ceros' ) . '</div>
		</div>';
	}

	// Determine if any embed codes are available.
	$has_full   = ! empty( $attributes['fullHeightEmbedCode'] );
	$has_scroll = ! empty( $attributes['scrollableEmbedCode'] );

	// Show the "experience not found" message when there are no embed codes saved for this block.
	if ( ! $has_full && ! $has_scroll ) {
		return '<div class="ceros-missing-experience" style="font-family: sans-serif;background-color:#000;color:#fff;min-height:700px;display:flex;align-items:center;justify-content:center;text-align:center;padding:2rem;">
			<div>
				<h2 style="font-size:2.5rem;margin:0 0 1rem;font-weight:600;">' . esc_html__( 'Experience not found', 'ceros' ) . '</h2>
				<p style="margin:0;max-width:640px;">' . esc_html__( "The folder / experience can't be found, possibly indicating that it's been deleted in Ceros admin.", 'ceros' ) . '</p>
			</div>
		</div>';
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
	return '<div class="ceros-missing-experience" style="font-family: sans-serif;background-color:#000;color:#fff;min-height:700px;display:flex;align-items:center;justify-content:center;text-align:center;padding:2rem;">
		<div>
			<h2 style="font-size:2.5rem;margin:0 0 1rem;font-weight:600;">' . esc_html__( 'Experience not found', 'ceros' ) . '</h2>
			<p style="margin:0;max-width:640px;">' . esc_html__( "The folder / experience can't be found, possibly indicating that it's been deleted in Ceros admin.", 'ceros' ) . '</p>
		</div>
	</div>';
}
