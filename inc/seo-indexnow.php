<?php
/**
 * IndexNow — tells Bing (and Yandex, Naver, Seznam) the moment a page is published,
 * updated or removed, instead of waiting for them to re-crawl the sitemap.
 *
 *   - The key is editable (paste your own from Bing Webmaster Tools) or generated here.
 *   - /{key}.txt is served virtually from whatever key is saved, so there is no file to upload
 *     and changing the key changes the file immediately.
 *   - Submits on publish / update / unpublish / trash, plus a row action and "submit everything".
 *
 * Opt-in (SEO Tools > IndexNow). Stands down with the rest of the SEO Suite when another SEO
 * plugin is active, and never pings while "Discourage search engines" is on or on a local site.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WEAVIT_INDEXNOW_KEY_OPTION', 'weavit_indexnow_key' );
define( 'WEAVIT_INDEXNOW_LOG_OPTION', 'weavit_indexnow_log' );

/* ---------------------------------------------------------------------
 * Key + state
 * ------------------------------------------------------------------- */

/** IndexNow keys are 8–128 characters of a-z, A-Z, 0-9 and dashes. */
function weavit_indexnow_valid_key( $key ) {
	return is_string( $key ) && (bool) preg_match( '/^[A-Za-z0-9-]{8,128}$/', $key );
}

function weavit_indexnow_generate_key() {
	return strtolower( wp_generate_password( 32, false, false ) );
}

/** The saved key; creates one the first time so the key file always has something valid to serve. */
function weavit_indexnow_key() {
	$key = get_option( WEAVIT_INDEXNOW_KEY_OPTION, '' );
	if ( ! weavit_indexnow_valid_key( $key ) ) {
		$key = weavit_indexnow_generate_key();
		update_option( WEAVIT_INDEXNOW_KEY_OPTION, $key, false );
	}
	return $key;
}

function weavit_indexnow_key_url() {
	return home_url( '/' . weavit_indexnow_key() . '.txt' );
}

function weavit_indexnow_enabled() {
	return function_exists( 'weavit_seo_suite_on' ) && weavit_seo_suite_on() && ! empty( weavit_seo_settings()['indexnow_enabled'] );
}

/** Why pings are being held back right now, or '' when they will go out. */
function weavit_indexnow_blocker() {
	if ( ! get_option( 'blog_public' ) ) {
		return 'Settings > Reading has "Discourage search engines" ticked, so nothing is sent.';
	}
	$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$env  = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
	$local = 'localhost' === $host || 'local' === $env || (bool) preg_match( '/\.(local|test|localhost)$/i', $host );
	if ( $local && ! apply_filters( 'weavit_indexnow_allow_local', false ) ) {
		return 'This is a local site (' . $host . '), so nothing is sent. Search engines cannot reach it.';
	}
	return '';
}

/* ---------------------------------------------------------------------
 * Serve /{key}.txt dynamically
 * ------------------------------------------------------------------- */

add_action( 'template_redirect', function () {
	if ( ! weavit_indexnow_enabled() ) {
		return;
	}
	$key  = weavit_indexnow_key();
	$want = untrailingslashit( (string) wp_parse_url( home_url( '/' . $key . '.txt' ), PHP_URL_PATH ) );
	$got  = untrailingslashit( (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( $got !== $want ) {
		return;
	}
	status_header( 200 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'Cache-Control: public, max-age=3600' );
	echo $key; // phpcs:ignore WordPress.Security.EscapeOutput -- validated [A-Za-z0-9-] only.
	exit;
}, 0 );

/* ---------------------------------------------------------------------
 * Submitting
 * ------------------------------------------------------------------- */

function weavit_indexnow_message( $code ) {
	$map = array(
		200 => 'Received',
		202 => 'Received, key check pending',
		400 => 'Bad request (invalid format)',
		403 => 'Key not found at the key file address (or does not match)',
		422 => 'URLs do not belong to this host, or the key is invalid',
		429 => 'Too many requests, try again later',
	);
	return $map[ $code ] ?? ( $code ? 'Unexpected response' : 'Could not reach the server' );
}

function weavit_indexnow_log( $count, $code, $source, $detail = '' ) {
	$log = (array) get_option( WEAVIT_INDEXNOW_LOG_OPTION, array() );
	array_unshift( $log, array(
		'time'   => time(),
		'count'  => (int) $count,
		'code'   => (int) $code,
		'source' => $source,
		'detail' => $detail ?: weavit_indexnow_message( $code ),
	) );
	update_option( WEAVIT_INDEXNOW_LOG_OPTION, array_slice( $log, 0, 20 ), false );
}

/**
 * Send URLs to IndexNow. Returns array( 'code' => int, 'message' => string, 'count' => int ).
 * Callers decide whether to check weavit_indexnow_enabled(); this only refuses when blocked.
 */
function weavit_indexnow_submit( array $urls, $source = 'auto' ) {
	$blocker = weavit_indexnow_blocker();
	if ( $blocker ) {
		return array( 'code' => 0, 'message' => $blocker, 'count' => 0 );
	}

	$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$list = array();
	foreach ( array_unique( array_filter( $urls ) ) as $url ) {
		$url = esc_url_raw( $url );
		if ( $url && wp_parse_url( $url, PHP_URL_HOST ) === $host ) {
			$list[] = $url;
		}
	}
	if ( ! $list ) {
		return array( 'code' => 0, 'message' => 'No valid URLs to send.', 'count' => 0 );
	}

	$endpoint = apply_filters( 'weavit_indexnow_endpoint', 'https://api.indexnow.org/indexnow' );
	$key      = weavit_indexnow_key();
	$code     = 0;
	$detail   = '';
	foreach ( array_chunk( $list, 10000 ) as $chunk ) {
		$response = wp_remote_post( $endpoint, array(
			'timeout' => 8,
			'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
			'body'    => wp_json_encode( array(
				'host'        => $host,
				'key'         => $key,
				'keyLocation' => weavit_indexnow_key_url(),
				'urlList'     => $chunk,
			) ),
		) );
		if ( is_wp_error( $response ) ) {
			$code   = 0;
			$detail = $response->get_error_message();
			break;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 300 ) {
			break;
		}
	}

	weavit_indexnow_log( count( $list ), $code, $source, $detail );
	return array( 'code' => $code, 'message' => $detail ?: weavit_indexnow_message( $code ), 'count' => count( $list ) );
}

/* ---------------------------------------------------------------------
 * Automatic pings (one batched request per page load)
 * ------------------------------------------------------------------- */

/** Post types worth pinging: the ones in the sitemap. */
function weavit_indexnow_post_types() {
	return (array) weavit_seo_settings()['sitemap_types'];
}

function weavit_indexnow_queue( $url = null ) {
	static $queue = array();
	if ( null === $url ) {
		return $queue;
	}
	$queue[ $url ] = true;
	return $queue;
}

/** Permalinks as they were while the post was still live — a draft or trashed post has no real URL. */
function weavit_indexnow_remember_url( $post_id, $url = null ) {
	static $urls = array();
	if ( null !== $url ) {
		$urls[ $post_id ] = $url;
	}
	return $urls[ $post_id ] ?? '';
}

function weavit_indexnow_remember_live( $post_id ) {
	$post = get_post( $post_id );
	if ( $post && 'publish' === $post->post_status && in_array( $post->post_type, weavit_indexnow_post_types(), true ) ) {
		// WordPress renames a trashed post's slug to "…__trashed" before our hook runs; undo that.
		weavit_indexnow_remember_url( $post_id, preg_replace( '/__trashed(?=[\/?]|$)/', '', get_permalink( $post ) ) );
	}
}
add_action( 'pre_post_update', 'weavit_indexnow_remember_live' );
add_action( 'wp_trash_post', 'weavit_indexnow_remember_live' );
add_action( 'before_delete_post', 'weavit_indexnow_remember_live' );

add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( ! weavit_indexnow_enabled() || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
		return;
	}
	if ( $new === $old && 'publish' !== $new ) {
		return;
	}
	if ( ! in_array( $post->post_type, weavit_indexnow_post_types(), true ) ) {
		return;
	}

	if ( 'publish' === $new ) {
		if ( get_post_meta( $post->ID, 'seo_noindex', true ) || '' !== $post->post_password ) {
			return;
		}
		$url = get_permalink( $post );
		if ( get_transient( 'weavit_in_' . md5( $url ) ) ) {
			return; // Pinged a moment ago (editors save twice: post, then meta boxes).
		}
		set_transient( 'weavit_in_' . md5( $url ), 1, 5 * MINUTE_IN_SECONDS );
		weavit_indexnow_queue( $url );
	} elseif ( 'publish' === $old ) {
		$url = weavit_indexnow_remember_url( $post->ID );
		if ( $url ) {
			weavit_indexnow_queue( $url ); // Removed pages are reported too, so Bing drops them.
		}
	}
}, 10, 3 );

add_action( 'shutdown', function () {
	$queue = weavit_indexnow_queue();
	if ( $queue && weavit_indexnow_enabled() ) {
		weavit_indexnow_submit( array_keys( $queue ), 'auto' );
	}
} );

/* ---------------------------------------------------------------------
 * Manual: row action, settings, "submit everything"
 * ------------------------------------------------------------------- */

function weavit_indexnow_row_action( $actions, $post ) {
	if ( weavit_indexnow_enabled() && 'publish' === $post->post_status && in_array( $post->post_type, weavit_indexnow_post_types(), true ) && current_user_can( 'edit_post', $post->ID ) ) {
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=weavit_indexnow_submit&post=' . (int) $post->ID ), 'weavit_indexnow_submit_' . $post->ID );
		$actions['weavit_indexnow'] = '<a href="' . esc_url( $url ) . '">IndexNow: Submit</a>';
	}
	return $actions;
}
add_filter( 'post_row_actions', 'weavit_indexnow_row_action', 10, 2 );
add_filter( 'page_row_actions', 'weavit_indexnow_row_action', 10, 2 );

function weavit_indexnow_redirect_result( $result, $back ) {
	set_transient( 'weavit_indexnow_flash_' . get_current_user_id(), $result, 60 );
	wp_safe_redirect( $back );
	exit;
}

add_action( 'admin_post_weavit_indexnow_submit', function () {
	$post_id = (int) ( $_GET['post'] ?? 0 );
	check_admin_referer( 'weavit_indexnow_submit_' . $post_id );
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_die( 'Not allowed.' );
	}
	$result = weavit_indexnow_enabled()
		? weavit_indexnow_submit( array( get_permalink( $post_id ) ), 'manual' )
		: array( 'code' => 0, 'message' => 'IndexNow is switched off.', 'count' => 0 );
	weavit_indexnow_redirect_result( $result, wp_get_referer() ?: admin_url( 'edit.php' ) );
} );

add_action( 'admin_post_weavit_indexnow_all', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'weavit_indexnow_all' );
	$result = array( 'code' => 0, 'message' => 'IndexNow is switched off.', 'count' => 0 );
	if ( weavit_indexnow_enabled() ) {
		$ids = get_posts( array(
			'post_type'      => weavit_indexnow_post_types(),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'has_password'   => false,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				'relation' => 'OR',
				array( 'key' => 'seo_noindex', 'compare' => 'NOT EXISTS' ),
				array( 'key' => 'seo_noindex', 'value' => array( '', '0' ), 'compare' => 'IN' ),
			),
		) );
		$urls = array_map( 'get_permalink', $ids );
		$result = weavit_indexnow_submit( $urls, 'submit all' );
	}
	weavit_indexnow_redirect_result( $result, bootg_seo_tab_url( 'indexnow' ) );
} );

add_action( 'admin_post_weavit_indexnow_key', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'weavit_indexnow_key' );
	$new = isset( $_POST['regenerate'] ) ? weavit_indexnow_generate_key() : trim( sanitize_text_field( wp_unslash( $_POST['indexnow_key'] ?? '' ) ) );
	if ( weavit_indexnow_valid_key( $new ) ) {
		update_option( WEAVIT_INDEXNOW_KEY_OPTION, $new, false );
		$result = array( 'code' => -1, 'message' => 'Key saved. The key file now lives at ' . weavit_indexnow_key_url(), 'count' => 0 );
	} else {
		$result = array( 'code' => -2, 'message' => 'That key is not valid. Use 8–128 letters, numbers or dashes.', 'count' => 0 );
	}
	weavit_indexnow_redirect_result( $result, bootg_seo_tab_url( 'indexnow' ) );
} );

add_action( 'admin_notices', function () {
	$flash = get_transient( 'weavit_indexnow_flash_' . get_current_user_id() );
	if ( ! $flash ) {
		return;
	}
	delete_transient( 'weavit_indexnow_flash_' . get_current_user_id() );
	$ok = $flash['code'] < 0 ? -1 === $flash['code'] : ( $flash['code'] >= 200 && $flash['code'] < 300 );
	$text = $flash['count'] ? sprintf( 'IndexNow: %d URL(s) sent. %s', $flash['count'], $flash['message'] ) : 'IndexNow: ' . $flash['message'];
	echo '<div class="notice notice-' . ( $ok ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
} );

/* ---------------------------------------------------------------------
 * Admin tab
 * ------------------------------------------------------------------- */

function weavit_seo_render_indexnow_tab() {
	$s       = weavit_seo_settings();
	$key     = weavit_indexnow_key();
	$blocker = weavit_indexnow_blocker();
	weavit_seo_status_notice();
	weavit_seo_saved_notice();
	?>
	<h2>IndexNow (Bing &amp; others)</h2>
	<p>Pings Bing, Yandex, Naver and Seznam the moment a page is published, updated or removed, instead of waiting for them to crawl the sitemap. Google does not use IndexNow. Check results in <a href="https://www.bing.com/webmasters" target="_blank" rel="noopener">Bing Webmaster Tools</a>.</p>
	<?php if ( ! empty( $s['indexnow_enabled'] ) && $blocker ) : ?>
		<div class="notice notice-warning inline"><p><?php echo esc_html( $blocker ); ?></p></div>
	<?php endif; ?>
	<?php weavit_seo_form_open( 'indexnow' ); ?>
		<table class="form-table" role="presentation">
			<tr><th>Instant indexing</th><td>
				<label><input type="checkbox" name="indexnow_enabled" value="1" <?php checked( $s['indexnow_enabled'] ); ?>> Send changes to IndexNow automatically</label>
				<p class="description">Covers the content types in your sitemap. Pages marked "Hide from search engines" or password protected are skipped. Adds an "IndexNow: Submit" link to each row in the page lists.</p>
			</td></tr>
		</table>
		<?php submit_button( 'Save', 'primary', 'submit', false ); ?>
	</form>

	<h2 style="margin-top:34px;">API key</h2>
	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<?php wp_nonce_field( 'weavit_indexnow_key' ); ?>
		<input type="hidden" name="action" value="weavit_indexnow_key">
		<table class="form-table" role="presentation">
			<tr><th><label for="indexnow_key">Key</label></th><td>
				<input type="text" id="indexnow_key" name="indexnow_key" class="large-text code" value="<?php echo esc_attr( $key ); ?>" maxlength="128" pattern="[A-Za-z0-9\-]{8,128}">
				<p class="description">Paste the key from Bing Webmaster Tools, or keep the generated one. 8–128 letters, numbers or dashes.</p>
			</td></tr>
			<tr><th>Key file</th><td>
				<a href="<?php echo esc_url( weavit_indexnow_key_url() ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( weavit_indexnow_key_url() ); ?></code></a>
				<p class="description">Served automatically from the key above, nothing to upload. It follows the key if you change it<?php echo empty( $s['indexnow_enabled'] ) ? ' (it only goes live once IndexNow is switched on)' : ''; ?>.</p>
			</td></tr>
		</table>
		<?php submit_button( 'Save key', 'primary', 'submit', false ); ?>
		<?php submit_button( 'Generate a new key', 'secondary', 'regenerate', false, array( 'onclick' => "return confirm('Replace the current key? Bing will need the new one.');" ) ); ?>
	</form>

	<h2 style="margin-top:34px;">Submit everything</h2>
	<p>Sends every published page in your sitemap in one go. Useful once after setup. Day to day, changes are sent automatically.</p>
	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<?php wp_nonce_field( 'weavit_indexnow_all' ); ?>
		<input type="hidden" name="action" value="weavit_indexnow_all">
		<?php submit_button( 'Submit all pages now', 'secondary', 'submit', false, empty( $s['indexnow_enabled'] ) ? array( 'disabled' => 'disabled' ) : array() ); ?>
	</form>

	<h2 style="margin-top:34px;">Recent submissions</h2>
	<?php $log = (array) get_option( WEAVIT_INDEXNOW_LOG_OPTION, array() ); ?>
	<?php if ( ! $log ) : ?>
		<p class="description">Nothing sent yet.</p>
	<?php else : ?>
		<table class="widefat striped" style="max-width:780px;"><thead><tr><th>When</th><th>Source</th><th>URLs</th><th>Result</th></tr></thead><tbody>
			<?php foreach ( $log as $row ) : ?>
				<tr>
					<td><?php echo esc_html( human_time_diff( $row['time'] ) . ' ago' ); ?></td>
					<td><?php echo esc_html( $row['source'] ); ?></td>
					<td><?php echo (int) $row['count']; ?></td>
					<td><?php echo esc_html( ( $row['code'] ? $row['code'] . ' — ' : '' ) . $row['detail'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody></table>
	<?php endif; ?>
	<?php
}
