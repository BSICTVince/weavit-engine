<?php
/**
 * Generic starter-content importer. Reads plain JSON data files (shipped by
 * a theme's own content/ directory — this engine ships none of its own)
 * and creates the matching WordPress objects. Every function here is
 * idempotent — safe to run more than once — and theme-agnostic: nothing
 * in this file references Bookkeeping On The Go by name.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Reads and decodes a JSON file. Returns an empty array (never false/null) on any failure. */
function bootg_load_json( $path ) {
	if ( ! file_exists( $path ) ) {
		return array();
	}
	$raw = file_get_contents( $path ); // phpcs:ignore
	if ( false === $raw ) {
		return array();
	}
	$data = json_decode( $raw, true );
	return is_array( $data ) ? $data : array();
}

/**
 * Creates one post per item for a given post type, skipping any whose
 * title already exists. Each item: { title, summary?, image?, meta? }.
 * summary becomes post_content; image (an external URL) is sideloaded
 * into the Media Library and set as the featured image.
 */
function bootg_import_cpt_items( $post_type, $items ) {
	if ( ! function_exists( 'media_sideload_image' ) ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	$created = 0;

	foreach ( $items as $item ) {
		if ( empty( $item['title'] ) ) {
			continue;
		}

		$existing = get_posts( array(
			'post_type'              => $post_type,
			'title'                  => $item['title'],
			'post_status'            => 'any',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		) );
		if ( $existing ) {
			continue;
		}

		$post_id = wp_insert_post( array(
			'post_type'    => $post_type,
			'post_title'   => $item['title'],
			'post_content' => $item['summary'] ?? '',
			'post_status'  => 'publish',
		), true );

		if ( is_wp_error( $post_id ) ) {
			continue;
		}

		foreach ( ( $item['meta'] ?? array() ) as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		if ( ! empty( $item['image'] ) ) {
			$tmp = download_url( $item['image'] );
			if ( ! is_wp_error( $tmp ) ) {
				$file_array = array(
					'name'     => basename( wp_parse_url( $item['image'], PHP_URL_PATH ) ),
					'tmp_name' => $tmp,
				);
				$attachment_id = media_handle_sideload( $file_array, $post_id );
				if ( ! is_wp_error( $attachment_id ) ) {
					set_post_thumbnail( $post_id, $attachment_id );
				} else {
					@unlink( $tmp ); // phpcs:ignore
				}
			}
		}

		++$created;
	}

	return $created;
}

/**
 * Backfills post_content + meta on EXISTING posts (matched by title),
 * skipping any post whose `guard_meta_key` already has a value — the
 * pattern for a two-pass seed (create bare posts first, then fill in
 * richer detail content later without overwriting manual edits).
 * Each fix: { content, meta: { key => value, ... } }.
 */
function bootg_import_content_fixes( $post_type, $fixes, $guard_meta_key = 'features' ) {
	$fixed = 0;

	foreach ( $fixes as $title => $fix ) {
		$existing = get_posts( array(
			'post_type'      => $post_type,
			'title'          => $title,
			'post_status'    => 'any',
			'posts_per_page' => 1,
		) );
		if ( ! $existing ) {
			continue;
		}

		$post_id = $existing[0]->ID;
		if ( get_post_meta( $post_id, $guard_meta_key, true ) ) {
			continue;
		}

		if ( isset( $fix['content'] ) ) {
			wp_update_post( array( 'ID' => $post_id, 'post_content' => $fix['content'] ) );
		}
		foreach ( ( $fix['meta'] ?? array() ) as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		++$fixed;
	}

	return $fixed;
}

/** Find a bootg_form by its post_name, or create it from the given definition. Idempotent by slug. */
function bootg_get_or_create_system_form( $slug, $title, $fields, $settings = array() ) {
	$existing = get_posts( array(
		'post_type'      => 'bootg_form',
		'name'           => $slug,
		'post_status'    => 'publish',
		'posts_per_page' => 1,
	) );
	if ( $existing ) {
		return $existing[0]->ID;
	}

	$form_id = wp_insert_post( array(
		'post_type'   => 'bootg_form',
		'post_status' => 'publish',
		'post_title'  => $title,
		'post_name'   => $slug,
	), true );

	if ( is_wp_error( $form_id ) ) {
		return 0;
	}

	bootg_save_form_schema( $form_id, $fields );
	bootg_save_form_settings( $form_id, $settings );

	return $form_id;
}

/**
 * Creates every form defined in a JSON file, idempotent by slug. Each
 * item: { slug, title, fields: [...], settings: {...} } — fields/settings
 * use exactly the same shape bootg_save_form_schema()/bootg_save_form_settings()
 * already store internally.
 */
function bootg_import_forms( $forms ) {
	$ids = array();
	foreach ( $forms as $form ) {
		if ( empty( $form['slug'] ) || empty( $form['title'] ) ) {
			continue;
		}
		$ids[ $form['slug'] ] = bootg_get_or_create_system_form(
			$form['slug'],
			$form['title'],
			$form['fields'] ?? array(),
			$form['settings'] ?? array()
		);
	}
	return $ids;
}

/** Get a previously-imported system form's ID by its slug (0 if it doesn't exist yet). */
function bootg_get_system_form_id( $slug ) {
	$existing = get_posts( array(
		'post_type'      => 'bootg_form',
		'name'           => $slug,
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
	) );
	return $existing ? (int) $existing[0] : 0;
}
