/**
 * Embed option values (iframe embed sizing).
 */
export const EMBED_OPTIONS = {
	FULL: 'full',
	SCROLL: 'scroll',
};

/**
 * Delivery mode values.
 *
 * - IFRAME: the classic iframe embed (works for every experience).
 * - INLINE: Flex Inline — iframeless, Shadow DOM (Flex experiences only, Beta).
 * - SSR: Flex SSR — the WP server fetches the manifest and renders the
 *   experience HTML inline; the browser only loads a hydration script
 *   (Flex experiences only, Beta).
 */
export const DELIVERY_MODES = {
	IFRAME: 'iframe',
	INLINE: 'inline',
	SSR: 'ssr',
};

/**
 * Undo the entity encoding wp_kses applies to attribute values, which rewrites a
 * bare `&` as `&#038;`. Parsing in a detached textarea keeps the content as raw
 * text — no markup in it is ever built or run.
 *
 * @param {string} value The encoded attribute value.
 * @return {string} The decoded value.
 */
function decodeEntities( value ) {
	const textarea = document.createElement( 'textarea' );
	textarea.innerHTML = value;
	return textarea.value;
}

/**
 * Extract the manifest URL from a Flex Inline snippet's
 * `data-flex-manifest-url` attribute. Returns '' when not present.
 *
 * The snippet reaching the editor has been through `ceros_sanitize_embed_code`,
 * so its entities are decoded on the way out: an encoded `&` would otherwise
 * corrupt a manifest URL carrying query parameters. This mirrors the
 * `html_entity_decode()` in the PHP `ceros_manifest_url_from_inline()`, which
 * extracts from the raw snippet server-side and supplies the `manifestUrl` this
 * function only has to fall back for.
 *
 * @param {string} inlineEmbedCode The Flex Inline snippet.
 * @return {string} The manifest URL, or ''.
 */
export function manifestUrlFromInline( inlineEmbedCode ) {
	if ( ! inlineEmbedCode || typeof inlineEmbedCode !== 'string' ) {
		return '';
	}
	const match = inlineEmbedCode.match( /data-flex-manifest-url="([^"]+)"/i );
	return match ? decodeEntities( match[ 1 ] ) : '';
}

/**
 * Action types for the reducer
 */
export const ACTION_TYPES = {
	// API actions
	SET_CURRENT_ACCOUNT: 'SET_CURRENT_ACCOUNT',
	SET_FOLDER_TREE: 'SET_FOLDER_TREE',
	SET_CURRENT_ACCOUNT_ERROR: 'SET_CURRENT_ACCOUNT_ERROR',
	SET_FOLDER_TREE_ERROR: 'SET_FOLDER_TREE_ERROR',
	SET_LOADING_TREE: 'SET_LOADING_TREE',
	UPDATE_FOLDER_TREE_NODE: 'UPDATE_FOLDER_TREE_NODE',

	// Tree UI actions
	TOGGLE_EXPANDED_NODE: 'TOGGLE_EXPANDED_NODE',
	ADD_LOADING_NODE: 'ADD_LOADING_NODE',
	REMOVE_LOADING_NODE: 'REMOVE_LOADING_NODE',

	// Selection actions
	SELECT_EXPERIENCE: 'SELECT_EXPERIENCE',
	SET_EMBED_CODES: 'SET_EMBED_CODES',
	SET_EMBED_OPTION: 'SET_EMBED_OPTION',
	SET_DELIVERY_MODE: 'SET_DELIVERY_MODE',
	SET_INCLUDE_CUSTOM_HTML: 'SET_INCLUDE_CUSTOM_HTML',

	// Modal actions
	OPEN_MODAL: 'OPEN_MODAL',
	CLOSE_MODAL: 'CLOSE_MODAL',
};
