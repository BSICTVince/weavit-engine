<?php
/**
 * Google reCAPTCHA (v2 checkbox or v3 invisible, switchable) for the Forms
 * engine. Verification happens in the one shared submission handler
 * (inc/forms/forms-submit.php), so every form built on this engine is
 * covered automatically — nothing per-form to wire up.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BOOTG_RECAPTCHA_OPTION', 'bootg_recaptcha_settings' );

function bootg_recaptcha_defaults() {
	return array(
		'enabled'       => false,
		'version'       => 'v3',
		'v2_site_key'   => '',
		'v2_secret_key' => '',
		'v3_site_key'   => '',
		'v3_secret_key' => '',
		'v3_threshold'  => 0.5,
	);
}

function bootg_get_recaptcha_settings() {
	return wp_parse_args( get_option( BOOTG_RECAPTCHA_OPTION, array() ), bootg_recaptcha_defaults() );
}

/** True only when enabled AND the active version's key pair is actually filled in. */
function bootg_recaptcha_is_active() {
	$s = bootg_get_recaptcha_settings();
	if ( empty( $s['enabled'] ) ) {
		return false;
	}
	if ( 'v2' === $s['version'] ) {
		return $s['v2_site_key'] && $s['v2_secret_key'];
	}
	return $s['v3_site_key'] && $s['v3_secret_key'];
}

add_action( 'admin_menu', function () {
	add_submenu_page( 'bootg-forms', 'reCAPTCHA', 'reCAPTCHA', 'manage_options', 'bootg-recaptcha-settings', 'bootg_render_recaptcha_settings_page' );
} );

add_action( 'admin_init', function () {
	register_setting( 'bootg_recaptcha_group', BOOTG_RECAPTCHA_OPTION, 'bootg_sanitize_recaptcha_settings' );
} );

function bootg_sanitize_recaptcha_settings( $input ) {
	$threshold = isset( $input['v3_threshold'] ) ? (float) $input['v3_threshold'] : 0.5;
	return array(
		'enabled'       => ! empty( $input['enabled'] ),
		'version'       => 'v2' === ( $input['version'] ?? 'v3' ) ? 'v2' : 'v3',
		'v2_site_key'   => sanitize_text_field( $input['v2_site_key'] ?? '' ),
		'v2_secret_key' => sanitize_text_field( $input['v2_secret_key'] ?? '' ),
		'v3_site_key'   => sanitize_text_field( $input['v3_site_key'] ?? '' ),
		'v3_secret_key' => sanitize_text_field( $input['v3_secret_key'] ?? '' ),
		'v3_threshold'  => min( 1, max( 0, $threshold ) ),
	);
}

function bootg_render_recaptcha_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s = bootg_get_recaptcha_settings();
	?>
	<div class="wrap">
		<h1>reCAPTCHA</h1>
		<p class="description">Spam protection for every form on the Forms engine (Contact, signups, anything built with the builder) — one setting covers all of them. Get keys from <a href="https://www.google.com/recaptcha/admin/create" target="_blank" rel="noopener">google.com/recaptcha/admin</a>. Both key pairs are saved at once so you can switch versions without re-entering anything.</p>

		<form action="options.php" method="post">
			<?php settings_fields( 'bootg_recaptcha_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="recaptcha_enabled">Enable reCAPTCHA</label></th>
					<td><label><input type="checkbox" id="recaptcha_enabled" name="<?php echo esc_attr( BOOTG_RECAPTCHA_OPTION ); ?>[enabled]" value="1" <?php checked( $s['enabled'] ); ?>> Protect all Forms-engine forms</label></td>
				</tr>
				<tr>
					<th><label for="recaptcha_version">Version</label></th>
					<td>
						<select id="recaptcha_version" name="<?php echo esc_attr( BOOTG_RECAPTCHA_OPTION ); ?>[version]">
							<option value="v3" <?php selected( $s['version'], 'v3' ); ?>>v3 — Invisible (score-based, no widget)</option>
							<option value="v2" <?php selected( $s['version'], 'v2' ); ?>>v2 — "I'm not a robot" checkbox</option>
						</select>
					</td>
				</tr>
			</table>

			<h2>v2 keys</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="v2_site_key">Site Key</label></th>
					<td><input type="text" id="v2_site_key" name="<?php echo esc_attr( BOOTG_RECAPTCHA_OPTION ); ?>[v2_site_key]" value="<?php echo esc_attr( $s['v2_site_key'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="v2_secret_key">Secret Key</label></th>
					<td><input type="text" id="v2_secret_key" name="<?php echo esc_attr( BOOTG_RECAPTCHA_OPTION ); ?>[v2_secret_key]" value="<?php echo esc_attr( $s['v2_secret_key'] ); ?>" class="regular-text"></td>
				</tr>
			</table>

			<h2>v3 keys</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="v3_site_key">Site Key</label></th>
					<td><input type="text" id="v3_site_key" name="<?php echo esc_attr( BOOTG_RECAPTCHA_OPTION ); ?>[v3_site_key]" value="<?php echo esc_attr( $s['v3_site_key'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="v3_secret_key">Secret Key</label></th>
					<td><input type="text" id="v3_secret_key" name="<?php echo esc_attr( BOOTG_RECAPTCHA_OPTION ); ?>[v3_secret_key]" value="<?php echo esc_attr( $s['v3_secret_key'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="v3_threshold">Score threshold</label></th>
					<td>
						<input type="number" id="v3_threshold" name="<?php echo esc_attr( BOOTG_RECAPTCHA_OPTION ); ?>[v3_threshold]" value="<?php echo esc_attr( $s['v3_threshold'] ); ?>" min="0" max="1" step="0.1" class="small-text">
						<p class="description">0.0 (bot-like) to 1.0 (human-like). Submissions scoring below this are blocked. Google recommends starting at 0.5.</p>
					</td>
				</tr>
			</table>

			<?php submit_button( 'Save reCAPTCHA Settings' ); ?>
		</form>

		<?php if ( $s['enabled'] && ! bootg_recaptcha_is_active() ) : ?>
			<div class="notice notice-warning inline"><p>Enabled, but the <?php echo esc_html( strtoupper( $s['version'] ) ); ?> key pair above is incomplete — forms are currently running <strong>without</strong> reCAPTCHA protection until both keys are filled in.</p></div>
		<?php endif; ?>
	</div>
	<?php
}

/** Renders the widget markup to embed inside a <form> — empty string when inactive. */
function bootg_render_recaptcha_field() {
	if ( ! bootg_recaptcha_is_active() ) {
		return '';
	}
	$s = bootg_get_recaptcha_settings();
	if ( 'v2' === $s['version'] ) {
		return '<div class="bootg-recaptcha-v2 g-recaptcha" data-sitekey="' . esc_attr( $s['v2_site_key'] ) . '" style="margin-bottom:1.25rem"></div>';
	}
	return '<input type="hidden" class="bootg-recaptcha-v3-token" name="bootg_recaptcha_token" value="">';
}

add_action( 'wp_enqueue_scripts', function () {
	if ( ! bootg_recaptcha_is_active() ) {
		return;
	}
	$s = bootg_get_recaptcha_settings();
	if ( 'v2' === $s['version'] ) {
		wp_enqueue_script( 'bootg-recaptcha-api', 'https://www.google.com/recaptcha/api.js', array(), null, true );
		return;
	}
	wp_enqueue_script( 'bootg-recaptcha-api', 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $s['v3_site_key'] ), array(), null, true );
	wp_enqueue_script( 'bootg-recaptcha-v3', WEAVIT_ENGINE_URI . '/assets/js/recaptcha-v3.js', array( 'bootg-recaptcha-api' ), WEAVIT_ENGINE_VERSION, true );
	wp_localize_script( 'bootg-recaptcha-v3', 'bootgRecaptchaV3', array( 'siteKey' => $s['v3_site_key'] ) );
} );

/**
 * Verifies a submitted token against Google's siteverify endpoint. Fails
 * OPEN (allows the submission through) on a network/HTTP error so a
 * transient Google outage never locks out real customers — fails CLOSED
 * (blocks it) only on an explicit failure or low score from Google itself.
 */
function bootg_verify_recaptcha( $token ) {
	if ( ! bootg_recaptcha_is_active() ) {
		return true;
	}
	$s = bootg_get_recaptcha_settings();
	if ( '' === trim( (string) $token ) ) {
		return false;
	}

	$secret = 'v2' === $s['version'] ? $s['v2_secret_key'] : $s['v3_secret_key'];

	$response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array(
		'timeout' => 8,
		'body'    => array(
			'secret'   => $secret,
			'response' => $token,
			'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
		),
	) );

	if ( is_wp_error( $response ) ) {
		return true; // Fail open — Google unreachable shouldn't block real submissions.
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( empty( $body['success'] ) ) {
		return false;
	}
	if ( 'v3' === $s['version'] && isset( $body['score'] ) && $body['score'] < $s['v3_threshold'] ) {
		return false;
	}
	return true;
}

/** Reads the right POST field for whichever version is active. */
function bootg_get_submitted_recaptcha_token() {
	$s = bootg_get_recaptcha_settings();
	if ( 'v2' === $s['version'] ) {
		return isset( $_POST['g-recaptcha-response'] ) ? sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ) ) : '';
	}
	return isset( $_POST['bootg_recaptcha_token'] ) ? sanitize_text_field( wp_unslash( $_POST['bootg_recaptcha_token'] ) ) : '';
}
