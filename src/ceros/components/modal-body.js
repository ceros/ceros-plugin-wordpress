import { TreeView } from './tree-view';

export const CerosModalBody = ( {
	currentAccountError,
	folderTreeError,
	isLoadingTree,
	folderTreeData,
	handleNodeClick,
	expandedNodes,
	loadingNodes,
	selectedNodeId,
} ) => (
	<div className="ceros-block__modal-body">
		{ currentAccountError && (
			<p style={ { color: 'red' } }>{ currentAccountError }</p>
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
);
