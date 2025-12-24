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
import { useBlockProps, BlockControls } from '@wordpress/block-editor';
import {
	ToolbarGroup,
	ToolbarButton,
	DropdownMenu,
	MenuGroup,
	MenuItem,
} from '@wordpress/components';

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
import { ACTION_TYPES, EMBED_OPTIONS } from './constants';

/**
 * Extract error message from various error object structures.
 * Returns an object with the message and flags for special error types.
 *
 * @param {Error|Object|string} err - The error to extract message from
 * @return {Object} Object with message, is403, and isApiKeyError properties
 */
function extractErrorInfo( err ) {
	let message = '';
	let is403 = false;

	// Check for 403 Forbidden response first (most specific)
	if ( err.code === 403 && err.error ) {
		message = err.error;
		is403 = true;
	}
	// Check for nested error structure
	else if ( err.data && err.data.code === 403 && err.data.error ) {
		message = err.data.error;
		is403 = true;
	}
	// Check for direct error property
	else if ( err.error ) {
		message = err.error;
	}
	// Check for message property
	else if ( err.message ) {
		message = err.message;
	}
	// Check for nested data error
	else if ( err.data && err.data.error ) {
		message = err.data.error;
	}
	// Check for string error
	else if ( typeof err === 'string' ) {
		message = err;
	} else {
		message = 'Unknown error occurred';
	}

	// Check if this is an API key related error
	const isApiKeyError =
		is403 ||
		message.includes( 'API key is not set' ) ||
		message.includes( 'api key' ) ||
		message.includes( 'API key' ) ||
		message.includes( 'ceros_api_key_missing' ) ||
		message.includes( 'Ceros API key' );

	return { message, is403, isApiKeyError };
}

/**
 * Get the Ceros settings URL, handling various WordPress admin URL configurations
 */
function getCerosSettingsUrl() {
	// Method 1: Use server-provided settings URL (most reliable)
	if ( window.cerosBlockData && window.cerosBlockData.settingsUrl ) {
		return window.cerosBlockData.settingsUrl;
	}

	// Method 2: Check legacy cerosAdmin data
	if ( window.cerosAdmin && window.cerosAdmin.settingsUrl ) {
		return window.cerosAdmin.settingsUrl;
	}

	// Fallback methods for when server data isn't available
	let adminUrl = '';

	// Method 3: Check if ajaxurl is available (contains admin-ajax.php)
	if ( window.ajaxurl ) {
		adminUrl = window.ajaxurl.replace( '/admin-ajax.php', '/' );
	}

	// Method 4: Try to get from current page URL if we're in admin
	if ( ! adminUrl && window.location.pathname.includes( '/wp-admin/' ) ) {
		const pathParts = window.location.pathname.split( '/wp-admin/' );
		adminUrl = window.location.origin + pathParts[ 0 ] + '/wp-admin/';
	}

	// Method 5: Use WordPress REST API base URL to derive admin URL
	if ( ! adminUrl && wp && wp.url && wp.url.path ) {
		const restBase = wp.url.path;
		// REST API is typically at /wp-json/, so admin would be at /wp-admin/
		adminUrl = restBase.replace( '/wp-json/', '/wp-admin/' );
	}

	// Method 6: Parse current URL for WordPress subdirectory installations
	if ( ! adminUrl ) {
		const origin = window.location.origin;
		const pathname = window.location.pathname;

		// Check if we're in a subdirectory WordPress installation
		if ( pathname.includes( '/wp-admin/' ) ) {
			const pathParts = pathname.split( '/wp-admin/' );
			adminUrl = origin + pathParts[ 0 ] + '/wp-admin/';
		} else if ( pathname.includes( '/wp/' ) ) {
			const pathParts = pathname.split( '/wp/' );
			adminUrl = origin + pathParts[ 0 ] + '/wp/wp-admin/';
		} else {
			// Standard WordPress installation
			adminUrl = origin + '/wp-admin/';
		}
	}

	// Ensure adminUrl ends with /
	if ( adminUrl && ! adminUrl.endsWith( '/' ) ) {
		adminUrl += '/';
	}

	return adminUrl + 'options-general.php?page=ceros_settings';
}

/**
 * Icon component for full height embed option
 */
const FullHeightIcon = ( { isActive = false } ) => {
	const color = isActive ? '#2271b1' : 'currentColor';
	return (
		<svg
			width="20"
			height="20"
			viewBox="0 0 20 20"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
		>
			<rect width="20" height="14.5" fill={ color } />
			<rect x="3" y="17" width="14" height="3" fill={ color } />
		</svg>
	);
};

/**
 * Icon component for scrolling embed option
 */
const ScrollingIcon = ( { isActive = false } ) => {
	const color = isActive ? '#2271b1' : 'currentColor';
	return (
		<svg
			width="20"
			height="20"
			viewBox="0 0 20 20"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
		>
			<rect y="5.5" width="20" height="9" fill={ color } />
			<rect x="3" y="17" width="14" height="3" fill={ color } />
			<rect x="3" width="14" height="3" fill={ color } />
		</svg>
	);
};

/**
 * Initial state for the reducer
 */
const initialState = ( attributes ) => ( {
	api: {
		currentAccountResult: null,
		folderTreeData: null,
		currentAccountError: null,
		folderTreeError: null,
		isLoadingTree: true,
		apiKeyConfigured: null, // null = checking, true = configured, false = not configured
	},
	tree: {
		expandedNodes: new Set(),
		loadingNodes: new Set(),
	},
	selection: {
		selectedNodeId: null,
		selectedExperienceName: attributes.experienceName || '',
		currentEmbedCodes: {
			fullHeightEmbedCode: attributes.fullHeightEmbedCode || '',
			scrollableEmbedCode: attributes.scrollableEmbedCode || '',
		},
		selectedEmbedOption: attributes.selectedOption || EMBED_OPTIONS.FULL,
	},
	modal: {
		isOpen: false,
		hasOpenedFromInsert: false,
	},
} );

/**
 * Reducer function to manage all component state
 */
function cerosReducer( state, action ) {
	switch ( action.type ) {
		case ACTION_TYPES.SET_API_KEY_STATUS:
			return {
				...state,
				api: {
					...state.api,
					apiKeyConfigured: action.payload,
				},
			};

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
					selectedEmbedOption:
						action.payload.embedOption ||
						state.selection.selectedEmbedOption,
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

		case ACTION_TYPES.SET_HAS_OPENED_FROM_INSERT:
			return {
				...state,
				modal: {
					...state.modal,
					hasOpenedFromInsert: action.payload,
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
	const [ state, dispatch ] = useReducer(
		cerosReducer,
		attributes,
		initialState
	);

	// Destructure state for easier access
	const {
		api: {
			currentAccountResult,
			folderTreeData,
			currentAccountError,
			folderTreeError,
			isLoadingTree,
			apiKeyConfigured,
		},
		tree: { expandedNodes, loadingNodes },
		selection: {
			selectedNodeId,
			selectedExperienceName,
			currentEmbedCodes,
			selectedEmbedOption,
		},
		modal: { isOpen: isModalOpen, hasOpenedFromInsert },
	} = state;

	// Determine if this block is currently selected (used to detect insertion)
	const isSelected = useSelect(
		( select ) => {
			const selectedId =
				select( 'core/block-editor' ).getSelectedBlockClientId?.();
			return selectedId === clientId;
		},
		[ clientId ]
	);

	// Track previous modal state to detect when modal first opens
	const prevModalOpenRef = useRef( false );
	const hasInitializedSelectionRef = useRef( false );
	const lastInitializedExperienceNameRef = useRef( null );
	// Ref to track expandedNodes without causing re-renders when used in useEffect
	const expandedNodesRef = useRef( expandedNodes );

	// Keep the ref in sync with state
	useEffect( () => {
		expandedNodesRef.current = expandedNodes;
	}, [ expandedNodes ] );

	// If the block is newly inserted (selected) and not configured, open modal once.
	useEffect( () => {
		const hasCodes =
			Boolean( attributes.fullHeightEmbedCode ) ||
			Boolean( attributes.scrollableEmbedCode );
		if ( ! hasOpenedFromInsert && isSelected && ! hasCodes ) {
			dispatch( { type: ACTION_TYPES.OPEN_MODAL } );
			dispatch( {
				type: ACTION_TYPES.SET_HAS_OPENED_FROM_INSERT,
				payload: true,
			} );
		}
	}, [
		isSelected,
		hasOpenedFromInsert,
		attributes.fullHeightEmbedCode,
		attributes.scrollableEmbedCode,
	] );

	// Hide toolbar when modal is open
	useEffect( () => {
		if ( isModalOpen ) {
			document.body.classList.add( 'ceros-modal-open' );
		} else {
			document.body.classList.remove( 'ceros-modal-open' );
		}

		// Cleanup function to remove class when component unmounts
		return () => {
			document.body.classList.remove( 'ceros-modal-open' );
		};
	}, [ isModalOpen ] );

	// When modal opens, find and select the currently saved experience in the tree
	// Run when: modal opens, folder tree loads, or saved experience name changes
	// But NOT when user manually selects a different experience
	useEffect( () => {
		// Reset initialization flag when modal closes
		if ( ! isModalOpen ) {
			hasInitializedSelectionRef.current = false;
			prevModalOpenRef.current = false;
			lastInitializedExperienceNameRef.current = null;
			return;
		}

		// Check if we should initialize:
		// 1. Modal just opened (transition from closed to open)
		// 2. Folder tree data just became available
		// 3. Saved experience name changed (user changed it outside modal)
		const modalJustOpened = ! prevModalOpenRef.current && isModalOpen;
		const experienceNameChanged =
			attributes.experienceName &&
			attributes.experienceName !==
				lastInitializedExperienceNameRef.current;

		prevModalOpenRef.current = isModalOpen;

		// Only initialize if we have the necessary data and one of the conditions is met
		if ( ! folderTreeData || ! attributes.experienceName ) {
			return;
		}

		// If we've already initialized for this experience name, don't run again
		// (unless the experience name changed, which means user changed it outside modal)
		if ( hasInitializedSelectionRef.current && ! experienceNameChanged ) {
			return;
		}

		// Recursive function to search for a node by name and collect parent folder IDs
		const findNodeByName = ( nodes, targetName, parentIds = [] ) => {
			for ( const node of nodes ) {
				if ( node.name === targetName && node.isExperience ) {
					return { nodeId: node.resourceId, parentIds };
				}
				if ( node.children && node.children.length > 0 ) {
					const found = findNodeByName( node.children, targetName, [
						...parentIds,
						node.resourceId,
					] );
					if ( found ) return found;
				}
			}
			return null;
		};

		const result = findNodeByName(
			folderTreeData,
			attributes.experienceName
		);
		if ( result ) {
			// Expand all parent folders so the selected node is visible
			if ( result.parentIds && result.parentIds.length > 0 ) {
				result.parentIds.forEach( ( parentId ) => {
					// Use ref to avoid infinite re-renders when expandedNodes changes
					if ( ! expandedNodesRef.current.has( parentId ) ) {
						dispatch( {
							type: ACTION_TYPES.TOGGLE_EXPANDED_NODE,
							payload: parentId,
						} );
					}
				} );
			}

			// Select the node
			dispatch( {
				type: ACTION_TYPES.SELECT_EXPERIENCE,
				payload: {
					nodeId: String( result.nodeId ),
					name: attributes.experienceName,
					embedOption:
						attributes.selectedOption || EMBED_OPTIONS.FULL,
				},
			} );

			// Mark as initialized for this experience name
			hasInitializedSelectionRef.current = true;
			lastInitializedExperienceNameRef.current =
				attributes.experienceName;
		}
		// Note: expandedNodes intentionally omitted from deps - we use expandedNodesRef
		// to read latest value without causing re-renders when nodes are toggled
	}, [ isModalOpen, folderTreeData, attributes.experienceName ] );

	// Initialize currentEmbedCodes from attributes if they exist
	useEffect( () => {
		if (
			attributes.fullHeightEmbedCode ||
			attributes.scrollableEmbedCode
		) {
			dispatch( {
				type: ACTION_TYPES.SET_EMBED_CODES,
				payload: {
					fullHeightEmbedCode: attributes.fullHeightEmbedCode || '',
					scrollableEmbedCode: attributes.scrollableEmbedCode || '',
				},
			} );
		}
	}, [ attributes.fullHeightEmbedCode, attributes.scrollableEmbedCode ] );

	// Auto-select available embed option when codes change
	useEffect( () => {
		if ( ! currentEmbedCodes ) return;
		const hasFull = Boolean(
			currentEmbedCodes.fullHeightEmbedCode &&
				String( currentEmbedCodes.fullHeightEmbedCode ).trim()
		);
		const hasScroll = Boolean(
			currentEmbedCodes.scrollableEmbedCode &&
				String( currentEmbedCodes.scrollableEmbedCode ).trim()
		);
		if (
			! hasFull &&
			hasScroll &&
			selectedEmbedOption !== EMBED_OPTIONS.SCROLL
		) {
			dispatch( {
				type: ACTION_TYPES.SET_EMBED_OPTION,
				payload: EMBED_OPTIONS.SCROLL,
			} );
		} else if (
			! hasScroll &&
			hasFull &&
			selectedEmbedOption !== EMBED_OPTIONS.FULL
		) {
			dispatch( {
				type: ACTION_TYPES.SET_EMBED_OPTION,
				payload: EMBED_OPTIONS.FULL,
			} );
		}
	}, [ currentEmbedCodes, selectedEmbedOption ] );

	// Initial API data fetch - runs once on mount only.
	// Note: Empty dependency array is intentional. This effect should only run once
	// when the component mounts to fetch the initial account and folder tree data.
	// The reference to `currentAccountResult` in the catch block is safe because
	// at mount time it's always null, and we're just checking whether to set the
	// error on currentAccount vs folderTree based on which API call failed.
	useEffect( () => {
		// First, check if API key is configured before making any API calls
		apiFetch( { path: '/ceros/v1/api-key-status' } )
			.then( ( statusRes ) => {
				if ( ! statusRes.configured ) {
					// API key is not configured, show error immediately
					dispatch( {
						type: ACTION_TYPES.SET_API_KEY_STATUS,
						payload: false,
					} );
					dispatch( {
						type: ACTION_TYPES.SET_CURRENT_ACCOUNT_ERROR,
						payload:
							statusRes.message ||
							'Ceros API key is not set. Please add it in the Ceros settings first.',
					} );
					dispatch( {
						type: ACTION_TYPES.SET_LOADING_TREE,
						payload: false,
					} );
					return; // Stop here, don't make further API calls
				}

				// API key is configured, proceed with getting current account
				dispatch( {
					type: ACTION_TYPES.SET_API_KEY_STATUS,
					payload: true,
				} );
				return apiFetch( { path: '/ceros/v1/current-account' } );
			} )
			.then( ( res ) => {
				// If we don't have a response (API key was not configured), skip this
				if ( ! res ) return;

				dispatch( {
					type: ACTION_TYPES.SET_CURRENT_ACCOUNT,
					payload: res,
				} );

				// Extract accountResourceId from the response - try different possible field names
				const accountResourceId =
					res?.body?.accountResourceId ||
					res?.body?.accountId ||
					res?.body?.id ||
					res?.body?.resourceId;

				if ( accountResourceId ) {
					// Then get the folder tree using the accountResourceId
					return apiFetch( {
						path: `/ceros/v1/folder-tree/${ accountResourceId }`,
					} );
				} else {
					throw new Error(
						`accountResourceId not found in current account response. Available fields: ${ Object.keys(
							res?.body || {}
						).join( ', ' ) }`
					);
				}
			} )
			.then( ( folderRes ) => {
				// If we don't have a response (API key was not configured), skip this
				if ( ! folderRes ) return;

				// Expecting folderRes.body to be an array of folder nodes.
				// We intentionally ONLY store the folder structure here.
				// Experiences (files) are fetched lazily when a folder is clicked.
				const folderTreeNodes = folderRes?.body || [];
				dispatch( {
					type: ACTION_TYPES.SET_FOLDER_TREE,
					payload: folderTreeNodes,
				} );
			} )
			.catch( ( err ) => {
				dispatch( {
					type: ACTION_TYPES.SET_LOADING_TREE,
					payload: false,
				} );

				const { message, isApiKeyError } = extractErrorInfo( err );

				if ( isApiKeyError ) {
					dispatch( {
						type: ACTION_TYPES.SET_API_KEY_STATUS,
						payload: false,
					} );
				}

				if ( ! currentAccountResult ) {
					dispatch( {
						type: ACTION_TYPES.SET_CURRENT_ACCOUNT_ERROR,
						payload: message,
					} );
				} else {
					dispatch( {
						type: ACTION_TYPES.SET_FOLDER_TREE_ERROR,
						payload: message,
					} );
				}
			} );
	}, [] );

	// Handle click on tree node
	function handleNodeClick( node ) {
		// Only toggle expand/collapse for folder nodes. Experience nodes should not
		// use expand/collapse state, otherwise a prior click would require a second
		// click to re-select the same experience.
		if ( ! node.isExperience ) {
			dispatch( {
				type: ACTION_TYPES.TOGGLE_EXPANDED_NODE,
				payload: node.resourceId,
			} );

			// If collapsing, return early
			if ( expandedNodes.has( node.resourceId ) ) {
				return;
			}
		}

		// For experience nodes, we need to fetch embed codes
		if ( node.isExperience ) {
			// Make selection instantaneous for better UX - ensure nodeId is a string for consistency
			dispatch( {
				type: ACTION_TYPES.SELECT_EXPERIENCE,
				payload: {
					nodeId: String( node.resourceId ),
					name: node.name,
					embedOption: EMBED_OPTIONS.FULL,
				},
			} );

			// If node already has embed codes loaded, don't refetch
			if ( node.embedCodes ) {
				// Set the embed codes for preview
				dispatch( {
					type: ACTION_TYPES.SET_EMBED_CODES,
					payload: node.embedCodes,
				} );
				return;
			}

			// Add node to loading state for embed codes
			dispatch( {
				type: ACTION_TYPES.ADD_LOADING_NODE,
				payload: node.resourceId,
			} );
		} else {
			// For folder nodes, experiences should already be loaded from initial fetch
			// If node already has children loaded, don't refetch
			if ( node.children && node.children.length > 0 ) {
				return;
			}

			// Add node to loading state only if we need to fetch experiences
			dispatch( {
				type: ACTION_TYPES.ADD_LOADING_NODE,
				payload: node.resourceId,
			} );
		}

		// Determine endpoint based on node type
		const endpoint = node.isExperience
			? `/ceros/v1/experiences/${ node.resourceId }/embed-codes`
			: `/ceros/v1/folder/${ node.resourceId }/experiences`;

		// Only make API call if we actually need to fetch data
		if (
			( node.isExperience && ! node.embedCodes ) ||
			( ! node.isExperience &&
				( ! node.children || node.children.length === 0 ) )
		) {
			apiFetch( { path: endpoint } )
				.then( ( res ) => {
					if ( node.isExperience ) {
						// Store embed codes for preview
						const codes = res?.body || null;
						dispatch( {
							type: ACTION_TYPES.SET_EMBED_CODES,
							payload: codes,
						} );
						// Ensure nodeId is a string for consistency (selection already happened immediately above)
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
								dispatch( {
									type: ACTION_TYPES.SET_EMBED_OPTION,
									payload: EMBED_OPTIONS.SCROLL,
								} );
							} else if ( hasFull && ! hasScroll ) {
								dispatch( {
									type: ACTION_TYPES.SET_EMBED_OPTION,
									payload: EMBED_OPTIONS.FULL,
								} );
							} else if ( ! hasFull && ! hasScroll ) {
								dispatch( {
									type: ACTION_TYPES.SET_EMBED_OPTION,
									payload: EMBED_OPTIONS.FULL,
								} );
							}
						}

						// We fetched embed codes, attach to node and stop further processing
						const attachCodes = ( nodes ) => {
							let changed = false;
							const updated = nodes.map( ( n ) => {
								if ( n.resourceId === node.resourceId ) {
									changed = true;
									return { ...n, embedCodes: res?.body };
								}
								if ( n.children && n.children.length ) {
									const updatedChildren = attachCodes(
										n.children
									);
									if ( updatedChildren !== n.children ) {
										changed = true;
										return {
											...n,
											children: updatedChildren,
										};
									}
								}
								return n;
							} );
							return changed ? updated : nodes;
						};

						dispatch( {
							type: ACTION_TYPES.UPDATE_FOLDER_TREE_NODE,
							payload: attachCodes( folderTreeData ),
						} );
						return; // done for experience nodes
					}

					if ( ! node.isExperience ) {
						// Backend now filters experiences, so we can use the response directly
						let experiences = [];
						if ( Array.isArray( res?.body ) ) {
							experiences = res.body;
						} else if ( Array.isArray( res?.body?.items ) ) {
							experiences = res.body.items;
						} else if ( Array.isArray( res?.body?.data ) ) {
							experiences = res.body.data;
						}

						// Experiences are already filtered by the backend
						const childNodes = experiences.map( ( exp ) => ( {
							name: exp.name || exp.title || 'Experience',
							resourceId:
								exp.resourceId ||
								exp.id ||
								exp.experienceId ||
								Math.random().toString( 36 ).substr( 2, 5 ),
							children: [],
							isExperience: true,
						} ) );

						// If this folder has no valid experiences and no existing children (subfolders),
						// add an empty message node styled like a file
						let nodesToAdd = childNodes;
						if (
							childNodes.length === 0 &&
							( ! node.children || node.children.length === 0 )
						) {
							nodesToAdd = [
								{
									name: 'No experiences found',
									resourceId: `empty-${ node.resourceId }`,
									children: [],
									isExperience: false,
									isEmptyMessage: true,
								},
							];
						}

						const addChildren = ( nodes ) => {
							let changed = false;
							const updated = nodes.map( ( n ) => {
								if ( n.resourceId === node.resourceId ) {
									changed = true;
									return {
										...n,
										children: ( n.children || [] ).concat(
											nodesToAdd
										),
									};
								}
								if ( n.children && n.children.length ) {
									const updatedChildren = addChildren(
										n.children
									);
									if ( updatedChildren !== n.children ) {
										changed = true;
										return {
											...n,
											children: updatedChildren,
										};
									}
								}
								return n;
							} );
							return changed ? updated : nodes;
						};

						dispatch( {
							type: ACTION_TYPES.UPDATE_FOLDER_TREE_NODE,
							payload: addChildren( folderTreeData ),
						} );
					}
				} )
				.catch( ( err ) => {
					const { message, isApiKeyError } = extractErrorInfo( err );

					if ( message ) {
						dispatch( {
							type: ACTION_TYPES.SET_CURRENT_ACCOUNT_ERROR,
							payload: message,
						} );

						if ( isApiKeyError ) {
							dispatch( {
								type: ACTION_TYPES.SET_API_KEY_STATUS,
								payload: false,
							} );
						}
					}
				} )
				.finally( () => {
					// Remove node from loading state
					dispatch( {
						type: ACTION_TYPES.REMOVE_LOADING_NODE,
						payload: node.resourceId,
					} );
				} );
		} else {
			// Remove node from loading state immediately if no API call needed
			dispatch( {
				type: ACTION_TYPES.REMOVE_LOADING_NODE,
				payload: node.resourceId,
			} );
		}
	}

	// Helper function to check if embed code exists
	const hasEmbedCode = ( code ) => {
		return Boolean( code && String( code ).trim() );
	};

	// Check if embed codes are available
	const hasFullHeight =
		hasEmbedCode( attributes.fullHeightEmbedCode ) ||
		hasEmbedCode( currentEmbedCodes?.fullHeightEmbedCode );
	const hasScrolling =
		hasEmbedCode( attributes.scrollableEmbedCode ) ||
		hasEmbedCode( currentEmbedCodes?.scrollableEmbedCode );

	// Always use saved attributes for block preview to ensure it doesn't change until confirmed
	// This ensures the preview stays visible and unchanged when modal opens or new item is selected
	const previewEmbedCodes = {
		fullHeightEmbedCode: attributes.fullHeightEmbedCode || '',
		scrollableEmbedCode: attributes.scrollableEmbedCode || '',
	};
	const previewEmbedOption = attributes.selectedOption || EMBED_OPTIONS.FULL;

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

	// Get current icon for toolbar button - use saved option from attributes
	const toolbarEmbedOption =
		attributes.selectedOption || selectedEmbedOption || EMBED_OPTIONS.FULL;
	const getCurrentIcon = () => {
		return toolbarEmbedOption === EMBED_OPTIONS.FULL ? (
			<FullHeightIcon isActive={ false } />
		) : (
			<ScrollingIcon isActive={ false } />
		);
	};

	return (
		<>
			{ /* Toolbar Controls */ }
			{ showToolbar && (
				<BlockControls>
					<ToolbarGroup>
						<DropdownMenu
							icon={ getCurrentIcon() }
							label={ __( 'Change embed type', 'ceros' ) }
							toggleProps={ {
								'aria-label': __(
									'Change embed type',
									'ceros'
								),
							} }
						>
							{ ( { onClose } ) => {
								const menuItemTextStyle = {
									display: 'flex',
									flexDirection: 'column',
									alignItems: 'flex-start',
								};
								const isFullSelected =
									toolbarEmbedOption === EMBED_OPTIONS.FULL;
								const isScrollSelected =
									toolbarEmbedOption === EMBED_OPTIONS.SCROLL;
								const fullTitleStyle = {
									fontWeight: 500,
									color: isFullSelected
										? '#2271b1'
										: 'inherit',
								};
								const scrollTitleStyle = {
									fontWeight: 500,
									color: isScrollSelected
										? '#2271b1'
										: 'inherit',
								};
								const descriptionStyle = {
									fontSize: '12px',
									color: '#757575',
									marginTop: '2px',
								};

								return (
									<MenuGroup>
										<MenuItem
											icon={
												<FullHeightIcon
													isActive={ isFullSelected }
												/>
											}
											onClick={ () => {
												if ( hasFullHeight ) {
													dispatch( {
														type: ACTION_TYPES.SET_EMBED_OPTION,
														payload:
															EMBED_OPTIONS.FULL,
													} );
													setAttributes( {
														selectedOption:
															EMBED_OPTIONS.FULL,
													} );
												}
												onClose();
											} }
											isSelected={ isFullSelected }
											disabled={ ! hasFullHeight }
										>
											<div style={ menuItemTextStyle }>
												<span style={ fullTitleStyle }>
													{ __(
														'Full height',
														'ceros'
													) }
												</span>
												<span
													style={ descriptionStyle }
												>
													{ __(
														'Scrolls with the page.',
														'ceros'
													) }
												</span>
											</div>
										</MenuItem>
										<MenuItem
											icon={
												<ScrollingIcon
													isActive={
														isScrollSelected
													}
												/>
											}
											onClick={ () => {
												if ( hasScrolling ) {
													dispatch( {
														type: ACTION_TYPES.SET_EMBED_OPTION,
														payload:
															EMBED_OPTIONS.SCROLL,
													} );
													setAttributes( {
														selectedOption:
															EMBED_OPTIONS.SCROLL,
													} );
												}
												onClose();
											} }
											isSelected={ isScrollSelected }
											disabled={ ! hasScrolling }
										>
											<div style={ menuItemTextStyle }>
												<span
													style={ scrollTitleStyle }
												>
													{ __(
														'Scrolling',
														'ceros'
													) }
												</span>
												<span
													style={ descriptionStyle }
												>
													{ __(
														'Scrolls in its own set area.',
														'ceros'
													) }
												</span>
											</div>
										</MenuItem>
									</MenuGroup>
								);
							} }
						</DropdownMenu>
					</ToolbarGroup>
					<ToolbarGroup>
						<ToolbarButton
							onClick={ () =>
								dispatch( { type: ACTION_TYPES.OPEN_MODAL } )
							}
							label={ __( 'Change experience', 'ceros' ) }
						>
							{ __( 'Replace', 'ceros' ) }
						</ToolbarButton>
					</ToolbarGroup>
				</BlockControls>
			) }

			{ /* Sidebar Controls */ }
			{ hasExperience && (
				<SidebarControls
					selectedExperienceName={ selectedExperienceName }
					attributes={ attributes }
					selectedEmbedOption={ selectedEmbedOption }
					hasFullHeight={ hasFullHeight }
					hasScrolling={ hasScrolling }
					dispatch={ dispatch }
					setAttributes={ setAttributes }
				/>
			) }
			<div { ...useBlockProps() }>
				{ /* Main Block Display */ }
				<div className="ceros-block">
					{ /* Show API key error immediately if present */ }
					{ apiKeyConfigured === false ||
					( currentAccountError &&
						( currentAccountError.includes(
							'API key is not set'
						) ||
							currentAccountError.includes( 'api key' ) ||
							currentAccountError.includes( 'API key' ) ||
							currentAccountError.includes(
								'ceros_api_key_missing'
							) ||
							currentAccountError.includes(
								'Ceros API key'
							) ) ) ? (
						<div className="ceros-block__error">
							<div className="ceros-block__error-content">
								<svg
									className="ceros-block__error-icon"
									xmlns="http://www.w3.org/2000/svg"
									width="24"
									height="24"
									viewBox="0 0 24 24"
									fill="none"
									stroke="currentColor"
									strokeWidth="2"
									strokeLinecap="round"
									strokeLinejoin="round"
								>
									<circle cx="12" cy="12" r="10"></circle>
									<line x1="15" y1="9" x2="9" y2="15"></line>
									<line x1="9" y1="9" x2="15" y2="15"></line>
								</svg>
								<div>
									<h3>Ceros API Key Required</h3>
									<p>
										The Ceros API key has not been set.
										Please configure your API key in the
										Ceros settings to use this block.
									</p>
									<a
										href={ getCerosSettingsUrl() }
										className="ceros-block__settings-link"
										target="_blank"
										rel="noopener noreferrer"
									>
										Go to Ceros Settings
									</a>
								</div>
							</div>
						</div>
					) : selectedExperienceName ||
					  attributes.experienceName ||
					  attributes.fullHeightEmbedCode ||
					  attributes.scrollableEmbedCode ? (
						<div className="ceros-block__selected">
							<CerosPreview
								currentEmbedCodes={ previewEmbedCodes }
								selectedEmbedOption={ previewEmbedOption }
							/>
						</div>
					) : isLoadingTree ? (
						<div className="ceros-block__loading">
							<svg
								className="ceros-block__loading-icon"
								xmlns="http://www.w3.org/2000/svg"
								width="24"
								height="24"
								viewBox="0 0 24 24"
								fill="none"
								stroke="currentColor"
								strokeWidth="2"
								strokeLinecap="round"
								strokeLinejoin="round"
							>
								<path d="M21 12a9 9 0 1 1-6.219-8.56" />
							</svg>
						</div>
					) : (
						! isModalOpen &&
						! currentAccountError &&
						apiKeyConfigured !== false && (
							<div className="ceros-block__empty">
								<h3>No Experience Selected</h3>
								<p>
									Click the button below to browse and select
									a Ceros experience.
								</p>
								<button
									className="ceros-block__button ceros-block__button--primary"
									onClick={ () =>
										dispatch( {
											type: ACTION_TYPES.OPEN_MODAL,
										} )
									}
								>
									Browse Experiences
								</button>
							</div>
						)
					) }
				</div>
			</div>
			<CerosModal
				isOpen={ isModalOpen }
				onClose={ () => dispatch( { type: ACTION_TYPES.CLOSE_MODAL } ) }
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
					setSelectedEmbedOption: ( option ) =>
						dispatch( {
							type: ACTION_TYPES.SET_EMBED_OPTION,
							payload: option,
						} ),
					setAttributes,
					selectedExperienceName,
				} }
			/>
		</>
	);
}
