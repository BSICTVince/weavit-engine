<?php
/**
 * A real redirect manager — no plugin needed. Each redirect is a
 * `bootg_redirect` post (hidden CPT, same pattern as the Forms engine's
 * bootg_form/bootg_form_entry): one or more source URLs (exact or
 * "contains" match), a destination, an HTTP status (301/302/307/410/451),
 * and an active/inactive/trash status — with hit counting so you can see
 * which redirects are actually being used. Matching happens early on
 * template_redirect, before any page is rendered.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	register_post_type( 'bootg_redirect', array(
		'labels'       => array( 'name' => 'Redirects', 'singular_name' => 'Redirect' ),
		'public'       => false,
		'show_ui'      => false,
		'show_in_menu' => false,
		'supports'     => array( 'title' ),
	) );
} );

/* ---------------------------------------------------------------------
 * Meta helpers
 * ------------------------------------------------------------------- */

function bootg_redirect_types() {
	return array(
		301 => '301 Permanent Move',
		302 => '302 Temporary Move',
		307 => '307 Temporary Redirect',
		410 => '410 Content Deleted',
		451 => '451 Content Unavailable for Legal Reasons',
	);
}

function bootg_get_redirect_sources( $post_id ) {
	$raw = json_decode( (string) get_post_meta( $post_id, '_bootg_sources', true ), true );
	return is_array( $raw ) ? $raw : array();
}

function bootg_get_redirect( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || 'bootg_redirect' !== $post->post_type ) {
		return null;
	}
	return array(
		'id'            => $post->ID,
		'sources'       => bootg_get_redirect_sources( $post_id ),
		'destination'   => get_post_meta( $post_id, '_bootg_destination', true ),
		'type'          => (int) get_post_meta( $post_id, '_bootg_type', true ) ?: 301,
		'hits'          => (int) get_post_meta( $post_id, '_bootg_hits', true ),
		'last_accessed' => get_post_meta( $post_id, '_bootg_last_accessed', true ),
		'status'        => $post->post_status,
		'created'       => $post->post_date,
	);
}

/** Normalizes a user-entered source into a bare path (strips domain if a full URL was pasted). */
function bootg_normalize_redirect_path( $value ) {
	$path = wp_parse_url( trim( $value ), PHP_URL_PATH ) ?: trim( $value );
	return '/' . ltrim( $path, '/' );
}

function bootg_save_redirect( $post_id, $data ) {
	$sources = array();
	foreach ( (array) ( $data['sources'] ?? array() ) as $s ) {
		$url = trim( $s['url'] ?? '' );
		if ( '' === $url ) {
			continue;
		}
		$sources[] = array(
			'url'   => bootg_normalize_redirect_path( $url ),
			'match' => 'contains' === ( $s['match'] ?? 'exact' ) ? 'contains' : 'exact',
		);
	}
	update_post_meta( $post_id, '_bootg_sources', wp_json_encode( $sources ) );

	$type = (int) ( $data['type'] ?? 301 );
	if ( ! isset( bootg_redirect_types()[ $type ] ) ) {
		$type = 301;
	}
	update_post_meta( $post_id, '_bootg_type', $type );

	$destination = in_array( $type, array( 410, 451 ), true ) ? '' : bootg_normalize_redirect_path( $data['destination'] ?? '' );
	if ( $destination && preg_match( '#^https?://#i', trim( $data['destination'] ?? '' ) ) ) {
		$destination = esc_url_raw( trim( $data['destination'] ) ); // A full external URL was entered — keep it as-is rather than mangling it into a path.
	}
	update_post_meta( $post_id, '_bootg_destination', $destination );

	if ( ! get_post_meta( $post_id, '_bootg_hits', true ) ) {
		update_post_meta( $post_id, '_bootg_hits', 0 );
	}

	$title = $sources ? $sources[0]['url'] : 'Untitled redirect';
	wp_update_post( array( 'ID' => $post_id, 'post_title' => $title ) );
}

/* ---------------------------------------------------------------------
 * Front-end matching
 * ------------------------------------------------------------------- */

add_action( 'template_redirect', function () {
	if ( is_admin() || is_user_logged_in() ) {
		// Never redirect a logged-in user mid-edit; keeps this from ever
		// interfering with previewing/editing the very page it targets.
		return;
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
	$current     = untrailingslashit( wp_parse_url( $request_uri, PHP_URL_PATH ) ?: '/' );
	if ( '' === $current ) {
		$current = '/';
	}

	$redirects = get_posts( array(
		'post_type'      => 'bootg_redirect',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
	) );

	foreach ( $redirects as $post ) {
		foreach ( bootg_get_redirect_sources( $post->ID ) as $source ) {
			$from    = untrailingslashit( $source['url'] );
			$matched = 'contains' === $source['match']
				? ( '' !== $from && str_contains( $current, $from ) )
				: $from === $current;

			if ( ! $matched ) {
				continue;
			}

			update_post_meta( $post->ID, '_bootg_hits', (int) get_post_meta( $post->ID, '_bootg_hits', true ) + 1 );
			update_post_meta( $post->ID, '_bootg_last_accessed', current_time( 'mysql' ) );

			$type = (int) get_post_meta( $post->ID, '_bootg_type', true ) ?: 301;
			if ( in_array( $type, array( 410, 451 ), true ) ) {
				status_header( $type );
				nocache_headers();
				wp_die( 410 === $type ? 'This content has been permanently removed.' : 'This content is unavailable for legal reasons.', $type, array( 'response' => $type ) );
			}

			$destination = get_post_meta( $post->ID, '_bootg_destination', true );
			$to          = str_starts_with( $destination, 'http' ) ? $destination : home_url( $destination );
			wp_redirect( esc_url_raw( $to ), $type ); // phpcs:ignore WordPress.Security.SafeRedirect -- admin-entered, capability-gated destinations, external redirects intentionally supported.
			exit;
		}
	}
}, 5 );
