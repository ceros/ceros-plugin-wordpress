/**
 * Tests for CerosModalBody — the modal's error, loading and tree states.
 *
 * The three states are independent conditions rather than a chain, so the
 * combinations matter: an error does not stop the tree rendering, and a
 * finished load with data is the only case that shows the tree.
 */
import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { CerosModalBody } from '../../src/ceros/components/modal-body';

// The spinner shares its path with TreeView's per-node spinner; scoping the
// query to the loading wrapper is what distinguishes them.
const LOADING_CLASS = '.ceros-block__loading';

const TREE = [ { resourceId: 'f1', name: 'Marketing', children: [] } ];

const renderBody = ( props = {} ) =>
	render(
		<CerosModalBody
			currentAccountError={ null }
			folderTreeError={ null }
			isLoadingTree={ false }
			folderTreeData={ null }
			handleNodeClick={ () => {} }
			expandedNodes={ new Set() }
			loadingNodes={ new Set() }
			selectedNodeId={ null }
			{ ...props }
		/>
	);

describe( 'CerosModalBody', () => {
	it( 'shows the account error', () => {
		renderBody( { currentAccountError: 'Invalid API key' } );

		expect( screen.getByText( 'Invalid API key' ) ).toBeInTheDocument();
	} );

	it( 'shows the folder tree error', () => {
		renderBody( { folderTreeError: 'Could not load folders' } );

		expect(
			screen.getByText( 'Could not load folders' )
		).toBeInTheDocument();
	} );

	it( 'shows both errors at once, since they come from separate requests', () => {
		renderBody( {
			currentAccountError: 'Invalid API key',
			folderTreeError: 'Could not load folders',
		} );

		expect( screen.getByText( 'Invalid API key' ) ).toBeInTheDocument();
		expect(
			screen.getByText( 'Could not load folders' )
		).toBeInTheDocument();
	} );

	it( 'spins while the tree is loading', () => {
		const { container } = renderBody( { isLoadingTree: true } );

		expect( container.querySelector( LOADING_CLASS ) ).not.toBeNull();
	} );

	it( 'holds back stale tree data while a reload is in flight', () => {
		const { container } = renderBody( {
			isLoadingTree: true,
			folderTreeData: TREE,
		} );

		expect( container.querySelector( LOADING_CLASS ) ).not.toBeNull();
		expect( screen.queryByText( 'Marketing' ) ).toBeNull();
	} );

	it( 'renders the tree once loading finishes', () => {
		const { container } = renderBody( { folderTreeData: TREE } );

		expect( container.querySelector( LOADING_CLASS ) ).toBeNull();
		expect( screen.getByText( 'Marketing' ) ).toBeInTheDocument();
	} );

	it( 'renders no tree and no spinner before a load has started', () => {
		const { container } = renderBody();

		expect( container.querySelector( LOADING_CLASS ) ).toBeNull();
		// TreeView renders this fallback for absent data, so its absence is
		// what proves TreeView was not rendered at all.
		expect( screen.queryByText( 'No tree data available' ) ).toBeNull();
	} );
} );
