<?php
/**
 * Dynamic SMTP settings — Gmail / Outlook / any custom host (GoDaddy,
 * Hostinger, Pressable, etc.), configured from wp-admin and applied via
 * PHPMailer's phpmailer_init hook. No WP Mail SMTP or any other plugin —
 * core Settings API + core's own mailer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BOOTG_SMTP_OPTION', 'bootg_smtp_settings' );

function bootg_smtp_provider_presets() {
	return array(
		'gmail'   => array( 'label' => 'Gmail / Google Workspace', 'host' => 'smtp.gmail.com', 'port' => 587, 'encryption' => 'tls' ),
		'outlook' => array( 'label' => 'Outlook / Microsoft 365', 'host' => 'smtp.office365.com', 'port' => 587, 'encryption' => 'tls' ),
		'custom'  => array( 'label' => 'Custom / My hosting provider (GoDaddy, Hostinger, etc.)', 'host' => '', 'port' => 587, 'encryption' => 'tls' ),
	);
}

function bootg_smtp_defaults() {
	return array(
		'enabled'    => 0,
		'provider'   => 'custom',
		'host'       => '',
		'port'       => 587,
		'encryption' => 'tls',
		'username'   => '',
		'password'   => '',
		'from_email' => get_option( 'admin_email' ),
		'from_name'  => get_bloginfo( 'name' ),
	);
}

function bootg_get_smtp_settings() {
	return wp_parse_args( get_option( BOOTG_SMTP_OPTION, array() ), bootg_smtp_defaults() );
}

add_action( 'admin_menu', function () {
	add_menu_page(
		'SMTP Settings',
		'SMTP',
		'manage_options',
		'bootg-smtp-settings',
		'bootg_render_smtp_settings_page',
		'dashicons-email',
		58 // Just under core's Appearance (60) — lands directly below the CPT menus (Services...Guides).
	);
} );

add_action( 'admin_init', function () {
	register_setting( 'bootg_smtp_settings_group', BOOTG_SMTP_OPTION, 'bootg_sanitize_smtp_settings' );
} );

function bootg_sanitize_smtp_settings( $input ) {
	$existing = bootg_get_smtp_settings();
	$password = trim( (string) ( $input['password'] ?? '' ) );

	return array(
		'enabled'    => empty( $input['enabled'] ) ? 0 : 1,
		'provider'   => in_array( $input['provider'] ?? '', array( 'gmail', 'outlook', 'custom' ), true ) ? $input['provider'] : 'custom',
		'host'       => sanitize_text_field( $input['host'] ?? '' ),
		'port'       => absint( $input['port'] ?? 587 ) ?: 587,
		'encryption' => in_array( $input['encryption'] ?? '', array( 'tls', 'ssl', '' ), true ) ? $input['encryption'] : 'tls',
		'username'   => sanitize_text_field( $input['username'] ?? '' ),
		// Blank password field on save = "keep the existing one" (so it's never
		// re-displayed in the form, but you're not forced to re-enter it every save).
		'password'   => '' !== $password ? $password : $existing['password'],
		'from_email' => sanitize_email( $input['from_email'] ?? '' ) ?: get_option( 'admin_email' ),
		'from_name'  => sanitize_text_field( $input['from_name'] ?? '' ) ?: get_bloginfo( 'name' ),
	);
}

/** Wire PHPMailer to the saved settings. */
add_action( 'phpmailer_init', function ( $phpmailer ) {
	$s = bootg_get_smtp_settings();
	if ( ! $s['enabled'] || ! $s['host'] || ! $s['username'] || ! $s['password'] ) {
		return;
	}

	$phpmailer->isSMTP();
	$phpmailer->Host       = $s['host'];
	$phpmailer->Port       = $s['port'];
	$phpmailer->SMTPAuth   = true;
	$phpmailer->Username   = $s['username'];
	$phpmailer->Password   = $s['password'];
	$phpmailer->SMTPSecure = $s['encryption'] ?: false;
	if ( ! $s['encryption'] ) {
		$phpmailer->SMTPAutoTLS = false;
	}
	$phpmailer->setFrom( $s['from_email'], $s['from_name'], false );
} );

add_filter( 'wp_mail_from', function ( $email ) {
	$s = bootg_get_smtp_settings();
	return ( $s['enabled'] && $s['from_email'] ) ? $s['from_email'] : $email;
} );

add_filter( 'wp_mail_from_name', function ( $name ) {
	$s = bootg_get_smtp_settings();
	return ( $s['enabled'] && $s['from_name'] ) ? $s['from_name'] : $name;
} );

/** Send Test Email */
add_action( 'admin_post_bootg_smtp_test', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'bootg_smtp_test' );

	$to  = wp_get_current_user()->user_email;
	$err = null;
	add_action( 'wp_mail_failed', function ( $wp_error ) use ( &$err ) {
		$err = $wp_error->get_error_message();
	} );

	$sent = wp_mail( $to, 'Test email from ' . get_bloginfo( 'name' ), "This is a test email confirming your SMTP settings work.\n\nSent " . current_time( 'mysql' ) . '.' );

	$redirect = add_query_arg(
		$sent
			? array( 'page' => 'bootg-smtp-settings', 'bootg_smtp_test' => 'success' )
			: array( 'page' => 'bootg-smtp-settings', 'bootg_smtp_test' => 'error', 'bootg_smtp_test_msg' => rawurlencode( $err ?: 'Unknown error' ) ),
		admin_url( 'admin.php' )
	);
	wp_safe_redirect( $redirect );
	exit;
} );

function bootg_render_smtp_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s        = bootg_get_smtp_settings();
	$presets  = bootg_smtp_provider_presets();
	?>
	<div class="wrap">
		<h1>SMTP Settings</h1>
		<p class="description">Configure real outgoing mail (Gmail, Outlook, or any hosting provider's SMTP) so <code>wp_mail()</code> — used by the Contact form and WordPress notifications — actually delivers. No plugin: this writes directly into PHPMailer via <code>phpmailer_init</code>.</p>

		<?php if ( isset( $_GET['bootg_smtp_test'] ) ) : ?>
			<?php if ( 'success' === $_GET['bootg_smtp_test'] ) : ?>
				<div class="notice notice-success is-dismissible"><p>Test email sent to <?php echo esc_html( wp_get_current_user()->user_email ); ?> — check your inbox (and spam folder).</p></div>
			<?php else : ?>
				<div class="notice notice-error is-dismissible"><p>Test email failed: <?php echo esc_html( wp_unslash( $_GET['bootg_smtp_test_msg'] ?? 'Unknown error' ) ); ?></p></div>
			<?php endif; ?>
		<?php endif; ?>

		<form action="options.php" method="post">
			<?php settings_fields( 'bootg_smtp_settings_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="smtp_enabled">Enable custom SMTP</label></th>
					<td>
						<label><input type="checkbox" id="smtp_enabled" name="<?php echo esc_attr( BOOTG_SMTP_OPTION ); ?>[enabled]" value="1" <?php checked( $s['enabled'] ); ?>> Use the settings below instead of PHP's default <code>mail()</code></label>
					</td>
				</tr>
				<tr>
					<th><label for="smtp_provider">Provider</label></th>
					<td>
						<select id="smtp_provider" name="<?php echo esc_attr( BOOTG_SMTP_OPTION ); ?>[provider]">
							<?php foreach ( $presets as $key => $preset ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['provider'], $key ); ?>><?php echo esc_html( $preset['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description">Gmail/Outlook auto-fill the host, port, and encryption below — you can still edit them (e.g. a Google Workspace alias).</p>
					</td>
				</tr>
				<tr>
					<th><label for="smtp_host">SMTP Host</label></th>
					<td><input type="text" id="smtp_host" name="<?php echo esc_attr( BOOTG_SMTP_OPTION ); ?>[host]" value="<?php echo esc_attr( $s['host'] ); ?>" class="regular-text" placeholder="mail.yourdomain.com"></td>
				</tr>
				<tr>
					<th><label for="smtp_port">Port</label></th>
					<td><input type="number" id="smtp_port" name="<?php echo esc_attr( BOOTG_SMTP_OPTION ); ?>[port]" value="<?php echo esc_attr( $s['port'] ); ?>" class="small-text"></td>
				</tr>
				<tr>
					<th><label for="smtp_encryption">Encryption</label></th>
					<td>
						<select id="smtp_encryption" name="<?php echo esc_attr( BOOTG_SMTP_OPTION ); ?>[encryption]">
							<option value="tls" <?php selected( $s['encryption'], 'tls' ); ?>>TLS (recommended, port 587)</option>
							<option value="ssl" <?php selected( $s['encryption'], 'ssl' ); ?>>SSL (port 465)</option>
							<option value="" <?php selected( $s['encryption'], '' ); ?>>None</option>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="smtp_username">Username</label></th>
					<td><input type="text" id="smtp_username" name="<?php echo esc_attr( BOOTG_SMTP_OPTION ); ?>[username]" value="<?php echo esc_attr( $s['username'] ); ?>" class="regular-text" placeholder="you@yourdomain.com" autocomplete="off"></td>
				</tr>
				<tr>
					<th><label for="smtp_password">Password</label></th>
					<td>
						<input type="password" id="smtp_password" name="<?php echo esc_attr( BOOTG_SMTP_OPTION ); ?>[password]" value="" class="regular-text" placeholder="<?php echo $s['password'] ? esc_attr( '•••••••• (saved — leave blank to keep it)' ) : ''; ?>" autocomplete="new-password">
						<p class="description">For Gmail/Outlook, this must be an <strong>App Password</strong>, not your normal login password (requires 2FA enabled on the account).</p>
					</td>
				</tr>
				<tr>
					<th><label for="smtp_from_email">From Email</label></th>
					<td><input type="email" id="smtp_from_email" name="<?php echo esc_attr( BOOTG_SMTP_OPTION ); ?>[from_email]" value="<?php echo esc_attr( $s['from_email'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="smtp_from_name">From Name</label></th>
					<td><input type="text" id="smtp_from_name" name="<?php echo esc_attr( BOOTG_SMTP_OPTION ); ?>[from_name]" value="<?php echo esc_attr( $s['from_name'] ); ?>" class="regular-text"></td>
				</tr>
			</table>
			<?php submit_button( 'Save SMTP Settings' ); ?>
		</form>

		<?php if ( $s['enabled'] && $s['host'] && $s['username'] ) : ?>
			<hr>
			<h2>Send Test Email</h2>
			<p class="description">Sends a test message to your account email (<?php echo esc_html( wp_get_current_user()->user_email ); ?>) using the saved settings above.</p>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<?php wp_nonce_field( 'bootg_smtp_test' ); ?>
				<input type="hidden" name="action" value="bootg_smtp_test">
				<?php submit_button( 'Send Test Email', 'secondary', 'submit', false ); ?>
			</form>
		<?php endif; ?>
	</div>

	<script>
	(function () {
		var presets = <?php echo wp_json_encode( $presets ); ?>;
		var providerEl = document.getElementById('smtp_provider');
		var hostEl = document.getElementById('smtp_host');
		var portEl = document.getElementById('smtp_port');
		var encEl = document.getElementById('smtp_encryption');
		providerEl.addEventListener('change', function () {
			var preset = presets[providerEl.value];
			if (!preset) { return; }
			if (preset.host) { hostEl.value = preset.host; }
			portEl.value = preset.port;
			encEl.value = preset.encryption;
		});
	})();
	</script>
	<?php
}
