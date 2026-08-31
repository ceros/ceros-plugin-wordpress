import { DELIVERY_MODES } from '../constants';

/**
 * Delivery-mode picker shown in the experience modal when the selected
 * experience is a Flex experience (i.e. the API returned an inline snippet).
 * Lets the user choose iframe vs iframeless before adding the experience.
 *
 * @param {Object}   props                         Component props.
 * @param {string}   props.selectedDeliveryMode    Currently selected delivery mode.
 * @param {Function} props.setSelectedDeliveryMode Sets the delivery mode.
 * @param {boolean}  props.includeCustomHtml       Whether to include the experience's custom body HTML.
 * @param {Function} props.setIncludeCustomHtml    Sets whether to include it.
 */
export const DeliveryOptions = ( {
	selectedDeliveryMode,
	setSelectedDeliveryMode,
	includeCustomHtml = true,
	setIncludeCustomHtml,
} ) => (
	<div className="ceros-block__embed-options">
		<div>
			<label className="ceros-block__embed-options-label">
				<input
					type="radio"
					value={ DELIVERY_MODES.IFRAME }
					checked={ selectedDeliveryMode === DELIVERY_MODES.IFRAME }
					onChange={ () =>
						setSelectedDeliveryMode( DELIVERY_MODES.IFRAME )
					}
				/>
				<span>
					<span>Embed (iframe)</span>
					<span className="ceros-block__embed-options-description">
						Renders inside an isolated iframe.
					</span>
				</span>
			</label>
			<label className="ceros-block__embed-options-label">
				<input
					type="radio"
					value={ DELIVERY_MODES.INLINE }
					checked={ selectedDeliveryMode === DELIVERY_MODES.INLINE }
					onChange={ () =>
						setSelectedDeliveryMode( DELIVERY_MODES.INLINE )
					}
				/>
				<span>
					<span>Inline — iframeless (Beta)</span>
					<span className="ceros-block__embed-options-description">
						Renders inline with no iframe. Best for a native feel.
					</span>
				</span>
			</label>
			<label className="ceros-block__embed-options-label">
				<input
					type="radio"
					value={ DELIVERY_MODES.SSR }
					checked={ selectedDeliveryMode === DELIVERY_MODES.SSR }
					onChange={ () =>
						setSelectedDeliveryMode( DELIVERY_MODES.SSR )
					}
				/>
				<span>
					<span>SSR — server-rendered (Beta)</span>
					<span className="ceros-block__embed-options-description">
						WordPress fetches and renders the experience HTML on the
						server. Best for SEO and first paint.
					</span>
				</span>
			</label>
			{ selectedDeliveryMode === DELIVERY_MODES.SSR && (
				<label className="ceros-block__embed-options-label ceros-block__embed-options-label--nested">
					<input
						type="checkbox"
						checked={ includeCustomHtml }
						onChange={ ( event ) =>
							setIncludeCustomHtml( event.target.checked )
						}
					/>
					<span>
						<span>Include custom body HTML/scripts</span>
						<span className="ceros-block__embed-options-description">
							Some experiences include custom code — for example
							answer tracking, scoring, or page navigation — that
							must load with the experience for it to work. Leave
							this on unless you know the experience does not need
							it. Custom head HTML is never included.
						</span>
					</span>
				</label>
			) }
		</div>
	</div>
);
