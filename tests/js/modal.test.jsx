/**
 * Tests for CerosModal — the portal wrapper that assembles the picker.
 *
 * The portal is the point: the overlay has to escape the block's own DOM to sit
 * above the editor chrome, so these assertions check it lands in document.body
 * rather than in the render container.
 */
import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { CerosModal } from '../../src/ceros/components/modal';
import { DELIVERY_MODES, EMBED_OPTIONS } from '../../src/ceros/constants';

const OVERLAY_CLASS = '.ceros-block__modal-overlay';
const PANEL_CLASS = '.ceros-block__modal';

const state = ( over = {} ) => ( {
	currentAccountError: null,
	folderTreeError: null,
	isLoadingTree: false,
	folderTreeData: [ { resourceId: 'f1', name: 'Marketing', children: [] } ],
	handleNodeClick: () => {},
	expandedNodes: new Set(),
	loadingNodes: new Set(),
	selectedNodeId: 'e1',
	currentEmbedCodes: {
		fullHeightEmbedCode: '<iframe title="full"></iframe>',
		scrollableEmbedCode: '<iframe title="scroll"></iframe>',
	},
	selectedEmbedOption: EMBED_OPTIONS.FULL,
	setSelectedEmbedOption: () => {},
	selectedDeliveryMode: DELIVERY_MODES.IFRAME,
	setSelectedDeliveryMode: () => {},
	includeCustomHtml: true,
	setIncludeCustomHtml: () => {},
	setAttributes: () => {},
	selectedExperienceName: 'Acme Experience',
	...over,
} );

describe( 'CerosModal', () => {
	// The footer receives each prop by name, so these two pin the custom-code
	// pair to the value the reducer is holding.
	it( 'passes the custom-code setting through to the footer', () => {
		render(
			<CerosModal
				isOpen
				onClose={ () => {} }
				state={ state( {
					currentEmbedCodes: {
						fullHeightEmbedCode: '<iframe title="full"></iframe>',
						scrollableEmbedCode: '<iframe title="scroll"></iframe>',
						inlineEmbedCode: '<div data-flex-inline></div>',
					},
					selectedDeliveryMode: DELIVERY_MODES.SSR,
					includeCustomHtml: false,
				} ) }
			/>
		);

		expect(
			screen.getByRole( 'checkbox', {
				name: /include custom body html/i,
			} )
		).not.toBeChecked();
	} );

	it( 'passes the custom-code setter through to the footer', async () => {
		const setIncludeCustomHtml = vi.fn();
		render(
			<CerosModal
				isOpen
				onClose={ () => {} }
				state={ state( {
					currentEmbedCodes: {
						fullHeightEmbedCode: '<iframe title="full"></iframe>',
						scrollableEmbedCode: '<iframe title="scroll"></iframe>',
						inlineEmbedCode: '<div data-flex-inline></div>',
					},
					selectedDeliveryMode: DELIVERY_MODES.SSR,
					includeCustomHtml: true,
					setIncludeCustomHtml,
				} ) }
			/>
		);

		await userEvent.click(
			screen.getByRole( 'checkbox', {
				name: /include custom body html/i,
			} )
		);

		expect( setIncludeCustomHtml ).toHaveBeenCalledWith( false );
	} );

	it( 'renders nothing while closed', () => {
		render(
			<CerosModal
				isOpen={ false }
				onClose={ () => {} }
				state={ state() }
			/>
		);

		expect( document.querySelector( OVERLAY_CLASS ) ).toBeNull();
	} );

	it( 'portals the overlay out of the block and into the document body', () => {
		const { container } = render(
			<CerosModal isOpen onClose={ () => {} } state={ state() } />
		);

		expect( container.querySelector( OVERLAY_CLASS ) ).toBeNull();
		expect( document.body.querySelector( OVERLAY_CLASS ) ).not.toBeNull();
	} );

	it( 'assembles the header, body and footer', () => {
		render( <CerosModal isOpen onClose={ () => {} } state={ state() } /> );

		expect(
			screen.getByRole( 'heading', {
				name: 'Browse Published Ceros Content',
			} )
		).toBeInTheDocument();
		expect( screen.getByText( 'Marketing' ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Add Experience' } )
		).toBeInTheDocument();
	} );

	it( 'closes when the backdrop is clicked', async () => {
		const onClose = vi.fn();
		render( <CerosModal isOpen onClose={ onClose } state={ state() } /> );

		await userEvent.click( document.querySelector( OVERLAY_CLASS ) );

		expect( onClose ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'stays open when the panel itself is clicked', async () => {
		const onClose = vi.fn();
		render( <CerosModal isOpen onClose={ onClose } state={ state() } /> );

		await userEvent.click( document.querySelector( PANEL_CLASS ) );

		expect( onClose ).not.toHaveBeenCalled();
	} );

	it( 'passes the close handler down to the header and footer', async () => {
		const onClose = vi.fn();
		render( <CerosModal isOpen onClose={ onClose } state={ state() } /> );

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Cancel' } )
		);

		expect( onClose ).toHaveBeenCalledTimes( 1 );
	} );
} );
