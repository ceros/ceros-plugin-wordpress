<?php
/**
 * Flex SSR "Store" mode — local asset persistence.
 *
 * Downloads a Flex experience's manifest bundle (the entered page plus every
 * linked page) and all referenced assets — SSR delivery scripts/styles,
 * webfonts (and the font files they reference), and media — into the WordPress
 * uploads directory, rewrites every URL in the manifests to the local copies,
 * and writes a per-page manifest plus an `index.json` sidecar that tags the
 * stored files to the experience + published version for cleanup.
 *
 * The published page then renders fully from local storage with no runtime
 * Ceros CDN dependency. Mirrors the Ceros AEM connector's "Store" mode
 * (CerosAssetStorageService), adapted to the WP filesystem.
 *
 * Layout (under wp-content/uploads):
 *   ceros-flex/<postId>/<account>--<experience>/<version>/
 *     index.json            sidecar tag + slug -> page-manifest map
 *     pages/<slug>.json      rewritten per-page manifests
 *     assets/<hash>-<file>   css / js
 *     fonts/<hash>-<file>    webfont css + font files
 *     media/<hash>-<file>    images / video
 *
 * @package ceros
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Top-level entry: fetch + store a Flex experience bundle locally.
 *
 * @param string $manifest_url The experience manifest URL.
 * @param int    $post_id      The post the block belongs to (scopes storage + cleanup).
 * @return array|WP_Error { @type string storedIndexPath, @type string storedAt, @type string storedVersion }
 */
function ceros_store_flex_manifest( $manifest_url, $post_id ) {
	$post_id = absint( $post_id );
	if ( ! $post_id ) {
		return new WP_Error( 'ceros_store_post', __( 'A post ID is required to store an experience.', 'ceros' ) );
	}

	$primary = ceros_fetch_flex_manifest( $manifest_url );
	if ( is_wp_error( $primary ) ) {
		return $primary;
	}

	$experience = isset( $primary['experience'] ) ? $primary['experience'] : [];
	$exp_key    = ceros_store_experience_key( $experience );
	$version    = ceros_store_version( $primary );

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

	// Build the page bundle: primary + every linked page (deduped by slug).
	$primary_slug = ceros_store_primary_slug( $primary );
	$pages        = [ $primary_slug => $primary ];

	foreach ( ( isset( $primary['pages'] ) ? $primary['pages'] : [] ) as $page ) {
		if ( ! is_array( $page ) ) {
			continue;
		}
		$slug = isset( $page['slug'] ) ? $page['slug'] : '';
		if ( '' === $slug || isset( $pages[ $slug ] ) || ! empty( $page['current'] ) ) {
			continue;
		}
		$page_url = isset( $page['manifestUrl'] ) ? $page['manifestUrl'] : '';
		if ( '' === $page_url ) {
			continue;
		}
		$page_manifest = ceros_fetch_flex_manifest( $page_url );
		if ( ! is_wp_error( $page_manifest ) && is_array( $page_manifest ) ) {
			$pages[ $slug ] = $page_manifest;
		}
	}

	// Download + rewrite assets for every page (URL map deduped across the bundle
	// so shared bundles like flex-ssr.js / components.css are fetched once).
	$url_map = [];
	foreach ( $pages as $slug => $manifest ) {
		$pages[ $slug ] = ceros_store_localize_manifest( $manifest, $abs_base, $url_base, $url_map );
	}

	// Rewrite cross-page navigation (pages[].manifestUrl) to the local per-page
	// manifest URLs so the client SPA router stays offline too.
	$local_manifest_urls = [];
	foreach ( $pages as $slug => $manifest ) {
		$local_manifest_urls[ $slug ] = $url_base . '/pages/' . ceros_store_slug_filename( $slug ) . '.json';
	}
	foreach ( $pages as $slug => $manifest ) {
		if ( ! empty( $manifest['pages'] ) && is_array( $manifest['pages'] ) ) {
			foreach ( $manifest['pages'] as $i => $pref ) {
				$ps = is_array( $pref ) && isset( $pref['slug'] ) ? $pref['slug'] : '';
				if ( isset( $local_manifest_urls[ $ps ] ) ) {
					$pages[ $slug ]['pages'][ $i ]['manifestUrl'] = $local_manifest_urls[ $ps ];
				}
			}
		}
	}

	// Write each page manifest.
	$pages_dir = $abs_base . '/pages';
	wp_mkdir_p( $pages_dir );
	$page_index = [];
	foreach ( $pages as $slug => $manifest ) {
		$rel = 'pages/' . ceros_store_slug_filename( $slug ) . '.json';
		ceros_store_write_file( $abs_base . '/' . $rel, wp_json_encode( $manifest ) );
		$page_index[ $slug ] = $rel;
	}

	// Sidecar tag + page map.
	$index = [
		'schema'            => 'ceros-flex-store/1',
		'postId'            => $post_id,
		'accountSlug'       => isset( $experience['accountSlug'] ) ? $experience['accountSlug'] : '',
		'experienceSlug'    => isset( $experience['slug'] ) ? $experience['slug'] : '',
		'version'           => $version,
		'sourceManifestUrl' => $manifest_url,
		'primarySlug'       => $primary_slug,
		'storedAt'          => gmdate( 'c' ),
		'pages'             => $page_index,
	];
	ceros_store_write_file( $abs_base . '/index.json', wp_json_encode( $index ) );

	// Drop any previous versions of this experience for this post.
	ceros_store_purge_old_versions( $upload['basedir'], $post_id, $exp_key, $version );

	return [
		'storedIndexPath' => $rel_base . '/index.json',
		'storedAt'        => $index['storedAt'],
		'storedVersion'   => $version,
	];
}

/**
 * Download + rewrite every external asset a single manifest references.
 *
 * @param array  $manifest The manifest (modified copy returned).
 * @param string $abs_base  Absolute version directory.
 * @param string $url_base  Public URL of the version directory.
 * @param array  $url_map   Shared remote-URL -> local-URL map (by reference).
 * @return array The manifest with local URLs.
 */
function ceros_store_localize_manifest( $manifest, $abs_base, $url_base, &$url_map ) {
	// SSR delivery-mode styles + scripts (components.css, reset.css, flex-ssr.js …).
	if ( isset( $manifest['deliveryModes']['ssr'] ) && is_array( $manifest['deliveryModes']['ssr'] ) ) {
		$ssr = &$manifest['deliveryModes']['ssr'];
		foreach ( [ 'styles', 'scripts' ] as $kind ) {
			if ( empty( $ssr[ $kind ] ) || ! is_array( $ssr[ $kind ] ) ) {
				continue;
			}
			foreach ( $ssr[ $kind ] as $i => $entry ) {
				$url = is_array( $entry ) && ! empty( $entry['url'] ) ? $entry['url'] : '';
				$local = '' !== $url ? ceros_store_download( $url, $abs_base, $url_base, 'assets', $url_map ) : '';
				if ( '' !== $local ) {
					$ssr[ $kind ][ $i ]['url'] = $local;
					// A local file can't honour a remote SRI hash; drop it.
					unset( $ssr[ $kind ][ $i ]['integrity'] );
				}
			}
		}
		unset( $ssr );
	}

	// Assets: webfonts (with nested font files) + per-experience styles.
	if ( ! empty( $manifest['assets'] ) && is_array( $manifest['assets'] ) ) {
		foreach ( $manifest['assets'] as $i => $asset ) {
			if ( ! is_array( $asset ) || empty( $asset['src']['url'] ) ) {
				continue;
			}
			$type = isset( $asset['type'] ) ? $asset['type'] : '';
			$url  = $asset['src']['url'];

			if ( 'webfont' === $type ) {
				$local = ceros_store_download_webfont_css( $url, $abs_base, $url_base, $url_map );
			} elseif ( 'style' === $type ) {
				$local = ceros_store_download( $url, $abs_base, $url_base, 'assets', $url_map );
			} else {
				$local = '';
			}

			if ( '' !== $local ) {
				$manifest['assets'][ $i ]['src']['url'] = $local;
				unset( $manifest['assets'][ $i ]['src']['integrity'] );
			}
		}
	}

	// Media (images / video referenced by the experience body).
	if ( ! empty( $manifest['media'] ) && is_array( $manifest['media'] ) ) {
		foreach ( $manifest['media'] as $i => $entry ) {
			$url = is_array( $entry ) && ! empty( $entry['url'] ) ? $entry['url'] : '';
			if ( '' === $url ) {
				continue;
			}
			$local = ceros_store_is_hls_url( $url )
				? ceros_store_download_hls( $url, $abs_base, $url_base, $url_map )
				: ceros_store_download( $url, $abs_base, $url_base, 'media', $url_map );
			if ( '' !== $local ) {
				$manifest['media'][ $i ]['url'] = $local;
			}
		}
	}

	// Rewrite any of the localized URLs that also appear inline in the html-body
	// or inline scripts (e.g. <img src> in the rendered markup).
	if ( ! empty( $url_map ) ) {
		foreach ( ( isset( $manifest['assets'] ) ? $manifest['assets'] : [] ) as $i => $asset ) {
			if ( ! is_array( $asset ) || empty( $asset['src']['content'] ) || ! is_string( $asset['src']['content'] ) ) {
				continue;
			}
			$type = isset( $asset['type'] ) ? $asset['type'] : '';
			if ( 'html-body' === $type || 'script' === $type ) {
				$manifest['assets'][ $i ]['src']['content'] = strtr( $asset['src']['content'], $url_map );
			}
		}
	}

	return $manifest;
}

/**
 * Download a single asset, returning its local URL ('' on failure).
 *
 * @param string $url      Remote URL.
 * @param string $abs_base Absolute version directory.
 * @param string $url_base Public URL of the version directory.
 * @param string $subdir   Category subdir (assets|fonts|media).
 * @param array  $url_map  Shared remote->local map (by reference).
 * @return string Local URL, or ''.
 */
function ceros_store_download( $url, $abs_base, $url_base, $subdir, &$url_map ) {
	if ( isset( $url_map[ $url ] ) ) {
		return $url_map[ $url ];
	}
	if ( ! ceros_store_is_allowed_asset_url( $url ) ) {
		return '';
	}

	$response = wp_remote_get(
		$url,
		[
			'timeout'     => CEROS_API_REQUEST_TIMEOUT,
			'redirection' => 2,
		]
	);
	if ( is_wp_error( $response ) ) {
		return '';
	}
	$code = wp_remote_retrieve_response_code( $response );
	if ( $code < 200 || $code >= 300 ) {
		return '';
	}
	$body = wp_remote_retrieve_body( $response );
	if ( '' === $body ) {
		return '';
	}

	$filename = ceros_store_filename( $url );
	$rel      = $subdir . '/' . $filename;
	if ( ! ceros_store_write_file( $abs_base . '/' . $rel, $body ) ) {
		return '';
	}

	$local           = $url_base . '/' . $rel;
	$url_map[ $url ] = $local;
	return $local;
}

/**
 * Whether a URL points at an HLS playlist (`.m3u8`).
 *
 * @param string $url The URL.
 * @return bool
 */
function ceros_store_is_hls_url( $url ) {
	$path = strtolower( (string) wp_parse_url( $url, PHP_URL_PATH ) );
	return ceros_str_ends_with( $path, '.m3u8' );
}

/**
 * Download an HLS playlist, localize everything it references — media segments,
 * variant playlists (recursively), and tag URIs (EXT-X-KEY / EXT-X-MAP / …) —
 * and store a rewritten playlist whose URIs are all absolute local URLs.
 *
 * This is stronger than co-locating segments under their original names: it
 * works for relative AND absolute segment URIs and for master→variant
 * playlists, because every URI in the stored playlist is rewritten in place.
 *
 * @param string $url      Playlist URL.
 * @param string $abs_base Absolute version directory.
 * @param string $url_base Public URL of the version directory.
 * @param array  $url_map  Shared remote->local map (by reference).
 * @param int    $depth    Recursion guard for master->variant nesting.
 * @return string Local playlist URL, or ''.
 */
function ceros_store_download_hls( $url, $abs_base, $url_base, &$url_map, $depth = 0 ) {
	if ( isset( $url_map[ $url ] ) ) {
		return $url_map[ $url ];
	}
	if ( $depth > 3 || ! ceros_store_is_allowed_asset_url( $url ) ) {
		return '';
	}

	$response = wp_remote_get( $url, [ 'timeout' => CEROS_API_REQUEST_TIMEOUT, 'redirection' => 2 ] );
	if ( is_wp_error( $response ) ) {
		return '';
	}
	$code = wp_remote_retrieve_response_code( $response );
	if ( $code < 200 || $code >= 300 ) {
		return '';
	}
	$playlist = wp_remote_retrieve_body( $response );
	if ( '' === $playlist ) {
		return '';
	}

	$rel   = 'media/' . ceros_store_filename( $url, 'm3u8' );
	$local = $url_base . '/' . $rel;
	// Reserve the mapping before recursing so a self/circular reference can't loop.
	$url_map[ $url ] = $local;

	$lines = preg_split( '/\r\n|\r|\n/', $playlist );
	foreach ( $lines as $idx => $line ) {
		$trimmed = trim( $line );
		if ( '' === $trimmed ) {
			continue;
		}

		// Tag lines: rewrite any URI="..." attribute (EXT-X-KEY, EXT-X-MAP, …).
		if ( '#' === $trimmed[0] ) {
			$lines[ $idx ] = preg_replace_callback(
				'/URI="([^"]+)"/i',
				function ( $m ) use ( $url, $abs_base, $url_base, &$url_map, $depth ) {
					$local_uri = ceros_store_localize_hls_ref( $m[1], $url, $abs_base, $url_base, $url_map, $depth );
					return '' !== $local_uri ? 'URI="' . $local_uri . '"' : $m[0];
				},
				$line
			);
			continue;
		}

		// Bare lines are media segments or variant playlists.
		$local_ref = ceros_store_localize_hls_ref( $trimmed, $url, $abs_base, $url_base, $url_map, $depth );
		if ( '' !== $local_ref ) {
			$lines[ $idx ] = $local_ref;
		}
	}

	if ( ! ceros_store_write_file( $abs_base . '/' . $rel, implode( "\n", $lines ) ) ) {
		unset( $url_map[ $url ] );
		return '';
	}
	return $local;
}

/**
 * Resolve + download a single reference from an HLS playlist, recursing into
 * nested playlists. Returns the local URL ('' on failure).
 *
 * @param string $ref          The (possibly relative) reference.
 * @param string $playlist_url  The playlist URL the ref is relative to.
 * @param string $abs_base      Absolute version directory.
 * @param string $url_base      Public URL of the version directory.
 * @param array  $url_map       Shared remote->local map (by reference).
 * @param int    $depth         Current recursion depth.
 * @return string Local URL, or ''.
 */
function ceros_store_localize_hls_ref( $ref, $playlist_url, $abs_base, $url_base, &$url_map, $depth ) {
	$abs = ceros_store_absolute_url( trim( $ref ), $playlist_url );
	if ( '' === $abs ) {
		return '';
	}
	return ceros_store_is_hls_url( $abs )
		? ceros_store_download_hls( $abs, $abs_base, $url_base, $url_map, $depth + 1 )
		: ceros_store_download( $abs, $abs_base, $url_base, 'media', $url_map );
}

/**
 * Download a webfont stylesheet, localize the font files it references, and
 * store the rewritten CSS locally.
 *
 * @param string $css_url  Webfont CSS URL.
 * @param string $abs_base Absolute version directory.
 * @param string $url_base Public URL of the version directory.
 * @param array  $url_map  Shared remote->local map (by reference).
 * @return string Local CSS URL, or ''.
 */
function ceros_store_download_webfont_css( $css_url, $abs_base, $url_base, &$url_map ) {
	if ( isset( $url_map[ $css_url ] ) ) {
		return $url_map[ $css_url ];
	}
	if ( ! ceros_store_is_allowed_asset_url( $css_url ) ) {
		return '';
	}

	$response = wp_remote_get( $css_url, [ 'timeout' => CEROS_API_REQUEST_TIMEOUT, 'redirection' => 2 ] );
	if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) < 200 || wp_remote_retrieve_response_code( $response ) >= 300 ) {
		return '';
	}
	$css = wp_remote_retrieve_body( $response );

	// Localize each url(...) font reference, resolving relative URLs against the CSS URL.
	if ( preg_match_all( '/url\(\s*([\'"]?)([^\'")]+)\1\s*\)/i', $css, $matches ) ) {
		foreach ( array_unique( $matches[2] ) as $font_ref ) {
			$abs_font = ceros_store_absolute_url( trim( $font_ref ), $css_url );
			if ( '' === $abs_font ) {
				continue;
			}
			$local_font = ceros_store_download( $abs_font, $abs_base, $url_base, 'fonts', $url_map );
			if ( '' !== $local_font ) {
				$css = str_replace( $font_ref, $local_font, $css );
			}
		}
	}

	$filename = ceros_store_filename( $css_url, 'css' );
	$rel      = 'fonts/' . $filename;
	if ( ! ceros_store_write_file( $abs_base . '/' . $rel, $css ) ) {
		return '';
	}
	$local             = $url_base . '/' . $rel;
	$url_map[ $css_url ] = $local;
	return $local;
}

/**
 * Build a collision-free local filename for a remote URL: an 8-char hash of the
 * full URL prefixed to the sanitized basename.
 *
 * @param string $url           Remote URL.
 * @param string $force_ext     Optional extension to force (e.g. 'css').
 * @return string Filename.
 */
function ceros_store_filename( $url, $force_ext = '' ) {
	$path     = (string) wp_parse_url( $url, PHP_URL_PATH );
	$basename = sanitize_file_name( wp_basename( $path ) );
	if ( '' === $basename ) {
		$basename = 'asset';
	}
	if ( '' !== $force_ext && ! preg_match( '/\.' . preg_quote( $force_ext, '/' ) . '$/i', $basename ) ) {
		$basename .= '.' . $force_ext;
	}
	return substr( md5( $url ), 0, 8 ) . '-' . $basename;
}

/**
 * Resolve a possibly-relative URL against a base URL.
 *
 * @param string $ref  The (possibly relative) reference.
 * @param string $base The base URL.
 * @return string Absolute URL, or '' for data: URIs and unresolvable inputs.
 */
function ceros_store_absolute_url( $ref, $base ) {
	if ( '' === $ref || 0 === stripos( $ref, 'data:' ) ) {
		return '';
	}
	if ( preg_match( '#^https?://#i', $ref ) ) {
		return $ref;
	}
	$parts = wp_parse_url( $base );
	if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
		return '';
	}
	$origin = $parts['scheme'] . '://' . $parts['host'] . ( ! empty( $parts['port'] ) ? ':' . $parts['port'] : '' );
	if ( 0 === strpos( $ref, '/' ) ) {
		return $origin . $ref;
	}
	$dir = isset( $parts['path'] ) ? preg_replace( '#/[^/]*$#', '/', $parts['path'] ) : '/';
	return $origin . $dir . $ref;
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
 * @param string $dir Absolute directory path.
 * @return void
 */
function ceros_store_rrmdir( $dir ) {
	if ( false === strpos( $dir, '/ceros-flex/' ) || ! is_dir( $dir ) ) {
		return;
	}
	foreach ( (array) glob( trailingslashit( $dir ) . '*' ) as $item ) {
		if ( is_dir( $item ) ) {
			ceros_store_rrmdir( $item );
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

	$upload   = wp_upload_dir();
	$abs_index = trailingslashit( $upload['basedir'] ) . $index_rel_path;
	if ( ! is_file( $abs_index ) ) {
		return '';
	}

	$index = json_decode( ceros_store_read_file( $abs_index ), true );
	if ( ! is_array( $index ) || empty( $index['pages'] ) ) {
		return '';
	}

	$dir      = dirname( $abs_index );
	$url_dir  = trailingslashit( $upload['baseurl'] ) . dirname( $index_rel_path );
	$primary  = isset( $index['primarySlug'] ) ? $index['primarySlug'] : '';

	// Resolve the deep-linked page using the primary manifest's experience slug.
	$primary_rel = isset( $index['pages'][ $primary ] ) ? $index['pages'][ $primary ] : reset( $index['pages'] );
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
