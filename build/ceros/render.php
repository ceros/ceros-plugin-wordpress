<?php
/**
 * Server-side rendering for the Ceros block.
 *
 * @param array $attributes Block attributes.
 * @return string HTML to render on the front-end.
 */
if ( ! function_exists( 'render_create_block_ceros' ) ) {
function render_create_block_ceros( $attributes ) {
    // Debug: log what we received
    error_log( 'Ceros block render called with attributes: ' . print_r( $attributes, true ) );
    
    // Check if API key is configured
    if ( ! ceros_is_api_configured() ) {
        // Show error message on frontend if API key is not set
        return '<div class="ceros-block-error" style="background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 0.5rem; padding: 1rem; text-align: center; margin: 1rem 0;">
            <div style="color: #dc2626; font-weight: 600; margin-bottom: 0.5rem;">Ceros API Key Required</div>
            <div style="color: #991b1b;">The Ceros API key has not been configured. Please contact your site administrator to set up the Ceros integration.</div>
        </div>';
    }
    
    $selected = isset( $attributes['selectedOption'] ) ? $attributes['selectedOption'] : 'full';
    error_log( 'Selected option: ' . $selected );

    $host   = preg_replace( '/^https?:\/\//', '', home_url() );

    $replace_placeholders = function ( $code ) use ( $host ) {
        if ( empty( $code ) ) {
            return '';
        }
        // Replace any "https://undefined" occurrences with the current site host.
        // $code = str_replace( 'https://undefined', 'https://' . $host, $code );
        // Replace origin domain placeholder as well.
        // $code = str_replace( 'data-ceros-origin-domains="undefined"', 'data-ceros-origin-domains="' . esc_attr( $host ) . '"', $code );
        return $code;
    };

    if ( 'scroll' === $selected && ! empty( $attributes['scrollableEmbedCode'] ) ) {
        $output = $replace_placeholders( $attributes['scrollableEmbedCode'] );
        error_log( 'Returning scrollable embed code: ' . substr( $output, 0, 200 ) . '...' );
        return $output;
    }

    if ( ! empty( $attributes['fullHeightEmbedCode'] ) ) {
        $output = $replace_placeholders( $attributes['fullHeightEmbedCode'] );
        error_log( 'Returning full height embed code: ' . substr( $output, 0, 200 ) . '...' );
        return $output;
    }

    error_log( 'No embed code found, returning empty string' );
    return '';
}


}
