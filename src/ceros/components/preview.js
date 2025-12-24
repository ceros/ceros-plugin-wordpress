/**
 * Preview component for showing the selected embed inside the block
 */
import { EMBED_OPTIONS } from '../constants';

export const CerosPreview = ( { currentEmbedCodes, selectedEmbedOption } ) => {
	if ( ! currentEmbedCodes ) {
		return null;
	}

	return (
		<div className="ceros-block__preview-section">
			{ selectedEmbedOption === EMBED_OPTIONS.FULL &&
				currentEmbedCodes?.fullHeightEmbedCode && (
					<div
						className="ceros-block__preview"
						dangerouslySetInnerHTML={ {
							__html: currentEmbedCodes.fullHeightEmbedCode,
						} }
					/>
				) }
			{ selectedEmbedOption === EMBED_OPTIONS.SCROLL &&
				currentEmbedCodes?.scrollableEmbedCode && (
					<div
						className="ceros-block__preview"
						dangerouslySetInnerHTML={ {
							__html: currentEmbedCodes.scrollableEmbedCode,
						} }
					/>
				) }
		</div>
	);
};
