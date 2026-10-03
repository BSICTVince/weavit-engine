<?php
/**
 * Cache tools — one place to see and purge the caches that sit between an
 * edit and what visitors see, plus automatic purging when images change.
 *
 *   - Weavit WebP image cache (inc/image-optimizer.php)
 *   - Page caches from other plugins (LiteSpeed Cache, WP Rocket, W3 Total
 *     Cache, WP Super Cache, WP Fastest Cache, Cache Enabler, SiteGround
 *     Optimizer, Breeze, Autoptimize) — purged through their own public
 *     functions/hooks, never by touching their files
 *   - WordPress's object cache
 *
 * Surfaces: Weavit > Cache, and a "Cache" menu in the admin toolbar next to
 * Customize with Purge All / Images / Pages / Object cache.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WEAVIT_CACHE_OPTION', 'weavit_cache_settings' );

function weavit_cache_settings() {
	return wp_parse_args( get_option( WEAVIT_CACHE_OPTION, array() ), array(
		'admin_bar'         => 1,
		'auto_purge_images' => 1,
		'auto_purge_pages'  => 1,
	) );
}

function weavit_cache_enabled() {
	return bootg_module_enabled( 'cache-tools' );
}

/** Page-cache plugins this knows how to purge: id => array( label, is active ). */
function weavit_cache_detect() {
	return array(
		'litespeed'    => array( 'LiteSpeed Cache', defined( 'LSCWP_V' ) || class_exists( 'LiteSpeed\Core' ) ),
		'wprocket'     => array( 'WP Rocket', function_exists( 'rocket_clean_domain' ) ),
		'w3tc'         => array( 'W3 Total Cache', function_exists( 'w3tc_flush_all' ) ),
		'wpsc'         => array( 'WP Super Cache', function_exists( 'wp_cache_clear_cache' ) ),
		'wpfc'         => array( 'WP Fastest Cache', class_exists( 'WpFastestCache' ) ),
		'cacheenabler' => array( 'Cache Enabler', class_exists( 'Cache_Enabler' ) ),
		'sgo'          => array( 'SiteGround Optimizer', function_exists( 'sg_cachepress_purge_cache' ) ),
		'breeze'       => array( 'Breeze', has_action( 'breeze_clear_all_cache' ) ),
		'autoptimize'  => array( 'Autoptimize (CSS/JS)', class_exists( 'autoptimizeCache' ) ),
	);
}

function weavit_cache_detected_labels() {
	$labels = array();
	foreach ( weavit_cache_detect() as $plugin ) {
		if ( $plugin[1] ) {
			$labels[] = $plugin[0];
		}
	}
	return $labels;
}

/** Purges every supported page-cache plugin that's active. Returns their names. */
function weavit_cache_purge_pages() {
	$done = array();

	if ( defined( 'LSCWP_V' ) || class_exists( 'LiteSpeed\Core' ) ) {
		do_action( 'litespeed_purge_all' );
		$done[] = 'LiteSpeed Cache';
	}
	if ( function_exists( 'rocket_clean_domain' ) ) {
		rocket_clean_domain();
		$done[] = 'WP Rocket';
	}
	if ( function_exists( 'w3tc_flush_all' ) ) {
		w3tc_flush_all();
		$done[] = 'W3 Total Cache';
	}
	if ( function_exists( 'wp_cache_clear_cache' ) ) {
		wp_cache_clear_cache();
		$done[] = 'WP Super Cache';
	}
	if ( class_exists( 'WpFastestCache' ) && isset( $GLOBALS['wp_fastest_cache'] ) && method_exists( $GLOBALS['wp_fastest_cache'], 'deleteCache' ) ) {
		$GLOBALS['wp_fastest_cache']->deleteCache( true );
		$done[] = 'WP Fastest Cache';
	}
	if ( class_exists( 'Cache_Enabler' ) ) {
		do_action( 'cache_enabler_clear_complete_cache' );
		$done[] = 'Cache Enabler';
	}
	if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
		sg_cachepress_purge_cache();
		$done[] = 'SiteGround Optimizer';
	}
	if ( has_action( 'breeze_clear_all_cache' ) ) {
		do_action( 'breeze_clear_all_cache' );
		$done[] = 'Breeze';
	}
	if ( class_exists( 'autoptimizeCache' ) && method_exists( 'autoptimizeCache', 'clearall' ) ) {
		autoptimizeCache::clearall();
		$done[] = 'Autoptimize';
	}

	return (array) apply_filters( 'weavit_cache_purged_pages', $done );
}

/** Runs one purge ('images' | 'pages' | 'object' | 'all') and returns a human summary. */
function weavit_cache_purge( $type ) {
	$parts = array();

	if ( in_array( $type, array( 'images', 'all' ), true ) && function_exists( 'weavit_imgopt_clear_cache' ) ) {
		$parts[] = 'image cache (' . weavit_imgopt_clear_cache() . ' file(s))';
	}
	if ( in_array( $type, array( 'pages', 'all' ), true ) ) {
		$pages = weavit_cache_purge_pages();
		if ( $pages ) {
			$parts[] = 'page cache (' . implode( ', ', $pages ) . ')';
		} elseif ( 'pages' === $type ) {
			$parts[] = 'no page-cache plugin found, so nothing to purge';
		}
	}
	if ( in_array( $type, array( 'object', 'all' ), true ) ) {
		wp_cache_flush();
		$parts[] = 'object cache';
	}

	do_action( 'weavit_cache_purged', $type );
	return $parts ? 'Purged: ' . implode( '; ', $parts ) . '.' : 'Nothing to purge.';
}

function weavit_cache_purge_url( $type ) {
	return wp_nonce_url( add_query_arg( array( 'action' => 'weavit_purge', 'type' => $type ), admin_url( 'admin-post.php' ) ), 'weavit_purge' );
}

add_action( 'admin_post_weavit_purge', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'weavit_purge' );
	$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'all';
	if ( ! in_array( $type, array( 'images', 'pages', 'object', 'all' ), true ) ) {
		$type = 'all';
	}
	set_transient( 'weavit_purge_msg_' . get_current_user_id(), weavit_cache_purge( $type ), 120 );

	$back = wp_get_referer() ?: admin_url( 'admin.php?page=weavit-cache' );
	wp_safe_redirect( add_query_arg( 'weavit_purged', 1, remove_query_arg( 'weavit_purged', $back ) ) );
	exit;
} );

/** One-shot confirmation after a purge: a normal notice in wp-admin, a small toast on the site. */
function weavit_cache_pop_message() {
	if ( empty( $_GET['weavit_purged'] ) || ! current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return '';
	}
	$key = 'weavit_purge_msg_' . get_current_user_id();
	$msg = get_transient( $key );
	delete_transient( $key );
	return is_string( $msg ) ? $msg : '';
}

add_action( 'admin_notices', function () {
	$msg = weavit_cache_pop_message();
	if ( $msg ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}
} );

add_action( 'wp_footer', function () {
	$msg = weavit_cache_pop_message();
	if ( ! $msg ) {
		return;
	}
	echo '<div id="weavit-purge-toast" style="position:fixed;left:16px;bottom:16px;z-index:999999;max-width:calc(100vw - 32px);background:#1d1a20;color:#fff;padding:12px 16px;border-radius:8px;font:14px/1.4 sans-serif;box-shadow:0 6px 24px rgba(0,0,0,.3);">' . esc_html( $msg ) . '</div>';
	echo '<script>setTimeout(function(){var t=document.getElementById("weavit-purge-toast");if(t){t.remove();}},5000);</script>';
} );

/* ---------------------------------------------------------------------
 * Toolbar menu, next to "Customize"
 * ------------------------------------------------------------------- */

add_action( 'admin_bar_menu', function ( $bar ) {
	if ( ! current_user_can( 'manage_options' ) || ! weavit_cache_enabled() || empty( weavit_cache_settings()['admin_bar'] ) ) {
		return;
	}

	$bar->add_node( array(
		'id'    => 'weavit-cache',
		'title' => '<span class="ab-icon" aria-hidden="true"></span><span class="ab-label">Cache</span>',
		'href'  => admin_url( 'admin.php?page=weavit-cache' ),
		'meta'  => array( 'title' => 'Weavit cache tools' ),
	) );

	$items = array( 'all' => 'Purge All', 'images' => 'Purge Images (WebP)' );
	if ( weavit_cache_detected_labels() ) {
		$items['pages'] = 'Purge Pages (' . implode( ', ', weavit_cache_detected_labels() ) . ')';
	}
	$items['object'] = 'Purge Object Cache';

	foreach ( $items as $type => $label ) {
		$bar->add_node( array(
			'id'     => 'weavit-cache-' . $type,
			'parent' => 'weavit-cache',
			'title'  => esc_html( $label ),
			'href'   => weavit_cache_purge_url( $type ),
		) );
	}
	$bar->add_node( array(
		'id'     => 'weavit-cache-settings',
		'parent' => 'weavit-cache',
		'title'  => 'Cache Settings',
		'href'   => admin_url( 'admin.php?page=weavit-cache' ),
	) );
}, 41 ); // Core's Customize item is at 40.

function weavit_cache_toolbar_css() {
	if ( is_admin_bar_showing() && weavit_cache_enabled() ) {
		wp_add_inline_style( 'admin-bar', '#wpadminbar #wp-admin-bar-weavit-cache .ab-icon:before{content:"\f463";top:2px}' );
	}
}
add_action( 'wp_enqueue_scripts', 'weavit_cache_toolbar_css', 20 );
add_action( 'admin_enqueue_scripts', 'weavit_cache_toolbar_css', 20 );

/* ---------------------------------------------------------------------
 * Automatic purging when images are deleted or edited
 * ------------------------------------------------------------------- */

add_action( 'delete_attachment', function ( $attachment_id ) {
	if ( ! weavit_cache_enabled() ) {
		return;
	}
	$s = weavit_cache_settings();
	if ( ! empty( $s['auto_purge_images'] ) && function_exists( 'weavit_imgopt_purge_file' ) ) {
		$file = get_attached_file( $attachment_id );
		if ( $file ) {
			weavit_imgopt_purge_file( $file );
			$meta = wp_get_attachment_metadata( $attachment_id );
			if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
				foreach ( $meta['sizes'] as $size ) {
					if ( ! empty( $size['file'] ) ) {
						weavit_imgopt_purge_file( dirname( $file ) . '/' . $size['file'] );
					}
				}
			}
		}
	}
	if ( ! empty( $s['auto_purge_pages'] ) ) {
		weavit_cache_purge_pages();
	}
}, 10 );

// Editing an image's details (title, caption, alt text...) can change the HTML pages print for it.
add_action( 'attachment_updated', function () {
	if ( weavit_cache_enabled() && ! empty( weavit_cache_settings()['auto_purge_pages'] ) ) {
		weavit_cache_purge_pages();
	}
} );

/* ---------------------------------------------------------------------
 * Weavit > Cache
 * ------------------------------------------------------------------- */

add_action( 'admin_menu', function () {
	add_submenu_page( 'weavit', 'Cache', 'Cache', 'manage_options', 'weavit-cache', 'weavit_cache_render_page' );
} );

add_action( 'admin_post_weavit_cache_save', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'weavit_cache_save' );
	update_option( WEAVIT_CACHE_OPTION, array(
		'admin_bar'         => empty( $_POST['admin_bar'] ) ? 0 : 1,
		'auto_purge_images' => empty( $_POST['auto_purge_images'] ) ? 0 : 1,
		'auto_purge_pages'  => empty( $_POST['auto_purge_pages'] ) ? 0 : 1,
	) );
	wp_safe_redirect( add_query_arg( array( 'page' => 'weavit-cache', 'saved' => 1 ), admin_url( 'admin.php' ) ) );
	exit;
} );

function weavit_cache_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s        = weavit_cache_settings();
	$detected = weavit_cache_detected_labels();

	$img_files = 0;
	$img_bytes = 0;
	if ( function_exists( 'weavit_imgopt_cache_dir' ) && is_dir( weavit_imgopt_cache_dir() ) ) {
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( weavit_imgopt_cache_dir(), FilesystemIterator::SKIP_DOTS ) ) as $f ) {
			if ( $f->isFile() ) {
				++$img_files;
				$img_bytes += $f->getSize();
			}
		}
	}
	$img_on = function_exists( 'bootg_module_enabled' ) && bootg_module_enabled( 'image-optimizer' );
	?>
	<div class="wrap">
		<h1>Cache</h1>
		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
		<?php endif; ?>

		<p style="max-width:760px;">Caches make the site fast, but they can show visitors an old version after you change something. Purge exactly what you need here, or from the <strong>Cache</strong> menu in the toolbar at the top of every page (next to Customize).</p>

		<table class="widefat striped" style="max-width:860px;margin:16px 0;">
			<thead><tr><th style="width:230px;">Cache</th><th>Status</th><th style="width:150px;"></th></tr></thead>
			<tbody>
				<tr>
					<td><strong>Images (WebP)</strong></td>
					<td><?php echo (int) $img_files; ?> converted file(s), <?php echo esc_html( size_format( $img_bytes ) ); ?><?php echo $img_on ? '' : ' &mdash; Image Optimizer is off'; ?>. <a href="<?php echo esc_url( admin_url( 'admin.php?page=weavit-image-optimizer' ) ); ?>">Image Optimizer settings</a></td>
					<td><a class="button" href="<?php echo esc_url( weavit_cache_purge_url( 'images' ) ); ?>">Purge Images</a></td>
				</tr>
				<tr>
					<td><strong>Pages</strong></td>
					<td><?php echo $detected ? esc_html( implode( ', ', $detected ) ) . ' detected.' : 'No page-cache plugin detected (LiteSpeed Cache, WP Rocket, W3 Total Cache, WP Super Cache, WP Fastest Cache, Cache Enabler, SiteGround Optimizer, Breeze or Autoptimize). Nothing to purge here yet.'; ?></td>
					<td><a class="button<?php echo $detected ? '' : ' disabled'; ?>" href="<?php echo $detected ? esc_url( weavit_cache_purge_url( 'pages' ) ) : '#'; ?>">Purge Pages</a></td>
				</tr>
				<tr>
					<td><strong>Object cache</strong></td>
					<td><?php echo wp_using_ext_object_cache() ? 'A persistent object cache (Redis/Memcached) is in use.' : 'WordPress\'s built-in per-request cache only.'; ?></td>
					<td><a class="button" href="<?php echo esc_url( weavit_cache_purge_url( 'object' ) ); ?>">Purge Object Cache</a></td>
				</tr>
				<tr>
					<td><strong>Everything</strong></td>
					<td>Images, pages and object cache in one go.</td>
					<td><a class="button button-primary" href="<?php echo esc_url( weavit_cache_purge_url( 'all' ) ); ?>">Purge All</a></td>
				</tr>
			</tbody>
		</table>

		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<?php wp_nonce_field( 'weavit_cache_save' ); ?>
			<input type="hidden" name="action" value="weavit_cache_save">
			<h2>Automatic purging</h2>
			<table class="form-table" role="presentation">
				<tr><th>When an image is deleted</th><td>
					<label><input type="checkbox" name="auto_purge_images" value="1" <?php checked( $s['auto_purge_images'] ); ?>> Remove its converted WebP copies</label><br>
					<label><input type="checkbox" name="auto_purge_pages" value="1" <?php checked( $s['auto_purge_pages'] ); ?>> Purge the page cache (also when an image's title, caption or alt text is edited)</label>
					<p class="description">New uploads and replaced images need no purge: the WebP is made fresh and its address changes with the file. A page-cache plugin may still hold old page HTML, which is what "Purge Pages" is for.</p>
				</td></tr>
				<tr><th>Toolbar</th><td><label><input type="checkbox" name="admin_bar" value="1" <?php checked( $s['admin_bar'] ); ?>> Show the "Cache" menu in the toolbar at the top</label></td></tr>
			</table>
			<?php submit_button( 'Save Settings' ); ?>
		</form>
	</div>
	<?php
}
