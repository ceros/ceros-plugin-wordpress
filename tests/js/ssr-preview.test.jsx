/**
 * Tests for SsrPreview, and specifically for the warning that says the chosen
 * delivery mode is not the one that will render.
 *
 * When render.php cannot fetch the manifest it falls back to whatever the block
 * can still produce, and that fallback makes the block renderer succeed, so the
 * preview alone cannot tell the two apart. The manifest probe is what
 * distinguishes them, and these cases pin when it runs.
 */
import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import { SsrPreview } from '../../src/ceros/components/ssr-preview';
import { DELIVERY_MODES } from '../../src/ceros/constants';

const MANIFEST_URL =
	'https://example.ceros.site/an-experience/manifest.v1.json';
const WARNING = /server rendering is unavailable/i;
const NOTE = /preview of the/i;

let probes;

/**
 * Route the two endpoints the component calls, failing the manifest probe on
 * demand.
 *
 * @param {Object|null} manifestError Rejection body, or null to resolve.
 */
const handleRequests = ( manifestError = null ) => {
	probes = [];
	apiFetch.setFetchHandler( ( options ) => {
		const path = String( options.path || '' );
		if ( path.startsWith( '/ceros/v1/manifest-meta' ) ) {
			probes.push( path );
			return manifestError
				? Promise.reject( manifestError )
				: Promise.resolve( { publishedAt: '', flexVersion: '' } );
		}
		return Promise.resolve( { rendered: '<div>rendered</div>' } );
	} );
};

const ssrAttributes = ( over = {} ) => ( {
	deliveryMode: DELIVERY_MODES.SSR,
	manifestUrl: MANIFEST_URL,
	...over,
} );

beforeEach( () => handleRequests() );

afterEach( () => {
	apiFetch.setFetchHandler( () => {
		throw new Error( 'unexpected request' );
	} );
} );

describe( 'SsrPreview', () => {
	it( 'warns that the block renders as an iframe when the manifest cannot be read', async () => {
		handleRequests( {
			error: 'HTTP 404',
			error_code: 'ceros_manifest_http',
		} );
		render( <SsrPreview attributes={ ssrAttributes() } postId={ 1 } /> );

		expect( await screen.findByText( WARNING ) ).toBeInTheDocument();
	} );

	it( 'names the reason the manifest could not be read', async () => {
		handleRequests( {
			error: 'HTTP 404',
			error_code: 'ceros_manifest_http',
		} );
		render( <SsrPreview attributes={ ssrAttributes() } postId={ 1 } /> );

		await screen.findByText( WARNING );
		expect( screen.getByText( 'HTTP 404' ) ).toBeInTheDocument();
	} );

	it( 'stays quiet when the manifest reads cleanly', async () => {
		render( <SsrPreview attributes={ ssrAttributes() } postId={ 1 } /> );

		await screen.findByText( NOTE );
		expect( screen.queryByText( WARNING ) ).not.toBeInTheDocument();
		expect( probes ).toHaveLength( 1 );
	} );

	// Store mode renders from the copy on this site, so the live manifest being
	// unreachable says nothing about whether the block will render.
	it( 'does not probe the manifest in Store mode', async () => {
		handleRequests( { error: 'HTTP 404' } );
		render(
			<SsrPreview
				attributes={ ssrAttributes( {
					storedIndexPath: 'ceros-flex/stored/index.json',
				} ) }
				postId={ 1 }
			/>
		);

		await screen.findByText( NOTE );
		expect( screen.queryByText( WARNING ) ).not.toBeInTheDocument();
		expect( probes ).toHaveLength( 0 );
	} );

	it.each( [ DELIVERY_MODES.IFRAME, DELIVERY_MODES.INLINE ] )(
		'does not probe the manifest in the %s delivery mode',
		async ( mode ) => {
			handleRequests( { error: 'HTTP 404' } );
			render(
				<SsrPreview
					attributes={ ssrAttributes( { deliveryMode: mode } ) }
					postId={ 1 }
				/>
			);

			await screen.findByText( NOTE );
			expect( screen.queryByText( WARNING ) ).not.toBeInTheDocument();
			expect( probes ).toHaveLength( 0 );
		}
	);

	it( 'does not probe when the block carries no manifest URL', async () => {
		handleRequests( { error: 'HTTP 404' } );
		render(
			<SsrPreview
				attributes={ ssrAttributes( { manifestUrl: '' } ) }
				postId={ 1 }
			/>
		);

		await screen.findByText( NOTE );
		expect( probes ).toHaveLength( 0 );
	} );
} );
