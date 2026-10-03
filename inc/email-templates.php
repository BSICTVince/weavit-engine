<?php
/**
 * Email Templates — every email the site sends on its own, in one place and
 * editable any time from Weavit > Email Templates:
 *
 *   newsletter_confirm   subscriber   "Click here to confirm your subscription"
 *   newsletter_welcome   subscriber   thank-you after they confirm (off by default)
 *   newsletter_post      subscribers  a new blog post was published
 *   enquiry_notify       you          a form was submitted (all forms)
 *   enquiry_autoreply    the sender   "Thanks, we got your message" (contact form)
 *
 * Each template is plain text with {placeholders}; one shared layout (brand
 * colour, header, footer) wraps them all. Mail goes out through wp_mail(), so
 * the SMTP settings apply. A multipart plain-text version is attached too.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WEAVIT_EMAIL_TEMPLATES_OPTION', 'weavit_email_templates' );
define( 'WEAVIT_EMAIL_LAYOUT_OPTION', 'weavit_email_layout' );

/* ---------------------------------------------------------------------
 * 1. The templates and their defaults
 * ------------------------------------------------------------------- */

function weavit_email_registry() {
	return array(
		'newsletter_confirm' => array(
			'label'       => 'Newsletter confirmation',
			'audience'    => 'Sent to: the new subscriber',
			'description' => 'Sent as soon as someone subscribes. They only join the list once they click the link.',
			'tokens'      => '{site_name} {confirm_link}',
			'defaults'    => array(
				'enabled' => 1,
				'subject' => 'Confirm your subscription to {site_name}',
				'body'    => "Hello,\n\nYou've received this message because you subscribed to {site_name}. Please confirm your subscription to receive emails from us:\n\n{confirm_link}\n\nThank you,\n\n{site_name}\n\nIf you received this email by mistake, simply delete it. You won't receive any more emails from us unless you confirm your subscription using the link above.",
			),
		),
		'newsletter_welcome' => array(
			'label'       => 'Welcome after confirming',
			'audience'    => 'Sent to: the subscriber, right after they confirm',
			'description' => 'A short thank-you once their subscription is confirmed. Off by default.',
			'tokens'      => '{site_name} {unsubscribe_link}',
			'defaults'    => array(
				'enabled' => 0,
				'subject' => 'Welcome to {site_name}',
				'body'    => "Hello,\n\nThank you for confirming your subscription — you're now on the {site_name} list.\n\nWe'll send you helpful bookkeeping, BAS and payroll updates, and let you know when we publish something new.\n\nThank you,\n\n{site_name}\n\nYou can unsubscribe at any time:\n\n{unsubscribe_link}",
			),
		),
		'newsletter_post'    => array(
			'label'       => 'New blog post to subscribers',
			'audience'    => 'Sent to: every confirmed subscriber, when a blog post is published',
			'description' => 'Only goes out while the Newsletter Broadcast module is switched on (Weavit → Modules).',
			'tokens'      => '{site_name} {post_title} {post_excerpt} {post_link} {post_url} {unsubscribe_link}',
			'defaults'    => array(
				'enabled' => 1,
				'subject' => 'New on the {site_name} blog: {post_title}',
				'body'    => "{post_excerpt}\n\n{post_link}\n\nYou're receiving this because you subscribed to updates from {site_name}.\n\n{unsubscribe_link}",
			),
		),
		'enquiry_notify'     => array(
			'label'       => 'Form submission notice',
			'audience'    => 'Sent to: you (the address set on each form)',
			'description' => 'Tells you when anyone fills in a form. Replying to it replies straight to the sender.',
			'tokens'      => '{site_name} {form_name} {name} {fields}',
			'defaults'    => array(
				'enabled' => 1,
				'subject' => 'New "{form_name}" submission',
				'body'    => "You've received a new submission on {site_name}.\n\n{fields}\n\nReply to this email to respond to the sender.",
			),
		),
		'enquiry_autoreply'  => array(
			'label'       => 'Auto-reply to enquiries',
			'audience'    => 'Sent to: the person who used the contact form',
			'description' => 'Lets them know their message arrived. Sent for the contact form only.',
			'tokens'      => '{site_name} {name} {fields} {phone} {email}',
			'defaults'    => array(
				'enabled' => 1,
				'subject' => 'Thanks for contacting {site_name}',
				'body'    => "Hello {name},\n\nThank you for getting in touch with {site_name}. We've received your message and will reply within one business day.\n\nHere's a copy of what you sent us:\n\n{fields}\n\nIf you need to reach us sooner, call {phone}.\n\nKind regards,\n\n{site_name}\n\nIf you didn't send this message, you can safely ignore this email.",
			),
		),
	);
}

function weavit_email_template( $id ) {
	$registry = weavit_email_registry();
	if ( ! isset( $registry[ $id ] ) ) {
		return array( 'enabled' => 0, 'subject' => '', 'body' => '' );
	}
	$defaults = $registry[ $id ]['defaults'];
	$saved    = get_option( WEAVIT_EMAIL_TEMPLATES_OPTION, array() );
	$saved    = isset( $saved[ $id ] ) && is_array( $saved[ $id ] ) ? $saved[ $id ] : array();

	// Before this page existed the confirmation wording lived in the Newsletter settings; keep any edits made there.
	if ( 'newsletter_confirm' === $id && ! $saved ) {
		$legacy = get_option( 'weavit_newsletter_settings', array() );
		if ( ! empty( $legacy['subject'] ) ) {
			$defaults['subject'] = $legacy['subject'];
		}
		if ( ! empty( $legacy['body'] ) ) {
			$defaults['body'] = $legacy['body'];
		}
	}
	return wp_parse_args( $saved, $defaults );
}

function weavit_email_layout() {
	return wp_parse_args( get_option( WEAVIT_EMAIL_LAYOUT_OPTION, array() ), array(
		'brand_color' => '#643486',
		'header_text' => '',
		'footer_text' => '{site_name} · {phone} · {email}',
	) );
}

/* ---------------------------------------------------------------------
 * 2. Rendering
 * ------------------------------------------------------------------- */

function weavit_email_site_name() {
	return wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
}

/** Placeholder => value, for either the HTML or the plain-text version. */
function weavit_email_token_map( $vars, $html, $brand = '#643486' ) {
	$e    = function ( $v ) use ( $html ) {
		return $html ? esc_html( (string) $v ) : (string) $v;
	};
	$link = function ( $url, $label ) use ( $html, $brand ) {
		if ( ! $url ) {
			return '';
		}
		return $html ? '<a href="' . esc_url( $url ) . '" style="color:' . esc_attr( $brand ) . ';font-weight:700;">' . esc_html( $label ) . '</a>' : $label . ': ' . $url;
	};

	$rows = array();
	foreach ( (array) ( $vars['fields'] ?? array() ) as $r ) {
		$rows[] = $r;
	}
	if ( $html ) {
		$cells = '';
		foreach ( $rows as $r ) {
			$cells .= '<tr><td style="padding:6px 14px 6px 0;color:#6e6478;vertical-align:top;white-space:nowrap;">' . esc_html( $r['label'] ) . '</td><td style="padding:6px 0;color:#1d1a20;">' . str_replace( array( "\r\n", "\n", "\r" ), '<br>', esc_html( $r['value'] ) ) . '</td></tr>';
		}
		$fields = $cells ? '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0 16px;font-size:15px;">' . $cells . '</table>' : '';
	} else {
		$fields = '';
		foreach ( $rows as $r ) {
			$fields .= $r['label'] . ': ' . $r['value'] . "\n";
		}
		$fields = rtrim( $fields );
	}

	$name = isset( $vars['name'] ) && '' !== trim( (string) $vars['name'] ) ? $vars['name'] : 'there';

	return array(
		'{site_name}'        => $e( weavit_email_site_name() ),
		'{site_url}'         => $e( home_url( '/' ) ),
		'{phone}'            => $e( bootg_get_option( 'phone' ) ),
		'{email}'            => $e( bootg_get_option( 'email' ) ),
		'{name}'             => $e( $name ),
		'{form_name}'        => $e( $vars['form_name'] ?? '' ),
		'{fields}'           => $fields,
		'{post_title}'       => $e( $vars['post_title'] ?? '' ),
		'{post_excerpt}'     => $e( $vars['post_excerpt'] ?? '' ),
		'{post_url}'         => $e( $vars['post_url'] ?? '' ),
		'{confirm_link}'     => $link( $vars['confirm_url'] ?? '', 'Click here to confirm your subscription' ),
		'{post_link}'        => $link( $vars['post_url'] ?? '', 'Read the full post' ),
		'{unsubscribe_link}' => $link( $vars['unsubscribe_url'] ?? '', 'Unsubscribe' ),
	);
}

/** Builds subject + HTML + plain text for one template. */
function weavit_email_render( $id, $vars = array() ) {
	$t      = weavit_email_template( $id );
	$layout = weavit_email_layout();
	$brand  = preg_match( '/^#[0-9a-f]{3,6}$/i', $layout['brand_color'] ) ? $layout['brand_color'] : '#643486';

	$html_map  = weavit_email_token_map( $vars, true, $brand );
	$plain_map = weavit_email_token_map( $vars, false, $brand );

	$subject = trim( preg_replace( '/\s+/', ' ', strtr( $t['subject'], $plain_map ) ) );
	$body    = wpautop( strtr( esc_html( $t['body'] ), $html_map ) );
	$plain   = strtr( $t['body'], $plain_map );

	$logo_id = (int) get_theme_mod( 'custom_logo' );
	$logo    = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
	$header  = $logo
		? '<img src="' . esc_url( $logo ) . '" alt="' . esc_attr( weavit_email_site_name() ) . '" style="max-height:56px;width:auto;border:0;">'
		: esc_html( '' !== trim( $layout['header_text'] ) ? $layout['header_text'] : weavit_email_site_name() );
	$footer  = strtr( esc_html( $layout['footer_text'] ), $html_map );

	$html = '<div style="background:#f7f3fa;padding:24px 12px;font-family:Arial,Helvetica,sans-serif;">'
		. '<div style="max-width:580px;margin:0 auto;">'
		. '<div style="background:#ffffff;border-radius:10px;border-top:4px solid ' . esc_attr( $brand ) . ';padding:30px 32px;color:#1d1a20;font-size:16px;line-height:1.6;">'
		. '<div style="margin:0 0 20px;font-size:22px;font-weight:700;color:' . esc_attr( $brand ) . ';">' . $header . '</div>'
		. $body
		. '</div>'
		. '<p style="margin:14px 8px 0;text-align:center;font-size:12px;line-height:1.5;color:#6e6478;">' . $footer . '</p>'
		. '</div></div>';

	return array(
		'subject' => $subject,
		'html'    => $html,
		'plain'   => trim( $plain . "\n\n--\n" . strtr( $layout['footer_text'], $plain_map ) ),
	);
}

/**
 * Sends one template. $args: force (send even if switched off), prefix
 * (prepended to the subject), headers (extra mail headers).
 * Returns true when the mailer accepted it.
 */
function weavit_email_send( $id, $to, $vars = array(), $args = array() ) {
	$t = weavit_email_template( $id );
	if ( empty( $args['force'] ) && empty( $t['enabled'] ) ) {
		return false;
	}
	$m = weavit_email_render( $id, $vars );

	$set_type = function () {
		return 'text/html';
	};
	$set_alt  = function ( $phpmailer ) use ( $m ) {
		$phpmailer->AltBody = $m['plain']; // For mail apps that don't show HTML.
	};
	add_filter( 'wp_mail_content_type', $set_type );
	add_action( 'phpmailer_init', $set_alt );
	$sent = wp_mail( $to, ( $args['prefix'] ?? '' ) . $m['subject'], $m['html'], $args['headers'] ?? array() );
	remove_filter( 'wp_mail_content_type', $set_type );
	remove_action( 'phpmailer_init', $set_alt );

	return (bool) $sent;
}

/** Pulls a sender's name and email out of a submitted form. */
function weavit_email_form_person( $form_id, $data ) {
	$labels = array();
	foreach ( bootg_get_form_schema( $form_id ) as $field ) {
		$labels[ $field['field_id'] ] = $field['label'];
	}
	$by_label = array();
	foreach ( (array) $data as $row ) {
		$by_label[ $row['label'] ] = $row['value'];
	}
	$name = '';
	foreach ( array( 'name', 'first_name' ) as $field_id ) {
		if ( isset( $labels[ $field_id ] ) && ! empty( $by_label[ $labels[ $field_id ] ] ) ) {
			$name = $by_label[ $labels[ $field_id ] ];
			break;
		}
	}
	$email = '';
	foreach ( (array) $data as $row ) {
		if ( isset( $row['value'] ) && is_email( $row['value'] ) ) {
			$email = $row['value'];
			break;
		}
	}
	return array( $name, $email );
}

/** Form submission -> notice to you. Called from the forms handler. */
function weavit_email_form_notify( $to, $form, $data ) {
	list( $name, $email ) = weavit_email_form_person( $form->ID, $data );
	$headers = $email ? array( 'Reply-To: ' . ( $name ? str_replace( array( '"', "\r", "\n" ), '', $name ) . ' <' . $email . '>' : $email ) ) : array();
	return weavit_email_send( 'enquiry_notify', $to, array(
		'form_name' => $form->post_title,
		'name'      => $name,
		'fields'    => $data,
	), array( 'headers' => $headers ) );
}

/** Contact form -> thank-you to the sender. */
add_action( 'bootg_form_entry_saved', function ( $entry_id, $form_id, $data ) {
	$form  = get_post( $form_id );
	$slugs = (array) apply_filters( 'weavit_autoreply_form_slugs', array( 'contact-form' ) );
	if ( ! $form || ! in_array( $form->post_name, $slugs, true ) ) {
		return;
	}
	list( $name, $email ) = weavit_email_form_person( $form_id, $data );
	if ( ! $email ) {
		return;
	}
	// Don't let the form be used to spam someone else's inbox: one auto-reply per address per 10 minutes.
	$throttle = 'weavit_ar_' . md5( strtolower( $email ) );
	if ( get_transient( $throttle ) ) {
		return;
	}
	set_transient( $throttle, 1, 10 * MINUTE_IN_SECONDS );
	weavit_email_send( 'enquiry_autoreply', $email, array(
		'form_name' => $form->post_title,
		'name'      => $name,
		'fields'    => $data,
	) );
}, 20, 3 );

/* ---------------------------------------------------------------------
 * 3. Weavit > Email Templates
 * ------------------------------------------------------------------- */

add_action( 'admin_menu', function () {
	add_submenu_page( 'weavit', 'Email Templates', 'Email Templates', 'manage_options', 'weavit-emails', 'weavit_email_render_page' );
} );

function weavit_email_back( $args = array() ) {
	return add_query_arg( array_merge( array( 'page' => 'weavit-emails' ), $args ), admin_url( 'admin.php' ) );
}

function weavit_email_sample_vars() {
	return array(
		'name'            => 'Alex',
		'form_name'       => 'Contact form',
		'fields'          => array(
			array( 'label' => 'Name', 'value' => 'Alex Sample' ),
			array( 'label' => 'Email', 'value' => 'alex@example.com' ),
			array( 'label' => 'Message', 'value' => "Hi, I'd like some help with my BAS." ),
		),
		'confirm_url'     => add_query_arg( array( 'weavit_subscription' => 'confirm', 'token' => 'preview' ), home_url( '/' ) ),
		'post_title'      => 'Sample blog post title',
		'post_excerpt'    => 'This is a short sample of how the start of a new blog post will look in your subscribers\' inbox.',
		'post_url'        => home_url( '/blog/' ),
		'unsubscribe_url' => add_query_arg( 'weavit_unsubscribe', 'preview', home_url( '/' ) ),
	);
}

add_action( 'admin_post_weavit_email_save', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'weavit_email_save' );
	$id = sanitize_key( wp_unslash( $_POST['template'] ?? '' ) );
	$registry = weavit_email_registry();
	if ( isset( $registry[ $id ] ) ) {
		$d     = $registry[ $id ]['defaults'];
		$saved = get_option( WEAVIT_EMAIL_TEMPLATES_OPTION, array() );
		$saved[ $id ] = array(
			'enabled' => empty( $_POST['enabled'] ) ? 0 : 1,
			'subject' => sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) ) ?: $d['subject'],
			'body'    => sanitize_textarea_field( wp_unslash( $_POST['body'] ?? '' ) ) ?: $d['body'],
		);
		update_option( WEAVIT_EMAIL_TEMPLATES_OPTION, $saved );
	}
	wp_safe_redirect( weavit_email_back( array( 'saved' => $id, 'open' => $id ) ) . '#t-' . $id );
	exit;
} );

add_action( 'admin_post_weavit_email_reset', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'weavit_email_reset' );
	$id    = sanitize_key( wp_unslash( $_POST['template'] ?? '' ) );
	$saved = get_option( WEAVIT_EMAIL_TEMPLATES_OPTION, array() );
	unset( $saved[ $id ] );
	update_option( WEAVIT_EMAIL_TEMPLATES_OPTION, $saved );
	if ( 'newsletter_confirm' === $id ) {
		$legacy = get_option( 'weavit_newsletter_settings', array() ); // Also forget wording from the old Newsletter page.
		unset( $legacy['subject'], $legacy['body'] );
		update_option( 'weavit_newsletter_settings', $legacy );
	}
	wp_safe_redirect( weavit_email_back( array( 'reset' => $id, 'open' => $id ) ) . '#t-' . $id );
	exit;
} );

add_action( 'admin_post_weavit_email_test', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'weavit_email_test' );
	$id = sanitize_key( wp_unslash( $_POST['template'] ?? '' ) );
	$to = sanitize_email( wp_unslash( $_POST['test_to'] ?? '' ) );
	if ( ! is_email( $to ) || ! isset( weavit_email_registry()[ $id ] ) ) {
		$result = 'invalid';
	} else {
		$result = weavit_email_send( $id, $to, weavit_email_sample_vars(), array( 'force' => true, 'prefix' => '[Test] ' ) ) ? 'sent' : 'failed';
	}
	wp_safe_redirect( weavit_email_back( array( 'test' => $result, 'to' => rawurlencode( $to ), 'open' => $id ) ) . '#t-' . $id );
	exit;
} );

add_action( 'admin_post_weavit_email_layout_save', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'weavit_email_layout_save' );
	$color = sanitize_hex_color( wp_unslash( $_POST['brand_color'] ?? '' ) );
	update_option( WEAVIT_EMAIL_LAYOUT_OPTION, array(
		'brand_color' => $color ?: '#643486',
		'header_text' => sanitize_text_field( wp_unslash( $_POST['header_text'] ?? '' ) ),
		'footer_text' => sanitize_text_field( wp_unslash( $_POST['footer_text'] ?? '' ) ) ?: '{site_name} · {phone} · {email}',
	) );
	wp_safe_redirect( weavit_email_back( array( 'layout' => 1 ) ) );
	exit;
} );

function weavit_email_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$layout = weavit_email_layout();
	$open   = sanitize_key( wp_unslash( $_GET['open'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$sample = weavit_email_sample_vars();
	$placeholders = array(
		'{site_name}' => 'your site name', '{name}' => 'the sender\'s name ("there" if none)', '{phone}' => 'your phone number (Site Options)', '{email}' => 'your email (Site Options)',
		'{fields}' => 'everything the person filled in', '{form_name}' => 'which form it came from', '{confirm_link}' => 'the "Click here to confirm" link',
		'{post_title}' => 'blog post title', '{post_excerpt}' => 'blog post summary', '{post_link}' => 'the "Read the full post" link', '{unsubscribe_link}' => 'the unsubscribe link',
	);
	?>
	<div class="wrap">
		<h1>Email Templates</h1>

		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success is-dismissible"><p>Template saved.</p></div>
		<?php elseif ( isset( $_GET['reset'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success is-dismissible"><p>Template reset to the default wording.</p></div>
		<?php elseif ( isset( $_GET['layout'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success is-dismissible"><p>Layout saved — it applies to every email.</p></div>
		<?php elseif ( isset( $_GET['test'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<?php $tr = sanitize_key( wp_unslash( $_GET['test'] ) ); // phpcs:ignore WordPress.Security.NonceVerification ?>
			<?php if ( 'sent' === $tr ) : ?>
				<div class="notice notice-success is-dismissible"><p>Test email handed to the mailer for <strong><?php echo esc_html( rawurldecode( sanitize_text_field( wp_unslash( $_GET['to'] ?? '' ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification ?></strong>. If it doesn't arrive in a few minutes, check the spam folder and Weavit &rarr; SMTP.</p></div>
			<?php elseif ( 'failed' === $tr ) : ?>
				<div class="notice notice-error"><p>The mailer couldn't send the test. Check Weavit &rarr; SMTP.</p></div>
			<?php else : ?>
				<div class="notice notice-error"><p>Please enter a valid email address.</p></div>
			<?php endif; ?>
		<?php endif; ?>

		<p style="max-width:780px;">These are all the emails the site sends by itself. Change the wording any time, switch an email off, or send yourself a test. Emails go out through <a href="<?php echo esc_url( admin_url( 'admin.php?page=bootg-smtp-settings' ) ); ?>">Weavit &rarr; SMTP</a>.</p>

		<?php foreach ( weavit_email_registry() as $id => $def ) : ?>
			<?php
			$t       = weavit_email_template( $id );
			$preview = weavit_email_render( $id, $sample );
			?>
			<details id="t-<?php echo esc_attr( $id ); ?>" class="postbox" style="max-width:900px;margin:12px 0;padding:0;" <?php echo ( $open === $id ) ? 'open' : ''; ?>>
				<summary style="cursor:pointer;padding:14px 18px;font-size:15px;">
					<strong><?php echo esc_html( $def['label'] ); ?></strong>
					<span style="margin-left:8px;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600;<?php echo $t['enabled'] ? 'background:#e6f4ea;color:#0a7a2f;' : 'background:#f0f0f1;color:#646970;'; ?>"><?php echo $t['enabled'] ? 'On' : 'Off'; ?></span>
					<span style="display:block;color:#646970;margin-top:3px;font-size:13px;"><?php echo esc_html( $def['audience'] ); ?></span>
				</summary>
				<div style="padding:4px 18px 18px;">
					<p class="description"><?php echo esc_html( $def['description'] ); ?></p>

					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
						<?php wp_nonce_field( 'weavit_email_save' ); ?>
						<input type="hidden" name="action" value="weavit_email_save">
						<input type="hidden" name="template" value="<?php echo esc_attr( $id ); ?>">
						<p><label><input type="checkbox" name="enabled" value="1" <?php checked( $t['enabled'] ); ?>> Send this email</label></p>
						<p><label for="s-<?php echo esc_attr( $id ); ?>"><strong>Subject</strong></label><br>
							<input type="text" id="s-<?php echo esc_attr( $id ); ?>" name="subject" class="large-text" value="<?php echo esc_attr( $t['subject'] ); ?>"></p>
						<p><label for="b-<?php echo esc_attr( $id ); ?>"><strong>Message</strong></label><br>
							<textarea id="b-<?php echo esc_attr( $id ); ?>" name="body" rows="11" class="large-text"><?php echo esc_textarea( $t['body'] ); ?></textarea></p>
						<p class="description">Plain text — a blank line starts a new paragraph. You can use:
							<?php
							$shown = array();
							foreach ( explode( ' ', $def['tokens'] ) as $tok ) {
								if ( isset( $placeholders[ $tok ] ) ) {
									$shown[] = '<code>' . esc_html( $tok ) . '</code> ' . esc_html( $placeholders[ $tok ] );
								} elseif ( '{post_url}' === $tok ) {
									$shown[] = '<code>{post_url}</code> the bare web address';
								}
							}
							echo implode( ' &middot; ', $shown ); // phpcs:ignore WordPress.Security.EscapeOutput
							?>
						</p>
						<?php submit_button( 'Save this email', 'primary', 'submit', false ); ?>
					</form>

					<div style="display:flex;gap:24px;flex-wrap:wrap;margin-top:18px;">
						<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="flex:1 1 280px;">
							<?php wp_nonce_field( 'weavit_email_test' ); ?>
							<input type="hidden" name="action" value="weavit_email_test">
							<input type="hidden" name="template" value="<?php echo esc_attr( $id ); ?>">
							<strong>Send a test</strong><br>
							<input type="email" name="test_to" placeholder="name@example.com" class="regular-text" required>
							<?php submit_button( 'Send test', 'secondary', 'submit', false ); ?>
							<p class="description">Uses sample details and the saved wording, even if the email is switched off.</p>
						</form>
						<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="flex:0 0 auto;" onsubmit="return confirm('Reset this email to the default wording? Your changes to it will be lost.');">
							<?php wp_nonce_field( 'weavit_email_reset' ); ?>
							<input type="hidden" name="action" value="weavit_email_reset">
							<input type="hidden" name="template" value="<?php echo esc_attr( $id ); ?>">
							<strong>Start over</strong><br>
							<?php submit_button( 'Reset to default', 'secondary', 'submit', false ); ?>
						</form>
					</div>

					<p style="margin:18px 0 6px;"><strong>Preview</strong> <span class="description">(sample details; save first to see edits)</span></p>
					<p style="margin:0 0 6px;color:#646970;font-size:13px;">Subject: <?php echo esc_html( $preview['subject'] ); ?></p>
					<iframe title="<?php echo esc_attr( $def['label'] ); ?> preview" sandbox="" srcdoc="<?php echo esc_attr( $preview['html'] ); ?>" style="width:100%;height:440px;border:1px solid #dcdcde;background:#fff;"></iframe>
				</div>
			</details>
		<?php endforeach; ?>

		<h2 style="margin-top:30px;">Look &amp; feel (all emails)</h2>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="max-width:900px;">
			<?php wp_nonce_field( 'weavit_email_layout_save' ); ?>
			<input type="hidden" name="action" value="weavit_email_layout_save">
			<table class="form-table" role="presentation">
				<tr><th><label for="brand_color">Brand colour</label></th><td><input type="text" id="brand_color" name="brand_color" value="<?php echo esc_attr( $layout['brand_color'] ); ?>" class="small-text" placeholder="#643486"> <span class="description">Header text, links and the top edge of the email.</span></td></tr>
				<tr><th><label for="header_text">Header</label></th><td><input type="text" id="header_text" name="header_text" value="<?php echo esc_attr( $layout['header_text'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( weavit_email_site_name() ); ?>"> <span class="description">Leave blank to use your logo, or the site name if there's no logo.</span></td></tr>
				<tr><th><label for="footer_text">Footer</label></th><td><input type="text" id="footer_text" name="footer_text" value="<?php echo esc_attr( $layout['footer_text'] ); ?>" class="large-text"> <p class="description">You can use <code>{site_name}</code>, <code>{phone}</code>, <code>{email}</code> and <code>{site_url}</code>.</p></td></tr>
			</table>
			<?php submit_button( 'Save look & feel' ); ?>
		</form>
	</div>
	<?php
}
