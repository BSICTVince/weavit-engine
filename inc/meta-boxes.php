<?php
/**
 * Custom meta boxes for the CPTs — plain core Meta Boxes API
 * (add_meta_box / register_post_meta), no ACF.
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
		case 'testimonial':
			return array(
				'quote'           => array( 'Quote', 'textarea' ),
				'author_name'     => array( 'Author name', 'text' ),
				'author_business' => array( 'Author business / location', 'text' ),
				'rating'          => array( 'Rating (1-5)', 'number' ),
				'is_spotlight'    => array( 'Show in homepage spotlight', 'checkbox' ),
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
	foreach ( array( 'service', 'integration', 'testimonial', 'team_member', 'guide' ) as $post_type ) {
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

add_action( 'add_meta_boxes', function () {
	foreach ( array( 'service', 'integration', 'testimonial', 'team_member', 'guide' ) as $post_type ) {
		add_meta_box(
			'bootg_' . $post_type . '_fields',
			'Details',
			'bootg_render_meta_box',
			$post_type,
			'normal',
			'high'
		);
	}
} );

function bootg_render_meta_box( $post ) {
	wp_nonce_field( 'bootg_save_meta', 'bootg_meta_nonce' );
	$fields = bootg_meta_fields( $post->post_type );
	echo '<table class="form-table">';
	foreach ( $fields as $key => $field ) {
		list( $label, $type ) = $field;
		$value = get_post_meta( $post->ID, $key, true );
		echo '<tr><th style="width:220px;"><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		switch ( $type ) {
			case 'textarea':
				echo '<textarea id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" rows="3" class="large-text">' . esc_textarea( $value ) . '</textarea>';
				break;
			case 'number':
				echo '<input type="number" min="1" max="5" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" class="small-text">';
				break;
			case 'checkbox':
				echo '<input type="checkbox" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="1" ' . checked( (bool) $value, true, false ) . '>';
				break;
			case 'url':
				echo '<input type="url" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" class="regular-text">';
				break;
			default:
				echo '<input type="text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" class="regular-text">';
		}
		echo '</td></tr>';
	}
	echo '</table>';
}

add_action( 'save_post', function ( $post_id ) {
	if ( ! isset( $_POST['bootg_meta_nonce'] ) || ! wp_verify_nonce( $_POST['bootg_meta_nonce'], 'bootg_save_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	$post_type = get_post_type( $post_id );
	$fields    = bootg_meta_fields( $post_type );
	if ( ! $fields || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	foreach ( $fields as $key => $field ) {
		$type      = $field[1];
		$sanitizer = bootg_meta_sanitizer( $type );
		if ( 'checkbox' === $type ) {
			update_post_meta( $post_id, $key, isset( $_POST[ $key ] ) ? 1 : 0 );
			continue;
		}
		if ( isset( $_POST[ $key ] ) ) {
			update_post_meta( $post_id, $key, call_user_func( $sanitizer, wp_unslash( $_POST[ $key ] ) ) );
		}
	}
} );
