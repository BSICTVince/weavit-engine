<?php
/**
 * Newsletter double opt-in. Someone who signs up through the newsletter form
 * is saved as "pending" and sent a confirmation email; they only start
 * receiving newsletters once they click the link in it. Same flow as the old
 * site's MailPoet confirmation email, built in:
 *
 *   signup form  ->  entry saved (pending) + confirmation email
 *   click link   ->  entry confirmed  ->  included in newsletter broadcasts
 *                                         (+ optional welcome email)
 *
 * Entries made before this existed have no status and count as confirmed.
 * The emails themselves (wording, on/off, tests) are edited on
 * Weavit > Email Templates (inc/email-templates.php).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WEAVIT_NEWSLETTER_OPTION', 'weavit_newsletter_settings' );

function weavit_newsletter_defaults() {
	return array(
		'require_confirmation' => 1,
		'after_signup'         => 'Almost done — we\'ve emailed you a link. Please click it to confirm your subscription.',
	);
}

function weavit_newsletter_settings() {
	return wp_parse_args( get_option( WEAVIT_NEWSLETTER_OPTION, array() ), weavit_newsletter_defaults() );
}

function weavit_newsletter_site_name() {
	return wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
}

/** The email address on a newsletter entry, or ''. */
function weavit_newsletter_entry_email( $entry_id ) {
	foreach ( (array) bootg_get_entry_data( $entry_id ) as $row ) {
		if ( isset( $row['value'] ) && is_email( $row['value'] ) ) {
			return $row['value'];
		}
	}
	return '';
}

/** One-click unsubscribe address for an entry (the token is created on first use). */
function weavit_newsletter_unsubscribe_url( $entry_id ) {
	$token = get_post_meta( $entry_id, '_weavit_unsubscribe_token', true );
	if ( ! $token ) {
		$token = wp_generate_password( 32, false );
		update_post_meta( $entry_id, '_weavit_unsubscribe_token', $token );
	}
	return add_query_arg( 'weavit_unsubscribe', $token, home_url( '/' ) );
}

/** Sends the confirmation email (Email Templates > Newsletter confirmation). Returns true if handed to the mailer. */
function weavit_newsletter_send_confirmation( $to, $token ) {
	return weavit_email_send( 'newsletter_confirm', $to, array(
		'confirm_url' => add_query_arg( array( 'weavit_subscription' => 'confirm', 'token' => $token ), home_url( '/' ) ),
	) );
}

/* ---------------------------------------------------------------------
 * 1. A new signup: mark pending and send the email
 * ------------------------------------------------------------------- */

add_action( 'bootg_form_entry_saved', function ( $entry_id, $form_id, $data ) {
	if ( empty( weavit_newsletter_settings()['require_confirmation'] ) ) {
		return;
	}
	if ( (int) $form_id !== (int) bootg_get_system_form_id( weavit_newsletter_form_slug() ) ) {
		return;
	}
	$email = '';
	foreach ( (array) $data as $row ) {
		if ( isset( $row['value'] ) && is_email( $row['value'] ) ) {
			$email = $row['value'];
			break;
		}
	}
	if ( ! $email ) {
		return;
	}

	$token = wp_generate_password( 32, false );
	update_post_meta( $entry_id, '_weavit_sub_status', 'pending' );
	update_post_meta( $entry_id, '_weavit_confirm_token', $token );
	update_post_meta( $entry_id, '_weavit_sub_requested', time() );

	// Don't let the form be used to mail-bomb someone: at most one confirmation email per address per 5 minutes.
	$throttle = 'weavit_nl_conf_' . md5( strtolower( $email ) );
	if ( get_transient( $throttle ) ) {
		return;
	}
	set_transient( $throttle, 1, 5 * MINUTE_IN_SECONDS );
	weavit_newsletter_send_confirmation( $email, $token );
}, 10, 3 );

// What the visitor sees after pressing Subscribe.
add_filter( 'bootg_form_success_message', function ( $message, $form_id ) {
	$s = weavit_newsletter_settings();
	if ( ! empty( $s['require_confirmation'] ) && (int) $form_id === (int) bootg_get_system_form_id( weavit_newsletter_form_slug() ) ) {
		return $s['after_signup'];
	}
	return $message;
}, 10, 2 );

/* ---------------------------------------------------------------------
 * 2. The link in the email
 * ------------------------------------------------------------------- */

add_action( 'template_redirect', function () {
	if ( 'confirm' !== ( $_GET['weavit_subscription'] ?? '' ) || empty( $_GET['token'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	$token = sanitize_text_field( wp_unslash( $_GET['token'] ) ); // phpcs:ignore WordPress.Security.NonceVerification

	$ids = get_posts( array(
		'post_type'      => 'bootg_form_entry',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'meta_key'       => '_weavit_confirm_token', // phpcs:ignore
		'meta_value'     => $token, // phpcs:ignore
		'fields'         => 'ids',
	) );

	$site  = esc_html( weavit_newsletter_site_name() );
	$title = 'Subscription';
	$msg   = 'This confirmation link isn\'t valid, or the subscription has been cancelled. Please subscribe again from our website.';

	if ( $ids ) {
		$id        = $ids[0];
		$status    = get_post_meta( $id, '_weavit_sub_status', true );
		$requested = (int) get_post_meta( $id, '_weavit_sub_requested', true );
		$expiry    = max( 1, (int) apply_filters( 'weavit_newsletter_confirm_expiry_days', 7 ) );

		if ( 'confirmed' === $status ) {
			$title = 'Already confirmed';
			$msg   = 'Your subscription to ' . $site . ' is already confirmed. Thank you!';
		} elseif ( $requested && ( time() - $requested ) > $expiry * DAY_IN_SECONDS ) {
			$title = 'Link expired';
			$msg   = 'This confirmation link has expired. Please subscribe again from our website and we\'ll send you a fresh one.';
		} else {
			update_post_meta( $id, '_weavit_sub_status', 'confirmed' );
			update_post_meta( $id, '_weavit_sub_confirmed', time() );
			$title = 'You\'re subscribed';
			$msg   = 'Thank you — your subscription to ' . $site . ' is confirmed. You\'ll now receive our latest updates.';

			$email = weavit_newsletter_entry_email( $id );
			if ( $email ) {
				weavit_email_send( 'newsletter_welcome', $email, array( 'unsubscribe_url' => weavit_newsletter_unsubscribe_url( $id ) ) ); // Off unless switched on in Email Templates.
			}
		}
	}

	nocache_headers();
	header( 'X-Robots-Tag: noindex' );
	wp_die(
		'<div style="max-width:480px;margin:80px auto;padding:40px;text-align:center;font-family:sans-serif;">'
			. '<h1 style="font-size:1.4rem;">' . $site . '</h1>'
			. '<h2 style="font-size:1.1rem;">' . esc_html( $title ) . '</h2>'
			. '<p>' . $msg . '</p>' // $msg holds only text built above plus an already-escaped site name.
			. '<p><a href="' . esc_url( home_url( '/' ) ) . '">Return to the site</a></p>'
			. '</div>',
		esc_html( $title ),
		array( 'response' => 200 )
	);
} );

/* ---------------------------------------------------------------------
 * 3. Weavit > Newsletter: on/off, message after Subscribe, subscriber list
 * ------------------------------------------------------------------- */

add_action( 'admin_menu', function () {
	add_submenu_page( 'weavit', 'Newsletter', 'Newsletter', 'manage_options', 'weavit-newsletter', 'weavit_newsletter_render_page' );
} );

function weavit_newsletter_back( $args ) {
	return add_query_arg( array_merge( array( 'page' => 'weavit-newsletter' ), $args ), admin_url( 'admin.php' ) );
}

add_action( 'admin_post_weavit_newsletter_save', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'weavit_newsletter_save' );
	$d = weavit_newsletter_defaults();
	update_option( WEAVIT_NEWSLETTER_OPTION, array_merge( (array) get_option( WEAVIT_NEWSLETTER_OPTION, array() ), array(
		'require_confirmation' => empty( $_POST['require_confirmation'] ) ? 0 : 1,
		'after_signup'         => sanitize_text_field( wp_unslash( $_POST['after_signup'] ?? '' ) ) ?: $d['after_signup'],
	) ) );
	wp_safe_redirect( weavit_newsletter_back( array( 'saved' => 1 ) ) );
	exit;
} );

add_action( 'admin_post_weavit_newsletter_resend', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'weavit_newsletter_resend' );
	$entry = absint( $_GET['entry'] ?? 0 );
	$token = get_post_meta( $entry, '_weavit_confirm_token', true );
	$email = weavit_newsletter_entry_email( $entry );
	$ok    = $token && $email && 'pending' === get_post_meta( $entry, '_weavit_sub_status', true );
	if ( $ok ) {
		update_post_meta( $entry, '_weavit_sub_requested', time() ); // Fresh 7 days.
		$ok = weavit_newsletter_send_confirmation( $email, $token );
	}
	wp_safe_redirect( weavit_newsletter_back( array( 'resend' => $ok ? 'sent' : 'failed' ) ) );
	exit;
} );

function weavit_newsletter_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s       = weavit_newsletter_settings();
	$form_id = bootg_get_system_form_id( weavit_newsletter_form_slug() );

	$rows      = array();
	$confirmed = 0;
	$pending   = 0;
	if ( $form_id ) {
		$query = bootg_get_form_entries( $form_id, array( 'posts_per_page' => 200 ) );
		foreach ( $query->posts as $entry ) {
			$status = get_post_meta( $entry->ID, '_weavit_sub_status', true );
			'pending' === $status ? ++$pending : ++$confirmed;
			$rows[] = array( $entry->ID, weavit_newsletter_entry_email( $entry->ID ), 'pending' === $status ? 'pending' : 'confirmed', $entry->post_date );
		}
	}
	?>
	<div class="wrap">
		<h1>Newsletter</h1>

		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
		<?php endif; ?>
		<?php if ( isset( $_GET['resend'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-<?php echo 'sent' === $_GET['resend'] ? 'success' : 'error'; // phpcs:ignore ?> is-dismissible"><p><?php echo 'sent' === $_GET['resend'] ? 'Confirmation email sent again.' : 'Couldn\'t resend that confirmation email.'; // phpcs:ignore ?></p></div>
		<?php endif; ?>

		<p style="max-width:780px;">When someone signs up with the newsletter form, they get a confirmation email and only join the list once they click the link in it. Pending sign-ups are never sent newsletters. The wording of the confirmation, welcome and new-post emails is edited under <a href="<?php echo esc_url( admin_url( 'admin.php?page=weavit-emails' ) ); ?>">Weavit &rarr; Email Templates</a>.</p>

		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<?php wp_nonce_field( 'weavit_newsletter_save' ); ?>
			<input type="hidden" name="action" value="weavit_newsletter_save">
			<table class="form-table" role="presentation">
				<tr><th>Email confirmation</th><td><label><input type="checkbox" name="require_confirmation" value="1" <?php checked( $s['require_confirmation'] ); ?>> Require new subscribers to confirm by email</label><p class="description">Untick to add people to the list immediately (not recommended — a confirmed list keeps your emails out of spam and meets Australian Spam Act consent rules).</p></td></tr>
				<tr><th><label for="after_signup">Message after Subscribe</label></th><td><input type="text" id="after_signup" name="after_signup" class="large-text" value="<?php echo esc_attr( $s['after_signup'] ); ?>"></td></tr>
			</table>
			<?php submit_button( 'Save Settings' ); ?>
		</form>

		<h2 style="margin-top:28px;">Subscribers <span style="font-weight:400;font-size:14px;">— <?php echo (int) $confirmed; ?> confirmed, <?php echo (int) $pending; ?> waiting for confirmation</span></h2>
		<?php if ( ! $rows ) : ?>
			<p>No sign-ups yet.</p>
		<?php else : ?>
			<table class="widefat striped" style="max-width:860px;">
				<thead><tr><th>Email</th><th style="width:170px;">Status</th><th style="width:170px;">Signed up</th><th style="width:130px;"></th></tr></thead>
				<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row[1] ); ?></td>
						<td><?php echo 'pending' === $row[2] ? '<span style="color:#b32d2e;">Waiting for confirmation</span>' : '<strong style="color:#00a32a;">Confirmed</strong>'; ?></td>
						<td><?php echo esc_html( $row[3] ); ?></td>
						<td><?php if ( 'pending' === $row[2] ) : ?><a class="button button-small" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'weavit_newsletter_resend', 'entry' => $row[0] ), admin_url( 'admin-post.php' ) ), 'weavit_newsletter_resend' ) ); ?>">Resend email</a><?php endif; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}
