/**
 * Modal component for browsing and selecting Ceros experiences
 */
import { __ } from '@wordpress/i18n';
import { createPortal } from '@wordpress/element';
import { EMBED_OPTIONS } from '../constants';

// Simple Tree View Component
const TreeNode = ( {
	node,
	onNodeClick,
	expandedNodes,
	loadingNodes,
	selectedNodeId,
} ) => {
	const isExpanded = expandedNodes.has( node.resourceId );
	const childArray = node.children || [];
	const hasChildren = childArray.length > 0;
	const isLoading = loadingNodes.has( node.resourceId );
	// Only mark as selected if it's an experience node and matches the selectedNodeId
	// Convert both to strings for reliable comparison
	const isSelected =
		node.isExperience &&
		selectedNodeId !== null &&
		selectedNodeId !== undefined &&
		String( selectedNodeId ) === String( node.resourceId );
	const isEmptyMessage = node.isEmptyMessage || false;

	// Determine if this node should show expand/collapse functionality
	// Show arrow for folders (not experiences) or for experiences that have children
	const shouldShowArrow = ! node.isExperience || hasChildren;

	// Empty message nodes should be styled like files but not clickable
	const isFileStyle = node.isExperience || isEmptyMessage;

	return (
		<div
			className={
				isFileStyle ? 'ceros-block__file' : 'ceros-block__folder'
			}
		>
			<div
				className={ `ceros-block__item ${
					isSelected ? 'ceros-block__item--selected' : ''
				} ${
					isEmptyMessage ? 'ceros-block__item--empty-message' : ''
				}` }
				onClick={
					isEmptyMessage
						? undefined
						: ( e ) => {
								e.stopPropagation();
								onNodeClick( node );
						  }
				}
				data-resource-id={ node.resourceId }
				style={
					isEmptyMessage ? { cursor: 'default', opacity: 0.6 } : {}
				}
			>
				{ shouldShowArrow &&
					! isEmptyMessage &&
					( isLoading ? (
						<svg
							className="ceros-block__item-icon -loading"
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
							<path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
						</svg>
					) : isExpanded ? (
						<svg
							className="ceros-block__item-icon -arrow"
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
							<path d="m6 9 6 6 6-6"></path>
						</svg>
					) : (
						<svg
							className="ceros-block__item-icon -arrow"
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
							<path d="m9 18 6-6-6-6"></path>
						</svg>
					) ) }
				{ isEmptyMessage ? (
					<svg
						className="ceros-block__item-icon -file"
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
						<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
						<path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
						<path d="M10 9H8"></path>
						<path d="M16 13H8"></path>
						<path d="M16 17H8"></path>
					</svg>
				) : node.isExperience ? (
					<svg
						className="ceros-block__item-icon -file"
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
						<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
						<path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
						<path d="M10 9H8"></path>
						<path d="M16 13H8"></path>
						<path d="M16 17H8"></path>
					</svg>
				) : (
					<svg
						className="ceros-block__item-icon -folder"
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
						<path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"></path>
					</svg>
				) }
				<span className="ceros-block__item-name">{ node.name }</span>
			</div>
			{ isExpanded && hasChildren && (
				<div>
					{ childArray.map( ( child, index ) => (
						<TreeNode
							key={ child.resourceId || index }
							node={ child }
							onNodeClick={ onNodeClick }
							expandedNodes={ expandedNodes }
							loadingNodes={ loadingNodes }
							selectedNodeId={ selectedNodeId }
						/>
					) ) }
				</div>
			) }
		</div>
	);
};

const TreeView = ( {
	data,
	onNodeClick,
	expandedNodes,
	loadingNodes,
	selectedNodeId,
} ) => {
	if ( ! data || ! Array.isArray( data ) ) {
		return <p>{ __( 'No tree data available', 'ceros' ) }</p>;
	}

	return (
		<div className="ceros-block__files">
			{ data.map( ( node, index ) => (
				<TreeNode
					key={ node.resourceId || index }
					node={ node }
					onNodeClick={ onNodeClick }
					expandedNodes={ expandedNodes }
					loadingNodes={ loadingNodes }
					selectedNodeId={ selectedNodeId }
				/>
			) ) }
		</div>
	);
};

// Modal component rendered via portal so overlay can sit above entire editor chrome
export const CerosModal = ( { isOpen, onClose, state } ) => {
	if ( ! isOpen || typeof document === 'undefined' ) {
		return null;
	}

	const {
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
		setSelectedEmbedOption,
		setAttributes,
		selectedExperienceName,
	} = state;

	return createPortal(
		<div className="ceros-block__modal-overlay" onClick={ onClose }>
			<div
				className="ceros-block__modal"
				onClick={ ( e ) => e.stopPropagation() }
			>
				<div className="ceros-block__modal-header">
					<h2>{ __( 'Browse Ceros Content', 'ceros' ) }</h2>
					<button
						className="ceros-block__modal-close"
						onClick={ onClose }
					>
						<svg
							xmlns="http://www.w3.org/2000/svg"
							width="24"
							height="24"
							viewBox="0 0 24 24"
							fill="none"
							stroke="currentColor"
							strokeWidth="2"
							strokeLinecap="round"
							strokeLinejoin="round"
							className="lucide lucide-x h-4 w-4"
						>
							<path d="M18 6 6 18"></path>
							<path d="m6 6 12 12"></path>
						</svg>
					</button>
				</div>

				<div className="ceros-block__modal-body">
					{ currentAccountError && (
						<p style={ { color: 'red' } }>
							{ currentAccountError }
						</p>
					) }
					{ folderTreeError && (
						<p style={ { color: 'red' } }>{ folderTreeError }</p>
					) }

					{ isLoadingTree && (
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
								<path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
							</svg>
						</div>
					) }

					{ folderTreeData && ! isLoadingTree && (
						<TreeView
							data={ folderTreeData }
							onNodeClick={ handleNodeClick }
							expandedNodes={ expandedNodes }
							loadingNodes={ loadingNodes }
							selectedNodeId={ selectedNodeId }
						/>
					) }
				</div>

				{ currentEmbedCodes && (
					<div className="ceros-block__modal-footer">
						<div className="ceros-block__embed-options">
							<div>
								<label className="ceros-block__embed-options-label">
									<input
										type="radio"
										value={ EMBED_OPTIONS.FULL }
										checked={
											selectedEmbedOption ===
											EMBED_OPTIONS.FULL
										}
										disabled={
											! Boolean(
												currentEmbedCodes?.fullHeightEmbedCode &&
													String(
														currentEmbedCodes?.fullHeightEmbedCode
													).trim()
											)
										}
										onChange={ () =>
											setSelectedEmbedOption(
												EMBED_OPTIONS.FULL
											)
										}
									/>
									<span>
										<span>{ __( 'Full height', 'ceros' ) }</span>
										<span className="ceros-block__embed-options-description">
											{ __( 'This option scrolls naturally with your parent page without additional scrollbars.', 'ceros' ) }
										</span>
									</span>
								</label>
								<label className="ceros-block__embed-options-label">
									<input
										type="radio"
										value={ EMBED_OPTIONS.SCROLL }
										checked={
											selectedEmbedOption ===
											EMBED_OPTIONS.SCROLL
										}
										disabled={
											! Boolean(
												currentEmbedCodes?.scrollableEmbedCode &&
													String(
														currentEmbedCodes?.scrollableEmbedCode
													).trim()
											)
										}
										onChange={ () =>
											setSelectedEmbedOption(
												EMBED_OPTIONS.SCROLL
											)
										}
									/>
									<span>
										<span>{ __( 'Scrolling', 'ceros' ) }</span>
										<span className="ceros-block__embed-options-description">
											{ __( 'Displays your content in a viewport with internal scrollbars.', 'ceros' ) }
										</span>
									</span>
								</label>
							</div>
						</div>
						<div className="ceros-block__modal-actions">
							<button
								className="ceros-block__button ceros-block__button--secondary"
								onClick={ onClose }
							>
								{ __( 'Cancel', 'ceros' ) }
							</button>
							<button
								className="ceros-block__button ceros-block__button--primary"
								disabled={ ! selectedNodeId }
								onClick={ () => {
									if ( currentEmbedCodes ) {
										setAttributes( {
											fullHeightEmbedCode:
												currentEmbedCodes.fullHeightEmbedCode,
											scrollableEmbedCode:
												currentEmbedCodes.scrollableEmbedCode,
											selectedOption: selectedEmbedOption,
											experienceName:
												selectedExperienceName,
										} );
										onClose();
									}
								} }
							>
								{ __( 'Add Experience', 'ceros' ) }
							</button>
						</div>
					</div>
				) }
			</div>
		</div>,
		document.body
	);
};
