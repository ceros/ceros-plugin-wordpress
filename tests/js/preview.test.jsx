/**
 * Tests for CerosPreview — the in-editor preview of the selected experience.
 *
 * Two things carry the risk here. The embed HTML is chosen by a chain of
 * fallbacks, so a preview can silently show the wrong variant; and the effect
 * rebuilds <script> tags by hand, because scripts assigned through innerHTML
 * never run.
 */
import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { CerosPreview } from '../../src/ceros/components/preview';
import { DELIVERY_MODES, EMBED_OPTIONS } from '../../src/ceros/constants';

const FULL =
	'<iframe title="full" src="https://view.ceros.com/acme/full"></iframe>';
const SCROLL =
	'<iframe title="scroll" src="https://view.ceros.com/acme/scroll"></iframe>';

const PREVIEW_CLASS = '.ceros-block__preview';

const renderPreview = ( props = {} ) =>
	render(
		<CerosPreview
			currentEmbedCodes={ {
				fullHeightEmbedCode: FULL,
				scrollableEmbedCode: SCROLL,
			} }
			{ ...props }
		/>
	);

const previewHtml = ( container ) =>
	container.querySelector( PREVIEW_CLASS ).innerHTML;

describe( 'CerosPreview', () => {
	describe( 'choosing which embed to show', () => {
		it( 'shows the full-height embed by default', () => {
			const { container } = renderPreview();

			expect( previewHtml( container ) ).toContain( 'acme/full' );
		} );

		it( 'shows the scrollable embed when scrolling is selected', () => {
			const { container } = renderPreview( {
				selectedEmbedOption: EMBED_OPTIONS.SCROLL,
			} );

			expect( previewHtml( container ) ).toContain( 'acme/scroll' );
		} );

		it( 'falls back to full-height when scrolling is selected but unavailable', () => {
			const { container } = renderPreview( {
				currentEmbedCodes: { fullHeightEmbedCode: FULL },
				selectedEmbedOption: EMBED_OPTIONS.SCROLL,
			} );

			expect( previewHtml( container ) ).toContain( 'acme/full' );
		} );

		it( 'falls back to the scrollable embed when there is no full-height one', () => {
			const { container } = renderPreview( {
				currentEmbedCodes: { scrollableEmbedCode: SCROLL },
			} );

			expect( previewHtml( container ) ).toContain( 'acme/scroll' );
		} );

		it.each( [
			[ 'no embed codes', null ],
			[ 'an empty embed-codes object', {} ],
			[ 'blank embed codes', { fullHeightEmbedCode: '' } ],
		] )( 'renders nothing given %s', ( _label, currentEmbedCodes ) => {
			const { container } = render(
				<CerosPreview currentEmbedCodes={ currentEmbedCodes } />
			);

			expect( container ).toBeEmptyDOMElement();
		} );
	} );

	describe( 'the delivery-mode note', () => {
		it( 'explains that inline delivery still previews as an iframe', () => {
			renderPreview( { deliveryMode: DELIVERY_MODES.INLINE } );

			expect(
				screen.getByText(
					'Published as Flex Inline (iframeless). Preview shown as an iframe.'
				)
			).toBeInTheDocument();
		} );

		it( 'explains that SSR delivery still previews as an iframe', () => {
			renderPreview( { deliveryMode: DELIVERY_MODES.SSR } );

			expect(
				screen.getByText(
					'Published as Flex SSR (server-rendered). Preview shown as an iframe.'
				)
			).toBeInTheDocument();
		} );

		it( 'is absent for iframe delivery, where the preview is accurate', () => {
			const { container } = renderPreview( {
				deliveryMode: DELIVERY_MODES.IFRAME,
			} );

			expect(
				container.querySelector( '.ceros-block__preview-note' )
			).toBeNull();
		} );
	} );

	describe( 'injecting the embed HTML', () => {
		it( 'rebuilds script tags, which innerHTML would leave inert', () => {
			const { container } = renderPreview( {
				currentEmbedCodes: {
					fullHeightEmbedCode:
						'<div id="target"></div><script async src="https://view.ceros.com/scroll-proxy.js" data-ceros-origin-domains="acme.com"></script>',
				},
			} );

			const script = container.querySelector(
				`${ PREVIEW_CLASS } script`
			);
			expect( script ).not.toBeNull();
			// A script node parsed from innerHTML is flagged already-started and
			// never executes. Attributes surviving the rebuild is what shows the
			// node was recreated rather than cloned across.
			expect( script.getAttribute( 'src' ) ).toBe(
				'https://view.ceros.com/scroll-proxy.js'
			);
			expect( script.getAttribute( 'async' ) ).not.toBeNull();
			expect( script.getAttribute( 'data-ceros-origin-domains' ) ).toBe(
				'acme.com'
			);
		} );

		it( 'keeps the non-script markup alongside the rebuilt script', () => {
			const { container } = renderPreview( {
				currentEmbedCodes: {
					fullHeightEmbedCode:
						'<div id="target"></div><script>window.cerosRan = true;</script>',
				},
			} );

			expect(
				container.querySelector( `${ PREVIEW_CLASS } #target` )
			).not.toBeNull();
		} );

		it( 'replaces the previous embed instead of appending to it', () => {
			const { container, rerender } = renderPreview();

			rerender(
				<CerosPreview
					currentEmbedCodes={ {
						fullHeightEmbedCode: FULL,
						scrollableEmbedCode: SCROLL,
					} }
					selectedEmbedOption={ EMBED_OPTIONS.SCROLL }
				/>
			);

			expect(
				container.querySelectorAll( `${ PREVIEW_CLASS } iframe` )
			).toHaveLength( 1 );
			expect( previewHtml( container ) ).toContain( 'acme/scroll' );
		} );
	} );
} );
