const TreeNode = ( { node, onNodeClick, expandedNodes, loadingNodes, selectedNodeId } ) => {
	const isExpanded = expandedNodes.has( node.resourceId );
	const childArray = node.children || [];
	const hasChildren = childArray.length > 0;
	const isLoading = loadingNodes.has( node.resourceId );
	const isSelected =
		node.isExperience &&
		selectedNodeId !== null &&
		selectedNodeId !== undefined &&
		String( selectedNodeId ) === String( node.resourceId );
	const isEmptyMessage = node.isEmptyMessage || false;

	const shouldShowArrow = ! node.isExperience || hasChildren;
	const isFileStyle = node.isExperience || isEmptyMessage;

	return (
		<div className={ isFileStyle ? 'ceros-block__file' : 'ceros-block__folder' }>
			<div
				className={ `ceros-block__item ${
					isSelected ? 'ceros-block__item--selected' : ''
				} ${ isEmptyMessage ? 'ceros-block__item--empty-message' : '' }` }
				onClick={
					isEmptyMessage
						? undefined
						: ( e ) => {
								e.stopPropagation();
								onNodeClick( node );
						  }
				}
				data-resource-id={ node.resourceId }
				style={ isEmptyMessage ? { cursor: 'default', opacity: 0.6 } : {} }
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

export const TreeView = ( {
	data,
	onNodeClick,
	expandedNodes,
	loadingNodes,
	selectedNodeId,
} ) => {
	if ( ! data || ! Array.isArray( data ) ) {
		return <p>No tree data available</p>;
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

