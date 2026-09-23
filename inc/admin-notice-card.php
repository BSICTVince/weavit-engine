<?php
/**
 * Reusable "there's an update available" admin notice card, styled like the
 * banner-style notices third-party plugins show (Elementor, Complianz, Really
 * Simple Security) rather than the plain wp-admin update row — icon, message,
 * an "Update now" action, "Maybe later" (snoozes a week), and "Dismiss" (hides
 * until the next release after this one). Shared by both the plugin and the
 * theme, so it lives in the engine and the theme calls it if present.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param string $type     'plugin' or 'theme'.
 * @param string $slug     Plugin file relative to plugins dir ("weavit-engine/weavit-engine.php")
 *                          or theme stylesheet slug ("bookkeeping-on-the-go").
 * @param string $label    Display name, e.g. "Weavit Engine".
 * @param string $icon_url Small square icon for the card.
 */
function bootg_render_update_notice_card( $type, $slug, $label, $icon_url ) {
	if ( ! current_user_can( 'update_plugins' ) && ! current_user_can( 'update_themes' ) ) {
		return;
	}

	$new_version = bootg_get_available_update_version( $type, $slug );
	if ( ! $new_version ) {
		return;
	}

	$dismiss_key = 'bootg_update_notice_dismissed_' . sanitize_key( $slug );
	$dismissed   = get_user_meta( get_current_user_id(), $dismiss_key, true );
	if ( $dismissed && $dismissed['version'] === $new_version ) {
		if ( 'forever' === $dismissed['until'] || time() < $dismissed['until'] ) {
			return;
		}
	}

	$nonce = wp_create_nonce( 'bootg_dismiss_update_notice' );
	$id    = 'bootg-update-notice-' . sanitize_key( $slug );

	if ( 'theme' === $type ) {
		$update_url = wp_nonce_url( self_admin_url( 'update.php?action=upgrade-theme&theme=' . rawurlencode( $slug ) ), 'upgrade-theme_' . $slug );
	} else {
		$update_url = wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( $slug ) ), 'upgrade-plugin_' . $slug );
	}
	?>
	<div id="<?php echo esc_attr( $id ); ?>" class="notice bootg-update-card" style="display:flex;align-items:flex-start;gap:14px;padding:16px 20px;border-left-color:#2271b1;">
		<img src="<?php echo esc_url( $icon_url ); ?>" alt="" style="width:32px;height:32px;border-radius:6px;flex-shrink:0;" onerror="this.style.display='none';">
		<div style="flex:1;">
			<p style="margin:0 0 10px;">
				<strong><?php echo esc_html( $label ); ?> <?php echo esc_html( $new_version ); ?></strong> is available (you're on <?php echo esc_html( bootg_get_installed_version( $type, $slug ) ); ?>). Built and released on GitHub for this site.
			</p>
			<p style="margin:0;">
				<a href="<?php echo esc_url( $update_url ); ?>" class="button button-primary">Update now</a>
				<a href="#" class="bootg-update-notice-snooze" data-slug="<?php echo esc_attr( $slug ); ?>" data-version="<?php echo esc_attr( $new_version ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-until="week" style="margin-left:10px;">Maybe later</a>
				<a href="#" class="bootg-update-notice-snooze" data-slug="<?php echo esc_attr( $slug ); ?>" data-version="<?php echo esc_attr( $new_version ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-until="forever" style="margin-left:10px;">Don't show again</a>
			</p>
		</div>
	</div>
	<script>
	( function () {
		var card = document.getElementById( <?php echo wp_json_encode( $id ); ?> );
		if ( ! card ) { return; }
		card.querySelectorAll( '.bootg-update-notice-snooze' ).forEach( function ( link ) {
			link.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var data = new FormData();
				data.append( 'action', 'bootg_dismiss_update_notice' );
				data.append( 'slug', link.dataset.slug );
				data.append( 'version', link.dataset.version );
				data.append( 'until', link.dataset.until );
				data.append( '_wpnonce', link.dataset.nonce );
				fetch( ajaxurl, { method: 'POST', credentials: 'same-origin', body: data } );
				card.remove();
			} );
		} );
	} )();
	</script>
	<?php
}

function bootg_get_installed_version( $type, $slug ) {
	if ( 'theme' === $type ) {
		return wp_get_theme( $slug )->get( 'Version' );
	}
	if ( ! function_exists( 'get_plugin_data' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $slug, false, false );
	return $data['Version'] ?? '';
}

/** Reads WordPress's own update transient — populated by the Plugin Update Checker library's GitHub check — rather than querying GitHub again here. */
function bootg_get_available_update_version( $type, $slug ) {
	if ( 'theme' === $type ) {
		$transient = get_site_transient( 'update_themes' );
		return $transient->response[ $slug ]['new_version'] ?? null;
	}
	$transient = get_site_transient( 'update_plugins' );
	return isset( $transient->response[ $slug ] ) ? $transient->response[ $slug ]->new_version : null;
}

add_action( 'wp_ajax_bootg_dismiss_update_notice', function () {
	check_ajax_referer( 'bootg_dismiss_update_notice' );
	$slug    = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';
	$version = isset( $_POST['version'] ) ? sanitize_text_field( wp_unslash( $_POST['version'] ) ) : '';
	$until   = isset( $_POST['until'] ) && 'forever' === $_POST['until'] ? 'forever' : ( time() + WEEK_IN_SECONDS );
	if ( '' === $slug || '' === $version ) {
		wp_send_json_error();
	}
	update_user_meta( get_current_user_id(), 'bootg_update_notice_dismissed_' . sanitize_key( $slug ), array(
		'version' => $version,
		'until'   => $until,
	) );
	wp_send_json_success();
} );
