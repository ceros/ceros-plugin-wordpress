/**
 * Tests for PasteUrlPanel, the empty-state surface used when no API key is
 * configured. It is the only way to add an experience without one, and it
 * carries its own copy of the delivery-mode choice and its own setAttributes
 * call, so the values it was showing have to be the values the block ends up
 * with.
 *
 * `@wordpress/api-fetch` is installed rather than stubbed: it is a real request
 * client, not a passthrough, so a stand-in would mean asserting against the
 * stand-in. Its own `setFetchHandler` replaces the final request step, which
 * keeps the package's middleware chain in play and the network out of it.
 */
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';
import { PasteUrlPanel } from '../../src/ceros/components/paste-url-panel';
import {
	ACTION_TYPES,
	DELIVERY_MODES,
	EMBED_OPTIONS,
} from '../../src/ceros/constants';

const URL = 'https://example.ceros.site/an-experience';

const FLEX_CODES = {
	fullHeightEmbedCode: '<div data-ceros-experience="' + URL + '"></div>',
	scrollableEmbedCode: '<div data-ceros-experience="' + URL + '"></div>',
	inlineEmbedCode:
		'<div data-flex-inline data-flex-manifest-url="' +
		URL +
		'/manifest.v1.json"></div>',
};

const RESOLVED = {
	...FLEX_CODES,
	isFlex: true,
	viewUrl: URL,
	manifestUrl: URL + '/manifest.v1.json',
};

const CHECKBOX = { name: /include custom body html/i };

beforeEach( () => {
	apiFetch.setFetchHandler( () => Promise.resolve( RESOLVED ) );
} );

afterEach( () => {
	apiFetch.setFetchHandler( () => {
		throw new Error( 'unexpected request' );
	} );
} );

const renderPanel = ( props = {} ) => {
	const dispatch = vi.fn();
	const setAttributes = vi.fn();
	render(
		<PasteUrlPanel
			dispatch={ dispatch }
			setAttributes={ setAttributes }
			settingsUrl="http://example.test/wp-admin/options-general.php"
			currentEmbedCodes={ FLEX_CODES }
			selectedDeliveryMode={ DELIVERY_MODES.SSR }
			selectedEmbedOption={ EMBED_OPTIONS.FULL }
			apiKeyConfigured={ false }
			{ ...props }
		/>
	);
	return { dispatch, setAttributes };
};

/**
 * Resolve a URL so the delivery-mode options and the Add button render.
 *
 * @param {Object} user A user-event instance.
 */
const load = async ( user ) => {
	await user.type( screen.getByLabelText( /public experience url/i ), URL );
	await user.click(
		screen.getByRole( 'button', { name: /load experience/i } )
	);
	await screen.findByRole( 'button', { name: /add experience/i } );
};

describe( 'PasteUrlPanel', () => {
	it( 'offers the custom-code checkbox once a Flex experience is resolved in SSR mode', async () => {
		const user = userEvent.setup();
		renderPanel();

		expect(
			screen.queryByRole( 'checkbox', CHECKBOX )
		).not.toBeInTheDocument();
		await load( user );

		expect( screen.getByRole( 'checkbox', CHECKBOX ) ).toBeChecked();
	} );

	it.each( [ DELIVERY_MODES.IFRAME, DELIVERY_MODES.INLINE ] )(
		'omits the custom-code checkbox in the %s delivery mode',
		async ( mode ) => {
			const user = userEvent.setup();
			renderPanel( { selectedDeliveryMode: mode } );
			await load( user );

			expect(
				screen.queryByRole( 'checkbox', CHECKBOX )
			).not.toBeInTheDocument();
		}
	);

	it( 'reflects the setting being off', async () => {
		const user = userEvent.setup();
		renderPanel( { includeCustomHtml: false } );
		await load( user );

		expect( screen.getByRole( 'checkbox', CHECKBOX ) ).not.toBeChecked();
	} );

	it( 'reports the checkbox change so the choice survives to the commit', async () => {
		const user = userEvent.setup();
		const { dispatch } = renderPanel();
		await load( user );

		await user.click( screen.getByRole( 'checkbox', CHECKBOX ) );

		expect( dispatch ).toHaveBeenCalledWith( {
			type: ACTION_TYPES.SET_INCLUDE_CUSTOM_HTML,
			payload: false,
		} );
	} );

	it( 'commits the custom-code choice it was showing', async () => {
		const user = userEvent.setup();
		const { setAttributes } = renderPanel( { includeCustomHtml: false } );
		await load( user );

		await user.click(
			screen.getByRole( 'button', { name: /add experience/i } )
		);

		expect( setAttributes ).toHaveBeenCalledWith(
			expect.objectContaining( {
				deliveryMode: DELIVERY_MODES.SSR,
				includeCustomHtml: false,
			} )
		);
	} );

	// block.json defaults the attribute to true, and the panel is reached before
	// any attribute exists, so an untracked value has to commit as on.
	it( 'commits the setting as on when no value is supplied', async () => {
		const user = userEvent.setup();
		const { setAttributes } = renderPanel();
		await load( user );

		await user.click(
			screen.getByRole( 'button', { name: /add experience/i } )
		);

		expect( setAttributes ).toHaveBeenCalledWith(
			expect.objectContaining( { includeCustomHtml: true } )
		);
	} );
} );
