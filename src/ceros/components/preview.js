import { useEffect, useRef } from '@wordpress/element';
import { EMBED_OPTIONS } from '../constants';

/**
 * Preview component for showing the selected embed inside the block.
 *
 * Uses a ref + effect so that any <script> tags inside the embed HTML
 * are re-created and executed in the editor (they don't run via innerHTML).
 */
export const CerosPreview = ( { currentEmbedCodes, selectedEmbedOption } ) => {
	if ( ! currentEmbedCodes ) {
		return null;
	}

	const embedHtml =
		selectedEmbedOption === EMBED_OPTIONS.FULL
			? currentEmbedCodes?.fullHeightEmbedCode
			: selectedEmbedOption === EMBED_OPTIONS.SCROLL
				? currentEmbedCodes?.scrollableEmbedCode
				: null;

	if ( ! embedHtml ) {
		return null;
	}

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

	return (
		<div className="ceros-block__preview-section">
			<div
				ref={ containerRef }
				className="ceros-block__preview"
			/>
		</div>
	);
};
