/**
 * Server-rendered preview for the SSR delivery mode.
 *
 * The other delivery modes preview an iframe embed of the live experience, but
 * for SSR — especially Store mode — that wouldn't match what actually
 * publishes (Store renders from locally-persisted, URL-rewritten assets). This
 * component asks WordPress to render the block server-side (the same render.php
 * path the front end uses, honouring storedIndexPath/manifestUrl) and drops the
 * result into a same-origin `srcdoc` iframe. That keeps the editor isolated
 * while still running flex-ssr.js so the preview hydrates exactly like the
 * published page.
 *
 * Note: scroll-driven triggers in the experience won't fire here — the preview
 * frame is sized to its content rather than being a scroll container — which is
 * fine for an editor preview; the published page scrolls normally.
 */

import { __ } from '@wordpress/i18n';
import { useState, useEffect, useRef } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

/**
 * @param {Object} props
 * @param {Object} props.attributes Current block attributes.
 * @param {number} props.postId     The post being edited (render context).
 */
export function SsrPreview( { attributes, postId } ) {
	const [ html, setHtml ] = useState( null );
	const [ error, setError ] = useState( '' );
	const iframeRef = useRef( null );

	useEffect( () => {
		let cancelled = false;

		( async () => {
			setError( '' );
			try {
				const path = addQueryArgs(
					'/wp/v2/block-renderer/create-block/ceros',
					{
						context: 'edit',
						attributes,
						post_id: postId || 0,
					}
				);
				const res = await apiFetch( { path } );
				if ( ! cancelled ) {
					setHtml( res?.rendered || '' );
				}
			} catch ( err ) {
				if ( ! cancelled ) {
					setError(
						__(
							'Could not load the server-rendered preview.',
							'ceros'
						)
					);
				}
			}
		} )();

		return () => {
			cancelled = true;
		};
		// Re-render whenever something that changes the server output changes.
	}, [
		attributes.deliveryMode,
		attributes.storedIndexPath,
		attributes.storedAt,
		attributes.manifestUrl,
		attributes.selectedOption,
		postId,
	] );

	// Size the iframe to its (same-origin) content as the experience hydrates.
	useEffect( () => {
		const iframe = iframeRef.current;
		if ( ! iframe || html === null ) {
			return undefined;
		}

		let observer;
		const fit = () => {
			try {
				const doc = iframe.contentDocument;
				if ( doc && doc.documentElement ) {
					iframe.style.height =
						doc.documentElement.scrollHeight + 'px';
				}
			} catch ( e ) {
				// Cross-origin guard; srcdoc is same-origin so this shouldn't fire.
			}
		};

		const onLoad = () => {
			fit();
			try {
				const doc = iframe.contentDocument;
				if ( doc && typeof window.ResizeObserver !== 'undefined' ) {
					observer = new window.ResizeObserver( fit );
					// Observe both <html> and <body>: fit() measures
					// documentElement.scrollHeight, and flex-ssr.js can grow the
					// document without reflowing <body> (its stage is absolutely
					// positioned), so watching <body> alone misses those changes.
					observer.observe( doc.documentElement );
					if ( doc.body ) {
						observer.observe( doc.body );
					}
				}
			} catch ( e ) {}
		};

		iframe.addEventListener( 'load', onLoad );
		// flex-ssr.js hydrates and scales asynchronously, and some of that
		// scaling sets sizes via script without reflowing an observed element —
		// which the ResizeObserver can't see. Two post-load re-measures cover
		// that tail without falling back to continuous polling.
		const timers = [ setTimeout( fit, 600 ), setTimeout( fit, 2000 ) ];

		return () => {
			iframe.removeEventListener( 'load', onLoad );
			timers.forEach( clearTimeout );
			if ( observer ) {
				observer.disconnect();
			}
		};
	}, [ html ] );

	if ( error ) {
		return <p className="ceros-block__preview-note">{ error }</p>;
	}

	if ( html === null ) {
		return (
			<p className="ceros-block__preview-note">
				{ __( 'Loading server-rendered preview…', 'ceros' ) }
			</p>
		);
	}

	// Distinguish a live preview (re-fetched from the published manifest on
	// every republish) from a snapshot rendered off the stored copy.
	const note = attributes.storedIndexPath
		? __( 'Preview of the stored copy.', 'ceros' )
		: __( 'Live preview of the published embed.', 'ceros' );

	const srcDoc =
		'<!DOCTYPE html><html><head><meta charset="utf-8">' +
		'<meta name="viewport" content="width=device-width,initial-scale=1">' +
		'<base target="_blank"></head><body style="margin:0">' +
		html +
		'</body></html>';

	return (
		<div className="ceros-block__preview-section">
			<p className="ceros-block__preview-note">{ note }</p>
			<iframe
				ref={ iframeRef }
				className="ceros-block__ssr-preview-frame"
				srcDoc={ srcDoc }
				title={ __( 'Ceros SSR preview', 'ceros' ) }
			/>
		</div>
	);
}
