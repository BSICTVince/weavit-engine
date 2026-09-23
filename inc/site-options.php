<?php
/**
 * Site-wide options (phone, email, socials, footer text) editable from
 * wp-admin via the core Settings API — no ACF, no builder plugin. Reusable
 * across any Weavit-built theme; a theme calls bootg_get_option( $key ) to
 * read whatever it needs (header phone number, footer socials, etc.).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BOOTG_OPTION', 'bootg_theme_options' );

/**
 * Defaults double as this build's actual values until the Site Options
 * form is explicitly saved (at which point wp_options takes over). A
 * future starter-content JSON pass should seed these via the importer
 * instead of hardcoding them here — tracked as follow-up work, not done
 * as part of this plugin/theme split so as not to risk blanking live
 * values that were never explicitly re-saved.
 */
function bootg_default_options() {
	return array(
		'phone'              => '08 6249 0115',
		'phone_link'         => '0862490115',
		'email'              => 'info@bookkeepingonthego.net.au',
		'address'            => '',
		'facebook_url'       => 'https://www.facebook.com/bookkeeperperthwa/',
		'twitter_url'        => 'https://x.com/go_bookkeeping',
		'instagram_url'      => 'https://www.instagram.com/bookkeeping_on_the_go_perth',
		'linkedin_url'       => 'https://www.linkedin.com/in/natalie-adams-0159b582',
		'footer_tagline'     => 'Professional, experienced bookkeeping, payroll, and BAS services based in Perth and operating virtually across Australia.',
		'footer_copyright'   => '&copy; ' . gmdate( 'Y' ) . ' Bookkeeping On The Go. All rights reserved.',
		'privacy_url'        => '',
		'terms_url'          => '',
	);
}

function bootg_get_option( $key ) {
	$options = wp_parse_args( get_option( BOOTG_OPTION, array() ), bootg_default_options() );
	return isset( $options[ $key ] ) ? $options[ $key ] : '';
}

add_action( 'admin_menu', function () {
	add_theme_page(
		'Site Options',
		'Site Options',
		'manage_options',
		'bootg-site-options',
		'bootg_render_options_page'
	);
} );

add_action( 'admin_init', function () {
	register_setting( 'bootg_options_group', BOOTG_OPTION, 'bootg_sanitize_options' );
} );

/**
 * One-click logo import from an external URL — handy during migration to
 * pull an existing brand logo into the Media Library and set it as the
 * Site Logo, without needing shell/WP-CLI access.
 */
add_action( 'admin_post_bootg_import_logo', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'bootg_import_logo' );

	if ( ! function_exists( 'media_sideload_image' ) ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	$url      = isset( $_POST['logo_url'] ) ? esc_url_raw( wp_unslash( $_POST['logo_url'] ) ) : '';
	$redirect = add_query_arg( 'page', 'bootg-site-options', admin_url( 'themes.php' ) );

	if ( ! $url ) {
		wp_safe_redirect( add_query_arg( 'bootg_logo_error', rawurlencode( 'No URL provided.' ), $redirect ) );
		exit;
	}

	$tmp = download_url( $url );
	if ( is_wp_error( $tmp ) ) {
		wp_safe_redirect( add_query_arg( 'bootg_logo_error', rawurlencode( $tmp->get_error_message() ), $redirect ) );
		exit;
	}

	$file_array = array(
		'name'     => basename( wp_parse_url( $url, PHP_URL_PATH ) ),
		'tmp_name' => $tmp,
	);

	$attachment_id = media_handle_sideload( $file_array, 0, 'Site logo' );

	if ( is_wp_error( $attachment_id ) ) {
		@unlink( $tmp ); // phpcs:ignore
		wp_safe_redirect( add_query_arg( 'bootg_logo_error', rawurlencode( $attachment_id->get_error_message() ), $redirect ) );
		exit;
	}

	set_theme_mod( 'custom_logo', $attachment_id );

	wp_safe_redirect( add_query_arg( 'bootg_logo_success', '1', $redirect ) );
	exit;
} );

function bootg_sanitize_options( $input ) {
	$output = array();
	$output['phone']             = sanitize_text_field( $input['phone'] ?? '' );
	$output['phone_link']        = preg_replace( '/[^0-9+]/', '', $input['phone_link'] ?? '' );
	$output['email']             = sanitize_email( $input['email'] ?? '' );
	$output['address']           = sanitize_text_field( $input['address'] ?? '' );
	$output['facebook_url']      = esc_url_raw( $input['facebook_url'] ?? '' );
	$output['twitter_url']       = esc_url_raw( $input['twitter_url'] ?? '' );
	$output['instagram_url']     = esc_url_raw( $input['instagram_url'] ?? '' );
	$output['linkedin_url']      = esc_url_raw( $input['linkedin_url'] ?? '' );
	$output['footer_tagline']    = sanitize_textarea_field( $input['footer_tagline'] ?? '' );
	$output['footer_copyright']  = wp_kses_post( $input['footer_copyright'] ?? '' );
	$output['privacy_url']       = esc_url_raw( $input['privacy_url'] ?? '' );
	$output['terms_url']         = esc_url_raw( $input['terms_url'] ?? '' );
	return $output;
}

function bootg_render_options_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$o = wp_parse_args( get_option( BOOTG_OPTION, array() ), bootg_default_options() );
	?>
	<div class="wrap">
		<h1>Site Options</h1>

		<?php if ( isset( $_GET['bootg_logo_success'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Logo imported and set as the Site Logo.</p></div>
		<?php elseif ( isset( $_GET['bootg_logo_error'] ) ) : ?>
			<div class="notice notice-error is-dismissible"><p>Logo import failed: <?php echo esc_html( wp_unslash( $_GET['bootg_logo_error'] ) ); ?></p></div>
		<?php endif; ?>

		<h2>Site Logo</h2>
		<?php if ( has_custom_logo() ) : ?>
			<p><?php the_custom_logo(); ?></p>
		<?php else : ?>
			<p><em>No logo set yet.</em></p>
		<?php endif; ?>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="margin-bottom:2em;">
			<?php wp_nonce_field( 'bootg_import_logo' ); ?>
			<input type="hidden" name="action" value="bootg_import_logo">
			<label for="logo_url">Import logo from URL:</label>
			<input type="url" id="logo_url" name="logo_url" class="regular-text">
			<?php submit_button( 'Import & Set as Logo', 'secondary', 'submit', false ); ?>
			<p class="description">Or use <a href="<?php echo esc_url( admin_url( 'customize.php?autofocus[section]=title_tagline' ) ); ?>">Appearance &rarr; Customize &rarr; Site Identity</a> to upload a logo file directly.</p>
		</form>

		<?php
		/**
		 * A theme (or its starter-content tooling) can add its own admin
		 * notices / one-click content sections here without touching this
		 * file — e.g. the Bookkeeping On The Go theme's "Content Tools"
		 * page links back to this screen instead of duplicating it.
		 */
		do_action( 'weavit_site_options_page' );
		?>

		<form action="options.php" method="post">
			<?php settings_fields( 'bootg_options_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="phone">Phone (display)</label></th>
					<td><input type="text" id="phone" name="<?php echo esc_attr( BOOTG_OPTION ); ?>[phone]" value="<?php echo esc_attr( $o['phone'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="phone_link">Phone (tel: link, digits only)</label></th>
					<td><input type="text" id="phone_link" name="<?php echo esc_attr( BOOTG_OPTION ); ?>[phone_link]" value="<?php echo esc_attr( $o['phone_link'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="email">Email</label></th>
					<td><input type="email" id="email" name="<?php echo esc_attr( BOOTG_OPTION ); ?>[email]" value="<?php echo esc_attr( $o['email'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="address">Address</label></th>
					<td><input type="text" id="address" name="<?php echo esc_attr( BOOTG_OPTION ); ?>[address]" value="<?php echo esc_attr( $o['address'] ); ?>" class="regular-text"></td>
				</tr>
				<tr><th colspan="2"><h2>Social Links</h2></th></tr>
				<tr>
					<th><label for="facebook_url">Facebook URL</label></th>
					<td><input type="url" id="facebook_url" name="<?php echo esc_attr( BOOTG_OPTION ); ?>[facebook_url]" value="<?php echo esc_attr( $o['facebook_url'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="twitter_url">X / Twitter URL</label></th>
					<td><input type="url" id="twitter_url" name="<?php echo esc_attr( BOOTG_OPTION ); ?>[twitter_url]" value="<?php echo esc_attr( $o['twitter_url'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="instagram_url">Instagram URL</label></th>
					<td><input type="url" id="instagram_url" name="<?php echo esc_attr( BOOTG_OPTION ); ?>[instagram_url]" value="<?php echo esc_attr( $o['instagram_url'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="linkedin_url">LinkedIn URL</label></th>
					<td><input type="url" id="linkedin_url" name="<?php echo esc_attr( BOOTG_OPTION ); ?>[linkedin_url]" value="<?php echo esc_attr( $o['linkedin_url'] ); ?>" class="regular-text"></td>
				</tr>
				<tr><th colspan="2"><h2>Footer</h2></th></tr>
				<tr>
					<th><label for="footer_tagline">Footer tagline</label></th>
					<td><textarea id="footer_tagline" name="<?php echo esc_attr( BOOTG_OPTION ); ?>[footer_tagline]" rows="3" class="large-text"><?php echo esc_textarea( $o['footer_tagline'] ); ?></textarea></td>
				</tr>
				<tr>
					<th><label for="footer_copyright">Copyright line</label></th>
					<td><input type="text" id="footer_copyright" name="<?php echo esc_attr( BOOTG_OPTION ); ?>[footer_copyright]" value="<?php echo esc_attr( $o['footer_copyright'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="privacy_url">Privacy policy URL</label></th>
					<td><input type="url" id="privacy_url" name="<?php echo esc_attr( BOOTG_OPTION ); ?>[privacy_url]" value="<?php echo esc_attr( $o['privacy_url'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="terms_url">Terms URL</label></th>
					<td><input type="url" id="terms_url" name="<?php echo esc_attr( BOOTG_OPTION ); ?>[terms_url]" value="<?php echo esc_attr( $o['terms_url'] ); ?>" class="regular-text"></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
