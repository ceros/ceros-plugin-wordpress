import { EmbedOptions } from './embed-options';

export const CerosModalFooter = ( {
	onClose,
	currentEmbedCodes,
	selectedEmbedOption,
	setSelectedEmbedOption,
	selectedNodeId,
	setAttributes,
	selectedExperienceName,
} ) => {
	if ( ! currentEmbedCodes ) {
		return null;
	}

	return (
		<div className="ceros-block__modal-footer">
			<EmbedOptions
				currentEmbedCodes={ currentEmbedCodes }
				selectedEmbedOption={ selectedEmbedOption }
				setSelectedEmbedOption={ setSelectedEmbedOption }
			/>
			<div className="ceros-block__modal-actions">
				<button
					className="ceros-block__button ceros-block__button--secondary"
					onClick={ onClose }
				>
					Cancel
				</button>
				<button
					className="ceros-block__button ceros-block__button--primary"
					disabled={ ! selectedNodeId }
					onClick={ () => {
						if ( currentEmbedCodes ) {
							setAttributes( {
								fullHeightEmbedCode: currentEmbedCodes.fullHeightEmbedCode,
								scrollableEmbedCode:
									currentEmbedCodes.scrollableEmbedCode,
								selectedOption: selectedEmbedOption,
								experienceName: selectedExperienceName,
								experienceResourceId: selectedNodeId ? String( selectedNodeId ) : '',
							} );
							onClose();
						}
					} }
				>
					Add Experience
				</button>
			</div>
		</div>
	);
};

