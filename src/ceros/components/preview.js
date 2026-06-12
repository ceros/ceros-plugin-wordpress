import { useEffect, useRef } from '@wordpress/element';
import { DELIVERY_MODES } from '../constants';

/**
 * Preview component for showing the selected experience inside the block.
 *
 * The editor preview ALWAYS renders the full-height iframe embed (falling back
 * to the scrollable variant), regardless of the chosen delivery mode. Rendering
 * the iframeless Flex Inline snippet here would inject flex-client.js and a
 * Shadow DOM straight into the editor DOM; the iframe keeps the preview isolated
 * and side-effect-free. The published frontend still honours the real delivery
 * mode — only the editor preview is forced to the iframe.
 *
 * A ref + effect is used so any <script> tags inside the embed HTML are
 * re-created and executed in the editor (they don't run via innerHTML).
 */
export const CerosPreview = ( {
	currentEmbedCodes,
	deliveryMode = DELIVERY_MODES.IFRAME,
} ) => {
	const embedHtml =
		currentEmbedCodes?.fullHeightEmbedCode ||
		currentEmbedCodes?.scrollableEmbedCode ||
		null;

	const containerRef = useRef( null );

	useEffect( () => {
		// Guard for non-browser environments (tests, SSR).
		if ( typeof document === 'undefined' ) {
			return;
		}

		const container = containerRef.current;
		if ( ! container ) {
			return;
		}

		container.innerHTML = '';

		if ( ! embedHtml ) {
			return;
		}

		const temp = document.createElement( 'div' );
		temp.innerHTML = embedHtml;

		// Append non-script nodes as-is.
		Array.from( temp.childNodes ).forEach( ( node ) => {
			if ( node.tagName && node.tagName.toLowerCase() === 'script' ) {
				return;
			}
			container.appendChild( node.cloneNode( true ) );
		} );

		// Recreate script tags so they execute.
		const scripts = temp.querySelectorAll( 'script' );
		scripts.forEach( ( script ) => {
			const newScript = document.createElement( 'script' );

			Array.from( script.attributes ).forEach( ( attr ) => {
				newScript.setAttribute( attr.name, attr.value );
			} );

			if ( script.textContent ) {
				newScript.textContent = script.textContent;
			}

			container.appendChild( newScript );
		} );
	}, [ embedHtml ] );

	if ( ! embedHtml ) {
		return null;
	}

	const isInline = deliveryMode === DELIVERY_MODES.INLINE;

	return (
		<div className="ceros-block__preview-section">
			{ isInline && (
				<p className="ceros-block__preview-note">
					Published as Flex Inline (iframeless). Preview shown as an
					iframe.
				</p>
			) }
			<div ref={ containerRef } className="ceros-block__preview" />
		</div>
	);
};
