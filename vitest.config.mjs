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
	test: {
		environment: 'happy-dom',
		include: [ 'tests/js/**/*.test.{js,jsx}' ],
		setupFiles: [ './tests/js/setup.js' ],
		passWithNoTests: false,
	},
} );
