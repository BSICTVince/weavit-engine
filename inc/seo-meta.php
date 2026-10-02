<?php
/**
 * Per-page/post SEO fields — Meta Title, Meta Description, Keywords.
 * Core Meta Boxes API only, no SEO plugin. Gated behind the "SEO Meta
 * Fields" module (Weavit → Modules) — a site switching to Yoast, RankMath,
 * or another SEO plugin should turn this off rather than have two plugins
 * fighting over the same <title>/meta tags. register_post_meta() stays
 * registered either way so no data is lost if it's re-enabled later; only
 * the editor tab and the <head> output are gated.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bootg_seo_post_types() {
	return array( 'page', 'post', 'service', 'integration', 'guide', 'testimonial', 'team_member' );
}

add_action( 'init', function () {
	foreach ( bootg_seo_post_types() as $post_type ) {
		foreach ( array( 'meta_title', 'meta_description', 'meta_keywords' ) as $key ) {
			register_post_meta( $post_type, $key, array(
				'single'            => true,
				'type'              => 'string',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			) );
		}
	}
} );

// Contributes an "SEO" tab to the Weavit sidebar panel (inc/editor-panel.php)
// instead of its own classic meta box — same register_post_meta() fields above.
add_filter( 'weavit_editor_panel_tabs', function ( $tabs, $post_type ) {
	if ( ! bootg_module_enabled( 'seo-meta' ) || ! in_array( $post_type, bootg_seo_post_types(), true ) ) {
		return $tabs;
	}

	$tabs['seo'] = array(
		'label'  => 'SEO',
		'fields' => array(
			'meta_title'       => array(
				'label' => 'Meta Title',
				'type'  => 'text',
				'help'  => 'Leave blank to use the page title. Shown in the browser tab and search results.',
			),
			'meta_description' => array(
				'label' => 'Meta Description',
				'type'  => 'textarea',
				'help'  => 'Shown under the title in search results. Aim for ~150–160 characters.',
			),
			'meta_keywords'    => array(
				'label' => 'Keywords',
				'type'  => 'text',
				'help'  => 'Comma-separated. Largely ignored by modern search engines, kept for completeness.',
			),
		),
	);
	return $tabs;
}, 10, 2 );

/** Output: <title> override */
add_filter( 'pre_get_document_title', function ( $title ) {
	if ( ! bootg_module_enabled( 'seo-meta' ) || ! is_singular() ) {
		return $title;
	}
	$custom = get_post_meta( get_queried_object_id(), 'meta_title', true );
	return $custom ?: $title;
} );

/** Output: meta description + keywords tags */
add_action( 'wp_head', function () {
	if ( ! bootg_module_enabled( 'seo-meta' ) || ! is_singular() ) {
		return;
	}
	$id          = get_queried_object_id();
	$description = get_post_meta( $id, 'meta_description', true );
	$keywords    = get_post_meta( $id, 'meta_keywords', true );

	if ( $description ) {
		echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
	}
	if ( $keywords ) {
		echo '<meta name="keywords" content="' . esc_attr( $keywords ) . '">' . "\n";
	}
}, 1 );
