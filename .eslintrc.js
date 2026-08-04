/**
 * ESLint config for the Ceros block editor code.
 *
 * `recommended` is the WordPress preset bundled with @wordpress/scripts; it
 * includes prettier, which package.json already assumes via `npm run format`.
 */
module.exports = {
	root: true,
	extends: [ 'plugin:@wordpress/eslint-plugin/recommended' ],
	env: {
		browser: true,
	},
	settings: {
		// Flag translator calls that use the wrong text domain, matching phpcs.xml.
		'@wordpress/i18n-text-domain': {
			allowedTextDomain: [ 'ceros' ],
		},
	},
	rules: {
		// The preset requires an explicit htmlFor. Every label here instead wraps
		// its input, which is equally valid implicit association — and inventing
		// ids for radios in components that render repeatedly would risk
		// duplicates. `depth` covers label text nested in a span.
		'jsx-a11y/label-has-associated-control': [
			'error',
			{ assert: 'either', depth: 3 },
		],
		// Diagnostics for a caught error are worth keeping; chatter is not.
		'no-console': [ 'error', { allow: [ 'error', 'warn' ] } ],
	},
};
