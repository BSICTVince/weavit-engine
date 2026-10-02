<?php
/**
 * Custom fields for the CPTs — register_post_meta() (show_in_rest) plus
 * the Weavit toolbar icon/sidebar panel (below) as the editing UI. No ACF.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Field map per post type: meta_key => [ label, type ].
 * type is one of: text, url, textarea, number, checkbox.
 */
function bootg_meta_fields( $post_type ) {
	switch ( $post_type ) {
		case 'service':
			return array(
				'card_summary'   => array( 'Homepage card summary (short)', 'textarea' ),
				'eyebrow'        => array( 'Eyebrow label (above "What\'s Included")', 'text' ),
				'hero_headline'  => array( 'Hero headline (leave blank to use the title)', 'text' ),
				'cta_label'      => array( 'CTA label', 'text' ),
				'cta_url'        => array( 'CTA URL', 'url' ),
				'features'       => array( 'Feature checklist (one per line)', 'textarea' ),
			);
		case 'integration':
			return array(
				'subtitle'            => array( 'Hero subtitle', 'text' ),
				'intro'               => array( 'Hero intro paragraph', 'textarea' ),
				'partner_badge_label' => array( 'Partner badge label (e.g. "Xero Gold Partner")', 'text' ),
				'login_url'           => array( 'Client login URL (optional)', 'url' ),
				'features'            => array( 'Feature checklist (one per line)', 'textarea' ),
			);
		case 'team_member':
			return array(
				'role'         => array( 'Role / title', 'text' ),
				'linkedin_url' => array( 'LinkedIn URL', 'url' ),
			);
		case 'guide':
			return array(
				'subtitle' => array( 'Subtitle', 'text' ),
				'cta_label' => array( 'CTA label', 'text' ),
				'cta_url'   => array( 'CTA URL', 'url' ),
			);
	}
	return array();
}

add_action( 'init', function () {
	foreach ( array( 'service', 'integration', 'team_member', 'guide' ) as $post_type ) {
		foreach ( bootg_meta_fields( $post_type ) as $key => $field ) {
			register_post_meta( $post_type, $key, array(
				'single'            => true,
				'type'              => 'checkbox' === $field[1] ? 'boolean' : 'string',
				'show_in_rest'      => true,
				'sanitize_callback' => bootg_meta_sanitizer( $field[1] ),
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			) );
		}
	}
} );

function bootg_meta_sanitizer( $type ) {
	switch ( $type ) {
		case 'url':
			return 'esc_url_raw';
		case 'textarea':
			return 'sanitize_textarea_field';
		case 'number':
			return 'absint';
		case 'checkbox':
			return 'rest_sanitize_boolean';
		default:
			return 'sanitize_text_field';
	}
}

/**
 * A "Weavit" icon in the block editor toolbar (next to the default WP
 * tabs, before Save — same spot RankMath puts its SEO icon) opening a
 * sidebar panel for this post type's fields. Same field map/data as the
 * classic meta box below — see assets/js/editor-panel.js.
 */
add_action( 'enqueue_block_editor_assets', function () {
	$screen    = get_current_screen();
	$post_type = $screen ? $screen->post_type : '';
	$fields    = bootg_meta_fields( $post_type );
	if ( ! $fields ) {
		return;
	}

	$js_fields = array();
	foreach ( $fields as $key => $field ) {
		$js_fields[ $key ] = array( 'label' => $field[0], 'type' => $field[1] );
	}

	wp_enqueue_script(
		'weavit-editor-panel',
		WEAVIT_ENGINE_URI . 'assets/js/editor-panel.js',
		array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data' ),
		WEAVIT_ENGINE_VERSION,
		true
	);
	wp_add_inline_script(
		'weavit-editor-panel',
		'window.weavitEditorPanel = ' . wp_json_encode( array( 'fields' => $js_fields ) ) . ';',
		'before'
	);
} );

// The classic "Details" meta box (add_meta_box/save_post) that used to
// live here has been replaced by the Weavit toolbar icon + sidebar panel
// above — same fields, same register_post_meta(show_in_rest) data, saved
// through the block editor's own REST request instead of a second $_POST
// handler. Removed instead of left duplicated.
