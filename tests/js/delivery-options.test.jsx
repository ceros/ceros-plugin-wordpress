/**
 * Tests for DeliveryOptions. The values sent back are the DELIVERY_MODES
 * constants, which the PHP renderer reads off the block, so they are asserted
 * against the constants rather than against literal strings.
 */
import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { DeliveryOptions } from '../../src/ceros/components/delivery-options';
import { DELIVERY_MODES } from '../../src/ceros/constants';

const setup = ( selectedDeliveryMode = DELIVERY_MODES.IFRAME ) => {
	const setSelected = vi.fn();
	render(
		<DeliveryOptions
			selectedDeliveryMode={ selectedDeliveryMode }
			setSelectedDeliveryMode={ setSelected }
		/>
	);
	return setSelected;
};

describe( 'DeliveryOptions', () => {
	it( 'offers one radio per delivery mode', () => {
		setup();

		const radios = screen.getAllByRole( 'radio' );
		expect( radios ).toHaveLength( 3 );
		expect( radios.map( ( r ) => r.value ) ).toEqual( [
			DELIVERY_MODES.IFRAME,
			DELIVERY_MODES.INLINE,
			DELIVERY_MODES.SSR,
		] );
	} );

	it.each( [
		[ DELIVERY_MODES.IFRAME, 0 ],
		[ DELIVERY_MODES.INLINE, 1 ],
		[ DELIVERY_MODES.SSR, 2 ],
	] )( 'checks only the %s radio', ( mode, index ) => {
		setup( mode );

		screen.getAllByRole( 'radio' ).forEach( ( radio, i ) => {
			if ( i === index ) {
				expect( radio ).toBeChecked();
			} else {
				expect( radio ).not.toBeChecked();
			}
		} );
	} );

	it.each( [
		[ 'inline', 1, DELIVERY_MODES.INLINE ],
		[ 'ssr', 2, DELIVERY_MODES.SSR ],
	] )( 'reports %s when chosen', async ( _label, index, expected ) => {
		const setSelected = setup();

		await userEvent.click( screen.getAllByRole( 'radio' )[ index ] );

		expect( setSelected ).toHaveBeenCalledWith( expected );
	} );

	it( 'labels the two beta modes as beta', () => {
		setup();

		expect(
			screen.getByText( /Inline — iframeless \(Beta\)/ )
		).toBeInTheDocument();
		expect(
			screen.getByText( /SSR — server-rendered \(Beta\)/ )
		).toBeInTheDocument();
	} );

	// The checkbox lives here, alongside the radio it belongs to, because both
	// surfaces that render this picker commit the choice themselves. It is
	// scoped to SSR because that is the only delivery mode whose output carries
	// the experience's custom body HTML.
	describe( 'custom-code checkbox', () => {
		const CHECKBOX = { name: /include custom body html/i };

		const setupCheckbox = ( props = {} ) => {
			const setIncludeCustomHtml = vi.fn();
			render(
				<DeliveryOptions
					selectedDeliveryMode={ DELIVERY_MODES.SSR }
					setSelectedDeliveryMode={ vi.fn() }
					setIncludeCustomHtml={ setIncludeCustomHtml }
					{ ...props }
				/>
			);
			return setIncludeCustomHtml;
		};

		it.each( [ DELIVERY_MODES.IFRAME, DELIVERY_MODES.INLINE ] )(
			'is absent for the %s delivery mode',
			( mode ) => {
				setupCheckbox( { selectedDeliveryMode: mode } );

				expect(
					screen.queryByRole( 'checkbox', CHECKBOX )
				).not.toBeInTheDocument();
			}
		);

		// block.json defaults the attribute to true, so a caller that has not
		// tracked the value yet must not render it as though the author had
		// switched it off.
		it( 'defaults to on', () => {
			setupCheckbox();

			expect( screen.getByRole( 'checkbox', CHECKBOX ) ).toBeChecked();
		} );

		it( 'reflects the setting being off', () => {
			setupCheckbox( { includeCustomHtml: false } );

			expect(
				screen.getByRole( 'checkbox', CHECKBOX )
			).not.toBeChecked();
		} );

		it( 'reports false when switched off', async () => {
			const setIncludeCustomHtml = setupCheckbox( {
				includeCustomHtml: true,
			} );

			await userEvent.click( screen.getByRole( 'checkbox', CHECKBOX ) );

			expect( setIncludeCustomHtml ).toHaveBeenCalledWith( false );
		} );

		it( 'reports true when switched back on', async () => {
			const setIncludeCustomHtml = setupCheckbox( {
				includeCustomHtml: false,
			} );

			await userEvent.click( screen.getByRole( 'checkbox', CHECKBOX ) );

			expect( setIncludeCustomHtml ).toHaveBeenCalledWith( true );
		} );

		it( 'does not change the delivery mode', async () => {
			const setSelected = vi.fn();
			const setIncludeCustomHtml = vi.fn();
			render(
				<DeliveryOptions
					selectedDeliveryMode={ DELIVERY_MODES.SSR }
					setSelectedDeliveryMode={ setSelected }
					includeCustomHtml={ true }
					setIncludeCustomHtml={ setIncludeCustomHtml }
				/>
			);

			await userEvent.click( screen.getByRole( 'checkbox', CHECKBOX ) );

			expect( setIncludeCustomHtml ).toHaveBeenCalledTimes( 1 );
			expect( setSelected ).not.toHaveBeenCalled();
		} );
	} );
} );
