export const EmbedOptions = ( {
	currentEmbedCodes,
	selectedEmbedOption,
	setSelectedEmbedOption,
} ) => (
	<div className="ceros-block__embed-options">
		<div>
			<label className="ceros-block__embed-options-label">
				<input
					type="radio"
					value="full"
					checked={ selectedEmbedOption === 'full' }
					disabled={ ! Boolean(
						currentEmbedCodes?.fullHeightEmbedCode &&
							String( currentEmbedCodes?.fullHeightEmbedCode ).trim()
					) }
					onChange={ () => setSelectedEmbedOption( 'full' ) }
				/>
				<span>
					<span>Full height</span>
					<span className="ceros-block__embed-options-description">
						This option scrolls naturally with your parent page without
						additional scrollbars.
					</span>
				</span>
			</label>
			<label className="ceros-block__embed-options-label">
				<input
					type="radio"
					value="scroll"
					checked={ selectedEmbedOption === 'scroll' }
					disabled={ ! Boolean(
						currentEmbedCodes?.scrollableEmbedCode &&
							String( currentEmbedCodes?.scrollableEmbedCode ).trim()
					) }
					onChange={ () => setSelectedEmbedOption( 'scroll' ) }
				/>
				<span>
					<span>Scrolling</span>
					<span className="ceros-block__embed-options-description">
						Displays your content in a viewport with internal scrollbars.
					</span>
				</span>
			</label>
		</div>
	</div>
);

