/**
 * Store-mode controls for Flex SSR.
 *
 * Lets an author download the experience (manifest + assets) into the WordPress
 * uploads directory so the published page renders fully locally, with no
 * runtime Ceros CDN dependency. Shown in the sidebar only when the SSR delivery
 * mode is selected for a Flex experience.
 */

import { __, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { BaseControl, Button } from '@wordpress/components';

/**
 * @param {Object}   props
 * @param {string}   props.manifestUrl     The experience manifest URL.
 * @param {string}   props.storedAt        ISO timestamp of the last store, if any.
 * @param {Function} props.setAttributes   Block setAttributes.
 */
export function StoreControls( { manifestUrl, storedAt, setAttributes } ) {
	const [ isBusy, setIsBusy ] = useState( false );
	const [ error, setError ] = useState( '' );

	const postId = useSelect(
		( select ) => select( 'core/editor' )?.getCurrentPostId?.() || 0,
		[]
	);

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
			} );
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
		} );
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
				{ error && (
					<p className="ceros-sidebar__store-error">{ error }</p>
				) }
			</div>
		</BaseControl>
	);
}
