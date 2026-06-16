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
				const body = iframe.contentDocument?.body;
				if ( body && typeof ResizeObserver !== 'undefined' ) {
					observer = new ResizeObserver( fit );
					observer.observe( body );
				}
			} catch ( e ) {}
		};

		iframe.addEventListener( 'load', onLoad );
		// Late hydration / async scaling can change height after load.
		const timers = [
			setTimeout( fit, 600 ),
			setTimeout( fit, 1500 ),
			setTimeout( fit, 3000 ),
		];

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

	const isStored = Boolean( attributes.storedIndexPath );
	const note = isStored
		? __(
				'Server-rendered preview from the stored copy (matches the published page).',
				'ceros'
		  )
		: __(
				'Server-rendered preview (matches the published page).',
				'ceros'
		  );

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
