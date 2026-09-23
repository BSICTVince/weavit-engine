<?php
/**
 * Forms engine — submission handling. One shared admin-post handler
 * processes every form (form_id travels as a hidden field), storing each
 * submission as a bootg_form_entry post and optionally emailing a
 * notification via wp_mail() (see inc/smtp-settings.php).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_post_bootg_form_submit', 'bootg_handle_form_submit' );
add_action( 'admin_post_nopriv_bootg_form_submit', 'bootg_handle_form_submit' );

function bootg_handle_form_submit() {
	$form_id     = absint( $_POST['bootg_form_id'] ?? 0 );
	$redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : home_url( '/' );
	$back        = add_query_arg( array( 'bootg_form' => $form_id ), $redirect_to );

	$form = get_post( $form_id );
	if ( ! $form || 'bootg_form' !== $form->post_type ) {
		wp_safe_redirect( add_query_arg( 'status', 'error', $back ) );
		exit;
	}

	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'bootg_form_submit_' . $form_id ) ) {
		wp_safe_redirect( add_query_arg( 'status', 'error', $back ) );
		exit;
	}

	// Honeypot: silently "succeed" without saving anything.
	if ( ! empty( $_POST['website'] ) ) {
		wp_safe_redirect( add_query_arg( 'status', 'success', $back ) );
		exit;
	}

	if ( ! bootg_verify_recaptcha( bootg_get_submitted_recaptcha_token() ) ) {
		wp_safe_redirect( add_query_arg( 'status', 'recaptcha', $back ) );
		exit;
	}

	$fields   = bootg_get_form_schema( $form_id );
	$posted   = (array) ( $_POST['bootg_field'] ?? array() );
	$data     = array();
	$is_valid = true;

	foreach ( $fields as $field ) {
		$raw = wp_unslash( $posted[ $field['field_id'] ] ?? '' );

		if ( 'checkbox' === $field['type'] ) {
			$value = ! empty( $raw ) ? 'Yes' : 'No';
			if ( ! empty( $field['required'] ) && 'Yes' !== $value ) {
				$is_valid = false;
			}
		} elseif ( 'textarea' === $field['type'] ) {
			$value = sanitize_textarea_field( $raw );
		} elseif ( 'email' === $field['type'] ) {
			$value = sanitize_email( $raw );
			if ( ! empty( $field['required'] ) && ! is_email( $value ) ) {
				$is_valid = false;
			}
		} else {
			$value = sanitize_text_field( $raw );
		}

		if ( ! empty( $field['required'] ) && 'checkbox' !== $field['type'] && '' === trim( (string) $value ) ) {
			$is_valid = false;
		}

		$data[] = array(
			'label' => $field['label'],
			'value' => $value,
		);
	}

	if ( ! $is_valid ) {
		wp_safe_redirect( add_query_arg( 'status', 'error', $back ) );
		exit;
	}

	$entry_id = wp_insert_post( array(
		'post_type'   => 'bootg_form_entry',
		'post_status' => 'publish',
		/* translators: %s: form title */
		'post_title'  => sprintf( '%s — %s', $form->post_title, current_time( 'Y-m-d H:i:s' ) ),
	), true );

	if ( ! is_wp_error( $entry_id ) ) {
		update_post_meta( $entry_id, '_bootg_entry_form_id', $form_id );
		update_post_meta( $entry_id, '_bootg_entry_data', $data );

		$settings = bootg_get_form_settings( $form_id );
		if ( ! empty( $settings['notify_enabled'] ) ) {
			$to = $settings['notify_email'] ?: ( bootg_get_option( 'email' ) ?: get_option( 'admin_email' ) );
			/* translators: %s: form title */
			$subject = sprintf( 'New "%s" submission', $form->post_title );
			$body    = '';
			foreach ( $data as $row ) {
				$body .= $row['label'] . ': ' . $row['value'] . "\n";
			}
			wp_mail( $to, $subject, $body );
		}
	}

	wp_safe_redirect( add_query_arg( 'status', 'success', $back ) );
	exit;
}
