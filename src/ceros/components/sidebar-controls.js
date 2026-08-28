/**
 * Sidebar Controls Component
 *
 * Displays the sidebar controls for the Ceros block, including:
 * - Selected file display with edit button
 * - Settings panel with embed type radio buttons
 */

import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	BaseControl,
	Button,
	ToggleControl,
} from '@wordpress/components';
import {
	ACTION_TYPES,
	EMBED_OPTIONS,
	DELIVERY_MODES,
	manifestUrlFromInline,
} from '../constants';
import { StoreControls } from './store-controls';

/**
 * Sidebar Controls Component
 *
 * @param {Object}   props
 * @param {string}   props.selectedExperienceName - Name of the selected experience
 * @param {Object}   props.attributes             - Block attributes (reads `includeCustomHtml`)
 * @param {string}   props.selectedEmbedOption    - Currently selected embed option ('full' or 'scroll')
 * @param {boolean}  props.hasFullHeight          - Whether full height embed code is available
 * @param {boolean}  props.hasScrolling           - Whether scrolling embed code is available
 * @param {string}   props.deliveryMode           - Current delivery mode ('iframe' or 'inline')
 * @param {boolean}  props.hasInline              - Whether the experience exposes a Flex Inline (iframeless) snippet
 * @param {string}   props.inlineEmbedCode        - The Ceros-provided Flex Inline snippet
 * @param {Function} props.dispatch               - Reducer dispatch function
 * @param {Function} props.setAttributes          - WordPress setAttributes function
 * @param {Function} props.onEdit                 - Reopens the experience picker
 */
export function SidebarControls( {
	selectedExperienceName,
	attributes,
	selectedEmbedOption,
	hasFullHeight,
	hasScrolling,
	deliveryMode = DELIVERY_MODES.IFRAME,
	hasInline = false,
	inlineEmbedCode = '',
	onEdit,
	dispatch,
	setAttributes,
} ) {
	const handleEdit =
		onEdit || ( () => dispatch( { type: ACTION_TYPES.OPEN_MODAL } ) );
	const isIframeDelivery = deliveryMode === DELIVERY_MODES.IFRAME;
	return (
		<InspectorControls>
			<PanelBody
				title={ __( 'Experiences selected', 'ceros' ) }
				initialOpen={ true }
			>
				<BaseControl>
					<div className="ceros-sidebar__file-selector">
						<div className="ceros-sidebar__file-input">
							<span className="ceros-sidebar__file-name">
								{ selectedExperienceName ||
									attributes?.experienceName ||
									__( 'No experiences selected', 'ceros' ) }
							</span>
						</div>
						<Button
							className="ceros-sidebar__file-edit-btn"
							onClick={ handleEdit }
							icon={
								<svg
									xmlns="http://www.w3.org/2000/svg"
									viewBox="0 0 24 24"
									width="24"
									height="24"
									aria-hidden="true"
									focusable="false"
								>
									<path d="m19 7-3-3-8.5 8.5-1 4 4-1L19 7Zm-7 11.5H5V20h7v-1.5Z"></path>
								</svg>
							}
							label={ __( 'Change experiences', 'ceros' ) }
						/>
					</div>
				</BaseControl>
			</PanelBody>
			{ hasInline && (
				<PanelBody
					title={ __( 'Delivery mode', 'ceros' ) }
					initialOpen={ true }
				>
					<BaseControl>
						<div className="ceros-sidebar__radio-group">
							<label className="ceros-sidebar__radio-label">
								<input
									className="ceros-sidebar__radio-input"
									type="radio"
									value={ DELIVERY_MODES.IFRAME }
									checked={
										deliveryMode === DELIVERY_MODES.IFRAME
									}
									onChange={ () => {
										dispatch( {
											type: ACTION_TYPES.SET_DELIVERY_MODE,
											payload: DELIVERY_MODES.IFRAME,
										} );
										setAttributes( {
											deliveryMode: DELIVERY_MODES.IFRAME,
										} );
									} }
								/>
								<div className="ceros-sidebar__radio-content">
									<span className="ceros-sidebar__radio-title">
										{ __( 'Embed (iframe)', 'ceros' ) }
									</span>
									<span className="ceros-sidebar__radio-description">
										{ __(
											'Renders inside an isolated iframe.',
											'ceros'
										) }
									</span>
								</div>
							</label>
							<label
								className={ `ceros-sidebar__radio-label${
									! hasInline
										? ' ceros-sidebar__radio-label--disabled'
										: ''
								}` }
							>
								<input
									className="ceros-sidebar__radio-input"
									type="radio"
									value={ DELIVERY_MODES.INLINE }
									checked={
										deliveryMode === DELIVERY_MODES.INLINE
									}
									disabled={ ! hasInline }
									onChange={ () => {
										if ( hasInline ) {
											dispatch( {
												type: ACTION_TYPES.SET_DELIVERY_MODE,
												payload: DELIVERY_MODES.INLINE,
											} );
											// Persist the inline snippet alongside the mode so the
											// saved block always has what render.php needs, even if
											// it only existed in live API state until now.
											setAttributes( {
												deliveryMode:
													DELIVERY_MODES.INLINE,
												inlineEmbedCode,
											} );
										}
									} }
								/>
								<div className="ceros-sidebar__radio-content">
									<span className="ceros-sidebar__radio-title">
										{ __(
											'Inline — iframeless (Beta)',
											'ceros'
										) }
									</span>
									<span className="ceros-sidebar__radio-description">
										{ __(
											'Renders inline with no iframe. Best for a native feel.',
											'ceros'
										) }
									</span>
								</div>
							</label>
							<label
								className={ `ceros-sidebar__radio-label${
									! hasInline
										? ' ceros-sidebar__radio-label--disabled'
										: ''
								}` }
							>
								<input
									className="ceros-sidebar__radio-input"
									type="radio"
									value={ DELIVERY_MODES.SSR }
									checked={
										deliveryMode === DELIVERY_MODES.SSR
									}
									disabled={ ! hasInline }
									onChange={ () => {
										if ( hasInline ) {
											dispatch( {
												type: ACTION_TYPES.SET_DELIVERY_MODE,
												payload: DELIVERY_MODES.SSR,
											} );
											// Persist the manifest URL so render.php
											// can re-fetch it server-side. Prefer the
											// value the embed-codes endpoint resolved
											// from the raw snippet; scraping the
											// sanitized one is the fallback.
											setAttributes( {
												deliveryMode:
													DELIVERY_MODES.SSR,
												inlineEmbedCode,
												manifestUrl:
													attributes?.manifestUrl ||
													manifestUrlFromInline(
														inlineEmbedCode
													),
											} );
										}
									} }
								/>
								<div className="ceros-sidebar__radio-content">
									<span className="ceros-sidebar__radio-title">
										{ __(
											'SSR — server-rendered (Beta)',
											'ceros'
										) }
									</span>
									<span className="ceros-sidebar__radio-description">
										{ __(
											'WordPress renders the experience HTML on the server. Best for SEO and first paint.',
											'ceros'
										) }
									</span>
								</div>
							</label>
						</div>
					</BaseControl>
				</PanelBody>
			) }
			{ deliveryMode === DELIVERY_MODES.SSR && (
				<PanelBody
					title={ __( 'Store locally (Beta)', 'ceros' ) }
					initialOpen={ true }
				>
					<StoreControls
						manifestUrl={
							attributes?.manifestUrl ||
							manifestUrlFromInline( inlineEmbedCode )
						}
						storedAt={ attributes?.storedAt || '' }
						storedPublishedAt={
							attributes?.storedPublishedAt || ''
						}
						storedFlexVersion={
							attributes?.storedFlexVersion || ''
						}
						setAttributes={ setAttributes }
					/>
				</PanelBody>
			) }
			{ deliveryMode === DELIVERY_MODES.SSR && (
				<PanelBody
					title={ __( 'Custom code', 'ceros' ) }
					initialOpen={ true }
				>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Include custom body HTML/scripts',
							'ceros'
						) }
						help={ __(
							'Some experiences include custom code — for example answer tracking, scoring, or page navigation — that must load with the experience for it to work. Leave this on unless you know the experience does not need it. Custom head HTML is never included.',
							'ceros'
						) }
						checked={ attributes?.includeCustomHtml !== false }
						onChange={ ( value ) => {
							dispatch( {
								type: ACTION_TYPES.SET_INCLUDE_CUSTOM_HTML,
								payload: value,
							} );
							setAttributes( { includeCustomHtml: value } );
						} }
					/>
				</PanelBody>
			) }
			{ isIframeDelivery && (
				<PanelBody
					title={ __( 'Settings', 'ceros' ) }
					initialOpen={ true }
				>
					<BaseControl>
						<div className="ceros-sidebar__radio-group">
							<label
								className={ `ceros-sidebar__radio-label${
									! hasFullHeight
										? ' ceros-sidebar__radio-label--disabled'
										: ''
								}` }
							>
								<input
									className="ceros-sidebar__radio-input"
									type="radio"
									value={ EMBED_OPTIONS.FULL }
									checked={
										selectedEmbedOption ===
										EMBED_OPTIONS.FULL
									}
									disabled={ ! hasFullHeight }
									onChange={ () => {
										if ( hasFullHeight ) {
											dispatch( {
												type: ACTION_TYPES.SET_EMBED_OPTION,
												payload: EMBED_OPTIONS.FULL,
											} );
											setAttributes( {
												selectedOption:
													EMBED_OPTIONS.FULL,
											} );
										}
									} }
								/>
								<div className="ceros-sidebar__radio-content">
									<span className="ceros-sidebar__radio-title">
										{ __( 'Full height', 'ceros' ) }
									</span>
									<span className="ceros-sidebar__radio-description">
										{ __(
											'Scrolls with the page.',
											'ceros'
										) }
									</span>
								</div>
							</label>
							<label
								className={ `ceros-sidebar__radio-label${
									! hasScrolling
										? ' ceros-sidebar__radio-label--disabled'
										: ''
								}` }
							>
								<input
									className="ceros-sidebar__radio-input"
									type="radio"
									value={ EMBED_OPTIONS.SCROLL }
									checked={
										selectedEmbedOption ===
										EMBED_OPTIONS.SCROLL
									}
									disabled={ ! hasScrolling }
									onChange={ () => {
										if ( hasScrolling ) {
											dispatch( {
												type: ACTION_TYPES.SET_EMBED_OPTION,
												payload: EMBED_OPTIONS.SCROLL,
											} );
											setAttributes( {
												selectedOption:
													EMBED_OPTIONS.SCROLL,
											} );
										}
									} }
								/>
								<div className="ceros-sidebar__radio-content">
									<span className="ceros-sidebar__radio-title">
										{ __( 'Scrolling', 'ceros' ) }
									</span>
									<span className="ceros-sidebar__radio-description">
										{ __(
											'Scrolls in its own set area.',
											'ceros'
										) }
									</span>
								</div>
							</label>
						</div>
					</BaseControl>
				</PanelBody>
			) }
		</InspectorControls>
	);
}
