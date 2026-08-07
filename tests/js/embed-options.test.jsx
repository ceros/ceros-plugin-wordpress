/**
 * Tests for EmbedOptions. The behaviour worth pinning is the disabling: each
 * radio is only selectable when the matching embed code is present and not
 * blank, so a user cannot pick a variant the API did not return.
 */
import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { EmbedOptions } from '../../src/ceros/components/embed-options';

const both = {
	fullHeightEmbedCode: '<div>full</div>',
	scrollableEmbedCode: '<div>scroll</div>',
};

const radios = () => screen.getAllByRole( 'radio' );

describe( 'EmbedOptions', () => {
	it( 'renders a radio per embed option', () => {
		render(
			<EmbedOptions
				currentEmbedCodes={ both }
				selectedEmbedOption="full"
				setSelectedEmbedOption={ () => {} }
			/>
		);

		expect( radios() ).toHaveLength( 2 );
		expect( screen.getByText( 'Full height' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Scrolling' ) ).toBeInTheDocument();
	} );

	it( 'checks the selected option only', () => {
		render(
			<EmbedOptions
				currentEmbedCodes={ both }
				selectedEmbedOption="scroll"
				setSelectedEmbedOption={ () => {} }
			/>
		);

		const [ full, scroll ] = radios();
		expect( full ).not.toBeChecked();
		expect( scroll ).toBeChecked();
	} );

	it.each( [
		[ 'missing', undefined ],
		[ 'empty', '' ],
		[ 'whitespace only', '   \n' ],
	] )( 'disables an option whose embed code is %s', ( _label, value ) => {
		render(
			<EmbedOptions
				currentEmbedCodes={ { ...both, scrollableEmbedCode: value } }
				selectedEmbedOption="full"
				setSelectedEmbedOption={ () => {} }
			/>
		);

		const [ full, scroll ] = radios();
		expect( full ).toBeEnabled();
		expect( scroll ).toBeDisabled();
	} );

	it( 'disables both when no embed codes are available', () => {
		render(
			<EmbedOptions
				currentEmbedCodes={ undefined }
				selectedEmbedOption="full"
				setSelectedEmbedOption={ () => {} }
			/>
		);

		radios().forEach( ( radio ) => expect( radio ).toBeDisabled() );
	} );

	it( 'reports the chosen option', async () => {
		const setSelected = vi.fn();
		render(
			<EmbedOptions
				currentEmbedCodes={ both }
				selectedEmbedOption="full"
				setSelectedEmbedOption={ setSelected }
			/>
		);

		await userEvent.click( radios()[ 1 ] );

		expect( setSelected ).toHaveBeenCalledWith( 'scroll' );
	} );
} );
