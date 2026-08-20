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
} );
