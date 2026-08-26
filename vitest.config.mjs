import { fileURLToPath } from 'node:url';
import { transformWithEsbuild } from 'vite';
import { defineConfig } from 'vitest/config';

export default defineConfig( {
	// Matches how wp-scripts builds the plugin: the automatic runtime, so no
	// file needs to import React.
	esbuild: { jsx: 'automatic' },
	plugins: [
		{
			// The sources are .js files containing JSX, and Vite picks the loader
			// from the extension. Renaming them to .jsx is the alternative, but
			// src/ceros/index.js is the block entry wp-scripts discovers by name.
			name: 'ceros:jsx-in-js',
			async transform( code, id ) {
				if ( ! /\/src\/.*\.js$/.test( id ) ) {
					return null;
				}
				return transformWithEsbuild( code, id, {
					loader: 'jsx',
					jsx: 'automatic',
				} );
			},
		},
	],
	// The @wordpress/* packages are webpack externals mapped to window.wp.* at
	// build time, so they are not installed. These two are stood in for because
	// they are passthroughs rather than real implementations — see the stub files
	// and tests/README.md. Anything else stays out of scope.
	resolve: {
		alias: {
			'@wordpress/element': fileURLToPath(
				new URL( './tests/js/stubs/wp-element.js', import.meta.url )
			),
			'@wordpress/i18n': fileURLToPath(
				new URL( './tests/js/stubs/wp-i18n.js', import.meta.url )
			),
		},
	},
	test: {
		environment: 'happy-dom',
		// The embed snippets under test carry real iframe and script URLs, and
		// happy-dom would otherwise fetch them — putting the network in the
		// pre-push path and making the suite fail wherever view.ceros.com is
		// unreachable. Nothing here asserts on a loaded resource.
		environmentOptions: {
			happyDOM: {
				settings: {
					disableJavaScriptFileLoading: true,
					disableCSSFileLoading: true,
					handleDisabledFileLoadingAsSuccess: true,
					navigation: {
						disableChildFrameNavigation: true,
						disableChildPageNavigation: true,
					},
				},
			},
		},
		include: [ 'tests/js/**/*.test.{js,jsx}' ],
		setupFiles: [ './tests/js/setup.js' ],
		passWithNoTests: false,
	},
} );
