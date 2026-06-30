/**
 * Store-mode controls for Flex SSR.
 *
 * Lets an author download the experience (manifest + assets) into the WordPress
 * uploads directory so the published page renders fully locally, with no
 * runtime Ceros CDN dependency. Shown in the sidebar only when the SSR delivery
 * mode is selected for a Flex experience.
 *
 * When an experience is already stored, on open we fetch the live manifest's
 * metadata and compare `publishedAt` / `flexVersion` against the stored copy;
 * if either changed, a "new version available" note is shown by the refresh
 * button so the author knows to re-store.
 */

import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { BaseControl, Button } from '@wordpress/components';

/**
 * @param {Object}   props
 * @param {string}   props.manifestUrl       The experience manifest URL.
 * @param {string}   props.storedAt          ISO timestamp of the last store, if any.
 * @param {string}   props.storedPublishedAt Manifest publishedAt captured at store time.
 * @param {string}   props.storedFlexVersion Manifest flexVersion captured at store time.
 * @param {Function} props.setAttributes     Block setAttributes.
 */
export function StoreControls( {
	manifestUrl,
	storedAt,
	storedPublishedAt,
	storedFlexVersion,
	setAttributes,
} ) {
	const [ isBusy, setIsBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ updateAvailable, setUpdateAvailable ] = useState( false );

	const postId = useSelect(
		( select ) => select( 'core/editor' )?.getCurrentPostId?.() || 0,
		[]
	);

	// On open (and whenever the stored baseline changes), check the live manifest
	// for a newer version. Only flag an update when we have a baseline to compare
	// against, so experiences stored before this field existed don't false-positive.
	useEffect( () => {
		if ( ! storedAt || ! manifestUrl ) {
			setUpdateAvailable( false );
			return;
		}
		if ( ! storedPublishedAt && ! storedFlexVersion ) {
			return;
		}

		let cancelled = false;
		apiFetch( {
			path:
				'/ceros/v1/manifest-meta?url=' +
				encodeURIComponent( manifestUrl ),
		} )
			.then( ( meta ) => {
				if ( cancelled ) {
					return;
				}
				const changed =
					( storedPublishedAt &&
						meta?.publishedAt &&
						meta.publishedAt !== storedPublishedAt ) ||
					( storedFlexVersion &&
						meta?.flexVersion &&
						meta.flexVersion !== storedFlexVersion );
				setUpdateAvailable( Boolean( changed ) );
			} )
			.catch( () => {
				// Best-effort: a failed check just means we don't show the hint.
				if ( ! cancelled ) {
					setUpdateAvailable( false );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ storedAt, manifestUrl, storedPublishedAt, storedFlexVersion ] );

	async function handleStore() {
		if ( ! manifestUrl ) {
			setError(
				__( 'This experience has no manifest to store.', 'ceros' )
			);
			return;
		}
		if ( ! postId ) {
			setError( __( 'Save the post once before storing.', 'ceros' ) );
			return;
		}

		setIsBusy( true );
		setError( '' );
		try {
			const res = await apiFetch( {
				path: '/ceros/v1/store-manifest',
				method: 'POST',
				data: { url: manifestUrl, post_id: postId },
			} );
			setAttributes( {
				storedIndexPath: res.storedIndexPath || '',
				storedAt: res.storedAt || '',
				storedVersion: res.storedVersion || '',
				storedPublishedAt: res.storedPublishedAt || '',
				storedFlexVersion: res.storedFlexVersion || '',
			} );
			setUpdateAvailable( false );
		} catch ( err ) {
			setError(
				err?.error ||
					err?.message ||
					__( 'Could not store the experience. Try again.', 'ceros' )
			);
		} finally {
			setIsBusy( false );
		}
	}

	function handleRemove() {
		// Reverts to live SSR. Stored files remain on disk (tagged for manual
		// cleanup); a later store of the same experience purges old versions.
		setAttributes( {
			storedIndexPath: '',
			storedAt: '',
			storedVersion: '',
			storedPublishedAt: '',
			storedFlexVersion: '',
		} );
		setUpdateAvailable( false );
	}

	let storedLabel = __( 'Not stored — rendering live.', 'ceros' );
	if ( storedAt ) {
		const when = new Date( storedAt );
		storedLabel = sprintf(
			/* translators: %s: date/time the experience was stored. */
			__( 'Stored locally on %s.', 'ceros' ),
			isNaN( when.getTime() ) ? storedAt : when.toLocaleString()
		);
	}

	return (
		<BaseControl __nextHasNoMarginBottom>
			<div className="ceros-sidebar__store">
				<p className="ceros-sidebar__store-status">{ storedLabel }</p>
				<div className="ceros-sidebar__store-actions">
					<Button
						variant="secondary"
						onClick={ handleStore }
						isBusy={ isBusy }
						disabled={ isBusy }
					>
						{ storedAt
							? __( 'Refresh stored copy', 'ceros' )
							: __( 'Fetch & store locally', 'ceros' ) }
					</Button>
					{ storedAt && (
						<Button
							variant="tertiary"
							isDestructive
							onClick={ handleRemove }
							disabled={ isBusy }
						>
							{ __( 'Remove', 'ceros' ) }
						</Button>
					) }
				</div>
				{ updateAvailable && ! isBusy && (
					<p className="ceros-sidebar__store-update">
						{ __(
							'A newer version of this experience is available — refresh to update the stored copy.',
							'ceros'
						) }
					</p>
				) }
				{ error && (
					<p className="ceros-sidebar__store-error">{ error }</p>
				) }
			</div>
		</BaseControl>
	);
}
