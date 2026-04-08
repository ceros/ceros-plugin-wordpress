/**
 * Sidebar Controls Component
 *
 * Displays the sidebar controls for the Ceros block, including:
 * - Selected file display with edit button
 * - Settings panel with embed type radio buttons
 */

import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, BaseControl, Button } from '@wordpress/components';
import { ACTION_TYPES, EMBED_OPTIONS } from '../constants';

/**
 * Sidebar Controls Component
 *
 * @param {Object} props
 * @param {string} props.selectedExperienceName - Name of the selected experience
 * @param {Object} props.attributes - Block attributes
 * @param {string} props.selectedEmbedOption - Currently selected embed option ('full' or 'scroll')
 * @param {boolean} props.hasFullHeight - Whether full height embed code is available
 * @param {boolean} props.hasScrolling - Whether scrolling embed code is available
 * @param {Function} props.dispatch - Reducer dispatch function
 * @param {Function} props.setAttributes - WordPress setAttributes function
 */
export function SidebarControls( {
	selectedExperienceName,
	attributes,
	selectedEmbedOption,
	hasFullHeight,
	hasScrolling,
	dispatch,
	setAttributes,
} ) {
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
							onClick={ () =>
								dispatch( { type: ACTION_TYPES.OPEN_MODAL } )
							}
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
			<PanelBody title={ __( 'Settings', 'ceros' ) } initialOpen={ true }>
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
									selectedEmbedOption === EMBED_OPTIONS.FULL
								}
								disabled={ ! hasFullHeight }
								onChange={ () => {
									if ( hasFullHeight ) {
										dispatch( {
											type: ACTION_TYPES.SET_EMBED_OPTION,
											payload: EMBED_OPTIONS.FULL,
										} );
										setAttributes( {
											selectedOption: EMBED_OPTIONS.FULL,
										} );
									}
								} }
							/>
							<div className="ceros-sidebar__radio-content">
								<span className="ceros-sidebar__radio-title">
									{ __( 'Full height', 'ceros' ) }
								</span>
								<span className="ceros-sidebar__radio-description">
									{ __( 'Scrolls with the page.', 'ceros' ) }
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
									selectedEmbedOption === EMBED_OPTIONS.SCROLL
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
		</InspectorControls>
	);
}
