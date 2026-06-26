import { useEffect, useRef } from '@wordpress/element';
import { DELIVERY_MODES, EMBED_OPTIONS } from '../constants';

/**
 * Preview component for showing the selected experience inside the block.
 *
 * The editor preview renders the iframe embed for the selected size option —
 * the scrollable (fixed-height) variant when "Scrolling" is picked, otherwise
 * the full-height variant — so the in-editor layout matches what publishes.
 * Inline (iframeless) delivery still previews as the iframe: rendering the
 * inline snippet here would inject flex-client.js and a Shadow DOM into the
 * editor DOM. The published frontend always honours the real delivery mode.
 *
 * A ref + effect is used so any <script> tags inside the embed HTML are
 * re-created and executed in the editor (they don't run via innerHTML).
 */
export const CerosPreview = ( {
	currentEmbedCodes,
	deliveryMode = DELIVERY_MODES.IFRAME,
	selectedEmbedOption = EMBED_OPTIONS.FULL,
} ) => {
	const embedHtml =
		( EMBED_OPTIONS.SCROLL === selectedEmbedOption &&
			currentEmbedCodes?.scrollableEmbedCode ) ||
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

	// Inline and SSR both publish without an iframe, but the editor preview is
	// always the isolated iframe — surface a short note explaining that.
	let note = '';
	if ( deliveryMode === DELIVERY_MODES.INLINE ) {
		note = 'Published as Flex Inline (iframeless). Preview shown as an iframe.';
	} else if ( deliveryMode === DELIVERY_MODES.SSR ) {
		note = 'Published as Flex SSR (server-rendered). Preview shown as an iframe.';
	}

	return (
		<div className="ceros-block__preview-section">
			{ note && (
				<p className="ceros-block__preview-note">{ note }</p>
			) }
			<div ref={ containerRef } className="ceros-block__preview" />
		</div>
	);
};
