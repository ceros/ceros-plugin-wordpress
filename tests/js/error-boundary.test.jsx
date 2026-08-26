/**
 * Tests for ErrorBoundary — the guard that keeps a block failure from taking
 * down the editor.
 *
 * React writes its own report to console.error whenever a boundary catches, on
 * top of the component's deliberate log, so console.error is stubbed
 * throughout. The assertions look for the component's own message rather than a
 * call count, which would be pinning React's internals.
 */
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ErrorBoundary } from '../../src/ceros/components/error-boundary';

const Boom = ( { throws = true } ) => {
	if ( throws ) {
		throw new Error( 'kaboom' );
	}
	return <p>recovered</p>;
};

let consoleError;

beforeEach( () => {
	consoleError = vi.spyOn( console, 'error' ).mockImplementation( () => {} );
} );

afterEach( () => {
	consoleError.mockRestore();
} );

describe( 'ErrorBoundary', () => {
	it( 'renders its children while nothing throws', () => {
		render(
			<ErrorBoundary>
				<p>the block</p>
			</ErrorBoundary>
		);

		expect( screen.getByText( 'the block' ) ).toBeInTheDocument();
	} );

	it( 'swaps in the fallback when a child throws', () => {
		render(
			<ErrorBoundary>
				<Boom />
			</ErrorBoundary>
		);

		expect(
			screen.getByRole( 'heading', { name: 'Something went wrong' } )
		).toBeInTheDocument();
		expect( screen.queryByText( 'recovered' ) ).toBeNull();
	} );

	it( 'logs the caught error for debugging', () => {
		render(
			<ErrorBoundary>
				<Boom />
			</ErrorBoundary>
		);

		expect( consoleError ).toHaveBeenCalledWith(
			'Ceros Block Error:',
			expect.objectContaining( { message: 'kaboom' } ),
			expect.anything()
		);
	} );

	it( 'retries the children when Try Again is used', async () => {
		// The boundary only clears its own state; whether the retry succeeds is
		// up to the child, so the child is re-rendered in a passing state.
		const { rerender } = render(
			<ErrorBoundary>
				<Boom />
			</ErrorBoundary>
		);

		expect(
			screen.getByRole( 'heading', { name: 'Something went wrong' } )
		).toBeInTheDocument();

		rerender(
			<ErrorBoundary>
				<Boom throws={ false } />
			</ErrorBoundary>
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Try Again' } )
		);

		expect( screen.getByText( 'recovered' ) ).toBeInTheDocument();
		expect( screen.queryByRole( 'heading' ) ).toBeNull();
	} );

	it( 'falls back again if the retry throws once more', async () => {
		render(
			<ErrorBoundary>
				<Boom />
			</ErrorBoundary>
		);

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Try Again' } )
		);

		expect(
			screen.getByRole( 'heading', { name: 'Something went wrong' } )
		).toBeInTheDocument();
	} );
} );
