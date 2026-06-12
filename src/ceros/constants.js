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
 */
export const DELIVERY_MODES = {
	IFRAME: 'iframe',
	INLINE: 'inline',
};

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

	// Modal actions
	OPEN_MODAL: 'OPEN_MODAL',
	CLOSE_MODAL: 'CLOSE_MODAL',
};
