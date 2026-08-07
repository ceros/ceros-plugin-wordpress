/**
 * Tests for manifestUrlFromInline(), the JS counterpart of the PHP
 * ceros_manifest_url_from_inline(). Both read the manifest URL back out of a
 * stored Flex Inline snippet, so they need to agree.
 */
import { describe, it, expect } from 'vitest';
import {
	manifestUrlFromInline,
	EMBED_OPTIONS,
	DELIVERY_MODES,
} from '../../src/ceros/constants';

const snippet = ( url ) =>
	`<div data-flex-inline data-flex-manifest-url="${ url }"></div>\n<script src="https://assets.ceros.site/js/flex-client.js"></script>`;

describe( 'manifestUrlFromInline', () => {
	it( 'reads the manifest URL out of a real inline snippet', () => {
		const url = 'https://a.ceros.site/exp/manifest.v1.json';

		expect( manifestUrlFromInline( snippet( url ) ) ).toBe( url );
	} );

	it( 'matches the attribute case-insensitively', () => {
		expect(
			manifestUrlFromInline(
				'<div DATA-FLEX-MANIFEST-URL="https://a.ceros.site/m.json"></div>'
			)
		).toBe( 'https://a.ceros.site/m.json' );
	} );

	it( 'takes the first attribute when a snippet carries two', () => {
		expect(
			manifestUrlFromInline(
				'<div data-flex-manifest-url="https://first.ceros.site/m.json"></div>' +
					'<div data-flex-manifest-url="https://second.ceros.site/m.json"></div>'
			)
		).toBe( 'https://first.ceros.site/m.json' );
	} );

	it.each( [
		[ 'empty string', '' ],
		[ 'null', null ],
		[ 'undefined', undefined ],
		[ 'a number', 123 ],
		[ 'an object', {} ],
		[ 'a snippet without the attribute', '<div data-flex-inline></div>' ],
		[ 'an empty attribute value', '<div data-flex-manifest-url=""></div>' ],
		// Single quotes are not what the plugin writes, so they are not accepted.
		[
			'single-quoted value',
			"<div data-flex-manifest-url='https://a.ceros.site/m.json'></div>",
		],
	] )( 'returns an empty string for %s', ( _label, input ) => {
		expect( manifestUrlFromInline( input ) ).toBe( '' );
	} );
} );

describe( 'shared constant values', () => {
	// These strings are persisted on the block and read by the PHP renderer, so
	// changing one is a data migration, not a rename.
	it( 'pins the embed option values', () => {
		expect( EMBED_OPTIONS ).toEqual( { FULL: 'full', SCROLL: 'scroll' } );
	} );

	it( 'pins the delivery mode values', () => {
		expect( DELIVERY_MODES ).toEqual( {
			IFRAME: 'iframe',
			INLINE: 'inline',
			SSR: 'ssr',
		} );
	} );
} );
