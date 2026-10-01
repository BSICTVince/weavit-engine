<?php
/**
 * Minimal "install a theme/plugin straight from its GitHub tags" helper —
 * the missing piece that lets Starter Sites work on a site that doesn't
 * have the target theme installed yet (same tag-based source as
 * inc/updates.php's Plugin Update Checker, just for a first install
 * instead of an update check).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Highest version-numbered git tag for an "owner/repo" GitHub repo, e.g. "v0.1.36". */
function weavit_github_latest_tag( $repo ) {
	$response = wp_remote_get( "https://api.github.com/repos/{$repo}/tags?per_page=100", array(
		'headers' => array( 'User-Agent' => 'WeavitEngine' ),
	) );
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return new WP_Error( 'github_tags_failed', 'Could not list tags for ' . $repo . '.' );
	}

	$tags = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( empty( $tags ) || ! is_array( $tags ) ) {
		return new WP_Error( 'no_tags', 'No tags found for ' . $repo . '.' );
	}

	$names = wp_list_pluck( $tags, 'name' );
	usort( $names, function ( $a, $b ) {
		return version_compare( ltrim( $b, 'v' ), ltrim( $a, 'v' ) );
	} );

	return $names[0];
}

/**
 * Downloads a GitHub tag's zipball and installs it as a theme or plugin
 * under the given slug (overwriting any existing copy at that slug).
 * Same zipball URL shape WordPress's own updater already uses for this
 * plugin/theme (see the "Downloading update from…" line a normal
 * `wp plugin update` / `wp theme update` prints).
 */
function weavit_install_github_package( $repo, $tag, $type, $slug ) {
	if ( ! function_exists( 'download_url' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

	$zip_url  = "https://api.github.com/repos/{$repo}/zipball/refs/tags/{$tag}";
	$tmp_file = download_url( $zip_url );
	if ( is_wp_error( $tmp_file ) ) {
		return $tmp_file;
	}

	if ( ! WP_Filesystem() ) {
		@unlink( $tmp_file );
		return new WP_Error( 'no_filesystem', 'Could not access the filesystem.' );
	}
	global $wp_filesystem;

	$tmp_dir = get_temp_dir() . 'weavit-install-' . wp_generate_password( 8, false, false );
	$unzipped = unzip_file( $tmp_file, $tmp_dir );
	@unlink( $tmp_file );

	if ( is_wp_error( $unzipped ) ) {
		return $unzipped;
	}

	// A GitHub tag zipball always extracts to one top-level "owner-repo-hash" folder.
	$entries = array_values( array_diff( (array) scandir( $tmp_dir ), array( '.', '..' ) ) );
	if ( 1 !== count( $entries ) || ! $wp_filesystem->is_dir( $tmp_dir . '/' . $entries[0] ) ) {
		$wp_filesystem->delete( $tmp_dir, true );
		return new WP_Error( 'unexpected_archive', 'Unexpected archive layout from GitHub.' );
	}
	$source = $tmp_dir . '/' . $entries[0];

	$dest_root = 'theme' === $type ? get_theme_root() : WP_PLUGIN_DIR;
	$dest      = $dest_root . '/' . $slug;

	if ( $wp_filesystem->exists( $dest ) ) {
		$wp_filesystem->delete( $dest, true );
	}

	$copied = copy_dir( $source, $dest );
	$wp_filesystem->delete( $tmp_dir, true );

	if ( is_wp_error( $copied ) ) {
		return $copied;
	}

	return true;
}
