<?php
/**
 * Flex SSR "Store" mode — local asset persistence.
 *
 * Persists a Flex experience locally so the published page renders with no
 * runtime Ceros-CDN dependency. The URL-rewriting is done server-side by
 * flex-shield: requesting `…/manifest.v1.json?baseUrl=<public root>` returns a
 * manifest whose Ceros-asset URLs already point under `<baseUrl>`, plus an
 * additive `assetRewrites` map (`{ baseUrl, assets: [{ from, path, to }] }`).
 * This module just mirrors that map — download each `from` and write it at
 * `<localRoot>/<path>` — and stores the returned manifest verbatim. No
 * client-side manifest scanning / URL rewriting any more.
 *
 * Layout (under wp-content/uploads):
 *   ceros-flex/<postId>/<account>--<experience>/<version>/
 *     index.json            sidecar tag + slug -> page-manifest map
 *     pages/<slug>.json      server-rewritten per-page manifests (URLs already local)
 *     <path…>               assets mirrored at the exact `path` the rewrite returns
 *
 * @package ceros
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Top-level entry: fetch + store a Flex experience bundle locally via the
 * server-side `?baseUrl=` rewrite.
 *
 * @param string $manifest_url The experience manifest URL.
 * @param int    $post_id      The post the block belongs to (scopes storage + cleanup).
 * @return array|WP_Error { @type string storedIndexPath, @type string storedAt, @type string storedVersion, @type string storedPublishedAt, @type string storedFlexVersion }
 */
function ceros_store_flex_manifest( $manifest_url, $post_id ) {
	$post_id = absint( $post_id );
	if ( ! $post_id ) {
		return new WP_Error( 'ceros_store_post', __( 'A post ID is required to store an experience.', 'ceros' ) );
	}

	// Metadata fetch (no baseUrl) to learn the experience, version, and page list
	// before we can build the storage paths that become the baseUrl.
	$primary_meta = ceros_fetch_flex_manifest( $manifest_url );
	if ( is_wp_error( $primary_meta ) ) {
		return $primary_meta;
	}

	$experience   = $primary_meta['experience'] ?? [];
	$exp_key      = ceros_store_experience_key( $experience );
	$version      = ceros_store_version( $primary_meta );
	$primary_slug = ceros_store_primary_slug( $primary_meta );

	// Captured so the editor can detect when the live experience has been
	// republished (publishedAt) or rebuilt on a new Flex runtime (flexVersion)
	// since this copy was stored, and surface a "new version available" hint.
	$published_at = isset( $primary_meta['publishedAt'] ) ? (string) $primary_meta['publishedAt'] : '';
	$flex_version = isset( $primary_meta['flexVersion'] ) ? (string) $primary_meta['flexVersion'] : '';

	$upload = wp_upload_dir();
	if ( ! empty( $upload['error'] ) ) {
		return new WP_Error( 'ceros_store_uploads', $upload['error'] );
	}

	$rel_base = 'ceros-flex/' . $post_id . '/' . $exp_key . '/' . $version;
	$abs_base = trailingslashit( $upload['basedir'] ) . $rel_base;
	$url_base = trailingslashit( $upload['baseurl'] ) . $rel_base;

	if ( ! wp_mkdir_p( $abs_base ) ) {
		return new WP_Error( 'ceros_store_mkdir', __( 'Could not create the local storage directory.', 'ceros' ) );
	}

	// Pages to store: primary + every linked page (deduped by slug).
	$page_urls = [ $primary_slug => $manifest_url ];
	foreach ( ( $primary_meta['pages'] ?? [] ) as $page ) {
		if ( ! is_array( $page ) ) {
			continue;
		}
		$slug = $page['slug'] ?? '';
		$purl = $page['manifestUrl'] ?? '';
		if ( '' !== $slug && '' !== $purl && ! isset( $page_urls[ $slug ] ) ) {
			$page_urls[ $slug ] = $purl;
		}
	}

	// Local URL each stored page manifest will live at (for offline SPA nav).
	$local_manifest_urls = [];
	foreach ( $page_urls as $slug => $purl ) {
		$local_manifest_urls[ $slug ] = $url_base . '/pages/' . ceros_store_slug_filename( $slug ) . '.json';
	}

	$seen       = [];
	$page_index = [];
	foreach ( $page_urls as $slug => $purl ) {
		$rewritten = ceros_store_fetch_rewritten( $purl, $url_base );

		// The primary page must succeed; secondary pages are best-effort.
		if ( is_wp_error( $rewritten ) ) {
			if ( $slug === $primary_slug ) {
				return $rewritten;
			}
			continue;
		}
		if ( ! isset( $rewritten['assetRewrites'] ) || ! is_array( $rewritten['assetRewrites'] ) ) {
			if ( $slug === $primary_slug ) {
				return new WP_Error(
					'ceros_store_unsupported',
					__( 'The Ceros experience host did not return rewrite data. It must support the ?baseUrl manifest rewrite to use Store mode.', 'ceros' )
				);
			}
			continue;
		}

		// Mirror each rewritten asset: download `from`, write it at `path`.
		$assets = isset( $rewritten['assetRewrites']['assets'] ) && is_array( $rewritten['assetRewrites']['assets'] )
			? $rewritten['assetRewrites']['assets']
			: [];
		foreach ( $assets as $asset ) {
			if ( is_array( $asset ) && ! empty( $asset['from'] ) && ! empty( $asset['path'] ) ) {
				ceros_store_download_asset( $asset['from'], $abs_base, $asset['path'], $seen );
			}
		}

		// Point cross-page navigation at the local stored manifests, and drop the
		// rewrite map (not needed once the assets are mirrored).
		if ( ! empty( $rewritten['pages'] ) && is_array( $rewritten['pages'] ) ) {
			foreach ( $rewritten['pages'] as $i => $pref ) {
				$ps = is_array( $pref ) && isset( $pref['slug'] ) ? $pref['slug'] : '';
				if ( isset( $local_manifest_urls[ $ps ] ) ) {
					$rewritten['pages'][ $i ]['manifestUrl'] = $local_manifest_urls[ $ps ];
				}
			}
		}
		unset( $rewritten['assetRewrites'] );

		$rel = 'pages/' . ceros_store_slug_filename( $slug ) . '.json';
		if ( ceros_store_write_file( $abs_base . '/' . $rel, wp_json_encode( $rewritten ) ) ) {
			$page_index[ $slug ] = $rel;
		}
	}

	if ( empty( $page_index ) ) {
		return new WP_Error( 'ceros_store_failed', __( 'Could not store the experience.', 'ceros' ) );
	}

	$index = [
		'schema'            => 'ceros-flex-store/2',
		'postId'            => $post_id,
		'accountSlug'       => $experience['accountSlug'] ?? '',
		'experienceSlug'    => $experience['slug'] ?? '',
		'version'           => $version,
		'publishedAt'       => $published_at,
		'flexVersion'       => $flex_version,
		'sourceManifestUrl' => $manifest_url,
		'primarySlug'       => $primary_slug,
		'baseUrl'           => $url_base,
		'storedAt'          => gmdate( 'c' ),
		'pages'             => $page_index,
	];
	ceros_store_write_file( $abs_base . '/index.json', wp_json_encode( $index ) );

	// Drop any previous versions of this experience for this post.
	ceros_store_purge_old_versions( $upload['basedir'], $post_id, $exp_key, $version );

	return [
		'storedIndexPath'   => $rel_base . '/index.json',
		'storedAt'          => $index['storedAt'],
		'storedVersion'     => $version,
		'storedPublishedAt' => $published_at,
		'storedFlexVersion' => $flex_version,
	];
}

/**
 * Fetch the server-rewritten manifest for a page (`<manifestUrl>?baseUrl=<…>`).
 *
 * @param string $manifest_url The page's manifest URL (no baseUrl).
 * @param string $base_url     The public root the assets will be served under.
 * @return array|WP_Error The decoded rewritten manifest, or WP_Error.
 */
function ceros_store_fetch_rewritten( $manifest_url, $base_url ) {
	$manifest_url = trim( (string) $manifest_url );

	if ( 'https' !== strtolower( (string) wp_parse_url( $manifest_url, PHP_URL_SCHEME ) ) ) {
		return new WP_Error( 'ceros_store_manifest_scheme', __( 'Manifest URL must use https.', 'ceros' ) );
	}
	$host = wp_parse_url( $manifest_url, PHP_URL_HOST );
	if ( empty( $host ) || ! ceros_is_public_host( $host ) ) {
		return new WP_Error( 'ceros_store_manifest_host', __( 'Manifest host is not publicly reachable.', 'ceros' ) );
	}

	$separator   = ( false === strpos( $manifest_url, '?' ) ) ? '?' : '&';
	$request_url = $manifest_url . $separator . 'baseUrl=' . rawurlencode( $base_url );

	$response = wp_remote_get(
		$request_url,
		[
			'timeout'     => CEROS_API_REQUEST_TIMEOUT,
			'redirection' => 0,
			'headers'     => [ 'Accept' => 'application/json' ],
		]
	);
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	$code = wp_remote_retrieve_response_code( $response );
	if ( $code < 200 || $code >= 300 ) {
		return new WP_Error( 'ceros_store_manifest_http', sprintf( 'HTTP %d', $code ) );
	}
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $data ) ) {
		return new WP_Error( 'ceros_store_manifest_json', __( 'Manifest response was not valid JSON.', 'ceros' ) );
	}
	return $data;
}

/**
 * Download one rewritten asset and write it at the server-supplied relative
 * path. Deduped across pages via `$seen` (shared bundles map to the same path).
 *
 * @param string $from     The original Ceros asset URL to download.
 * @param string $abs_base Absolute version directory.
 * @param string $path     Path relative to baseUrl (from the rewrite map).
 * @param array  $seen     Relative paths already written this run (by reference).
 * @return bool Whether the asset is present locally after the call.
 */
function ceros_store_download_asset( $from, $abs_base, $path, &$seen ) {
	$rel = ceros_store_safe_rel_path( $path );
	if ( '' === $rel ) {
		return false;
	}
	if ( isset( $seen[ $rel ] ) ) {
		return true;
	}
	if ( ! ceros_store_is_allowed_asset_url( $from ) ) {
		return false;
	}

	$response = wp_remote_get(
		$from,
		[
			'timeout'     => CEROS_API_REQUEST_TIMEOUT,
			'redirection' => 2,
		]
	);
	if ( is_wp_error( $response ) ) {
		return false;
	}
	$code = wp_remote_retrieve_response_code( $response );
	if ( $code < 200 || $code >= 300 ) {
		return false;
	}
	$body = wp_remote_retrieve_body( $response );
	if ( '' === $body ) {
		return false;
	}

	if ( ! ceros_store_write_file( $abs_base . '/' . $rel, $body ) ) {
		return false;
	}
	$seen[ $rel ] = true;
	return true;
}

/**
 * Validate a server-supplied relative asset path before writing it.
 *
 * `path` is the decoded filesystem path the asset must live at (its `to` URL is
 * that path percent-encoded under baseUrl, so the web server decodes the request
 * back to this name). Real Ceros keys contain spaces, commas, parentheses, etc.,
 * so we cannot restrict to a narrow alphabet — instead we strip the leading
 * slash and reject only what is actually dangerous: empty / `.` / `..` segments
 * (directory traversal) and control characters or backslashes (NUL injection,
 * Windows-style traversal). Returns '' if unsafe.
 *
 * @param string $path The decoded relative path from the rewrite map.
 * @return string The safe relative path, or '' when rejected.
 */
function ceros_store_safe_rel_path( $path ) {
	$path = ltrim( (string) $path, '/' );
	if ( '' === $path ) {
		return '';
	}
	$out = [];
	foreach ( explode( '/', $path ) as $segment ) {
		if ( '' === $segment || '.' === $segment || '..' === $segment ) {
			return '';
		}
		// Block control chars / NUL and backslashes; everything else (spaces,
		// commas, parens, unicode) is a legitimate filename character.
		if ( preg_match( '/[\x00-\x1f\x7f\\\\]/', $segment ) ) {
			return '';
		}
		$out[] = $segment;
	}
	return implode( '/', $out );
}

/**
 * Whether a URL is an allowed asset-download target: https, a Ceros-owned host,
 * and publicly routable (SSRF guard).
 *
 * @param string $url The URL.
 * @return bool
 */
function ceros_store_is_allowed_asset_url( $url ) {
	if ( 'https' !== strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) ) {
		return false;
	}
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	if ( '' === $host || ! ceros_is_public_host( $host ) ) {
		return false;
	}
	$allowed = [ 'ceros.site', 'ceros.com', 'cerosdev.site', 'cerosstage.site' ];
	foreach ( $allowed as $domain ) {
		if ( $host === $domain || ceros_str_ends_with( $host, '.' . $domain ) ) {
			return true;
		}
	}
	return false;
}

/**
 * PHP 7.4-safe str_ends_with.
 *
 * @param string $haystack Haystack.
 * @param string $needle   Needle.
 * @return bool
 */
function ceros_str_ends_with( $haystack, $needle ) {
	$len = strlen( $needle );
	return 0 === $len || ( strlen( $haystack ) >= $len && 0 === substr_compare( $haystack, $needle, -$len ) );
}

/**
 * Write a file, creating its directory. Returns success.
 *
 * @param string $abs_path Absolute file path.
 * @param string $contents File contents.
 * @return bool
 */
function ceros_store_write_file( $abs_path, $contents ) {
	wp_mkdir_p( dirname( $abs_path ) );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- writing to the uploads dir.
	return false !== file_put_contents( $abs_path, $contents );
}

/**
 * Sanitized "<account>--<experience>" directory key for an experience.
 *
 * @param array $experience The manifest experience object.
 * @return string
 */
function ceros_store_experience_key( $experience ) {
	$account = isset( $experience['accountSlug'] ) ? $experience['accountSlug'] : '';
	$slug    = isset( $experience['slug'] ) ? $experience['slug'] : '';
	$key     = sanitize_key( $account ) . '--' . sanitize_key( $slug );
	$key     = trim( $key, '-' );
	return '' !== $key && '--' !== $key ? $key : 'experience';
}

/**
 * Filesystem-safe version token derived from the manifest's publishedAt
 * (falls back to a content hash).
 *
 * @param array $manifest The manifest.
 * @return string
 */
function ceros_store_version( $manifest ) {
	$published = isset( $manifest['publishedAt'] ) ? (string) $manifest['publishedAt'] : '';
	$token     = preg_replace( '/[^A-Za-z0-9]+/', '-', $published );
	$token     = trim( (string) $token, '-' );
	return '' !== $token ? $token : 'v-' . substr( md5( wp_json_encode( $manifest ) ), 0, 12 );
}

/**
 * Filesystem-safe filename for a page slug.
 *
 * @param string $slug The page slug.
 * @return string
 */
function ceros_store_slug_filename( $slug ) {
	$name = sanitize_file_name( (string) $slug );
	return '' !== $name ? $name : 'index';
}

/**
 * Primary page slug for a manifest (experience.pageSlug, then current page).
 *
 * @param array $manifest The manifest.
 * @return string
 */
function ceros_store_primary_slug( $manifest ) {
	$experience = isset( $manifest['experience'] ) ? $manifest['experience'] : [];
	if ( ! empty( $experience['pageSlug'] ) ) {
		return $experience['pageSlug'];
	}
	foreach ( ( isset( $manifest['pages'] ) ? $manifest['pages'] : [] ) as $page ) {
		if ( is_array( $page ) && ! empty( $page['current'] ) && ! empty( $page['slug'] ) ) {
			return $page['slug'];
		}
	}
	return 'index';
}

/**
 * Delete previously-stored versions of an experience for a post, keeping the
 * just-written one.
 *
 * @param string $uploads_basedir Uploads base directory.
 * @param int    $post_id         Post ID.
 * @param string $exp_key         Experience key.
 * @param string $keep_version    Version directory to keep.
 * @return void
 */
function ceros_store_purge_old_versions( $uploads_basedir, $post_id, $exp_key, $keep_version ) {
	$exp_dir = trailingslashit( $uploads_basedir ) . 'ceros-flex/' . $post_id . '/' . $exp_key;
	if ( ! is_dir( $exp_dir ) ) {
		return;
	}
	foreach ( (array) glob( $exp_dir . '/*', GLOB_ONLYDIR ) as $version_dir ) {
		if ( wp_basename( $version_dir ) !== $keep_version ) {
			ceros_store_rrmdir( $version_dir );
		}
	}
}

/**
 * Recursively delete a directory, guarded to the plugin's storage root.
 *
 * Our layout is shallow (…/<version>/<hash>/<asset path>), so a very deep
 * recursion could only come from a symlink loop. We never follow symlinks
 * (deleting the link itself, not its target) and cap the depth as a backstop.
 *
 * @param string $dir   Absolute directory path.
 * @param int    $depth Current recursion depth (internal).
 * @return void
 */
function ceros_store_rrmdir( $dir, $depth = 0 ) {
	if ( $depth > 20 || false === strpos( $dir, '/ceros-flex/' ) || ! is_dir( $dir ) ) {
		return;
	}
	foreach ( (array) glob( trailingslashit( $dir ) . '*' ) as $item ) {
		if ( is_link( $item ) ) {
			// Delete the symlink itself; never descend into its target.
			wp_delete_file( $item );
		} elseif ( is_dir( $item ) ) {
			ceros_store_rrmdir( $item, $depth + 1 );
		} else {
			wp_delete_file( $item );
		}
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- removing our own empty storage dir.
	@rmdir( $dir );
}

/**
 * Render a stored experience bundle (no network calls).
 *
 * @param string $index_rel_path Path to index.json, relative to the uploads basedir.
 * @return string Rendered HTML, or '' when the bundle is missing/unreadable.
 */
function ceros_render_flex_ssr_stored( $index_rel_path ) {
	$index_rel_path = ltrim( (string) $index_rel_path, '/' );
	// Confine to our storage root.
	if ( false !== strpos( $index_rel_path, '..' ) || 0 !== strpos( $index_rel_path, 'ceros-flex/' ) ) {
		return '';
	}

	$upload    = wp_upload_dir();
	$abs_index = trailingslashit( $upload['basedir'] ) . $index_rel_path;
	if ( ! is_file( $abs_index ) ) {
		return '';
	}

	$index = json_decode( ceros_store_read_file( $abs_index ), true );
	if ( ! is_array( $index ) || empty( $index['pages'] ) ) {
		return '';
	}

	$dir     = dirname( $abs_index );
	$url_dir = trailingslashit( $upload['baseurl'] ) . dirname( $index_rel_path );
	$primary = isset( $index['primarySlug'] ) ? $index['primarySlug'] : '';

	// Resolve the deep-linked page using the primary manifest's experience slug.
	$primary_rel      = isset( $index['pages'][ $primary ] ) ? $index['pages'][ $primary ] : reset( $index['pages'] );
	$primary_manifest = json_decode( ceros_store_read_file( $dir . '/' . $primary_rel ), true );

	$requested = is_array( $primary_manifest ) ? ceros_flex_ssr_requested_slug( $primary_manifest ) : null;
	$served    = ( $requested && isset( $index['pages'][ $requested ] ) ) ? $requested : $primary;

	$served_rel      = isset( $index['pages'][ $served ] ) ? $index['pages'][ $served ] : $primary_rel;
	$served_manifest = json_decode( ceros_store_read_file( $dir . '/' . $served_rel ), true );
	if ( ! is_array( $served_manifest ) ) {
		return '';
	}

	return ceros_flex_ssr_render_manifest( $served_manifest, $url_dir . '/' . $served_rel );
}

/**
 * Read a file from the storage area.
 *
 * @param string $abs_path Absolute path.
 * @return string Contents, or ''.
 */
function ceros_store_read_file( $abs_path ) {
	if ( ! is_file( $abs_path ) ) {
		return '';
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents -- reading our own stored file.
	$contents = file_get_contents( $abs_path );
	return false === $contents ? '' : $contents;
}
