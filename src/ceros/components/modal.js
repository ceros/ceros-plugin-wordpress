/**
 * Modal component for browsing and selecting Ceros experiences
 */
import { createPortal } from '@wordpress/element';
import { CerosModalHeader } from './modal-header';
import { CerosModalBody } from './modal-body';
import { CerosModalFooter } from './modal-footer';

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
		selectedDeliveryMode,
		setSelectedDeliveryMode,
		setAttributes,
		selectedExperienceName,
	} = state;

	return createPortal(
		/* eslint-disable jsx-a11y/click-events-have-key-events, jsx-a11y/no-static-element-interactions --
		   The overlay is a backdrop and the inner div only stops propagation, so
		   neither is a control. The real gap is that this modal has no Escape
		   handler at all; adding one is a behaviour change, tracked separately. */
		<div className="ceros-block__modal-overlay" onClick={ onClose }>
			<div
				className="ceros-block__modal"
				onClick={ ( e ) => e.stopPropagation() }
			>
				<CerosModalHeader onClose={ onClose } />
				<CerosModalBody
					currentAccountError={ currentAccountError }
					folderTreeError={ folderTreeError }
					isLoadingTree={ isLoadingTree }
					folderTreeData={ folderTreeData }
					handleNodeClick={ handleNodeClick }
					expandedNodes={ expandedNodes }
					loadingNodes={ loadingNodes }
					selectedNodeId={ selectedNodeId }
				/>
				<CerosModalFooter
					onClose={ onClose }
					currentEmbedCodes={ currentEmbedCodes }
					selectedEmbedOption={ selectedEmbedOption }
					setSelectedEmbedOption={ setSelectedEmbedOption }
					selectedDeliveryMode={ selectedDeliveryMode }
					setSelectedDeliveryMode={ setSelectedDeliveryMode }
					selectedNodeId={ selectedNodeId }
					setAttributes={ setAttributes }
					selectedExperienceName={ selectedExperienceName }
				/>
			</div>
		</div>,
		/* eslint-enable jsx-a11y/click-events-have-key-events, jsx-a11y/no-static-element-interactions */
		document.body
	);
};
