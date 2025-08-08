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
import { useBlockProps } from '@wordpress/block-editor';

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './editor.scss';

import { useEffect, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

// Simple Tree View Component
const TreeNode = ({ node, onNodeClick, expandedNodes, loadingNodes, selectedNodeId }) => {
	const isExpanded = expandedNodes.has( node.resourceId );
	const childArray = node.children || [];
	const hasChildren = childArray.length > 0;
	const isLoading = loadingNodes.has( node.resourceId );
	const isSelected = selectedNodeId === node.resourceId;
	
	// Determine if this node should show expand/collapse functionality
	// Show arrow for folders (not experiences) or for experiences that have children
	const shouldShowArrow = !node.isExperience || hasChildren;

	return (
		<div className={node.isExperience ? "ceros-block__file" : "ceros-block__folder"}>
			<div
				className={`ceros-block__item ${isSelected ? 'ceros-block__item--selected' : ''}`}
				onClick={() => onNodeClick(node)}
				data-resource-id={node.resourceId}
			>
				{shouldShowArrow && (isLoading ?
					<svg className="ceros-block__item-icon -loading" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
					: isExpanded ?
					<svg className="ceros-block__item-icon -arrow" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
					:
					<svg className="ceros-block__item-icon -arrow" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m9 18 6-6-6-6"></path></svg>)}
					{node.isExperience ? (
						<svg className="ceros-block__item-icon -file" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path><path d="M14 2v4a2 2 0 0 0 2 2h4"></path><path d="M10 9H8"></path><path d="M16 13H8"></path><path d="M16 17H8"></path></svg>
					) : (
						<svg className="ceros-block__item-icon -folder" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"></path></svg>
					)}
					<span className="ceros-block__item-name">{node.name}</span>
			</div>
			{isExpanded && hasChildren && (
				<div>
					{childArray.map( ( child, index ) => (
						<TreeNode
							key={child.resourceId || index}
							node={child}
							onNodeClick={onNodeClick}
							expandedNodes={expandedNodes}
							loadingNodes={loadingNodes}
							selectedNodeId={selectedNodeId}
						/>
					) )}
				</div>
			)}
		</div>
	);
};

const TreeView = ({ data, onNodeClick, expandedNodes, loadingNodes, selectedNodeId }) => {
	if (!data || !Array.isArray(data)) {
		return <p>No tree data available</p>;
	}

	return (
		<div className="ceros-block__files">
			{data.map((node, index) => (
				<TreeNode
					key={node.resourceId || index}
					node={node}
					onNodeClick={onNodeClick}
					expandedNodes={expandedNodes}
					loadingNodes={loadingNodes}
					selectedNodeId={selectedNodeId}
				/>
			))}
		</div>
	);
};

/**
 * Recursively fetch experiences for all nodes in the folder tree
 */
async function fetchExperiencesForAllNodes( nodes ) {
	if ( !Array.isArray( nodes ) ) {
		return nodes;
	}

	const processedNodes = await Promise.all( nodes.map( async ( node ) => {
		let processedNode = { ...node };

		// For folder nodes (not experiences), fetch their experiences
		if ( !node.isExperience ) {
			try {
				const experiencesResponse = await apiFetch( { path: `/ceros/v1/folder/${node.resourceId}/experiences` } );
				
				let experiences = [];
				if ( Array.isArray( experiencesResponse?.body ) ) {
					experiences = experiencesResponse.body;
				} else if ( Array.isArray( experiencesResponse?.body?.items ) ) {
					experiences = experiencesResponse.body.items;
				} else if ( Array.isArray( experiencesResponse?.body?.data ) ) {
					experiences = experiencesResponse.body.data;
				}

				// Filter valid experiences
				const validExperiences = experiences.filter( ( exp ) => (
					exp.status === 'published' &&
					exp.isTemplate === false &&
					exp.isFlexExperience === false &&
					exp.isPasswordProtected === false &&
					exp.isSSOProtected === false
				) );

				// Convert experiences to child nodes
				const experienceNodes = validExperiences.map( ( exp ) => ( {
					name: exp.name || exp.title || 'Experience',
					resourceId: exp.resourceId || exp.id || exp.experienceId || Math.random().toString(36).substr(2,5),
					children: [],
					isExperience: true,
				} ) );

				// Add experiences to the existing children (if any)
				processedNode.children = [ ...( node.children || [] ), ...experienceNodes ];
			} catch ( error ) {
				console.error( `Error fetching experiences for folder ${node.resourceId}:`, error );
				// Keep the original children if error occurs
				processedNode.children = node.children || [];
			}
		}

		// Recursively process children (both original children and newly added experiences)
		if ( processedNode.children && processedNode.children.length > 0 ) {
			processedNode.children = await fetchExperiencesForAllNodes( processedNode.children );
		}

		return processedNode;
	} ) );

	return processedNodes;
}

/**
 * Get the Ceros settings URL, handling various WordPress admin URL configurations
 */
function getCerosSettingsUrl() {
	// Method 1: Use server-provided settings URL (most reliable)
	if (window.cerosBlockData && window.cerosBlockData.settingsUrl) {
		return window.cerosBlockData.settingsUrl;
	}
	
	// Method 2: Check legacy cerosAdmin data
	if (window.cerosAdmin && window.cerosAdmin.settingsUrl) {
		return window.cerosAdmin.settingsUrl;
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
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {Element} Element to render.
 */
export default function Edit( { attributes, setAttributes, clientId } ) {
	const [ currentAccountResult, setCurrentAccountResult ] = useState( null );
	const [ folderTreeData, setFolderTreeData ] = useState( null );
	const [ currentAccountError, setCurrentAccountError ] = useState( null );
	const [ folderTreeError, setFolderTreeError ] = useState( null );
	// Hold expanded node ids so we can toggle UI state easily
	const [ expandedNodes, setExpandedNodes ] = useState( new Set() );
	const [ currentEmbedCodes, setCurrentEmbedCodes ] = useState( {
		fullHeightEmbedCode: attributes.fullHeightEmbedCode || '',
		scrollableEmbedCode: attributes.scrollableEmbedCode || ''
	} );
	const [ selectedEmbedOption, setSelectedEmbedOption ] = useState( attributes.selectedOption || 'full' );
	const [ isLoadingTree, setIsLoadingTree ] = useState( true );
	const [ loadingNodes, setLoadingNodes ] = useState( new Set() );
	// Do not auto-open the modal on load; only open when user clicks the button
	const [ isModalOpen, setIsModalOpen ] = useState( false );
	const [ selectedExperienceName, setSelectedExperienceName ] = useState( attributes.experienceName || '' );
	const [ selectedNodeId, setSelectedNodeId ] = useState( null );
	const [ apiKeyConfigured, setApiKeyConfigured ] = useState( null ); // null = checking, true = configured, false = not configured
	const [ hasOpenedFromInsert, setHasOpenedFromInsert ] = useState( false );

	// Determine if this block is currently selected (used to detect insertion)
	const isSelected = useSelect( ( select ) => {
		const selectedId = select( 'core/block-editor' ).getSelectedBlockClientId?.();
		return selectedId === clientId;
	}, [ clientId ] );

	// If the block is newly inserted (selected) and not configured, open modal once.
	useEffect(() => {
		const hasCodes = Boolean(attributes.fullHeightEmbedCode) || Boolean(attributes.scrollableEmbedCode);
		if (!hasOpenedFromInsert && isSelected && !hasCodes) {
			setIsModalOpen(true);
			setHasOpenedFromInsert(true);
		}
	}, [isSelected, hasOpenedFromInsert, attributes.fullHeightEmbedCode, attributes.scrollableEmbedCode]);

	// Hide toolbar when modal is open
	useEffect(() => {
		if (isModalOpen) {
			document.body.classList.add('ceros-modal-open');
		} else {
			document.body.classList.remove('ceros-modal-open');
		}

		// Cleanup function to remove class when component unmounts
		return () => {
			document.body.classList.remove('ceros-modal-open');
		};
	}, [isModalOpen]);

	// Initialize currentEmbedCodes from attributes if they exist
	useEffect(() => {
		if (attributes.fullHeightEmbedCode || attributes.scrollableEmbedCode) {
			setCurrentEmbedCodes({
				fullHeightEmbedCode: attributes.fullHeightEmbedCode || '',
				scrollableEmbedCode: attributes.scrollableEmbedCode || ''
			});
		}
	}, [attributes.fullHeightEmbedCode, attributes.scrollableEmbedCode]);

	useEffect( () => {
		// First, check if API key is configured before making any API calls
		apiFetch( { path: '/ceros/v1/api-key-status' } )
			.then( ( statusRes ) => {
				console.log('API key status response:', statusRes);
				
				if ( !statusRes.configured ) {
					// API key is not configured, show error immediately
					setApiKeyConfigured( false );
					setCurrentAccountError( statusRes.message || 'Ceros API key is not set. Please add it in the Ceros settings first.' );
					setIsLoadingTree( false );
					return; // Stop here, don't make further API calls
				}
				
				// API key is configured, proceed with getting current account
				setApiKeyConfigured( true );
				return apiFetch( { path: '/ceros/v1/current-account' } );
			} )
			.then( ( res ) => {
				// If we don't have a response (API key was not configured), skip this
				if ( !res ) return;
				
				setCurrentAccountResult( res );

				// Extract accountResourceId from the response - try different possible field names
				const accountResourceId = res?.body?.accountResourceId ||
										  res?.body?.accountId ||
										  res?.body?.id ||
										  res?.body?.resourceId;

				console.log('Current account response:', res);
				console.log('Extracted accountResourceId:', accountResourceId);

				if ( accountResourceId ) {
					// Then get the folder tree using the accountResourceId
					return apiFetch( { path: `/ceros/v1/folder-tree/${accountResourceId}` } );
				} else {
					throw new Error( `accountResourceId not found in current account response. Available fields: ${Object.keys(res?.body || {}).join(', ')}` );
				}
			} )
			.then( ( folderRes ) => {
				// If we don't have a response (API key was not configured), skip this
				if ( !folderRes ) return;
				
				// Expecting folderRes.body to be an array of folder nodes
				const folderTreeNodes = folderRes?.body || [];
				
				// Now fetch experiences for each node in the folder tree
				return fetchExperiencesForAllNodes( folderTreeNodes ).then( ( nodesWithExperiences ) => {
					setFolderTreeData( nodesWithExperiences );
					setIsLoadingTree( false );
				} );
			} )
			.catch( ( err ) => {
				setIsLoadingTree( false );
				console.error('API Error:', err);
				
				// Extract error message from different possible error structures
				let errorMessage = '';
				
				// Check for 403 Forbidden response first (most specific)
				if ( err.code === 403 && err.error ) {
					errorMessage = err.error;
					setApiKeyConfigured( false );
				}
				// Check for nested error structure 
				else if ( err.data && err.data.code === 403 && err.data.error ) {
					errorMessage = err.data.error;
					setApiKeyConfigured( false );
				}
				// Check for direct error property
				else if ( err.error ) {
					errorMessage = err.error;
				}
				// Check for message property
				else if ( err.message ) {
					errorMessage = err.message;
				}
				// Check for nested data error
				else if ( err.data && err.data.error ) {
					errorMessage = err.data.error;
				}
				// Check for string error
				else if ( typeof err === 'string' ) {
					errorMessage = err;
				}
				else {
					errorMessage = 'Unknown error occurred';
				}
				
				// Check if this is an API key related error (for other error patterns)
				if ( errorMessage.includes('API key is not set') ||
					 errorMessage.includes('api key') ||
					 errorMessage.includes('API key') ||
					 errorMessage.includes('ceros_api_key_missing') ||
					 errorMessage.includes('Ceros API key') ) {
					setApiKeyConfigured( false );
				}
				
				if ( !currentAccountResult ) {
					setCurrentAccountError( errorMessage );
				} else {
					setFolderTreeError( errorMessage );
				}
			} );
	}, [] );

	// Handle click on tree node
	function handleNodeClick( node ) {
		const newExpanded = new Set( expandedNodes );
		if ( newExpanded.has( node.resourceId ) ) {
			newExpanded.delete( node.resourceId );
			setExpandedNodes( newExpanded );
			return;
		}

		newExpanded.add( node.resourceId );
		setExpandedNodes( newExpanded );

		// For experience nodes, we need to fetch embed codes
		if ( node.isExperience ) {
			// If node already has embed codes loaded, don't refetch
			if ( node.embedCodes ) {
				// Set the embed codes for preview
				const replaceDomains = ( embedCode ) => {
					if ( ! embedCode ) return '';
					return embedCode
						.replace( /https:\/\/undefined/g, 'https://wordpresspoc.view.cerosdev.com' )
						.replace( /"undefined"/g, '"wordpresspoc.view.cerosdev.com"' );
				};

				const codesWithDomains = {
					fullHeightEmbedCode: replaceDomains( node.embedCodes.fullHeightEmbedCode ),
					scrollableEmbedCode: replaceDomains( node.embedCodes.scrollableEmbedCode )
				};
				setCurrentEmbedCodes( codesWithDomains );
				setSelectedExperienceName( node.name );
				setSelectedNodeId( node.resourceId );
				setSelectedEmbedOption( 'full' );
				return;
			}

			// Add node to loading state for embed codes
			const newLoadingNodes = new Set( loadingNodes );
			newLoadingNodes.add( node.resourceId );
			setLoadingNodes( newLoadingNodes );
		} else {
			// For folder nodes, experiences should already be loaded from initial fetch
			// If node already has children loaded, don't refetch
			if ( node.children && node.children.length > 0 ) {
				return;
			}

			// Add node to loading state only if we need to fetch experiences
			const newLoadingNodes = new Set( loadingNodes );
			newLoadingNodes.add( node.resourceId );
			setLoadingNodes( newLoadingNodes );
		}

		// Determine endpoint based on node type
		const endpoint = node.isExperience ? `/ceros/v1/experiences/${node.resourceId}/embed-codes` : `/ceros/v1/folder/${node.resourceId}/experiences`;

		// Only make API call if we actually need to fetch data
		if ( ( node.isExperience && !node.embedCodes ) || ( !node.isExperience && ( !node.children || node.children.length === 0 ) ) ) {
			apiFetch( { path: endpoint } )
				.then( ( res ) => {
					if ( node.isExperience ) {
						// Function to replace undefined domains with correct Ceros domain
						const replaceDomains = ( embedCode ) => {
							if ( ! embedCode ) return '';
							return embedCode
								.replace( /https:\/\/undefined/g, 'https://wordpresspoc.view.cerosdev.com' )
								.replace( /"undefined"/g, '"wordpresspoc.view.cerosdev.com"' );
						};

						// store embed codes with domain replacement for preview
						const codes = res?.body || null;
						const codesWithDomains = codes ? {
							fullHeightEmbedCode: replaceDomains( codes.fullHeightEmbedCode ),
							scrollableEmbedCode: replaceDomains( codes.scrollableEmbedCode )
						} : null;
						setCurrentEmbedCodes( codesWithDomains );
						setSelectedExperienceName( node.name );
						setSelectedNodeId( node.resourceId );
						setSelectedEmbedOption( 'full' );
						
						// We fetched embed codes, attach to node and stop further processing
						setFolderTreeData( ( prev ) => {
							function attachCodes( nodes ) {
								return nodes.map( ( n ) => {
									if ( n.resourceId === node.resourceId ) {
										return { ...n, embedCodes: res?.body };
									}
									if ( n.children && n.children.length ) {
										return { ...n, children: attachCodes( n.children ) };
									}
									return n;
								} );
							}
							return attachCodes( prev );
						} );
						return; // done for experience nodes
					}

					if ( ! node.isExperience ) {
						let experiences = [];
						if ( Array.isArray( res?.body ) ) {
							experiences = res.body;
						} else if ( Array.isArray( res?.body?.items ) ) {
							experiences = res.body.items;
						} else if ( Array.isArray( res?.body?.data ) ) {
							experiences = res.body.data;
						} else {
							console.warn( 'Unexpected experiences response shape', res.body );
						}

						const validExperiences = experiences.filter( ( exp ) => (
							exp.status === 'published' &&
							exp.isTemplate === false &&
							exp.isFlexExperience === false &&
							exp.isPasswordProtected === false &&
							exp.isSSOProtected === false
						) );

						const childNodes = validExperiences.map( ( exp ) => ( {
							name: exp.name || exp.title || 'Experience',
							resourceId: exp.resourceId || exp.id || exp.experienceId || Math.random().toString(36).substr(2,5),
							children: [],
							isExperience: true,
						} ) );

						setFolderTreeData( ( prev ) => {
							function addChildren( nodes ) {
								return nodes.map( ( n ) => {
									if ( n.resourceId === node.resourceId ) {
										return { ...n, children: ( n.children || [] ).concat( childNodes ) };
									}
									if ( n.children && n.children.length ) {
										return { ...n, children: addChildren( n.children ) };
									}
									return n;
								} );
							}
							return addChildren( prev );
						} );
					}
				} )
				.catch( ( err ) => {
					console.error( 'Error fetching experiences:', err );
					
					// Extract error message with the same logic as the main API call
					let errorMessage = '';
					
					// Check for 403 Forbidden response first (most specific)
					if ( err.code === 403 && err.error ) {
						errorMessage = err.error;
						setApiKeyConfigured( false );
					}
					// Check for nested error structure 
					else if ( err.data && err.data.code === 403 && err.data.error ) {
						errorMessage = err.data.error;
						setApiKeyConfigured( false );
					}
					// Check for direct error property
					else if ( err.error ) {
						errorMessage = err.error;
					}
					// Check for message property
					else if ( err.message ) {
						errorMessage = err.message;
					}
					// Check for nested data error
					else if ( err.data && err.data.error ) {
						errorMessage = err.data.error;
					}
					// Check for string error
					else if ( typeof err === 'string' ) {
						errorMessage = err;
					}
					
					// If we have an error message, show it
					if ( errorMessage ) {
						setCurrentAccountError( errorMessage );
						
						// Check if this is an API key related error
						if ( errorMessage.includes('API key is not set') ||
							 errorMessage.includes('api key') ||
							 errorMessage.includes('API key') ||
							 errorMessage.includes('ceros_api_key_missing') ||
							 errorMessage.includes('Ceros API key') ) {
							setApiKeyConfigured( false );
						}
					}
				} )
				.finally( () => {
					// Remove node from loading state
					const newLoadingNodes = new Set( loadingNodes );
					newLoadingNodes.delete( node.resourceId );
					setLoadingNodes( newLoadingNodes );
				} );
		} else {
			// Remove node from loading state immediately if no API call needed
			const newLoadingNodes = new Set( loadingNodes );
			newLoadingNodes.delete( node.resourceId );
			setLoadingNodes( newLoadingNodes );
		}
	}

	return (
		<div { ...useBlockProps() }>
			{/* Main Block Display */}
			<div className="ceros-block">
							{/* Show API key error immediately if present */}
			{(apiKeyConfigured === false || (currentAccountError && (
				currentAccountError.includes('API key is not set') ||
				currentAccountError.includes('api key') ||
				currentAccountError.includes('API key') ||
				currentAccountError.includes('ceros_api_key_missing') ||
				currentAccountError.includes('Ceros API key')
			))) ? (
					<div className="ceros-block__error">
						<div className="ceros-block__error-content">
							<svg className="ceros-block__error-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
								<circle cx="12" cy="12" r="10"></circle>
								<line x1="15" y1="9" x2="9" y2="15"></line>
								<line x1="9" y1="9" x2="15" y2="15"></line>
							</svg>
							<div>
								<h3>Ceros API Key Required</h3>
								<p>The Ceros API key has not been set. Please configure your API key in the Ceros settings to use this block.</p>
								<a href={getCerosSettingsUrl()} className="ceros-block__settings-link" target="_blank" rel="noopener noreferrer">
									Go to Ceros Settings
								</a>
							</div>
						</div>
					</div>
				) : (selectedExperienceName || attributes.fullHeightEmbedCode || attributes.scrollableEmbedCode) && !isModalOpen ? (
					<div className="ceros-block__selected">
						<h2>Selected Experience: {selectedExperienceName || 'Previously Configured Experience'}</h2>
						<div className="ceros-block__embed-options">
							<div>
								<label className="ceros-block__embed-options-label">
									<input
										type="radio"
										value="full"
										checked={ selectedEmbedOption === 'full' }
										onChange={ () => { setSelectedEmbedOption( 'full' ); setAttributes( { selectedOption: 'full' } ); } }
									/>
									<span>
										<span>Full height</span>
										<span className="ceros-block__embed-options-description">This option scrolls naturally with your parent page without additional scrollbars.</span>
									</span>
								</label>
								<label className="ceros-block__embed-options-label">
									<input
										type="radio"
										value="scroll"
										checked={ selectedEmbedOption === 'scroll' }
										onChange={ () => { setSelectedEmbedOption( 'scroll' ); setAttributes( { selectedOption: 'scroll' } ); } }
									/>
									<span>
										<span>Scrolling</span>
										<span className="ceros-block__embed-options-description">Displays your content in a viewport with internal scrollbars.</span>
									</span>
								</label>
							</div>
						</div>

						{/* Preview Section */}
						{currentEmbedCodes && (
							<div className="ceros-block__preview-section">
								<h2>Preview</h2>
								{selectedEmbedOption === 'full' && currentEmbedCodes?.fullHeightEmbedCode && (
									<div className="ceros-block__preview" dangerouslySetInnerHTML={{ __html: currentEmbedCodes.fullHeightEmbedCode }} />
								)}
								{selectedEmbedOption === 'scroll' && currentEmbedCodes?.scrollableEmbedCode && (
									<div className="ceros-block__preview" dangerouslySetInnerHTML={{ __html: currentEmbedCodes.scrollableEmbedCode }} />
								)}
							</div>
						)}

						<div className="ceros-block__actions">
							<button 
								className="ceros-block__button ceros-block__button--primary"
								onClick={() => setIsModalOpen(true)}
							>
								Change Experience
							</button>
						</div>
					</div>
				) : !isModalOpen && !currentAccountError && apiKeyConfigured !== false && (
					<div className="ceros-block__empty">
						<h3>No Experience Selected</h3>
						<p>Click the button below to browse and select a Ceros experience.</p>
						<button 
							className="ceros-block__button ceros-block__button--primary"
							onClick={() => setIsModalOpen(true)}
						>
							Browse Experiences
						</button>
					</div>
				)}
			</div>

			{/* Modal/Popup */}
			{isModalOpen && (
				<div className="ceros-block__modal-overlay" onClick={() => setIsModalOpen(false)}>
					<div className="ceros-block__modal" onClick={(e) => e.stopPropagation()}>
						<div className="ceros-block__modal-header">
							<h2>Browse Ceros Content</h2>
							<button
								className="ceros-block__modal-close"
								onClick={() => setIsModalOpen(false)}
							>
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x h-4 w-4"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
							</button>
						</div>

						<div className="ceros-block__modal-body">
							{ currentAccountError && <p style={ { color: 'red' } }>{ currentAccountError }</p> }
							{ folderTreeError && <p style={ { color: 'red' } }>{ folderTreeError }</p> }

							{/* { currentAccountResult && (
								<pre style={ { fontSize: '0.8rem', maxHeight: '300px', overflow: 'auto' } }>
									{ JSON.stringify( currentAccountResult, null, 2 ) }
								</pre>
							) } */}

							{/* { folderTreeData && (
								<pre style={ { fontSize: '0.8rem', maxHeight: '300px', overflow: 'auto' } }>
									{ JSON.stringify( folderTreeData, null, 2 ) }
								</pre>
							) } */}

							{ isLoadingTree && (
								<div className="ceros-block__loading">
									<svg className="ceros-block__loading-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
								</div>
							) }

							{ folderTreeData && !isLoadingTree && (
								<TreeView
									data={folderTreeData}
									onNodeClick={handleNodeClick}
									expandedNodes={expandedNodes}
									loadingNodes={loadingNodes}
									selectedNodeId={selectedNodeId}
								/>
							) }
						</div>

						{/* Embed code section in modal */}
						{ currentEmbedCodes && (
							<div className="ceros-block__modal-footer">
								<div className="ceros-block__embed-options">
									<div>
										<label className="ceros-block__embed-options-label">
											<input
												type="radio"
												value="full"
												checked={ selectedEmbedOption === 'full' }
												onChange={ () => setSelectedEmbedOption( 'full' ) }
											/>
											<span>
												<span>Full height</span>
												<span className="ceros-block__embed-options-description">This option scrolls naturally with your parent page without additional scrollbars.</span>
											</span>
										</label>
										<label className="ceros-block__embed-options-label">
											<input
												type="radio"
												value="scroll"
												checked={ selectedEmbedOption === 'scroll' }
												onChange={ () => setSelectedEmbedOption( 'scroll' ) }
											/>
											<span>
												<span>Scrolling</span>
												<span className="ceros-block__embed-options-description">Displays your content in a viewport with internal scrollbars.</span>
											</span>
										</label>
									</div>
								</div>
								<div className="ceros-block__modal-actions">
									<button 
										className="ceros-block__button ceros-block__button--secondary"
										onClick={() => setIsModalOpen(false)}
									>
										Cancel
									</button>
									<button
										className="ceros-block__button ceros-block__button--primary"
										disabled={!selectedNodeId}
										onClick={() => {
											if (currentEmbedCodes) {
												setAttributes({
													fullHeightEmbedCode: currentEmbedCodes.fullHeightEmbedCode,
													scrollableEmbedCode: currentEmbedCodes.scrollableEmbedCode,
													selectedOption: selectedEmbedOption,
													experienceName: selectedExperienceName
												});
												setIsModalOpen(false);
											}
										}}
									>
										Add Experience
									</button>
								</div>
							</div>
						) }
					</div>
				</div>
			)}
		</div>
	);
}
