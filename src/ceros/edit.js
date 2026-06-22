/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */
import { __ } from '@wordpress/i18n';

/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { useBlockProps, BlockControls, InspectorControls } from '@wordpress/block-editor';
import { ToolbarGroup, ToolbarButton, DropdownMenu, MenuGroup, MenuItem, PanelBody, BaseControl, Button } from '@wordpress/components';

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './editor.scss';

import { useEffect, useReducer, useRef } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { CerosModal } from './components/modal';
import { CerosPreview } from './components/preview';
import { SidebarControls } from './components/sidebar-controls';
import { PasteUrlPanel } from './components/paste-url-panel';
import { ACTION_TYPES, DELIVERY_MODES } from './constants';

/**
 * Read a localized setting from either the block-editor (`cerosBlockData`) or
 * the legacy admin (`cerosAdmin`) global, preferring the former.
 *
 * @param {string} key The setting key to read.
 * @return {*} The value, or undefined when neither global provides it.
 */
function getCerosData( key ) {
	if ( typeof window === 'undefined' ) {
		return undefined;
	}
	if (
		window.cerosBlockData &&
		typeof window.cerosBlockData[ key ] !== 'undefined'
	) {
		return window.cerosBlockData[ key ];
	}
	if ( window.cerosAdmin && typeof window.cerosAdmin[ key ] !== 'undefined' ) {
		return window.cerosAdmin[ key ];
	}
	return undefined;
}

/**
 * Whether a Ceros API key is configured. When false, experience browsing is
 * disabled and the editor offers the paste-a-public-URL flow instead.
 *
 * Read once at module load: the flag is injected by PHP (`wp_localize_script`)
 * at page load and cannot change during an editor session, so it is a plain
 * module constant rather than reactive state — which is why the effects that
 * read it intentionally omit it from their dependency arrays.
 *
 * @type {boolean}
 */
const IS_API_KEY_CONFIGURED = Boolean( getCerosData( 'isApiConfigured' ) );

/**
 * Get the Ceros settings URL, handling various WordPress admin URL configurations
 */
function getCerosSettingsUrl() {
	// Method 1/2: Use the server-provided settings URL from either global
	// (most reliable).
	const providedUrl = getCerosData( 'settingsUrl' );
	if ( providedUrl ) {
		return providedUrl;
	}

	// Fallback methods for when server data isn't available
	let adminUrl = '';

	// Method 3: Check if ajaxurl is available (contains admin-ajax.php)
	if (window.ajaxurl) {
		adminUrl = window.ajaxurl.replace('/admin-ajax.php', '/');
	}

	// Method 4: Try to get from current page URL if we're in admin
	if (!adminUrl && window.location.pathname.includes('/wp-admin/')) {
		const pathParts = window.location.pathname.split('/wp-admin/');
		adminUrl = window.location.origin + pathParts[0] + '/wp-admin/';
	}

	// Method 5: Use WordPress REST API base URL to derive admin URL
	if (!adminUrl && wp && wp.url && wp.url.path) {
		const restBase = wp.url.path;
		// REST API is typically at /wp-json/, so admin would be at /wp-admin/
		adminUrl = restBase.replace('/wp-json/', '/wp-admin/');
	}

	// Method 6: Parse current URL for WordPress subdirectory installations
	if (!adminUrl) {
		const origin = window.location.origin;
		const pathname = window.location.pathname;

		// Check if we're in a subdirectory WordPress installation
		if (pathname.includes('/wp-admin/')) {
			const pathParts = pathname.split('/wp-admin/');
			adminUrl = origin + pathParts[0] + '/wp-admin/';
		} else if (pathname.includes('/wp/')) {
			const pathParts = pathname.split('/wp/');
			adminUrl = origin + pathParts[0] + '/wp/wp-admin/';
		} else {
			// Standard WordPress installation
			adminUrl = origin + '/wp-admin/';
		}
	}

	// Ensure adminUrl ends with /
	if (adminUrl && !adminUrl.endsWith('/')) {
		adminUrl += '/';
	}

	return adminUrl + 'options-general.php?page=ceros_settings';
}


/**
 * Initial state for the reducer
 */
const initialState = ( attributes ) => ( {
	api: {
		currentAccountResult: null,
		folderTreeData: null,
		currentAccountError: null,
		folderTreeError: null,
		isLoadingTree: true
	},
	tree: {
		expandedNodes: new Set(),
		loadingNodes: new Set(),
	},
	selection: {
		selectedNodeId: attributes.experienceResourceId || null,
		selectedExperienceName: attributes.experienceName || '',
		currentEmbedCodes: {
			fullHeightEmbedCode: attributes.fullHeightEmbedCode || '',
			scrollableEmbedCode: attributes.scrollableEmbedCode || '',
			inlineEmbedCode: attributes.inlineEmbedCode || '',
		},
		selectedEmbedOption: attributes.selectedOption || 'full',
		selectedDeliveryMode: attributes.deliveryMode || DELIVERY_MODES.IFRAME,
	},
	modal: {
		isOpen: false,
	},
} );

/**
 * Normalise the folder tree response into a flat array of folder/project nodes.
 *
 * Handles multiple possible shapes:
 * - [ {..}, {..} ]
 * - { items: [ ... ] }
 * - { data: [ ... ] }
 * - [ [ {..}, ... ], totalCount, "folder" ] (tuple-style responses)
 *
 * @param {Object|Array} folderRes Raw response from the REST API.
 * @return {Array} Normalised array of nodes.
 */
function normaliseFolderTreeResponse( folderRes ) {
	let body = folderRes?.body ?? folderRes ?? [];

	// Handle object wrappers: { items: [...] } or { data: [...] }.
	if ( body && ! Array.isArray( body ) && typeof body === 'object' ) {
		if ( Array.isArray( body.items ) ) {
			body = body.items;
		} else if ( Array.isArray( body.data ) ) {
			body = body.data;
		}
	}

	// Handle tuple-style responses: [ [ nodes... ], totalCount, "folder" ].
	if (
		Array.isArray( body ) &&
		body.length > 0 &&
		Array.isArray( body[ 0 ] ) &&
		( typeof body[ 1 ] !== 'undefined' || typeof body[ 2 ] !== 'undefined' )
	) {
		body = body[ 0 ];
	}

	// Ensure we always return an array of plain objects.
	if ( ! Array.isArray( body ) ) {
		return [];
	}

	return body.filter(
		( node ) => node && typeof node === 'object'
	);
}

/**
 * Helpers for working with Ceros API shapes.
 * These centralise the "try multiple fields" logic so it isn't repeated.
 */
function getAccountResourceId( body ) {
	if ( ! body || typeof body !== 'object' ) {
		return null;
	}
	// Ceros returns the canonical field as `accountResourceId`. We deliberately
	// do NOT fall back to `id`/`accountId`/`resourceId`: those are different
	// entities (user id, internal id, etc.) and using them silently produced a
	// 401/404 on /accounts/{id}/folder-tree. Fail loudly instead.
	return body.accountResourceId || null;
}

function getExperienceResourceId( experience ) {
	if ( ! experience || typeof experience !== 'object' ) {
		return null;
	}
	return (
		experience.resourceId ||
		experience.id ||
		experience.experienceId ||
		null
	);
}

function getExperienceName( experience ) {
	if ( ! experience || typeof experience !== 'object' ) {
		return 'Experience';
	}
	return experience.name || experience.title || 'Experience';
}

/**
 * Helper to immutably update nodes in the folder tree.
 *
 * Applies the provided updater to each node. If the updater returns a new node
 * instance, that node is used; otherwise the original node is kept. Children
 * are processed recursively. The original array reference is preserved when
 * no changes are made anywhere in the tree.
 *
 * @param {Array} nodes   Current folder tree nodes.
 * @param {Function} updater Function receiving a node and returning either a new node or the original.
 * @return {Array} Potentially updated nodes array.
 */
function updateTreeNodes( nodes, updater ) {
	if ( ! Array.isArray( nodes ) || nodes.length === 0 ) {
		return nodes;
	}

	let changed = false;

	const updated = nodes.map( ( node ) => {
		// First apply updater to the current node
		let nextNode = updater( node );
		if ( nextNode !== node ) {
			changed = true;
		} else {
			nextNode = node;
		}

		// Then process children recursively
		if ( nextNode.children && nextNode.children.length ) {
			const updatedChildren = updateTreeNodes( nextNode.children, updater );
			if ( updatedChildren !== nextNode.children ) {
				changed = true;
				nextNode = {
					...nextNode,
					children: updatedChildren,
				};
			}
		}

		return nextNode;
	} );

	return changed ? updated : nodes;
}

/**
 * Reducer function to manage all component state
 */
function cerosReducer( state, action ) {
	switch ( action.type ) {
		case ACTION_TYPES.SET_CURRENT_ACCOUNT:
			return {
				...state,
				api: {
					...state.api,
					currentAccountResult: action.payload,
					currentAccountError: null,
				},
			};

		case ACTION_TYPES.SET_FOLDER_TREE:
			return {
				...state,
				api: {
					...state.api,
					folderTreeData: action.payload,
					isLoadingTree: false,
					folderTreeError: null,
				},
			};

		case ACTION_TYPES.SET_CURRENT_ACCOUNT_ERROR:
			return {
				...state,
				api: {
					...state.api,
					currentAccountError: action.payload,
					isLoadingTree: false,
				},
			};

		case ACTION_TYPES.SET_FOLDER_TREE_ERROR:
			return {
				...state,
				api: {
					...state.api,
					folderTreeError: action.payload,
					isLoadingTree: false,
				},
			};

		case ACTION_TYPES.SET_LOADING_TREE:
			return {
				...state,
				api: {
					...state.api,
					isLoadingTree: action.payload,
				},
			};

		case ACTION_TYPES.UPDATE_FOLDER_TREE_NODE:
			return {
				...state,
				api: {
					...state.api,
					folderTreeData: action.payload,
				},
			};

		case ACTION_TYPES.TOGGLE_EXPANDED_NODE: {
			const newExpanded = new Set( state.tree.expandedNodes );
			if ( newExpanded.has( action.payload ) ) {
				newExpanded.delete( action.payload );
			} else {
				newExpanded.add( action.payload );
			}
			return {
				...state,
				tree: {
					...state.tree,
					expandedNodes: newExpanded,
				},
			};
		}

		case ACTION_TYPES.ADD_LOADING_NODE: {
			const newLoading = new Set( state.tree.loadingNodes );
			newLoading.add( action.payload );
			return {
				...state,
				tree: {
					...state.tree,
					loadingNodes: newLoading,
				},
			};
		}

		case ACTION_TYPES.REMOVE_LOADING_NODE: {
			const newLoading = new Set( state.tree.loadingNodes );
			newLoading.delete( action.payload );
			return {
				...state,
				tree: {
					...state.tree,
					loadingNodes: newLoading,
				},
			};
		}

		case ACTION_TYPES.SELECT_EXPERIENCE:
			return {
				...state,
				selection: {
					...state.selection,
					selectedNodeId: action.payload.nodeId,
					selectedExperienceName: action.payload.name,
					selectedEmbedOption: action.payload.embedOption || state.selection.selectedEmbedOption,
				},
			};

		case ACTION_TYPES.SET_EMBED_CODES:
			return {
				...state,
				selection: {
					...state.selection,
					currentEmbedCodes: action.payload,
				},
			};

		case ACTION_TYPES.SET_DELIVERY_MODE:
			return {
				...state,
				selection: {
					...state.selection,
					selectedDeliveryMode: action.payload,
				},
			};

		case ACTION_TYPES.SET_EMBED_OPTION:
			return {
				...state,
				selection: {
					...state.selection,
					selectedEmbedOption: action.payload,
				},
			};

		case ACTION_TYPES.OPEN_MODAL:
			return {
				...state,
				modal: {
					...state.modal,
					isOpen: true,
				},
			};

		case ACTION_TYPES.CLOSE_MODAL:
			return {
				...state,
				modal: {
					...state.modal,
					isOpen: false,
				},
			};

		default:
			return state;
	}
}

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {Element} Element to render.
 */
export default function Edit( { attributes, setAttributes, clientId } ) {
	const [ state, dispatch ] = useReducer( cerosReducer, attributes, initialState );

	// Destructure state for easier access
	const {
		api: {
			currentAccountResult,
			folderTreeData,
			currentAccountError,
			folderTreeError,
			isLoadingTree
		},
		tree: {
			expandedNodes,
			loadingNodes,
		},
		selection: {
			selectedNodeId,
			selectedExperienceName,
			currentEmbedCodes,
			selectedEmbedOption,
			selectedDeliveryMode,
		},
		modal: {
			isOpen: isModalOpen,
		},
	} = state;

	// Determine if this block is currently selected (used to detect insertion)
	const isSelected = useSelect( ( select ) => {
		const selectedId = select( 'core/block-editor' ).getSelectedBlockClientId?.();
		return selectedId === clientId;
	}, [ clientId ] );

	// Track previous modal state to detect when modal first opens
	const prevModalOpenRef = useRef(false);
	const hasInitializedSelectionRef = useRef(false);
	const lastInitializedExperienceNameRef = useRef(null);
	const lastInitializedResourceIdRef = useRef(null);

	// When modal opens, find and select the currently saved experience in the tree
	// Run when: modal opens, folder tree loads, or saved experience name changes
	// But NOT when user manually selects a different experience
	useEffect(() => {
		// Reset initialization flag when modal closes
		if (!isModalOpen) {
			hasInitializedSelectionRef.current = false;
			prevModalOpenRef.current = false;
			lastInitializedExperienceNameRef.current = null;
			return;
		}

		// Check if we should initialize:
		// 1. Folder tree data just became available
		// 2. Saved experience id or name changed (user changed it outside modal)
		const experienceIdChanged = attributes.experienceResourceId &&
			attributes.experienceResourceId !== lastInitializedResourceIdRef.current;
		const experienceNameChanged = attributes.experienceName &&
			attributes.experienceName !== lastInitializedExperienceNameRef.current;

		prevModalOpenRef.current = isModalOpen;

		// Only initialize if we have the necessary data and at least an id or name
		if (!folderTreeData || (!attributes.experienceResourceId && !attributes.experienceName)) {
			return;
		}

		// If we've already initialized for this experience id/name, don't run again
		// unless either of them changed (user changed it outside modal)
		if (hasInitializedSelectionRef.current && !experienceIdChanged && !experienceNameChanged) {
			return;
		}

		// Recursive function to search for a node by resource id and collect parent folder IDs
		const findNodeById = (nodes, targetId, parentIds = []) => {
			for (const node of nodes) {
				if (String(node.resourceId) === String(targetId) && node.isExperience) {
					return { nodeId: node.resourceId, parentIds };
				}
				if (node.children && node.children.length > 0) {
					const found = findNodeById(node.children, targetId, [...parentIds, node.resourceId]);
					if (found) return found;
				}
			}
			return null;
		};

		// Recursive function to search for a node by name and collect parent folder IDs
		const findNodeByName = (nodes, targetName, parentIds = []) => {
			for (const node of nodes) {
				if (node.name === targetName && node.isExperience) {
					return { nodeId: node.resourceId, parentIds };
				}
				if (node.children && node.children.length > 0) {
					const found = findNodeByName(node.children, targetName, [...parentIds, node.resourceId]);
					if (found) return found;
				}
			}
			return null;
		};

		// Prefer restoring by saved resource id, fall back to name for backwards compatibility
		let result = null;
		if (attributes.experienceResourceId) {
			result = findNodeById(folderTreeData, attributes.experienceResourceId);
		}
		if (!result && attributes.experienceName) {
			result = findNodeByName(folderTreeData, attributes.experienceName);
		}
		if (result) {
			// Expand all parent folders so the selected node is visible
			if (result.parentIds && result.parentIds.length > 0) {
				result.parentIds.forEach(parentId => {
					if (!expandedNodes.has(parentId)) {
						dispatch({ type: ACTION_TYPES.TOGGLE_EXPANDED_NODE, payload: parentId });
					}
				});
			}

			// Select the node using its resource id
			dispatch({
				type: ACTION_TYPES.SELECT_EXPERIENCE,
				payload: {
					nodeId: String(result.nodeId),
					name: attributes.experienceName || '',
					embedOption: attributes.selectedOption || 'full',
				}
			});

			// Mark as initialized for this experience id/name
			hasInitializedSelectionRef.current = true;
			lastInitializedExperienceNameRef.current = attributes.experienceName;
			lastInitializedResourceIdRef.current = attributes.experienceResourceId;
		}
	}, [isModalOpen, folderTreeData, attributes.experienceResourceId, attributes.experienceName, expandedNodes]);

	// Initialize currentEmbedCodes from attributes if they exist
	useEffect(() => {
		if (attributes.fullHeightEmbedCode || attributes.scrollableEmbedCode) {
			dispatch({
				type: ACTION_TYPES.SET_EMBED_CODES,
				payload: {
					fullHeightEmbedCode: attributes.fullHeightEmbedCode || '',
					scrollableEmbedCode: attributes.scrollableEmbedCode || '',
					inlineEmbedCode: attributes.inlineEmbedCode || ''
				}
			});
		}
	}, [attributes.fullHeightEmbedCode, attributes.scrollableEmbedCode, attributes.inlineEmbedCode]);

	// Auto-select available embed option when codes change
	useEffect( () => {
		if ( ! currentEmbedCodes ) {
			return;
		}

		const hasFull = Boolean(
			currentEmbedCodes.fullHeightEmbedCode &&
			String( currentEmbedCodes.fullHeightEmbedCode ).trim()
		);
		const hasScroll = Boolean(
			currentEmbedCodes.scrollableEmbedCode &&
			String( currentEmbedCodes.scrollableEmbedCode ).trim()
		);

		if ( ! hasFull && hasScroll && selectedEmbedOption !== 'scroll' ) {
			dispatch( {
				type: ACTION_TYPES.SET_EMBED_OPTION,
				payload: 'scroll',
			} );
		} else if ( ! hasScroll && hasFull && selectedEmbedOption !== 'full' ) {
			dispatch( {
				type: ACTION_TYPES.SET_EMBED_OPTION,
				payload: 'full',
			} );
		}
	}, [ currentEmbedCodes, selectedEmbedOption ] );

	// Reset internal refs when the block unmounts
	useEffect( () => {
		return () => {
			prevModalOpenRef.current = false;
			hasInitializedSelectionRef.current = false;
			lastInitializedExperienceNameRef.current = null;
			lastInitializedResourceIdRef.current = null;
		};
	}, [] );

	useEffect( () => {
		// Without an API key the tree endpoints will fail; skip the fetch so the
		// editor shows the paste-a-URL flow instead of an API-key error.
		if ( ! IS_API_KEY_CONFIGURED ) {
			dispatch( { type: ACTION_TYPES.SET_LOADING_TREE, payload: false } );
			return;
		}

		const fetchAccountAndTree = async () => {
			try {
				// Fetch current account directly. If the API key is missing or invalid,
				// the error handling below will surface a clear message.
				const res = await apiFetch( { path: '/ceros/v1/current-account' } );

				dispatch( {
					type: ACTION_TYPES.SET_CURRENT_ACCOUNT,
					payload: res,
				} );

				// Extract accountResourceId from the response via normalised helper
				const accountResourceId = getAccountResourceId( res?.body );

				if ( ! accountResourceId ) {
					throw new Error(
						`accountResourceId not found in current account response. Available fields: ${ Object.keys(
							res?.body || {}
						).join( ', ' ) }`
					);
				}

				// Then get the folder tree using the accountResourceId
				const folderRes = await apiFetch( {
					path: `/ceros/v1/folder-tree/${ accountResourceId }`,
				} );

				// Normalise the response so the tree always receives a flat list of nodes.
				// Experiences (files) are fetched lazily when a folder is clicked.
				const folderTreeNodes = normaliseFolderTreeResponse( folderRes );
				dispatch( {
					type: ACTION_TYPES.SET_FOLDER_TREE,
					payload: folderTreeNodes,
				} );
			} catch ( err ) {
				dispatch( {
					type: ACTION_TYPES.SET_LOADING_TREE,
					payload: false,
				} );

				const errorMessage = extractApiErrorMessage( err );

				if ( ! currentAccountResult ) {
					dispatch( {
						type: ACTION_TYPES.SET_CURRENT_ACCOUNT_ERROR,
						payload: errorMessage,
					} );
				} else {
					dispatch( {
						type: ACTION_TYPES.SET_FOLDER_TREE_ERROR,
						payload: errorMessage,
					} );
				}
			}
		};

		void fetchAccountAndTree();
	}, [] );

	// Extract a human-readable API error message from different error shapes
	function extractApiErrorMessage( err ) {
		if ( ! err ) {
			return '';
		}

		// Check for 403 Forbidden response first (most specific) – often indicates API key/auth issues
		if ( err.code === 403 && err.error ) {
			return err.error;
		}

		// Check for nested error structure
		if ( err.data && err.data.code === 403 && err.data.error ) {
			return err.data.error;
		}

		// Check for direct error / message properties
		if ( err.error ) {
			return err.error;
		}

		if ( err.message ) {
			return err.message;
		}

		// Check for nested data error
		if ( err.data && err.data.error ) {
			return err.data.error;
		}

		// Check for plain string error
		if ( typeof err === 'string' ) {
			return err;
		}

		return 'Unknown error occurred';
	}

	function handleExperienceNodeSuccess( node, res ) {
		// Store embed codes for preview
		const codes = res?.body || null;

		dispatch( { type: ACTION_TYPES.SET_EMBED_CODES, payload: codes } );

		// Ensure nodeId is a string for consistency (selection already happened immediately on click)
		dispatch( {
			type: ACTION_TYPES.SELECT_EXPERIENCE,
			payload: {
				nodeId: String( node.resourceId ),
				name: node.name,
			},
		} );

		// Choose default option based on availability
		if ( codes ) {
			const hasFull = Boolean(
				codes.fullHeightEmbedCode &&
				String( codes.fullHeightEmbedCode ).trim()
			);
			const hasScroll = Boolean(
				codes.scrollableEmbedCode &&
				String( codes.scrollableEmbedCode ).trim()
			);

			if ( ! hasFull && hasScroll ) {
				dispatch( { type: ACTION_TYPES.SET_EMBED_OPTION, payload: 'scroll' } );
			} else if ( hasFull && ! hasScroll ) {
				dispatch( { type: ACTION_TYPES.SET_EMBED_OPTION, payload: 'full' } );
			} else if ( ! hasFull && ! hasScroll ) {
				dispatch( { type: ACTION_TYPES.SET_EMBED_OPTION, payload: 'full' } );
			}
		}

		// Persist embed codes on the node in the folder tree
		dispatch( {
			type: ACTION_TYPES.UPDATE_FOLDER_TREE_NODE,
			payload: updateTreeNodes(
				folderTreeData,
				( n ) =>
					n.resourceId === node.resourceId
						? { ...n, embedCodes: res?.body }
						: n
			),
		} );
	}

	function handleFolderNodeSuccess( node, res ) {
		// Backend now filters experiences, so we can use the response directly
		let experiences = [];

		if ( Array.isArray( res?.body ) ) {
			experiences = res.body;
		} else if ( Array.isArray( res?.body?.items ) ) {
			experiences = res.body.items;
		} else if ( Array.isArray( res?.body?.data ) ) {
			experiences = res.body.data;
		}

		// Experiences are already filtered by the backend. Only keep items with a valid resource id.
		const childNodes = experiences
			.map( ( exp ) => {
				const resourceId = getExperienceResourceId( exp );

				if ( ! resourceId ) {
					return null;
				}

				return {
					name: getExperienceName( exp ),
					resourceId,
					children: [],
					isExperience: true,
				};
			} )
			.filter( Boolean );

		// If this folder has no valid experiences and no existing children (subfolders),
		// add an empty message node styled like a file
		let nodesToAdd = childNodes;
		if (
			childNodes.length === 0 &&
			( ! node.children || node.children.length === 0 )
		) {
			nodesToAdd = [
				{
					name: 'No published experiences found',
					resourceId: `empty-${ node.resourceId }`,
					children: [],
					isExperience: false,
					isEmptyMessage: true,
				},
			];
		}

		dispatch( {
			type: ACTION_TYPES.UPDATE_FOLDER_TREE_NODE,
			payload: updateTreeNodes(
				folderTreeData,
				( n ) =>
					n.resourceId === node.resourceId
						? {
								...n,
								children: ( n.children || [] ).concat( nodesToAdd ),
								experiencesLoaded: true,
						  }
						: n
			),
		} );
	}

	function handleNodeApiError( err ) {
		const errorMessage = extractApiErrorMessage( err );

		if ( ! errorMessage ) {
			return;
		}

		dispatch( {
			type: ACTION_TYPES.SET_CURRENT_ACCOUNT_ERROR,
			payload: errorMessage,
		} );
	}

	async function loadExperienceNodeData( node ) {
		const endpoint = `/ceros/v1/experiences/${ node.resourceId }/embed-codes`;

		dispatch( {
			type: ACTION_TYPES.ADD_LOADING_NODE,
			payload: node.resourceId,
		} );

		try {
			const res = await apiFetch( { path: endpoint } );
			handleExperienceNodeSuccess( node, res );
		} catch ( err ) {
			handleNodeApiError( err );
		} finally {
			dispatch( {
				type: ACTION_TYPES.REMOVE_LOADING_NODE,
				payload: node.resourceId,
			} );
		}
	}

	async function loadFolderNodeData( node ) {
		const endpoint = `/ceros/v1/folder/${ node.resourceId }/experiences`;

		dispatch( {
			type: ACTION_TYPES.ADD_LOADING_NODE,
			payload: node.resourceId,
		} );

		try {
			const res = await apiFetch( { path: endpoint } );
			handleFolderNodeSuccess( node, res );
		} catch ( err ) {
			handleNodeApiError( err );
		} finally {
			dispatch( {
				type: ACTION_TYPES.REMOVE_LOADING_NODE,
				payload: node.resourceId,
			} );
		}
	}

	function handleExperienceNodeClick( node ) {
		// Make selection instantaneous for better UX - ensure nodeId is a string for consistency
		dispatch( {
			type: ACTION_TYPES.SELECT_EXPERIENCE,
			payload: {
				nodeId: String( node.resourceId ),
				name: node.name,
				embedOption: 'full',
			},
		} );

		// If node already has embed codes loaded, don't refetch
		if ( node.embedCodes ) {
			dispatch( {
				type: ACTION_TYPES.SET_EMBED_CODES,
				payload: node.embedCodes,
			} );
			return;
		}

		loadExperienceNodeData( node );
	}

	function handleFolderNodeClick( node ) {
		// Toggle expand/collapse for folder nodes
		dispatch( {
			type: ACTION_TYPES.TOGGLE_EXPANDED_NODE,
			payload: node.resourceId,
		} );

		// If collapsing, return early
		if ( expandedNodes.has( node.resourceId ) ) {
			return;
		}

		// Only skip the fetch if we've previously loaded experiences for this folder.
		// Having subfolders in `children` does NOT mean experiences are loaded —
		// the initial folder-tree fetch only populates subfolders, and experiences
		// for this folder must still be fetched lazily on first expand.
		if ( node.experiencesLoaded ) {
			return;
		}

		loadFolderNodeData( node );
	}

	// Handle click on tree node
	function handleNodeClick( node ) {
		if ( node.isExperience ) {
			handleExperienceNodeClick( node );
			return;
		}

		handleFolderNodeClick( node );
	}

	// Helper function to check if embed code exists
	const hasEmbedCode = (code) => {
		return Boolean(code && String(code).trim());
	};

	// Check if embed codes are available
	const hasFullHeight = hasEmbedCode(attributes.fullHeightEmbedCode) || hasEmbedCode(currentEmbedCodes?.fullHeightEmbedCode);
	const hasScrolling = hasEmbedCode(attributes.scrollableEmbedCode) || hasEmbedCode(currentEmbedCodes?.scrollableEmbedCode);

	// Delivery mode (iframe vs iframeless). Iframeless is only offered for Flex
	// experiences, detected server-side via the manifest probe.
	const deliveryMode = attributes.deliveryMode || DELIVERY_MODES.IFRAME;
	const inlineEmbedCode =
		attributes.inlineEmbedCode || currentEmbedCodes?.inlineEmbedCode || '';
	const hasInline = hasEmbedCode( inlineEmbedCode );
	const isInlineDelivery = deliveryMode === DELIVERY_MODES.INLINE;

	// Always use saved attributes for block preview to ensure it doesn't change until confirmed
	// This ensures the preview stays visible and unchanged when modal opens or new item is selected
	const previewEmbedCodes = {
		fullHeightEmbedCode: attributes.fullHeightEmbedCode || '',
		scrollableEmbedCode: attributes.scrollableEmbedCode || ''
	};

	// Determine if toolbar should be shown
	// Always show toolbar if there's any saved experience data, even when modal is open
	const hasExperience = Boolean(
		selectedExperienceName ||
		attributes.experienceName ||
		attributes.fullHeightEmbedCode ||
		attributes.scrollableEmbedCode ||
		hasFullHeight ||
		hasScrolling
	);
	// Keep toolbar (embed option + Replace) available even when modal is open
	const showToolbar = hasExperience;

	// Icon components
	const FullHeightIcon = ({ isActive = false }) => {
		const color = isActive ? "#2271b1" : "currentColor";
		return (
			<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
				<rect width="20" height="14.5" fill={color}/>
				<rect x="3" y="17" width="14" height="3" fill={color}/>
			</svg>
		);
	};

	const ScrollingIcon = ({ isActive = false }) => {
		const color = isActive ? "#2271b1" : "currentColor";
		return (
			<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
				<rect y="5.5" width="20" height="9" fill={color}/>
				<rect x="3" y="17" width="14" height="3" fill={color}/>
				<rect x="3" width="14" height="3" fill={color}/>
			</svg>
		);
	};

	// Get current icon for toolbar button - use saved option from attributes
	const toolbarEmbedOption = attributes.selectedOption || selectedEmbedOption || 'full';
	const getCurrentIcon = () => {
		return toolbarEmbedOption === 'full'
			? <FullHeightIcon isActive={false} />
			: <ScrollingIcon isActive={false} />;
	};

	const isApiKeyError =
		Boolean(
			currentAccountError &&
			(
				currentAccountError.includes('API key is not set') ||
				currentAccountError.includes('api key') ||
				currentAccountError.includes('API key') ||
				currentAccountError.includes('ceros_api_key_missing') ||
				currentAccountError.includes('Ceros API key')
			)
		);

	// Opening the experience picker requires an API key. Without one, reset the
	// block back to the empty state so the author can paste a different URL.
	const handleOpenPicker = () => {
		if ( IS_API_KEY_CONFIGURED ) {
			dispatch( { type: ACTION_TYPES.OPEN_MODAL } );
			return;
		}
		dispatch( {
			type: ACTION_TYPES.SET_EMBED_CODES,
			payload: {
				fullHeightEmbedCode: '',
				scrollableEmbedCode: '',
				inlineEmbedCode: '',
			},
		} );
		setAttributes( {
			fullHeightEmbedCode: '',
			scrollableEmbedCode: '',
			inlineEmbedCode: '',
			experienceName: '',
			experienceResourceId: '',
			deliveryMode: DELIVERY_MODES.IFRAME,
			selectedOption: 'full',
		} );
	};

	return (
		<>
			{/* Toolbar Controls */}
			{showToolbar && (
				<BlockControls>
					{!isInlineDelivery && (
					<ToolbarGroup>
						<DropdownMenu
							icon={getCurrentIcon()}
							label={__('Change embed type', 'ceros')}
							toggleProps={{
								'aria-label': __('Change embed type', 'ceros'),
							}}
						>
							{({ onClose }) => {
								const menuItemTextStyle = {
									display: 'flex',
									flexDirection: 'column',
									alignItems: 'flex-start'
								};
								const isFullSelected = toolbarEmbedOption === 'full';
								const isScrollSelected = toolbarEmbedOption === 'scroll';
								const fullTitleStyle = { fontWeight: 500, color: isFullSelected ? '#2271b1' : 'inherit' };
								const scrollTitleStyle = { fontWeight: 500, color: isScrollSelected ? '#2271b1' : 'inherit' };
								const descriptionStyle = { fontSize: '12px', color: '#757575', marginTop: '2px' };

								return (
									<MenuGroup>
										<MenuItem
											icon={<FullHeightIcon isActive={isFullSelected} />}
											onClick={() => {
												if (hasFullHeight) {
													dispatch({ type: ACTION_TYPES.SET_EMBED_OPTION, payload: 'full' });
													setAttributes({ selectedOption: 'full' });
												}
												onClose();
											}}
											isSelected={isFullSelected}
											disabled={!hasFullHeight}
										>
											<div style={menuItemTextStyle}>
												<span style={fullTitleStyle}>{__('Full height', 'ceros')}</span>
												<span style={descriptionStyle}>
													{__('Scrolls with the page.', 'ceros')}
												</span>
											</div>
										</MenuItem>
										<MenuItem
											icon={<ScrollingIcon isActive={isScrollSelected} />}
											onClick={() => {
												if (hasScrolling) {
													dispatch({ type: ACTION_TYPES.SET_EMBED_OPTION, payload: 'scroll' });
													setAttributes({ selectedOption: 'scroll' });
												}
												onClose();
											}}
											isSelected={isScrollSelected}
											disabled={!hasScrolling}
										>
											<div style={menuItemTextStyle}>
												<span style={scrollTitleStyle}>{__('Scrolling', 'ceros')}</span>
												<span style={descriptionStyle}>
													{__('Scrolls in its own set area.', 'ceros')}
												</span>
											</div>
										</MenuItem>
									</MenuGroup>
								);
							}}
						</DropdownMenu>
					</ToolbarGroup>
					)}
					<ToolbarGroup>
						<ToolbarButton
							onClick={handleOpenPicker}
							label={__('Change experience', 'ceros')}
						>
							{__('Replace', 'ceros')}
						</ToolbarButton>
					</ToolbarGroup>
				</BlockControls>
			)}

			{/* Sidebar Controls */}
			{hasExperience && (
				<SidebarControls
					selectedExperienceName={selectedExperienceName}
					attributes={attributes}
					selectedEmbedOption={selectedEmbedOption}
					hasFullHeight={hasFullHeight}
					hasScrolling={hasScrolling}
					deliveryMode={deliveryMode}
					hasInline={hasInline}
					inlineEmbedCode={inlineEmbedCode}
					onEdit={handleOpenPicker}
					dispatch={dispatch}
					setAttributes={setAttributes}
				/>
			)}
			<div { ...useBlockProps() }>

			{/* Main Block Display */}
			<div className="ceros-block">
				{/* Show API / connection errors immediately if present, but keep the preview below */}
				{currentAccountError && (
					isApiKeyError ? (
						<div className="ceros-block__error">
							<div className="ceros-block__error-content">
								<svg className="ceros-block__error-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
									<circle cx="12" cy="12" r="10"></circle>
									<line x1="15" y1="9" x2="9" y2="15"></line>
									<line x1="9" y1="9" x2="15" y2="15"></line>
								</svg>
								<div>
									<h3>Ceros API Key Required</h3>
									<p>{currentAccountError}</p>
									<a href={getCerosSettingsUrl()} className="ceros-block__settings-link" target="_blank" rel="noopener noreferrer">
										Go to Ceros Settings
									</a>
								</div>
							</div>
						</div>
					) : (
						<div className="ceros-block__error">
							<div className="ceros-block__error-content">
								<svg className="ceros-block__error-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
									<circle cx="12" cy="12" r="10"></circle>
									<line x1="12" y1="8" x2="12" y2="12"></line>
									<line x1="12" y1="16" x2="12.01" y2="16"></line>
								</svg>
								<div>
									<h3>Ceros connection error</h3>
									<p>{currentAccountError}</p>
								</div>
							</div>
						</div>
					)
				)}

				{(selectedExperienceName || attributes.experienceName || attributes.fullHeightEmbedCode || attributes.scrollableEmbedCode) ? (
					<div className="ceros-block__selected">
						<CerosPreview
							currentEmbedCodes={ previewEmbedCodes }
							deliveryMode={ attributes.deliveryMode || DELIVERY_MODES.IFRAME }
							selectedEmbedOption={ attributes.selectedOption }
						/>
					</div>
				) : !isModalOpen && !currentAccountError && (
					IS_API_KEY_CONFIGURED ? (
						<div className="ceros-block__empty">
							<h3>No Experience Selected</h3>
							<p>Click the button below to browse and select a Ceros experience.</p>
							<button
								className="ceros-block__button ceros-block__button--primary"
								onClick={() => dispatch({ type: ACTION_TYPES.OPEN_MODAL })}
							>
								Browse Experiences
							</button>
						</div>
					) : (
						<PasteUrlPanel
							dispatch={dispatch}
							setAttributes={setAttributes}
							settingsUrl={getCerosSettingsUrl()}
							currentEmbedCodes={currentEmbedCodes}
							selectedDeliveryMode={selectedDeliveryMode}
							selectedEmbedOption={selectedEmbedOption}
						/>
					)
				)}
			</div>
		</div>
		<CerosModal
			isOpen={ isModalOpen }
			onClose={ () => dispatch({ type: ACTION_TYPES.CLOSE_MODAL }) }
			state={ {
				currentAccountError,
				folderTreeError,
				isLoadingTree,
				folderTreeData,
				handleNodeClick,
				expandedNodes,
				loadingNodes,
				selectedNodeId,
				currentEmbedCodes,
				selectedEmbedOption,
				setSelectedEmbedOption: ( option ) => dispatch({ type: ACTION_TYPES.SET_EMBED_OPTION, payload: option }),
				selectedDeliveryMode,
				setSelectedDeliveryMode: ( mode ) => dispatch({ type: ACTION_TYPES.SET_DELIVERY_MODE, payload: mode }),
				setAttributes,
				selectedExperienceName,
			} }
		/></>
	);
}
