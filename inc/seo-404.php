<?php
/**
 * 404 Monitor — remembers every address visitors (and Google) tried that
 * doesn't exist on the site, with how often and where from, so you can turn
 * each one into a redirect with one click. Especially useful right after
 * moving to a new site, when old links and bookmarks are still out there.
 *
 * Stores only the address, a hit count, dates and the referring page — no
 * visitor IPs. Capped at 1,000 addresses (oldest dropped). Logged-in users
 * and obvious hacker probes (.php, .env ...) aren't recorded. Gated by the
 * SEO Suite module (inc/seo-suite.php); the list stays viewable either way.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WEAVIT_404_DB_VERSION', '1' );

function weavit_404_table() {
	global $wpdb;
	return $wpdb->prefix . 'weavit_404';
}

function weavit_404_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table   = weavit_404_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		url_hash char(32) NOT NULL,
		url varchar(500) NOT NULL,
		hits int(10) unsigned NOT NULL DEFAULT 1,
		first_seen datetime NOT NULL,
		last_seen datetime NOT NULL,
		referrer varchar(500) NOT NULL DEFAULT '',
		PRIMARY KEY  (id),
		UNIQUE KEY url_hash (url_hash),
		KEY last_seen (last_seen)
	) {$charset};" );
	update_option( 'weavit_404_db', WEAVIT_404_DB_VERSION );
}

add_action( 'admin_init', function () {
	if ( WEAVIT_404_DB_VERSION !== get_option( 'weavit_404_db' ) ) {
		weavit_404_install();
	}
} );

/* ---------------------------------------------------------------------
 * Recording
 * ------------------------------------------------------------------- */

add_action( 'template_redirect', function () {
	if ( ! is_404() || is_user_logged_in() || ! weavit_seo_suite_on() ) {
		return;
	}
	if ( ! in_array( $_SERVER['REQUEST_METHOD'] ?? 'GET', array( 'GET', 'HEAD' ), true ) ) {
		return;
	}
	if ( WEAVIT_404_DB_VERSION !== get_option( 'weavit_404_db' ) ) {
		weavit_404_install();
	}

	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
	if ( '' === $path || strlen( $path ) > 480 ) {
		return;
	}
	$noise = (bool) preg_match( '#\.(php\d?|asp|aspx|env|sql|git|ini|bak)$|/\.|favicon\.ico$|apple-touch-icon#i', $path );
	if ( apply_filters( 'weavit_404_ignore', $noise, $path ) ) {
		return;
	}

	global $wpdb;
	$table = weavit_404_table();
	$now   = current_time( 'mysql' );
	$ref   = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( substr( (string) wp_unslash( $_SERVER['HTTP_REFERER'] ), 0, 480 ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		"INSERT INTO {$table} (url_hash, url, hits, first_seen, last_seen, referrer) VALUES (%s, %s, 1, %s, %s, %s)
		 ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = VALUES(last_seen), referrer = IF(referrer = '', VALUES(referrer), referrer)",
		md5( strtolower( $path ) ),
		$path,
		$now,
		$now,
		$ref
	) );

	if ( 1 === wp_rand( 1, 40 ) ) { // Occasionally trim to the newest 1,000.
		$wpdb->query( "DELETE FROM {$table} WHERE id NOT IN (SELECT id FROM (SELECT id FROM {$table} ORDER BY last_seen DESC LIMIT 1000) AS keep_rows)" ); // phpcs:ignore WordPress.DB
	}
}, 99 );

/* ---------------------------------------------------------------------
 * Actions
 * ------------------------------------------------------------------- */

function weavit_404_back( $args = array() ) {
	return add_query_arg( array_merge( array( 'page' => 'bootg-seo', 'tab' => 'notfound' ), $args ), admin_url( 'admin.php' ) );
}

/** A likely destination: a published page/post whose address ends the same way as the missing one. */
function weavit_404_suggest( $path ) {
	$segment = basename( untrailingslashit( (string) wp_parse_url( $path, PHP_URL_PATH ) ) );
	$segment = sanitize_title( preg_replace( '/\.[a-z0-9]{2,5}$/i', '', $segment ) );
	if ( '' === $segment ) {
		return '';
	}
	$found = get_posts( array(
		'name'           => $segment,
		'post_type'      => array( 'page', 'post', 'service', 'integration', 'guide', 'team_member' ),
		'post_status'    => 'publish',
		'posts_per_page' => 1,
	) );
	return $found ? (string) wp_parse_url( get_permalink( $found[0] ), PHP_URL_PATH ) : '';
}

add_action( 'admin_post_weavit_404_redirect', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'weavit_404_redirect' );
	global $wpdb;
	$id     = absint( $_POST['id'] ?? 0 );
	$target = trim( wp_unslash( $_POST['target'] ?? '' ) );
	$row    = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . weavit_404_table() . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB
	if ( ! $row || '' === $target ) {
		wp_safe_redirect( weavit_404_back( array( 'notice' => 'invalid' ) ) );
		exit;
	}
	$post_id = wp_insert_post( array( 'post_type' => 'bootg_redirect', 'post_status' => 'publish', 'post_title' => $row->url ), true );
	if ( is_wp_error( $post_id ) ) {
		wp_safe_redirect( weavit_404_back( array( 'notice' => 'invalid' ) ) );
		exit;
	}
	bootg_save_redirect( $post_id, array(
		'sources'     => array( array( 'url' => $row->url, 'match' => 'exact' ) ),
		'destination' => $target,
		'type'        => 301,
	) );
	$wpdb->delete( weavit_404_table(), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
	wp_safe_redirect( weavit_404_back( array( 'notice' => 'redirected' ) ) );
	exit;
} );

add_action( 'admin_post_weavit_404_delete', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'weavit_404_delete' );
	global $wpdb;
	if ( ! empty( $_POST['clear_all'] ) ) {
		$wpdb->query( 'TRUNCATE TABLE ' . weavit_404_table() ); // phpcs:ignore WordPress.DB
	} else {
		$wpdb->delete( weavit_404_table(), array( 'id' => absint( $_POST['id'] ?? 0 ) ) ); // phpcs:ignore WordPress.DB
	}
	wp_safe_redirect( weavit_404_back( array( 'notice' => 'deleted' ) ) );
	exit;
} );

/** How many different missing addresses were hit in the last $days days. */
function weavit_404_recent_count( $days = 30 ) {
	global $wpdb;
	if ( WEAVIT_404_DB_VERSION !== get_option( 'weavit_404_db' ) ) {
		return 0;
	}
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . weavit_404_table() . ' WHERE last_seen >= %s', wp_date( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB
}

/* ---------------------------------------------------------------------
 * Weavit > SEO > 404 Monitor
 * ------------------------------------------------------------------- */

function weavit_seo_render_notfound_tab() {
	global $wpdb;
	weavit_seo_status_notice();
	if ( WEAVIT_404_DB_VERSION !== get_option( 'weavit_404_db' ) ) {
		weavit_404_install();
	}
	$notice = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$texts  = array(
		'redirected' => array( 'success', 'Redirect created. Visitors to that old address now land on the new one.' ),
		'deleted'    => array( 'success', 'Removed from the list.' ),
		'invalid'    => array( 'error', 'Enter where the old address should go.' ),
	);
	if ( isset( $texts[ $notice ] ) ) {
		echo '<div class="notice notice-' . esc_attr( $texts[ $notice ][0] ) . ' is-dismissible inline"><p>' . esc_html( $texts[ $notice ][1] ) . '</p></div>';
	}

	$order = ( isset( $_GET['orderby'] ) && 'hits' === $_GET['orderby'] ) ? 'hits DESC, last_seen DESC' : 'last_seen DESC'; // phpcs:ignore WordPress.Security.NonceVerification
	$rows  = $wpdb->get_results( 'SELECT * FROM ' . weavit_404_table() . " ORDER BY {$order} LIMIT 200" ); // phpcs:ignore WordPress.DB
	?>
	<h2>404 Monitor</h2>
	<p style="max-width:780px;">Addresses that visitors or Google tried but that don't exist. Send each one to the right page and the visitor never sees an error. Logged-in users and obvious hacker probes aren't recorded.</p>

	<?php if ( ! $rows ) : ?>
		<p><strong>Nothing recorded yet</strong> — that's good. Anything that returns "page not found" will show up here.</p>
	<?php else : ?>
		<p>Sort by <a href="<?php echo esc_url( weavit_404_back() ); ?>">most recent</a> &middot; <a href="<?php echo esc_url( weavit_404_back( array( 'orderby' => 'hits' ) ) ); ?>">most visited</a></p>
		<table class="widefat striped" style="max-width:1100px;">
			<thead><tr><th>Missing address</th><th style="width:60px;">Visits</th><th style="width:140px;">Last seen</th><th>Came from</th><th style="width:330px;">Send them to</th></tr></thead>
			<tbody>
			<?php foreach ( $rows as $row ) : ?>
				<tr>
					<td><code style="word-break:break-all;"><?php echo esc_html( $row->url ); ?></code></td>
					<td><?php echo (int) $row->hits; ?></td>
					<td><?php echo esc_html( mysql2date( 'j M Y', $row->last_seen ) ); ?></td>
					<td style="word-break:break-all;"><?php echo $row->referrer ? esc_html( $row->referrer ) : '<span class="description">—</span>'; ?></td>
					<td>
						<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="display:flex;gap:6px;margin:0;">
							<?php wp_nonce_field( 'weavit_404_redirect' ); ?>
							<input type="hidden" name="action" value="weavit_404_redirect"><input type="hidden" name="id" value="<?php echo (int) $row->id; ?>">
							<input type="text" name="target" value="<?php echo esc_attr( weavit_404_suggest( $row->url ) ); ?>" placeholder="/new-page/" style="flex:1;min-width:0;" required>
							<button class="button button-primary" type="submit">Redirect</button>
						</form>
						<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="margin:4px 0 0;">
							<?php wp_nonce_field( 'weavit_404_delete' ); ?>
							<input type="hidden" name="action" value="weavit_404_delete"><input type="hidden" name="id" value="<?php echo (int) $row->id; ?>">
							<button class="button-link" type="submit" style="color:#b32d2e;">Ignore</button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="margin-top:14px;" onsubmit="return confirm('Clear the whole list?');">
			<?php wp_nonce_field( 'weavit_404_delete' ); ?>
			<input type="hidden" name="action" value="weavit_404_delete"><input type="hidden" name="clear_all" value="1">
			<button class="button" type="submit">Clear the list</button>
		</form>
	<?php endif; ?>
	<?php
}
