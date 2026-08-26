/**
 * Stand-in for `@wordpress/i18n`, a webpack external mapped to
 * `window.wp.i18n` at build time and so not installed.
 *
 * Passthrough, which is what the real package returns with no translations
 * loaded — the same reasoning as the `__()` shim in `tests/bootstrap.php`.
 *
 * `sprintf` is deliberately absent. It is a real implementation, not a
 * passthrough, so stubbing it would mean asserting against the stub. A test
 * that needs it fails with a missing-export error, which is the signal to
 * install the package instead.
 */

/**
 * @param {string} text Text to translate.
 * @return {string} The text, untranslated.
 */
export const __ = ( text ) => text;

/**
 * @param {string} text Text to translate.
 * @return {string} The text, untranslated.
 */
export const _x = ( text ) => text;

/**
 * @param {string} single Singular form.
 * @param {string} plural Plural form.
 * @param {number} number Number to decide the form by.
 * @return {string} The chosen form, untranslated.
 */
export const _n = ( single, plural, number ) =>
	1 === number ? single : plural;
