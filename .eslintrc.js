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
};
