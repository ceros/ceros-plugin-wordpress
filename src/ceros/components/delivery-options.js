import { DELIVERY_MODES } from '../constants';

/**
 * Delivery-mode picker shown in the experience modal when the selected
 * experience is a Flex experience (i.e. the API returned an inline snippet).
 * Lets the user choose iframe vs iframeless before adding the experience.
 * @param root0
 * @param root0.selectedDeliveryMode
 * @param root0.setSelectedDeliveryMode
 */
export const DeliveryOptions = ( {
	selectedDeliveryMode,
	setSelectedDeliveryMode,
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
		</div>
	</div>
);
