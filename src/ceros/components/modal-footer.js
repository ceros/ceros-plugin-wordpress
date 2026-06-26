import { EmbedOptions } from './embed-options';
import { DeliveryOptions } from './delivery-options';
import { DELIVERY_MODES, manifestUrlFromInline } from '../constants';

export const CerosModalFooter = ( {
	onClose,
	currentEmbedCodes,
	selectedEmbedOption,
	setSelectedEmbedOption,
	selectedDeliveryMode,
	setSelectedDeliveryMode,
	selectedNodeId,
	setAttributes,
	selectedExperienceName,
} ) => {
	if ( ! currentEmbedCodes ) {
		return null;
	}

	// The API only returns an inline snippet for Flex experiences. When present,
	// offer the iframe vs iframeless choice right here in the picker.
	const hasInline = Boolean(
		currentEmbedCodes.inlineEmbedCode &&
			String( currentEmbedCodes.inlineEmbedCode ).trim()
	);
	const effectiveDeliveryMode = hasInline
		? selectedDeliveryMode
		: DELIVERY_MODES.IFRAME;
	// The iframe sizing options only apply to the iframe delivery mode (the
	// inline and SSR modes have no full-vs-scroll choice).
	const isIframe = effectiveDeliveryMode === DELIVERY_MODES.IFRAME;

	return (
		<div className="ceros-block__modal-footer">
			{ hasInline && (
				<DeliveryOptions
					selectedDeliveryMode={ effectiveDeliveryMode }
					setSelectedDeliveryMode={ setSelectedDeliveryMode }
				/>
			) }
			{ isIframe && (
				<EmbedOptions
					currentEmbedCodes={ currentEmbedCodes }
					selectedEmbedOption={ selectedEmbedOption }
					setSelectedEmbedOption={ setSelectedEmbedOption }
				/>
			) }
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
								fullHeightEmbedCode:
									currentEmbedCodes.fullHeightEmbedCode,
								scrollableEmbedCode:
									currentEmbedCodes.scrollableEmbedCode,
								selectedOption: selectedEmbedOption,
								experienceName: selectedExperienceName,
								experienceResourceId: selectedNodeId
									? String( selectedNodeId )
									: '',
								// inlineEmbedCode is present only for Flex
								// experiences; it carries the iframeless snippet.
								inlineEmbedCode:
									currentEmbedCodes.inlineEmbedCode || '',
								// Commit the delivery mode chosen in the picker
								// (forced back to iframe for non-Flex experiences).
								deliveryMode: effectiveDeliveryMode,
								// Manifest URL drives the SSR delivery mode's
								// server-side fetch; derived from the inline snippet.
								manifestUrl: manifestUrlFromInline(
									currentEmbedCodes.inlineEmbedCode || ''
								),
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
