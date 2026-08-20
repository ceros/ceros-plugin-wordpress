/**
 * Tests for TreeView / TreeNode — the folder and experience picker.
 *
 * The icon assertions check the exact SVG path for each state. That is
 * deliberate: the two icon pickers are being extracted from nested ternaries,
 * and matching on the class alone would not notice a chevron pointing the wrong
 * way, since expanded and collapsed share the `-arrow` class.
 */
import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { TreeView } from '../../src/ceros/components/tree-view';

const ICON = {
	loading: 'M21 12a9 9 0 1 1-6.219-8.56',
	chevronDown: 'm6 9 6 6 6-6',
	chevronRight: 'm9 18 6-6-6-6',
	folder: 'M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z',
};

const folder = ( over = {} ) => ( {
	resourceId: 'f1',
	name: 'Marketing',
	children: [],
	...over,
} );

const experience = ( over = {} ) => ( {
	resourceId: 'e1',
	name: 'Spring Campaign',
	isExperience: true,
	...over,
} );

const renderTree = ( data, opts = {} ) =>
	render(
		<TreeView
			data={ data }
			onNodeClick={ opts.onNodeClick || ( () => {} ) }
			expandedNodes={ opts.expandedNodes || new Set() }
			loadingNodes={ opts.loadingNodes || new Set() }
			selectedNodeId={
				opts.selectedNodeId === undefined ? null : opts.selectedNodeId
			}
		/>
	);

const paths = ( container ) =>
	Array.from( container.querySelectorAll( 'path' ) ).map( ( p ) =>
		p.getAttribute( 'd' )
	);

describe( 'TreeView', () => {
	it.each( [
		[ 'null', null ],
		[ 'undefined', undefined ],
		[ 'a non-array', { nope: true } ],
		[ 'a string', 'nope' ],
	] )( 'falls back to a message when data is %s', ( _label, data ) => {
		renderTree( data );

		expect(
			screen.getByText( 'No tree data available' )
		).toBeInTheDocument();
	} );

	it( 'renders an empty container for an empty array', () => {
		const { container } = renderTree( [] );

		expect(
			screen.queryByText( 'No tree data available' )
		).not.toBeInTheDocument();
		expect(
			container.querySelectorAll( '.ceros-block__item' )
		).toHaveLength( 0 );
	} );

	it( 'renders one row per node, with its name and resource id', () => {
		const { container } = renderTree( [
			folder(),
			experience( { resourceId: 'e2', name: 'Autumn' } ),
		] );

		expect(
			container.querySelectorAll( '.ceros-block__item' )
		).toHaveLength( 2 );
		expect( screen.getByText( 'Marketing' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Autumn' ) ).toBeInTheDocument();
		expect(
			container.querySelector( '[data-resource-id="e2"]' )
		).toBeTruthy();
	} );
} );

describe( 'TreeNode styling', () => {
	it( 'styles a folder as a folder and an experience as a file', () => {
		const { container } = renderTree( [ folder(), experience() ] );

		expect(
			container.querySelectorAll( '.ceros-block__folder' ).length
		).toBeGreaterThan( 0 );
		expect(
			container.querySelectorAll( '.ceros-block__file' ).length
		).toBeGreaterThan( 0 );
	} );

	it( 'marks the selected experience', () => {
		const { container } = renderTree( [ experience() ], {
			selectedNodeId: 'e1',
		} );

		expect(
			container.querySelector( '.ceros-block__item--selected' )
		).toBeTruthy();
	} );

	it( 'compares the selected id as a string', () => {
		const { container } = renderTree(
			[ experience( { resourceId: 42 } ) ],
			{ selectedNodeId: '42' }
		);

		expect(
			container.querySelector( '.ceros-block__item--selected' )
		).toBeTruthy();
	} );

	it( 'never marks a folder as selected, even on an id match', () => {
		const { container } = renderTree( [ folder() ], {
			selectedNodeId: 'f1',
		} );

		expect(
			container.querySelector( '.ceros-block__item--selected' )
		).toBeNull();
	} );

	it( 'marks an empty-message row and never selects it', () => {
		// The shape edit.js builds for a folder that turned out empty.
		const { container } = renderTree(
			[
				folder( {
					resourceId: 'empty-f1',
					name: 'No published experiences found',
					isExperience: false,
					isEmptyMessage: true,
				} ),
			],
			{ selectedNodeId: 'empty-f1' }
		);

		expect(
			container.querySelector( '.ceros-block__item--empty-message' )
		).toBeTruthy();
		expect(
			container.querySelector( '.ceros-block__item--selected' )
		).toBeNull();
	} );
} );

describe( 'TreeNode icons', () => {
	it( 'shows the spinner while a node is loading', () => {
		const { container } = renderTree( [ folder() ], {
			loadingNodes: new Set( [ 'f1' ] ),
		} );

		expect( container.querySelector( '.-loading' ) ).toBeTruthy();
		expect( paths( container ) ).toContain( ICON.loading );
	} );

	it( 'points the chevron down when expanded', () => {
		const { container } = renderTree(
			[ folder( { children: [ experience() ] } ) ],
			{ expandedNodes: new Set( [ 'f1' ] ) }
		);

		expect( paths( container ) ).toContain( ICON.chevronDown );
		expect( paths( container ) ).not.toContain( ICON.chevronRight );
	} );

	it( 'points the chevron right when collapsed', () => {
		const { container } = renderTree( [ folder() ] );

		expect( paths( container ) ).toContain( ICON.chevronRight );
		expect( paths( container ) ).not.toContain( ICON.chevronDown );
	} );

	it( 'prefers the spinner over the chevron', () => {
		const { container } = renderTree( [ folder() ], {
			expandedNodes: new Set( [ 'f1' ] ),
			loadingNodes: new Set( [ 'f1' ] ),
		} );

		expect( paths( container ) ).toContain( ICON.loading );
		expect( paths( container ) ).not.toContain( ICON.chevronDown );
	} );

	it( 'gives a folder the folder icon', () => {
		const { container } = renderTree( [ folder() ] );

		expect( container.querySelector( '.-folder' ) ).toBeTruthy();
		expect( paths( container ) ).toContain( ICON.folder );
	} );

	it( 'gives an experience the file icon and no chevron', () => {
		const { container } = renderTree( [ experience() ] );

		expect( container.querySelector( '.-file' ) ).toBeTruthy();
		expect( paths( container ) ).not.toContain( ICON.chevronRight );
		expect( paths( container ) ).not.toContain( ICON.chevronDown );
	} );

	it( 'gives an experience with children a chevron as well', () => {
		const { container } = renderTree( [
			experience( { children: [ experience( { resourceId: 'e9' } ) ] } ),
		] );

		expect( paths( container ) ).toContain( ICON.chevronRight );
	} );

	it( 'gives an empty-message row the file icon and no chevron', () => {
		const { container } = renderTree( [
			folder( { isEmptyMessage: true } ),
		] );

		expect( container.querySelector( '.-file' ) ).toBeTruthy();
		expect( container.querySelector( '.-folder' ) ).toBeNull();
		expect( paths( container ) ).not.toContain( ICON.chevronRight );
	} );
} );

describe( 'TreeNode interaction', () => {
	it( 'reports the clicked node', async () => {
		const onNodeClick = vi.fn();
		renderTree( [ folder() ], { onNodeClick } );

		await userEvent.click( screen.getByText( 'Marketing' ) );

		expect( onNodeClick ).toHaveBeenCalledTimes( 1 );
		expect( onNodeClick.mock.calls[ 0 ][ 0 ] ).toMatchObject( {
			resourceId: 'f1',
		} );
	} );

	// Note this cannot verify the handler's stopPropagation call: the children
	// container is a sibling of the parent's clickable row, not a descendant, so
	// a child click never passes through the parent handler either way.
	it( 'reports the child, not the ancestor, when a nested row is clicked', async () => {
		const onNodeClick = vi.fn();
		renderTree( [ folder( { children: [ experience() ] } ) ], {
			onNodeClick,
			expandedNodes: new Set( [ 'f1' ] ),
		} );

		await userEvent.click( screen.getByText( 'Spring Campaign' ) );

		expect( onNodeClick ).toHaveBeenCalledTimes( 1 );
		expect( onNodeClick.mock.calls[ 0 ][ 0 ] ).toMatchObject( {
			resourceId: 'e1',
		} );
	} );

	it( 'ignores clicks on an empty-message row', async () => {
		const onNodeClick = vi.fn();
		renderTree( [ folder( { isEmptyMessage: true, name: 'Nothing' } ) ], {
			onNodeClick,
		} );

		await userEvent.click( screen.getByText( 'Nothing' ) );

		expect( onNodeClick ).not.toHaveBeenCalled();
	} );
} );

describe( 'TreeNode children', () => {
	it( 'renders children only when expanded', () => {
		const data = [ folder( { children: [ experience() ] } ) ];

		const collapsed = renderTree( data );
		expect(
			collapsed.queryByText?.( 'Spring Campaign' ) ??
				screen.queryByText( 'Spring Campaign' )
		).toBeNull();
		collapsed.unmount();

		renderTree( data, { expandedNodes: new Set( [ 'f1' ] ) } );
		expect( screen.getByText( 'Spring Campaign' ) ).toBeInTheDocument();
	} );

	it( 'renders nested descendants recursively', () => {
		const data = [
			folder( {
				children: [
					folder( {
						resourceId: 'f2',
						name: 'Q1',
						children: [ experience() ],
					} ),
				],
			} ),
		];

		renderTree( data, { expandedNodes: new Set( [ 'f1', 'f2' ] ) } );

		expect( screen.getByText( 'Q1' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Spring Campaign' ) ).toBeInTheDocument();
	} );
} );
