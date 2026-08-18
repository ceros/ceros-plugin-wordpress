/**
 * Tests for manifestUrlFromInline(), the editor-side fallback for recovering a
 * manifest URL from a Flex Inline snippet when the server did not supply one.
 *
 * The PHP ceros_manifest_url_from_inline() is the primary extractor: it reads
 * the raw snippet from the Ceros API before wp_kses touches it. This one reads
 * the sanitized snippet, so the two see different input and are not held to the
 * same contract — the PHP accepts either quote style because a raw snippet may
 * carry either, while wp_kses rewrites every attribute it keeps as name="value",
 * so single quotes cannot reach the editor.
 *
 * Where they do have to agree is entity decoding, since wp_kses is what encodes
 * the value in the first place. Both decode.
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
	] )( 'returns an empty string for %s', ( _label, input ) => {
		expect( manifestUrlFromInline( input ) ).toBe( '' );
	} );

	it( 'decodes the ampersands wp_kses encodes, so query parameters survive', () => {
		expect(
			manifestUrlFromInline(
				'<div data-flex-manifest-url="https://a.ceros.site/m.json?v=1&#038;p=2"></div>'
			)
		).toBe( 'https://a.ceros.site/m.json?v=1&p=2' );
	} );

	it( 'decodes a named ampersand entity too', () => {
		expect(
			manifestUrlFromInline(
				'<div data-flex-manifest-url="https://a.ceros.site/m.json?v=1&amp;p=2"></div>'
			)
		).toBe( 'https://a.ceros.site/m.json?v=1&p=2' );
	} );

	it( 'leaves a URL with no entities alone', () => {
		const url = 'https://a.ceros.site/exp/manifest.v1.json?v=1&p=2';

		expect( manifestUrlFromInline( snippet( url ) ) ).toBe( url );
	} );

	// Not a parity gap with the PHP extractor, which takes either quote style:
	// wp_kses rewrites the attributes it keeps as name="value", so a
	// single-quoted value cannot reach the editor in the first place.
	it( 'does not read a single-quoted value', () => {
		expect(
			manifestUrlFromInline(
				"<div data-flex-manifest-url='https://a.ceros.site/m.json'></div>"
			)
		).toBe( '' );
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
