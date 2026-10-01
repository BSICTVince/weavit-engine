<?php
/**
 * Topics Catalog — a shared content-planning library, in the same spirit
 * as a syndicated blog library (BizPress Blogs etc.): a searchable,
 * filterable, paginated grid of topics you browse and "Import" into a
 * given site, rather than something baked into every site.
 *
 * Where things live:
 *  - The CENTRAL catalog (topic id, title, category, facts, and which
 *    site(s) have used it with what angle/status/URL) lives in a private
 *    GitHub repo, OUTSIDE WordPress entirely — not shipped with this
 *    plugin's own code, so no site's install grows just by having the
 *    plugin active. It's fetched live over the GitHub API (briefly
 *    cached) and written to the same way when you add a topic or update
 *    your site's usage of one.
 *  - What's LOCAL to this site is only the small bit that's genuinely
 *    site-specific: which topics you've imported here, plus this site's
 *    own full drafted article text per topic (kept local so the shared
 *    repo doesn't balloon with full article bodies) — stored as one
 *    WordPress option, the normal place for small per-install settings.
 *
 * "Copy AI brief" pulls the facts, this site's angle, and every OTHER
 * site's already-recorded angle (read straight from the central catalog)
 * into one ready-to-paste prompt that explicitly asks for different
 * structure/examples/phrasing — so the next write-up is a deliberate
 * paraphrase, not a copy with a few words changed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BOOTG_TOPICS_GITHUB_OPTION', 'bootg_topics_github_settings' );
define( 'BOOTG_IMPORTED_TOPICS_OPTION', 'bootg_imported_topics' );
define( 'BOOTG_TOPICS_CACHE_KEY', 'bootg_topics_github_cache' );

add_action( 'admin_menu', function () {
	if ( ! bootg_module_enabled( 'topics-catalog' ) ) {
		return;
	}
	add_submenu_page( 'weavit', 'Topics Catalog', 'Topics Catalog', 'edit_posts', 'bootg-topics-catalog', 'bootg_render_topics_catalog_page' );
	add_submenu_page( 'weavit', 'Add New Topic', 'Topics: Add New', 'edit_posts', 'bootg-topics-catalog-new', 'bootg_render_topic_new_screen' );
	add_submenu_page( 'weavit', 'Topics Catalog Settings', 'Topics: Settings', 'manage_options', 'bootg-topics-catalog-settings', 'bootg_render_topics_settings_page' );
} );

function bootg_topic_status_options() {
	return array(
		''          => 'Not started',
		'drafted'   => 'Drafted',
		'published' => 'Published',
	);
}

/* ---------------------------------------------------------------------
 * Settings (GitHub connection) — same Settings API pattern as reCAPTCHA
 * and the other credential screens in this plugin.
 * ------------------------------------------------------------------- */

function bootg_topics_github_defaults() {
	return array(
		'owner'  => '',
		'repo'   => '',
		'branch' => 'main',
		'path'   => 'topics.json',
		'token'  => '',
	);
}

function bootg_topics_github_settings() {
	return wp_parse_args( get_option( BOOTG_TOPICS_GITHUB_OPTION, array() ), bootg_topics_github_defaults() );
}

function bootg_topics_github_configured() {
	$s = bootg_topics_github_settings();
	return $s['owner'] && $s['repo'] && $s['token'];
}

add_action( 'admin_init', function () {
	register_setting( 'bootg_topics_github_group', BOOTG_TOPICS_GITHUB_OPTION, function ( $input ) {
		return array(
			'owner'  => sanitize_text_field( $input['owner'] ?? '' ),
			'repo'   => sanitize_text_field( $input['repo'] ?? '' ),
			'branch' => sanitize_text_field( $input['branch'] ?? '' ) ?: 'main',
			'path'   => sanitize_text_field( $input['path'] ?? '' ) ?: 'topics.json',
			'token'  => sanitize_text_field( $input['token'] ?? '' ),
		);
	} );
} );

function bootg_render_topics_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s = bootg_topics_github_settings();
	?>
	<div class="wrap">
		<h1>Topics Catalog Settings</h1>
		<p class="description" style="max-width:700px;">Connects this site to the shared Topics Catalog — a private GitHub repo that holds the catalog outside WordPress. Every site sharing a catalog should point at the <strong>same</strong> owner/repo so "other sites using this topic" is accurate.</p>

		<?php if ( isset( $_GET['settings-updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
		<?php endif; ?>

		<form action="options.php" method="post">
			<?php settings_fields( 'bootg_topics_github_group' ); ?>
			<table class="form-table">
				<tr>
					<th><label for="owner">Repo owner</label></th>
					<td><input type="text" id="owner" name="<?php echo esc_attr( BOOTG_TOPICS_GITHUB_OPTION ); ?>[owner]" value="<?php echo esc_attr( $s['owner'] ); ?>" class="regular-text" placeholder="e.g. BSICTVince"></td>
				</tr>
				<tr>
					<th><label for="repo">Repo name</label></th>
					<td><input type="text" id="repo" name="<?php echo esc_attr( BOOTG_TOPICS_GITHUB_OPTION ); ?>[repo]" value="<?php echo esc_attr( $s['repo'] ); ?>" class="regular-text" placeholder="e.g. bookkeeping-topics-catalog"></td>
				</tr>
				<tr>
					<th><label for="branch">Branch</label></th>
					<td><input type="text" id="branch" name="<?php echo esc_attr( BOOTG_TOPICS_GITHUB_OPTION ); ?>[branch]" value="<?php echo esc_attr( $s['branch'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="path">File path</label></th>
					<td><input type="text" id="path" name="<?php echo esc_attr( BOOTG_TOPICS_GITHUB_OPTION ); ?>[path]" value="<?php echo esc_attr( $s['path'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="token">Access token</label></th>
					<td>
						<input type="password" id="token" name="<?php echo esc_attr( BOOTG_TOPICS_GITHUB_OPTION ); ?>[token]" value="<?php echo esc_attr( $s['token'] ); ?>" class="regular-text" autocomplete="off">
						<p class="description">A fine-grained GitHub token scoped to only this repo, with Contents: Read and write. Create one at <a href="https://github.com/settings/tokens?type=beta" target="_blank" rel="noopener">github.com/settings/tokens</a>.</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<?php if ( bootg_topics_github_configured() ) : ?>
			<h2>Connection test</h2>
			<?php
			$result = bootg_topics_github_get_file( true );
			if ( is_wp_error( $result ) ) :
				?>
				<div class="notice notice-error inline"><p>Couldn't reach the catalog: <?php echo esc_html( $result->get_error_message() ); ?></p></div>
			<?php else : ?>
				<div class="notice notice-success inline"><p>Connected — found <?php echo (int) count( $result['topics'] ); ?> topic(s) in the catalog.</p></div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}

/* ---------------------------------------------------------------------
 * GitHub API — read/write the single topics.json file in the catalog repo.
 * ------------------------------------------------------------------- */

function bootg_topics_github_api_url( $suffix = '' ) {
	$s = bootg_topics_github_settings();
	return sprintf( 'https://api.github.com/repos/%s/%s/contents/%s%s', rawurlencode( $s['owner'] ), rawurlencode( $s['repo'] ), ltrim( $s['path'], '/' ), $suffix );
}

function bootg_topics_github_headers() {
	$s = bootg_topics_github_settings();
	return array(
		'Authorization' => 'Bearer ' . $s['token'],
		'Accept'        => 'application/vnd.github+json',
		'User-Agent'    => 'WeavitEngine-TopicsCatalog',
	);
}

/**
 * Fetches and decodes the catalog file. Returns array('topics'=>[], 'sha'=>string)
 * or a WP_Error. Cached for 5 minutes unless $bypass_cache is true (used by
 * "Save", "Add Topic" and the Settings connection test, which all need a
 * fresh sha to write safely).
 */
function bootg_topics_github_get_file( $bypass_cache = false ) {
	if ( ! bootg_topics_github_configured() ) {
		return new WP_Error( 'bootg_topics_not_configured', 'Topics Catalog isn\'t connected yet — fill in the Settings screen first.' );
	}

	if ( ! $bypass_cache ) {
		$cached = get_transient( BOOTG_TOPICS_CACHE_KEY );
		if ( false !== $cached ) {
			return $cached;
		}
	}

	$s        = bootg_topics_github_settings();
	$response = wp_remote_get( bootg_topics_github_api_url( '?ref=' . rawurlencode( $s['branch'] ) ), array(
		'headers' => bootg_topics_github_headers(),
		'timeout' => 15,
	) );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	if ( 404 === $code ) {
		return new WP_Error( 'bootg_topics_not_found', 'The catalog file doesn\'t exist yet in that repo at the configured path.' );
	}
	if ( 200 !== $code ) {
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return new WP_Error( 'bootg_topics_github_error', 'GitHub API error (' . $code . '): ' . ( $body['message'] ?? 'unknown error' ) );
	}

	$body    = json_decode( wp_remote_retrieve_body( $response ), true );
	$content = isset( $body['content'] ) ? base64_decode( str_replace( "\n", '', $body['content'] ) ) : ''; // phpcs:ignore
	$decoded = json_decode( $content, true );
	if ( ! is_array( $decoded ) || ! isset( $decoded['topics'] ) ) {
		$decoded = array( 'topics' => array() );
	}

	$result = array( 'topics' => $decoded['topics'], 'sha' => $body['sha'] ?? '' );
	set_transient( BOOTG_TOPICS_CACHE_KEY, $result, 5 * MINUTE_IN_SECONDS );
	return $result;
}

/** Writes the full topics array back to the catalog file. $sha must be the current file's sha (fetch fresh, don't reuse an old one). */
function bootg_topics_github_put_file( $topics, $sha, $message ) {
	$s      = bootg_topics_github_settings();
	$body   = array(
		'message' => $message,
		'content' => base64_encode( wp_json_encode( array( 'topics' => $topics ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ), // phpcs:ignore
		'branch'  => $s['branch'],
	);
	if ( $sha ) {
		$body['sha'] = $sha;
	}

	$response = wp_remote_request( bootg_topics_github_api_url(), array(
		'method'  => 'PUT',
		'headers' => array_merge( bootg_topics_github_headers(), array( 'Content-Type' => 'application/json' ) ),
		'body'    => wp_json_encode( $body ),
		'timeout' => 20,
	) );

	if ( is_wp_error( $response ) ) {
		return $response;
	}
	$code = wp_remote_retrieve_response_code( $response );
	if ( $code < 200 || $code >= 300 ) {
		$resp_body = json_decode( wp_remote_retrieve_body( $response ), true );
		return new WP_Error( 'bootg_topics_github_write_error', 'GitHub API error (' . $code . '): ' . ( $resp_body['message'] ?? 'unknown error' ) );
	}

	delete_transient( BOOTG_TOPICS_CACHE_KEY );
	return true;
}

function bootg_topics_find( $topics, $id ) {
	foreach ( $topics as $i => $topic ) {
		if ( $topic['id'] === $id ) {
			return $i;
		}
	}
	return false;
}

function bootg_topic_slug( $title, $existing_ids ) {
	$base = sanitize_title( $title );
	$slug = $base;
	$n    = 2;
	while ( in_array( $slug, $existing_ids, true ) ) {
		$slug = $base . '-' . $n;
		++$n;
	}
	return $slug;
}

function bootg_topics_catalog_categories( $topics ) {
	$cats = array();
	foreach ( $topics as $topic ) {
		if ( ! empty( $topic['category'] ) ) {
			$cats[ $topic['category'] ] = true;
		}
	}
	$cats = array_keys( $cats );
	sort( $cats );
	return $cats;
}

/** This site's own label, used as the "site" key in the central usage array. */
function bootg_this_site_label() {
	return get_bloginfo( 'name' ) ?: home_url();
}

function bootg_imported_topics() {
	return get_option( BOOTG_IMPORTED_TOPICS_OPTION, array() );
}

/* ---------------------------------------------------------------------
 * Form handling
 * ------------------------------------------------------------------- */

add_action( 'admin_post_bootg_add_central_topic', function () {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'bootg_add_central_topic' );

	$title    = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
	$category = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
	$facts    = isset( $_POST['facts'] ) ? sanitize_textarea_field( wp_unslash( $_POST['facts'] ) ) : '';

	$current = bootg_topics_github_get_file( true );
	if ( is_wp_error( $current ) ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'bootg-topics-catalog-new', 'error' => rawurlencode( $current->get_error_message() ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	$topics       = $current['topics'];
	$existing_ids = wp_list_pluck( $topics, 'id' );
	$id           = bootg_topic_slug( $title ?: 'topic', $existing_ids );
	$topics[]     = array(
		'id'       => $id,
		'title'    => $title,
		'category' => $category,
		'facts'    => $facts,
		'usage'    => array(),
	);

	$result = bootg_topics_github_put_file( $topics, $current['sha'], 'Add topic: ' . $title );
	if ( is_wp_error( $result ) ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'bootg-topics-catalog-new', 'error' => rawurlencode( $result->get_error_message() ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	wp_safe_redirect( add_query_arg( array( 'page' => 'bootg-topics-catalog', 'added' => 1 ), admin_url( 'admin.php' ) ) );
	exit;
} );

add_action( 'admin_post_bootg_save_topic_usage', function () {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'bootg_save_topic_usage' );

	$topic_id = isset( $_POST['topic_id'] ) ? sanitize_title( wp_unslash( $_POST['topic_id'] ) ) : '';
	$angle    = isset( $_POST['angle'] ) ? sanitize_textarea_field( wp_unslash( $_POST['angle'] ) ) : '';
	$status   = isset( $_POST['status'] ) && array_key_exists( wp_unslash( $_POST['status'] ), bootg_topic_status_options() ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
	$url      = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
	$draft    = isset( $_POST['draft'] ) ? sanitize_textarea_field( wp_unslash( $_POST['draft'] ) ) : '';

	if ( ! $topic_id ) {
		wp_die( 'Missing topic.' );
	}

	// Local: this site's own record (including the full draft, kept out of the shared repo).
	$imported               = bootg_imported_topics();
	$imported[ $topic_id ]  = array(
		'angle'       => $angle,
		'status'      => $status,
		'url'         => $url,
		'draft'       => $draft,
		'imported_at' => $imported[ $topic_id ]['imported_at'] ?? current_time( 'mysql' ),
	);
	update_option( BOOTG_IMPORTED_TOPICS_OPTION, $imported );

	// Central: record this site's angle/status/url (not the draft) so other sites see it.
	$current = bootg_topics_github_get_file( true );
	if ( ! is_wp_error( $current ) ) {
		$topics = $current['topics'];
		$index  = bootg_topics_find( $topics, $topic_id );
		if ( false !== $index ) {
			$site_label  = bootg_this_site_label();
			$usage_index = false;
			foreach ( $topics[ $index ]['usage'] as $ui => $u ) {
				if ( $u['site'] === $site_label ) {
					$usage_index = $ui;
					break;
				}
			}
			$entry = array( 'site' => $site_label, 'angle' => $angle, 'status' => $status, 'url' => $url );
			if ( false !== $usage_index ) {
				$topics[ $index ]['usage'][ $usage_index ] = $entry;
			} else {
				$topics[ $index ]['usage'][] = $entry;
			}
			bootg_topics_github_put_file( $topics, $current['sha'], 'Update usage: ' . $site_label . ' on ' . ( $topics[ $index ]['title'] ?? $topic_id ) );
		}
	}

	wp_safe_redirect( add_query_arg( array( 'page' => 'bootg-topics-catalog', 'action' => 'manage', 'topic' => $topic_id, 'saved' => 1 ), admin_url( 'admin.php' ) ) );
	exit;
} );

add_action( 'admin_post_bootg_remove_import', function () {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'bootg_remove_import' );

	$topic_id = isset( $_GET['topic'] ) ? sanitize_title( wp_unslash( $_GET['topic'] ) ) : '';
	$imported = bootg_imported_topics();
	unset( $imported[ $topic_id ] );
	update_option( BOOTG_IMPORTED_TOPICS_OPTION, $imported );

	wp_safe_redirect( add_query_arg( array( 'page' => 'bootg-topics-catalog', 'removed' => 1 ), admin_url( 'admin.php' ) ) );
	exit;
} );

/* ---------------------------------------------------------------------
 * Admin screens
 * ------------------------------------------------------------------- */

function bootg_render_topics_catalog_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	if ( ! bootg_topics_github_configured() ) {
		?>
		<div class="wrap">
			<h1>Topics Catalog</h1>
			<div class="notice notice-warning"><p>Not connected yet. Go to <a href="<?php echo esc_url( admin_url( 'admin.php?page=bootg-topics-catalog-settings' ) ); ?>">Topics Catalog &rarr; Settings</a> and add the catalog repo + access token.</p></div>
		</div>
		<?php
		return;
	}
	$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';
	if ( 'manage' === $action ) {
		bootg_render_topic_manage_screen();
		return;
	}
	bootg_render_topics_list_screen();
}

function bootg_render_topics_list_screen() {
	$result = bootg_topics_github_get_file();
	if ( is_wp_error( $result ) ) {
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">Topics Catalog</h1>
			<hr class="wp-header-end">
			<div class="notice notice-error"><p>Couldn't load the catalog: <?php echo esc_html( $result->get_error_message() ); ?></p></div>
		</div>
		<?php
		return;
	}

	$topics   = $result['topics'];
	$statuses = bootg_topic_status_options();
	$imported = bootg_imported_topics();
	$per_page = 12;

	$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$cat    = isset( $_GET['cat'] ) ? sanitize_text_field( wp_unslash( $_GET['cat'] ) ) : '';
	$paged  = max( 1, (int) ( $_GET['paged'] ?? 1 ) );

	$filtered = array_values( array_filter( $topics, function ( $topic ) use ( $search, $cat ) {
		if ( $cat && ( $topic['category'] ?? '' ) !== $cat ) {
			return false;
		}
		if ( $search ) {
			$haystack = strtolower( ( $topic['title'] ?? '' ) . ' ' . ( $topic['facts'] ?? '' ) );
			if ( false === strpos( $haystack, strtolower( $search ) ) ) {
				return false;
			}
		}
		return true;
	} ) );
	$total       = count( $filtered );
	$total_pages = max( 1, (int) ceil( $total / $per_page ) );
	$paged       = min( $paged, $total_pages );
	$page_items  = array_slice( $filtered, ( $paged - 1 ) * $per_page, $per_page );
	$categories  = bootg_topics_catalog_categories( $topics );
	$base_url    = admin_url( 'admin.php?page=bootg-topics-catalog' );
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline">Topics Catalog</h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bootg-topics-catalog-new' ) ); ?>" class="page-title-action">Add New Topic</a>
		<hr class="wp-header-end">

		<?php if ( isset( $_GET['added'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Topic added to the catalog.</p></div>
		<?php elseif ( isset( $_GET['removed'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Removed from this site's imported topics (the catalog entry itself is untouched).</p></div>
		<?php endif; ?>

		<p class="description" style="max-width:760px;">Browsing the shared catalog (fetched from GitHub, not stored on this site). "Import" pulls a topic's facts in and lets you record this site's own angle — then <strong>Copy AI brief</strong> builds a prompt that explicitly asks for different wording than every other site already using it.</p>

		<form method="get" style="display:flex;gap:8px;align-items:center;margin:16px 0;flex-wrap:wrap;">
			<input type="hidden" name="page" value="bootg-topics-catalog">
			<select name="cat">
				<option value="">All categories</option>
				<?php foreach ( $categories as $c ) : ?>
					<option value="<?php echo esc_attr( $c ); ?>" <?php selected( $cat, $c ); ?>><?php echo esc_html( $c ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search topics…" class="regular-text">
			<button type="submit" class="button">Search</button>
			<?php if ( $search || $cat ) : ?>
				<a href="<?php echo esc_url( $base_url ); ?>" class="button">Clear</a>
			<?php endif; ?>
			<a href="<?php echo esc_url( add_query_arg( 'bootg_refresh', '1', $base_url ) ); ?>" class="button" title="Bypass the 5-minute cache and re-fetch from GitHub now">Refresh</a>
		</form>

		<?php if ( empty( $topics ) ) : ?>
			<p>No topics in the catalog yet. <a href="<?php echo esc_url( admin_url( 'admin.php?page=bootg-topics-catalog-new' ) ); ?>">Add the first one</a>.</p>
		<?php elseif ( empty( $page_items ) ) : ?>
			<p>No topics match that search. <a href="<?php echo esc_url( $base_url ); ?>">Clear filters</a>.</p>
		<?php else : ?>
			<p class="description"><?php echo esc_html( $total ); ?> topic<?php echo 1 === $total ? '' : 's'; ?><?php echo $search || $cat ? ' matching your filters' : ''; ?>.</p>

			<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;margin:16px 0;">
				<?php foreach ( $page_items as $topic ) :
					$is_imported = isset( $imported[ $topic['id'] ] );
					?>
					<div style="border:1px solid #dcdcde;border-radius:6px;background:#fff;padding:16px;display:flex;flex-direction:column;">
						<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
							<?php if ( ! empty( $topic['category'] ) ) : ?>
								<span style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.03em;color:#2271b1;background:#f0f6fc;border-radius:999px;padding:3px 10px;margin-bottom:8px;"><?php echo esc_html( $topic['category'] ); ?></span>
							<?php else : ?>
								<span></span>
							<?php endif; ?>
							<?php if ( $is_imported ) : ?>
								<span style="font-size:11px;font-weight:600;color:#1a7f37;">&#10003; Imported here</span>
							<?php endif; ?>
						</div>
						<strong style="font-size:15px;margin-bottom:6px;"><?php echo esc_html( $topic['title'] ?: '(untitled)' ); ?></strong>
						<p style="color:#50575e;font-size:13px;line-height:1.5;flex-grow:1;margin:0 0 10px;"><?php echo esc_html( wp_trim_words( $topic['facts'] ?? '', 20 ) ); ?></p>
						<div style="font-size:12px;color:#50575e;margin-bottom:12px;">
							<?php if ( empty( $topic['usage'] ) ) : ?>
								<em>Not used anywhere yet</em>
							<?php else : ?>
								<?php foreach ( $topic['usage'] as $u ) : ?>
									<div style="margin-bottom:2px;">
										<strong><?php echo esc_html( $u['site'] ); ?></strong>
										— <?php echo esc_html( $statuses[ $u['status'] ] ?? 'Not started' ); ?>
										<?php if ( ! empty( $u['url'] ) ) : ?>
											(<a href="<?php echo esc_url( $u['url'] ); ?>" target="_blank" rel="noopener">view</a>)
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>
						<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'bootg-topics-catalog', 'action' => 'manage', 'topic' => $topic['id'] ), admin_url( 'admin.php' ) ) ); ?>" class="button <?php echo $is_imported ? '' : 'button-primary'; ?>" style="align-self:flex-start;"><?php echo $is_imported ? 'Edit my usage' : 'Import'; ?></a>
					</div>
				<?php endforeach; ?>
			</div>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav"><div class="tablenav-pages" style="display:flex;gap:6px;align-items:center;">
					<?php
					echo wp_kses_post( paginate_links( array(
						'base'      => add_query_arg( 'paged', '%#%', add_query_arg( array( 's' => $search, 'cat' => $cat ), $base_url ) ),
						'format'    => '',
						'current'   => $paged,
						'total'     => $total_pages,
						'prev_text' => 'Previous',
						'next_text' => 'Next',
					) ) );
					?>
				</div></div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}

add_action( 'admin_init', function () {
	if ( isset( $_GET['page'], $_GET['bootg_refresh'] ) && 'bootg-topics-catalog' === $_GET['page'] ) {
		delete_transient( BOOTG_TOPICS_CACHE_KEY );
	}
} );

function bootg_render_topic_new_screen() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	if ( ! bootg_topics_github_configured() ) {
		echo '<div class="wrap"><h1>Add New Topic</h1><div class="notice notice-warning"><p>Connect the catalog first in <a href="' . esc_url( admin_url( 'admin.php?page=bootg-topics-catalog-settings' ) ) . '">Settings</a>.</p></div></div>';
		return;
	}
	?>
	<div class="wrap">
		<h1>Add New Topic</h1>

		<?php if ( isset( $_GET['error'] ) ) : ?>
			<div class="notice notice-error"><p>Couldn't add the topic: <?php echo esc_html( wp_unslash( $_GET['error'] ) ); ?></p></div>
		<?php endif; ?>

		<p class="description" style="max-width:700px;">This writes straight to the shared catalog repo, so it's available to every site that reads from it right away.</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'bootg_add_central_topic' ); ?>
			<input type="hidden" name="action" value="bootg_add_central_topic">
			<table class="form-table">
				<tr>
					<th><label for="title">Title</label></th>
					<td><input type="text" id="title" name="title" class="large-text" placeholder="e.g. What is a break-even point?" required></td>
				</tr>
				<tr>
					<th><label for="category">Category</label></th>
					<td><input type="text" id="category" name="category" class="regular-text" placeholder="e.g. Cash flow"></td>
				</tr>
				<tr>
					<th><label for="facts">Facts / outline</label></th>
					<td>
						<textarea id="facts" name="facts" rows="8" class="large-text" style="font-family:monospace;" placeholder="Write what's actually true about this topic in plain notes -- the process, the numbers, the rules -- not finished sentences. Every site writes its own wording from this."></textarea>
						<p class="description">Kept as notes on purpose — each site's write-up should turn these into its own sentences, not reuse this wording.</p>
					</td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" class="button button-primary">Add to Catalog</button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bootg-topics-catalog' ) ); ?>" class="button">Cancel</a>
			</p>
		</form>
	</div>
	<?php
}

function bootg_render_topic_manage_screen() {
	$topic_id = isset( $_GET['topic'] ) ? sanitize_title( wp_unslash( $_GET['topic'] ) ) : '';
	$result   = bootg_topics_github_get_file();
	if ( is_wp_error( $result ) ) {
		echo '<div class="wrap"><h1>Manage Topic</h1><div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div></div>';
		return;
	}

	$topics = $result['topics'];
	$index  = bootg_topics_find( $topics, $topic_id );
	if ( false === $index ) {
		echo '<div class="wrap"><h1>Manage Topic</h1><div class="notice notice-error"><p>That topic wasn\'t found in the catalog — it may have been removed.</p></div></div>';
		return;
	}
	$topic    = $topics[ $index ];
	$statuses = bootg_topic_status_options();
	$imported = bootg_imported_topics();
	$local    = $imported[ $topic_id ] ?? array( 'angle' => '', 'status' => '', 'url' => '', 'draft' => '' );
	$site_label = bootg_this_site_label();

	$others = array();
	foreach ( $topic['usage'] as $u ) {
		if ( $u['site'] !== $site_label ) {
			$others[] = $u['site'] . ( ! empty( $u['angle'] ) ? ' (angle: ' . $u['angle'] . ')' : '' );
		}
	}
	?>
	<div class="wrap">
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=bootg-topics-catalog' ) ); ?>">&larr; Back to Topics Catalog</a></p>
		<h1><?php echo esc_html( $topic['title'] ); ?></h1>
		<?php if ( ! empty( $topic['category'] ) ) : ?>
			<p><span style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.03em;color:#2271b1;background:#f0f6fc;border-radius:999px;padding:3px 10px;"><?php echo esc_html( $topic['category'] ); ?></span></p>
		<?php endif; ?>

		<?php if ( isset( $_GET['saved'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Saved — this site's usage is recorded in the shared catalog, and the full draft is saved locally.</p></div>
		<?php endif; ?>

		<h2>Facts (shared, read-only here — edit from the catalog repo)</h2>
		<p id="bootg-facts-text" style="max-width:700px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;padding:12px 16px;white-space:pre-wrap;"><?php echo esc_html( $topic['facts'] ); ?></p>

		<?php if ( ! empty( $topic['usage'] ) ) : ?>
			<h2>Who else is using this topic</h2>
			<ul style="max-width:700px;">
				<?php foreach ( $topic['usage'] as $u ) : ?>
					<li>
						<strong><?php echo esc_html( $u['site'] ); ?></strong>
						<?php if ( $u['site'] === $site_label ) : ?> <em>(this site)</em><?php endif; ?>
						— <?php echo esc_html( $statuses[ $u['status'] ] ?? 'Not started' ); ?>
						<?php if ( ! empty( $u['angle'] ) ) : ?><br><span style="color:#50575e;">Angle: <?php echo esc_html( $u['angle'] ); ?></span><?php endif; ?>
						<?php if ( ! empty( $u['url'] ) ) : ?> — <a href="<?php echo esc_url( $u['url'] ); ?>" target="_blank" rel="noopener">view</a><?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<h2>This site's usage</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'bootg_save_topic_usage' ); ?>
			<input type="hidden" name="action" value="bootg_save_topic_usage">
			<input type="hidden" name="topic_id" value="<?php echo esc_attr( $topic_id ); ?>">
			<table class="form-table">
				<tr>
					<th>Site</th>
					<td><?php echo esc_html( $site_label ); ?> <span class="description">(from this site's title — change under Settings &rarr; General if needed)</span></td>
				</tr>
				<tr>
					<th><label for="angle">Angle for this site</label></th>
					<td><textarea id="angle" name="angle" rows="3" class="large-text" placeholder="Target reader, tone, structure..."><?php echo esc_textarea( $local['angle'] ); ?></textarea></td>
				</tr>
				<tr>
					<th><label for="status">Status</label></th>
					<td>
						<select id="status" name="status">
							<?php foreach ( $statuses as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $local['status'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="url">Published URL</label></th>
					<td><input type="url" id="url" name="url" value="<?php echo esc_attr( $local['url'] ); ?>" class="regular-text" placeholder="https://..."></td>
				</tr>
				<tr>
					<th><label for="draft">Full draft <span style="font-weight:400;color:#787c82;">(optional, kept local to this site only)</span></label></th>
					<td><textarea id="draft" name="draft" rows="10" class="large-text" placeholder="Paste the finished article here once it's written."><?php echo esc_textarea( $local['draft'] ); ?></textarea></td>
				</tr>
			</table>
			<p>
				<button type="button" class="button" id="bootg-copy-brief">Copy AI brief for this site</button>
				<span id="bootg-brief-copied" style="display:none;margin-left:8px;color:#2271b1;">Copied!</span>
			</p>
			<p class="submit">
				<button type="submit" class="button button-primary">Save</button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bootg-topics-catalog' ) ); ?>" class="button">Cancel</a>
				<?php if ( isset( $imported[ $topic_id ] ) ) : ?>
					<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'bootg_remove_import', 'topic' => $topic_id ), admin_url( 'admin-post.php' ) ), 'bootg_remove_import' ) ); ?>" class="button" style="color:#b32d2e;" onclick="return confirm('Remove this topic from this site\'s imported list? The catalog entry itself is untouched.');">Remove from this site</a>
				<?php endif; ?>
			</p>
		</form>
	</div>
	<script>
	(function () {
		var others = <?php echo wp_json_encode( $others ); ?>;
		document.getElementById('bootg-copy-brief').addEventListener('click', function () {
			var facts = document.getElementById('bootg-facts-text').textContent.trim();
			var thisSite = <?php echo wp_json_encode( $site_label ); ?>;
			var thisAngle = document.getElementById('angle').value.trim() || '(no angle set yet)';

			var brief = 'Write content for "' + thisSite + '" on this topic.\n\n'
				+ 'FACTS (use these, but put them in your own words -- do not copy this phrasing directly):\n' + facts + '\n\n'
				+ 'ANGLE FOR THIS SITE:\n' + thisAngle + '\n\n'
				+ (others.length
					? 'IMPORTANT -- this same topic is already used on: ' + others.join('; ') + '. Write this version with different structure, different examples, and different phrasing throughout -- same underlying facts, genuinely different piece of writing, not a reworded copy.'
					: 'No other site has used this topic yet -- write it naturally; later versions for other sites will be asked to differ from this one.');

			var textarea = document.createElement('textarea');
			textarea.value = brief;
			textarea.style.position = 'fixed';
			textarea.style.opacity = '0';
			document.body.appendChild(textarea);
			textarea.select();
			try { navigator.clipboard.writeText(brief); } catch (err) { document.execCommand('copy'); }
			document.body.removeChild(textarea);

			var flag = document.getElementById('bootg-brief-copied');
			flag.style.display = 'inline';
			setTimeout(function () { flag.style.display = 'none'; }, 2000);
		});
	})();
	</script>
	<?php
}
