import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { CerosModalHeader } from '../../src/ceros/components/modal-header';

describe( 'CerosModalHeader', () => {
	it( 'renders the modal title', () => {
		render( <CerosModalHeader onClose={ () => {} } /> );

		expect(
			screen.getByRole( 'heading', {
				name: 'Browse Published Ceros Content',
			} )
		).toBeInTheDocument();
	} );

	it( 'closes when the close button is used', async () => {
		const onClose = vi.fn();
		render( <CerosModalHeader onClose={ onClose } /> );

		await userEvent.click( screen.getByRole( 'button' ) );

		expect( onClose ).toHaveBeenCalledTimes( 1 );
	} );
} );
