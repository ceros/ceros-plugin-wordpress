import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { CerosModalFooter } from '../../src/ceros/components/modal-footer';
import { DELIVERY_MODES, EMBED_OPTIONS } from '../../src/ceros/constants';

// The delivery and embed pickers are identified by their option labels rather
// than a role name: each <label> wraps its radio along with a description
// paragraph, so the accessible name carries the whole description too.
const DELIVERY_PICKER = 'Embed (iframe)';
const EMBED_PICKER = 'Full height';

const EMBED_CODES = {
	fullHeightEmbedCode:
		'<iframe src="https://view.ceros.com/acme/full"></iframe>',
	scrollableEmbedCode:
		'<iframe src="https://view.ceros.com/acme/scroll"></iframe>',
};

const FLEX_EMBED_CODES = {
	...EMBED_CODES,
	inlineEmbedCode:
		'<div data-flex-inline data-flex-manifest-url="https://assets.ceros.site/acme/manifest.v1.json"></div>',
	manifestUrl: 'https://assets.ceros.site/acme/manifest.v1.json',
	viewUrl: 'https://view.ceros.com/acme/experience',
};

const renderFooter = ( props = {} ) => {
	const setAttributes = vi.fn();
	const onClose = vi.fn();
	const setSelectedDeliveryMode = vi.fn();

	render(
		<CerosModalFooter
			onClose={ onClose }
			currentEmbedCodes={ EMBED_CODES }
			selectedEmbedOption={ EMBED_OPTIONS.FULL }
			setSelectedEmbedOption={ () => {} }
			selectedDeliveryMode={ DELIVERY_MODES.IFRAME }
			setSelectedDeliveryMode={ setSelectedDeliveryMode }
			selectedNodeId="abc123"
			setAttributes={ setAttributes }
			selectedExperienceName="Acme Experience"
			{ ...props }
		/>
	);

	return { setAttributes, onClose, setSelectedDeliveryMode };
};

describe( 'CerosModalFooter', () => {
	it( 'renders nothing until an experience has embed codes', () => {
		const { container } = render(
			<CerosModalFooter currentEmbedCodes={ null } />
		);

		expect( container ).toBeEmptyDOMElement();
	} );

	describe( 'the delivery-mode picker', () => {
		it( 'is hidden for a non-Flex experience, which has no inline snippet', () => {
			renderFooter( { currentEmbedCodes: EMBED_CODES } );

			expect( screen.queryByText( DELIVERY_PICKER ) ).toBeNull();
		} );

		it( 'is offered once the API returns an inline snippet', () => {
			renderFooter( { currentEmbedCodes: FLEX_EMBED_CODES } );

			expect( screen.getByText( DELIVERY_PICKER ) ).toBeInTheDocument();
		} );

		it( 'treats a blank inline snippet as no inline snippet', () => {
			renderFooter( {
				currentEmbedCodes: { ...EMBED_CODES, inlineEmbedCode: '   ' },
			} );

			expect( screen.queryByText( DELIVERY_PICKER ) ).toBeNull();
		} );
	} );

	describe( 'the iframe sizing picker', () => {
		it( 'is shown in iframe delivery mode', () => {
			renderFooter( {
				currentEmbedCodes: FLEX_EMBED_CODES,
				selectedDeliveryMode: DELIVERY_MODES.IFRAME,
			} );

			expect( screen.getByText( EMBED_PICKER ) ).toBeInTheDocument();
		} );

		it.each( [ DELIVERY_MODES.INLINE, DELIVERY_MODES.SSR ] )(
			'is hidden in %s delivery mode, which has no full-vs-scroll choice',
			( deliveryMode ) => {
				renderFooter( {
					currentEmbedCodes: FLEX_EMBED_CODES,
					selectedDeliveryMode: deliveryMode,
				} );

				expect( screen.queryByText( EMBED_PICKER ) ).toBeNull();
			}
		);

		it( 'stays visible when inline is selected for an experience that cannot do it', () => {
			// The delivery mode is sticky on the block, so a stale 'inline' can
			// arrive with a non-Flex experience. It is forced back to iframe,
			// which means the sizing options still apply.
			renderFooter( {
				currentEmbedCodes: EMBED_CODES,
				selectedDeliveryMode: DELIVERY_MODES.INLINE,
			} );

			expect( screen.getByText( EMBED_PICKER ) ).toBeInTheDocument();
		} );
	} );

	describe( 'adding the experience', () => {
		it( 'is blocked until an experience is selected', () => {
			renderFooter( { selectedNodeId: null } );

			expect(
				screen.getByRole( 'button', { name: 'Add Experience' } )
			).toBeDisabled();
		} );

		it( 'commits the attributes the renderer reads, then closes', async () => {
			const { setAttributes, onClose } = renderFooter( {
				currentEmbedCodes: FLEX_EMBED_CODES,
				selectedDeliveryMode: DELIVERY_MODES.INLINE,
				selectedEmbedOption: EMBED_OPTIONS.SCROLL,
			} );

			await userEvent.click(
				screen.getByRole( 'button', { name: 'Add Experience' } )
			);

			expect( setAttributes ).toHaveBeenCalledWith( {
				fullHeightEmbedCode: FLEX_EMBED_CODES.fullHeightEmbedCode,
				scrollableEmbedCode: FLEX_EMBED_CODES.scrollableEmbedCode,
				selectedOption: EMBED_OPTIONS.SCROLL,
				experienceName: 'Acme Experience',
				experienceResourceId: 'abc123',
				inlineEmbedCode: FLEX_EMBED_CODES.inlineEmbedCode,
				deliveryMode: DELIVERY_MODES.INLINE,
				includeCustomHtml: true,
				manifestUrl: FLEX_EMBED_CODES.manifestUrl,
				experienceUrl: FLEX_EMBED_CODES.viewUrl,
			} );
			expect( onClose ).toHaveBeenCalledTimes( 1 );
		} );

		// The picker is one of the two places the choice can be made, so the
		// value it was showing has to reach the block rather than falling back
		// to the block.json default once the experience is added.
		it( 'commits the custom-code choice made in the picker', async () => {
			const { setAttributes } = renderFooter( {
				currentEmbedCodes: FLEX_EMBED_CODES,
				selectedDeliveryMode: DELIVERY_MODES.SSR,
				includeCustomHtml: false,
			} );

			await userEvent.click(
				screen.getByRole( 'button', { name: 'Add Experience' } )
			);

			expect( setAttributes ).toHaveBeenCalledWith(
				expect.objectContaining( {
					deliveryMode: DELIVERY_MODES.SSR,
					includeCustomHtml: false,
				} )
			);
		} );

		it( 'shows the custom-code checkbox for a Flex experience in SSR mode', () => {
			renderFooter( {
				currentEmbedCodes: FLEX_EMBED_CODES,
				selectedDeliveryMode: DELIVERY_MODES.SSR,
			} );

			expect(
				screen.getByRole( 'checkbox', {
					name: /include custom body html/i,
				} )
			).toBeChecked();
		} );

		it( 'commits iframe delivery for an experience with no inline snippet', async () => {
			const { setAttributes } = renderFooter( {
				currentEmbedCodes: EMBED_CODES,
				selectedDeliveryMode: DELIVERY_MODES.SSR,
			} );

			await userEvent.click(
				screen.getByRole( 'button', { name: 'Add Experience' } )
			);

			expect( setAttributes ).toHaveBeenCalledWith(
				expect.objectContaining( {
					deliveryMode: DELIVERY_MODES.IFRAME,
					inlineEmbedCode: '',
					manifestUrl: '',
					experienceUrl: '',
				} )
			);
		} );

		it( 'scrapes the manifest URL from the snippet when the API omits it', async () => {
			const { manifestUrl, ...withoutManifestUrl } = FLEX_EMBED_CODES;
			const { setAttributes } = renderFooter( {
				currentEmbedCodes: withoutManifestUrl,
			} );

			await userEvent.click(
				screen.getByRole( 'button', { name: 'Add Experience' } )
			);

			expect( setAttributes ).toHaveBeenCalledWith(
				expect.objectContaining( { manifestUrl } )
			);
		} );

		it( 'stringifies a numeric resource id', async () => {
			const { setAttributes } = renderFooter( { selectedNodeId: 12345 } );

			await userEvent.click(
				screen.getByRole( 'button', { name: 'Add Experience' } )
			);

			expect( setAttributes ).toHaveBeenCalledWith(
				expect.objectContaining( { experienceResourceId: '12345' } )
			);
		} );
	} );

	it( 'closes without committing anything when cancelled', async () => {
		const { setAttributes, onClose } = renderFooter();

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Cancel' } )
		);

		expect( onClose ).toHaveBeenCalledTimes( 1 );
		expect( setAttributes ).not.toHaveBeenCalled();
	} );
} );
