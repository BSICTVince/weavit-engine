<?php
/**
 * A small .htaccess editor for wp-admin — handy for a host that doesn't
 * give easy file-manager/FTP access, or just to avoid leaving wp-admin.
 * Edits the real file at the site root. A malformed .htaccess can take the
 * ENTIRE site down (500 error) until fixed, so every save keeps a one-deep
 * rolling backup and the file is re-read from disk on every page load
 * (never cached), so what you see is always what's actually live. Lives as
 * a tab on the SEO menu (inc/seo-tools.php) alongside Redirects.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bootg_htaccess_path() {
	return ABSPATH . '.htaccess';
}

function bootg_htaccess_backup_path() {
	return ABSPATH . '.htaccess.bootg-backup';
}

function bootg_default_htaccess() {
	return "# BEGIN WordPress\n"
		. "# The directives (lines) between \"BEGIN WordPress\" and \"END WordPress\" are\n"
		. "# dynamically generated, and should only be modified via WordPress filters.\n"
		. "# Any changes to the directives between these markers will be overwritten.\n"
		. "<IfModule mod_rewrite.c>\n"
		. "RewriteEngine On\n"
		. "RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]\n"
		. "RewriteBase /\n"
		. "RewriteRule ^index\\.php$ - [L]\n"
		. "RewriteCond %{REQUEST_FILENAME} !-f\n"
		. "RewriteCond %{REQUEST_FILENAME} !-d\n"
		. "RewriteRule . /index.php [L]\n"
		. "</IfModule>\n\n"
		. "# END WordPress\n";
}

add_action( 'admin_post_bootg_save_htaccess', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'bootg_save_htaccess' );

	$path    = bootg_htaccess_path();
	$content = isset( $_POST['htaccess_content'] ) ? wp_unslash( $_POST['htaccess_content'] ) : '';
	// Deliberately not sanitized/escaped — this is server config text (Apache
	// directives), not HTML; the whole point is to write it verbatim. Access
	// is capability-gated the same as any other admin-only settings screen.

	$dir_writable  = is_writable( ABSPATH );
	$file_writable = ! file_exists( $path ) || is_writable( $path );

	if ( ! $dir_writable || ! $file_writable ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'bootg-seo', 'tab' => 'htaccess', 'bootg_htaccess' => 'unwritable' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	if ( file_exists( $path ) ) {
		copy( $path, bootg_htaccess_backup_path() );
	}

	$written = file_put_contents( $path, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

	wp_safe_redirect( add_query_arg(
		array( 'page' => 'bootg-seo', 'tab' => 'htaccess', 'bootg_htaccess' => false !== $written ? 'saved' : 'error' ),
		admin_url( 'admin.php' )
	) );
	exit;
} );

add_action( 'admin_post_bootg_restore_htaccess_backup', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'bootg_restore_htaccess_backup' );

	$backup = bootg_htaccess_backup_path();
	$status = 'error';
	if ( file_exists( $backup ) && is_writable( ABSPATH ) ) {
		if ( copy( $backup, bootg_htaccess_path() ) ) {
			$status = 'restored';
		}
	}

	wp_safe_redirect( add_query_arg( array( 'page' => 'bootg-seo', 'tab' => 'htaccess', 'bootg_htaccess' => $status ), admin_url( 'admin.php' ) ) );
	exit;
} );

function bootg_render_htaccess_tab() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$path       = bootg_htaccess_path();
	$exists     = file_exists( $path );
	$content    = $exists ? file_get_contents( $path ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_get_contents
	$has_backup = file_exists( bootg_htaccess_backup_path() );
	?>
	<p class="description">Edits the real <code><?php echo esc_html( str_replace( ABSPATH, '/', $path ) ); ?></code> file at the site root. <strong>A mistake here can take the entire site down</strong> (a 500 error, with no wp-admin access to fix it from) until corrected via FTP or your host's file manager — save carefully, and check the site after saving. Every save keeps one backup you can restore below.</p>

	<?php if ( isset( $_GET['bootg_htaccess'] ) ) : ?>
		<?php if ( 'saved' === $_GET['bootg_htaccess'] ) : ?>
			<div class="notice notice-success is-dismissible"><p>Saved. <a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank">Open the site in a new tab</a> to confirm it still loads.</p></div>
		<?php elseif ( 'restored' === $_GET['bootg_htaccess'] ) : ?>
			<div class="notice notice-success is-dismissible"><p>Restored from the last backup.</p></div>
		<?php elseif ( 'unwritable' === $_GET['bootg_htaccess'] ) : ?>
			<div class="notice notice-error"><p>The site root isn't writable by PHP, so nothing was saved. Edit <code>.htaccess</code> via FTP or your host's file manager instead.</p></div>
		<?php else : ?>
			<div class="notice notice-error"><p>Something went wrong writing the file — nothing was changed on disk.</p></div>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( ! $exists ) : ?>
		<div class="notice notice-warning"><p>No <code>.htaccess</code> file exists yet at the site root. Without one, only the homepage will load correctly on most Apache hosts — every other URL will 404. The box below is pre-filled with WordPress's standard rewrite rules; save to create it.</p></div>
	<?php endif; ?>

	<?php if ( ! is_writable( ABSPATH ) || ( $exists && ! is_writable( $path ) ) ) : ?>
		<div class="notice notice-warning"><p>PHP doesn't currently have write access here — saving will fail until file permissions allow it (ask your host, or edit via FTP/file manager instead).</p></div>
	<?php endif; ?>

	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<?php wp_nonce_field( 'bootg_save_htaccess' ); ?>
		<input type="hidden" name="action" value="bootg_save_htaccess">
		<textarea name="htaccess_content" rows="22" class="large-text code" spellcheck="false" style="font-family:Consolas,Monaco,monospace;font-size:13px;white-space:pre;"><?php echo esc_textarea( $exists ? $content : bootg_default_htaccess() ); ?></textarea>
		<?php submit_button( 'Save .htaccess' ); ?>
	</form>

	<?php if ( $has_backup ) : ?>
		<h2>Backup</h2>
		<p class="description">From your last save, before it was overwritten.</p>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<?php wp_nonce_field( 'bootg_restore_htaccess_backup' ); ?>
			<input type="hidden" name="action" value="bootg_restore_htaccess_backup">
			<?php submit_button( 'Restore Backup', 'secondary', 'submit', false ); ?>
		</form>
	<?php endif; ?>
	<?php
}
