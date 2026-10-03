<?php
/**
 * Newsletter Broadcast — when a Blog post is published, emails every
 * subscriber captured by the theme's newsletter signup form. Off by
 * default (see admin-menu.php's 'default' => false) since this is a
 * new, site-visible behavior, not something a live site could already
 * be depending on.
 *
 * Nothing here hardcodes a business's email address or name — the
 * "From" address/name come entirely from the existing SMTP settings
 * (wp_mail_from / wp_mail_from_name, inc/smtp-settings.php), exactly
 * like every other email this engine sends.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The newsletter signup form's slug — themes can point this at a different form via this filter. */
function weavit_newsletter_form_slug() {
	return apply_filters( 'weavit_newsletter_form_slug', 'newsletter-signup' );
}

/** Subscriber rows: array of { entry_id, email, token }. Skips entries with no usable email. */
function weavit_newsletter_subscribers() {
	$form_id = bootg_get_system_form_id( weavit_newsletter_form_slug() );
	if ( ! $form_id ) {
		return array();
	}

	$email_labels = array();
	foreach ( bootg_get_form_schema( $form_id ) as $field ) {
		if ( 'email' === $field['type'] ) {
			$email_labels[] = $field['label'];
		}
	}
	if ( ! $email_labels ) {
		return array();
	}

	$query = bootg_get_form_entries( $form_id, array( 'posts_per_page' => -1 ) );
	$subscribers = array();

	foreach ( $query->posts as $entry ) {
		// Double opt-in (inc/newsletter-confirm.php): sign-ups that haven't clicked their confirmation link yet get nothing.
		if ( 'pending' === get_post_meta( $entry->ID, '_weavit_sub_status', true ) ) {
			continue;
		}
		$email = '';
		foreach ( bootg_get_entry_data( $entry->ID ) as $row ) {
			if ( in_array( $row['label'], $email_labels, true ) && is_email( $row['value'] ) ) {
				$email = $row['value'];
				break;
			}
		}
		if ( ! $email ) {
			continue;
		}

		$token = get_post_meta( $entry->ID, '_weavit_unsubscribe_token', true );
		if ( ! $token ) {
			$token = wp_generate_password( 32, false );
			update_post_meta( $entry->ID, '_weavit_unsubscribe_token', $token );
		}

		$subscribers[] = array( 'entry_id' => $entry->ID, 'email' => $email, 'token' => $token );
	}

	return $subscribers;
}

/** Sends one Blog post's notification to every current subscriber. Idempotent per post (guarded by post meta). */
function weavit_broadcast_post_to_subscribers( $post_id ) {
	if ( get_post_meta( $post_id, '_weavit_newsletter_sent', true ) ) {
		return;
	}
	// Mark first (optimistic lock) so a slow/partial send can't double-fire from a rapid repeat status transition.
	update_post_meta( $post_id, '_weavit_newsletter_sent', current_time( 'mysql' ) );

	$post = get_post( $post_id );
	if ( ! $post ) {
		return;
	}

	$subscribers = weavit_newsletter_subscribers();
	if ( ! $subscribers ) {
		return;
	}

	$site_name = get_bloginfo( 'name' );
	$subject   = apply_filters( 'weavit_newsletter_subject', sprintf( 'New on the %s blog: %s', $site_name, get_the_title( $post ) ), $post );
	$excerpt   = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 );
	$permalink = get_permalink( $post );

	foreach ( $subscribers as $subscriber ) {
		$unsubscribe_url = add_query_arg( 'weavit_unsubscribe', $subscriber['token'], home_url( '/' ) );

		$body = $excerpt . "\n\n" . 'Read the full post: ' . $permalink . "\n\n"
			. '---' . "\n"
			. 'You\'re receiving this because you subscribed to updates from ' . $site_name . '.' . "\n"
			. 'Unsubscribe at any time: ' . $unsubscribe_url;

		wp_mail( $subscriber['email'], $subject, apply_filters( 'weavit_newsletter_body', $body, $post, $subscriber ) );
	}
}

add_action( 'transition_post_status', function ( $new_status, $old_status, $post ) {
	if ( ! bootg_module_enabled( 'newsletter-broadcast' ) ) {
		return;
	}
	if ( 'publish' !== $new_status || 'publish' === $old_status || 'post' !== $post->post_type ) {
		return;
	}
	weavit_broadcast_post_to_subscribers( $post->ID );
}, 10, 3 );

/** One-click unsubscribe — no login, no confirmation step (the link itself is the confirmation, standard practice). */
add_action( 'template_redirect', function () {
	if ( empty( $_GET['weavit_unsubscribe'] ) ) {
		return;
	}
	$token = sanitize_text_field( wp_unslash( $_GET['weavit_unsubscribe'] ) );

	$entries = get_posts( array(
		'post_type'      => 'bootg_form_entry',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'meta_key'       => '_weavit_unsubscribe_token', // phpcs:ignore
		'meta_value'     => $token, // phpcs:ignore
		'fields'         => 'ids',
	) );

	$site_name = get_bloginfo( 'name' );
	$message   = $entries ? 'You\'ve been unsubscribed and won\'t receive any more emails from us.' : 'That unsubscribe link has already been used or is no longer valid.';

	if ( $entries ) {
		wp_trash_post( $entries[0] );
	}

	wp_die(
		'<div style="max-width:480px;margin:80px auto;padding:40px;text-align:center;font-family:sans-serif;">'
			. '<h1 style="font-size:1.4rem;">' . esc_html( $site_name ) . '</h1>'
			. '<p>' . esc_html( $message ) . '</p>'
			. '<p><a href="' . esc_url( home_url( '/' ) ) . '">Return to the site</a></p>'
			. '</div>',
		'Unsubscribed',
		array( 'response' => 200 )
	);
} );
