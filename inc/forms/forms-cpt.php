<?php
/**
 * Forms engine — data layer. Two native CPTs, no custom DB tables, no
 * plugins: `bootg_form` (the form definition + field schema) and
 * `bootg_form_entry` (one submission). Both are admin-only; the custom
 * "Forms" admin UI (inc/forms/forms-admin.php) replaces the default
 * post-list screens entirely.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	register_post_type( 'bootg_form', array(
		'labels'       => array(
			'name'          => 'Forms',
			'singular_name' => 'Form',
		),
		'public'       => false,
		'show_ui'      => false,
		'show_in_menu' => false,
		'supports'     => array( 'title' ),
	) );

	register_post_type( 'bootg_form_entry', array(
		'labels'       => array(
			'name'          => 'Form Entries',
			'singular_name' => 'Form Entry',
		),
		'public'       => false,
		'show_ui'      => false,
		'show_in_menu' => false,
		'supports'     => array( 'title' ),
	) );
} );

/**
 * The URL of the page currently being rendered. Deliberately NOT
 * get_permalink() — that reads the global $post, which loop-based
 * shortcodes (e.g. [bootg_latest_post]) can leave pointing at the wrong
 * post by the time a form in the footer renders, sending redirects to
 * the wrong page. $wp->request reflects the actual matched route instead.
 */
function bootg_current_url() {
	global $wp;
	return home_url( isset( $wp->request ) ? $wp->request : '' );
}

/** Valid field types and their admin-UI labels. */
function bootg_form_field_types() {
	return array(
		'text'     => 'Single Line Text',
		'email'    => 'Email',
		'tel'      => 'Phone',
		'number'   => 'Number',
		'textarea' => 'Paragraph Text',
		'select'   => 'Dropdown',
		'checkbox' => 'Checkbox',
	);
}

/** Read a form's field schema (array of field defs) from postmeta. */
function bootg_get_form_schema( $form_id ) {
	$raw = get_post_meta( $form_id, '_bootg_form_schema', true );
	if ( ! $raw ) {
		return array();
	}
	$fields = json_decode( $raw, true );
	return is_array( $fields ) ? $fields : array();
}

/** Persist a form's field schema. Fields are re-keyed with a stable field_id if missing. */
function bootg_save_form_schema( $form_id, $fields ) {
	$clean = array();
	foreach ( (array) $fields as $field ) {
		$types = array_keys( bootg_form_field_types() );
		$type  = in_array( $field['type'] ?? '', $types, true ) ? $field['type'] : 'text';
		$item  = array(
			'field_id'    => sanitize_key( $field['field_id'] ?? '' ) ?: 'field_' . wp_generate_password( 8, false ),
			'type'        => $type,
			'label'       => sanitize_text_field( $field['label'] ?? '' ),
			'placeholder' => sanitize_text_field( $field['placeholder'] ?? '' ),
			'required'    => ! empty( $field['required'] ),
			'width'       => 'half' === ( $field['width'] ?? 'full' ) ? 'half' : 'full',
		);
		if ( 'select' === $type ) {
			$options = array();
			foreach ( (array) ( $field['options'] ?? array() ) as $opt ) {
				$opt = trim( sanitize_text_field( $opt ) );
				if ( '' !== $opt ) {
					$options[] = $opt;
				}
			}
			$item['options'] = $options;
		}
		$clean[] = $item;
	}
	update_post_meta( $form_id, '_bootg_form_schema', wp_json_encode( $clean ) );
	return $clean;
}

function bootg_get_form_settings( $form_id ) {
	$raw = get_post_meta( $form_id, '_bootg_form_settings', true );
	$defaults = array(
		'notify_enabled'  => true,
		'notify_email'    => '',
		'success_message' => 'Thanks — your submission has been received.',
		'submit_label'    => 'Submit',
	);
	$saved = is_array( $raw ) ? $raw : array();
	return wp_parse_args( $saved, $defaults );
}

function bootg_save_form_settings( $form_id, $settings ) {
	$clean = array(
		'notify_enabled'  => ! empty( $settings['notify_enabled'] ),
		'notify_email'    => sanitize_email( $settings['notify_email'] ?? '' ),
		'success_message' => sanitize_text_field( $settings['success_message'] ?? '' ) ?: 'Thanks — your submission has been received.',
		'submit_label'    => sanitize_text_field( $settings['submit_label'] ?? '' ) ?: 'Submit',
	);
	update_post_meta( $form_id, '_bootg_form_settings', $clean );
	return $clean;
}

/** The shortcode tag for a form: [form_{id}_{slug}]. */
function bootg_form_shortcode_tag( $form_id, $title = null ) {
	if ( null === $title ) {
		$title = get_the_title( $form_id );
	}
	$slug = sanitize_title( $title );
	$slug = str_replace( '-', '_', $slug );
	$slug = preg_replace( '/[^a-z0-9_]/', '', $slug );
	return 'form_' . absint( $form_id ) . '_' . ( $slug ?: 'form' );
}

function bootg_get_all_forms() {
	return get_posts( array(
		'post_type'      => 'bootg_form',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
	) );
}

function bootg_count_form_entries( $form_id ) {
	$q = new WP_Query( array(
		'post_type'      => 'bootg_form_entry',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'meta_key'       => '_bootg_entry_form_id', // phpcs:ignore
		'meta_value'     => $form_id, // phpcs:ignore
		'fields'         => 'ids',
	) );
	return (int) $q->found_posts;
}

function bootg_get_form_entries( $form_id, $args = array() ) {
	$defaults = array(
		'post_type'      => 'bootg_form_entry',
		'post_status'    => 'publish',
		'posts_per_page' => 30,
		'paged'          => 1,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'meta_key'       => '_bootg_entry_form_id', // phpcs:ignore
		'meta_value'     => $form_id, // phpcs:ignore
	);
	return new WP_Query( wp_parse_args( $args, $defaults ) );
}

function bootg_get_entry_data( $entry_id ) {
	$raw = get_post_meta( $entry_id, '_bootg_entry_data', true );
	return is_array( $raw ) ? $raw : array();
}
