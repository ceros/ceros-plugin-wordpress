/**
 * Paste-a-public-URL panel.
 *
 * Shown in the block's empty state when no Ceros API key is configured. The
 * "Browse Experiences" button needs an API key, so it is disabled here; instead
 * the author pastes a public experience URL. The server resolves it (detecting
 * Flex vs legacy) and returns the matching embed codes, after which the right
 * delivery-mode / embed-size options are shown before the experience is added.
 */

import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { ACTION_TYPES, DELIVERY_MODES, EMBED_OPTIONS } from '../constants';
import { DeliveryOptions } from './delivery-options';
import { EmbedOptions } from './embed-options';

/**
 * Derive a human-friendly experience name from a view URL's last path segment.
 *
 * @param {string} url The experience URL.
 * @return {string} A display name.
 */
function experienceNameFromUrl( url ) {
	try {
		const segments = new URL( url ).pathname.split( '/' ).filter( Boolean );
		const slug = segments[ segments.length - 1 ] || '';
		const name = slug.replace( /[-_]+/g, ' ' ).trim();
		return name || __( 'Ceros experience', 'ceros' );
	} catch ( e ) {
		return __( 'Ceros experience', 'ceros' );
	}
}

/**
 * @param {Object}   props
 * @param {Function} props.dispatch             Reducer dispatch.
 * @param {Function} props.setAttributes        Block setAttributes.
 * @param {string}   props.settingsUrl          URL to the Ceros settings page.
 * @param {Object}   props.currentEmbedCodes    Resolved embed codes (from state).
 * @param {string}   props.selectedDeliveryMode Current delivery mode.
 * @param {string}   props.selectedEmbedOption  Current iframe size option.
 */
export function PasteUrlPanel( {
	dispatch,
	setAttributes,
	settingsUrl,
	currentEmbedCodes,
	selectedDeliveryMode,
	selectedEmbedOption,
} ) {
	const [ url, setUrl ] = useState( '' );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ resolved, setResolved ] = useState( null );

	const hasInline = Boolean(
		currentEmbedCodes?.inlineEmbedCode &&
			String( currentEmbedCodes.inlineEmbedCode ).trim()
	);
	const deliveryMode = hasInline ? selectedDeliveryMode : DELIVERY_MODES.IFRAME;
	// Full-vs-scroll sizing only applies to the iframe delivery mode.
	const isIframe = deliveryMode === DELIVERY_MODES.IFRAME;

	async function handleLoad() {
		const trimmed = url.trim();
		if ( ! trimmed ) {
			setError( __( 'Please enter a Ceros experience URL.', 'ceros' ) );
			return;
		}

		setIsLoading( true );
		setError( '' );
		setResolved( null );

		try {
			const res = await apiFetch( {
				path: '/ceros/v1/resolve-public-url',
				method: 'POST',
				data: { url: trimmed },
			} );

			const codes = {
				fullHeightEmbedCode: res.fullHeightEmbedCode || '',
				scrollableEmbedCode: res.scrollableEmbedCode || '',
				inlineEmbedCode: res.inlineEmbedCode || '',
			};

			dispatch( { type: ACTION_TYPES.SET_EMBED_CODES, payload: codes } );
			dispatch( {
				type: ACTION_TYPES.SET_DELIVERY_MODE,
				payload: DELIVERY_MODES.IFRAME,
			} );
			dispatch( {
				type: ACTION_TYPES.SET_EMBED_OPTION,
				payload: String( codes.fullHeightEmbedCode ).trim()
					? EMBED_OPTIONS.FULL
					: EMBED_OPTIONS.SCROLL,
			} );

			setResolved( {
				isFlex: Boolean( res.isFlex ),
				viewUrl: res.viewUrl || trimmed,
				manifestUrl: res.manifestUrl || '',
			} );
		} catch ( err ) {
			setError(
				err?.error ||
					err?.message ||
					__(
						'Could not load that experience. Check the URL and try again.',
						'ceros'
					)
			);
		} finally {
			setIsLoading( false );
		}
	}

	function handleAdd() {
		if ( ! currentEmbedCodes ) {
			return;
		}
		setAttributes( {
			fullHeightEmbedCode: currentEmbedCodes.fullHeightEmbedCode || '',
			scrollableEmbedCode: currentEmbedCodes.scrollableEmbedCode || '',
			inlineEmbedCode: currentEmbedCodes.inlineEmbedCode || '',
			selectedOption: selectedEmbedOption,
			deliveryMode: hasInline ? selectedDeliveryMode : DELIVERY_MODES.IFRAME,
			// Manifest URL drives the SSR delivery mode's server-side fetch.
			manifestUrl: resolved?.manifestUrl || '',
			// Canonical (Ceros-owned) view URL. Lets render.php rebuild a legacy
			// Studio scroll-proxy embed fresh at render time — its <script> is
			// otherwise stripped on hosts without unfiltered_html (e.g. WP.com).
			experienceUrl: resolved?.viewUrl || '',
			experienceName: experienceNameFromUrl( resolved?.viewUrl || url ),
			// No resource id is available via the public URL flow.
			experienceResourceId: '',
		} );
	}

	return (
		<div className="ceros-block__empty">
			<h3>{ __( 'Add a Ceros Experience', 'ceros' ) }</h3>
			<p>
				{ __(
					'No API key is configured, so experience browsing is disabled. Paste a public Ceros experience URL to add it directly.',
					'ceros'
				) }
			</p>

			<button
				className="ceros-block__button ceros-block__button--primary"
				type="button"
				disabled={ true }
				title={ __(
					'Add a Ceros API key in settings to browse experiences.',
					'ceros'
				) }
			>
				{ __( 'Browse Experiences', 'ceros' ) }
			</button>

			<div className="ceros-block__paste">
				<label className="ceros-block__paste-label" htmlFor="ceros-paste-url">
					{ __( 'Public experience URL', 'ceros' ) }
				</label>
				<div className="ceros-block__paste-row">
					<input
						id="ceros-paste-url"
						className="ceros-block__paste-input"
						type="url"
						placeholder="https://…"
						value={ url }
						disabled={ isLoading }
						onChange={ ( e ) => setUrl( e.target.value ) }
						onKeyDown={ ( e ) => {
							if ( e.key === 'Enter' ) {
								e.preventDefault();
								handleLoad();
							}
						} }
					/>
					<button
						className="ceros-block__button ceros-block__button--primary"
						type="button"
						disabled={ isLoading }
						onClick={ handleLoad }
					>
						{ isLoading
							? __( 'Loading…', 'ceros' )
							: __( 'Load experience', 'ceros' ) }
					</button>
				</div>

				{ error && (
					<p className="ceros-block__paste-error">{ error }</p>
				) }

				{ resolved && (
					<div className="ceros-block__paste-result">
						<p className="ceros-block__paste-type">
							{ resolved.isFlex
								? __(
										'Detected a Ceros Flex experience. Choose how to deliver it:',
										'ceros'
								  )
								: __(
										'Detected a Ceros Studio experience.',
										'ceros'
								  ) }
						</p>

						{ hasInline && (
							<DeliveryOptions
								selectedDeliveryMode={ deliveryMode }
								setSelectedDeliveryMode={ ( mode ) =>
									dispatch( {
										type: ACTION_TYPES.SET_DELIVERY_MODE,
										payload: mode,
									} )
								}
							/>
						) }

						{ isIframe && (
							<EmbedOptions
								currentEmbedCodes={ currentEmbedCodes }
								selectedEmbedOption={ selectedEmbedOption }
								setSelectedEmbedOption={ ( option ) =>
									dispatch( {
										type: ACTION_TYPES.SET_EMBED_OPTION,
										payload: option,
									} )
								}
							/>
						) }

						<button
							className="ceros-block__button ceros-block__button--primary"
							type="button"
							onClick={ handleAdd }
						>
							{ __( 'Add experience', 'ceros' ) }
						</button>
					</div>
				) }
			</div>

			<p className="ceros-block__paste-hint">
				<a
					href={ settingsUrl }
					target="_blank"
					rel="noopener noreferrer"
				>
					{ __(
						'Add a Ceros API key to enable experience browsing.',
						'ceros'
					) }
				</a>
			</p>
		</div>
	);
}
