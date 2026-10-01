<?php
/**
 * Topics Catalog — a shared content-planning library in the same spirit as
 * a syndicated blog library (BizPress Blogs etc.): a searchable, filterable,
 * paginated grid of topics, each showing which site(s) have used it. Each
 * Topic stores the shared FACTS once (what's true, not how it's phrased),
 * a category, and a "usage" row per site that used it: which site, the
 * angle/voice it was written in, status, published URL, and optionally the
 * full finished draft for that site (so a topic's card can double as the
 * record of what was actually published, not just a planning note). A
 * "Copy AI brief" button assembles the facts + that site's angle + an
 * explicit instruction to differ from every other site's angle into one
 * ready-to-paste prompt — so the next write-up is a deliberate paraphrase,
 * not a copy with a few words changed.
 *
 * Storage: a single small JSON file shipped inside the plugin itself
 * (data/topics-catalog.json) — not WordPress posts/postmeta. That keeps
 * it out of the database (nothing to migrate, export, or bloat per site)
 * and, because it travels with the plugin's own git history, pushing a
 * plugin update to a second site running Weavit Engine carries the same
 * catalog across with it — a lightweight, version-controlled shared
 * library rather than a live database sync between two separate sites.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BOOTG_TOPICS_FILE', WEAVIT_ENGINE_DIR . 'data/topics-catalog.json' );

add_action( 'admin_menu', function () {
	add_menu_page(
		'Topics Catalog',
		'Topics Catalog',
		'edit_posts',
		'bootg-topics-catalog',
		'bootg_render_topics_catalog_page',
		'dashicons-networking',
		26
	);
} );

function bootg_topic_status_options() {
	return array(
		''          => 'Not started',
		'drafted'   => 'Drafted',
		'published' => 'Published',
	);
}

/** Reads the catalog, tolerating a missing/corrupt file rather than fataling. */
function bootg_topics_catalog_read() {
	if ( ! file_exists( BOOTG_TOPICS_FILE ) ) {
		return array( 'topics' => array() );
	}
	$raw  = file_get_contents( BOOTG_TOPICS_FILE ); // phpcs:ignore
	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) || ! isset( $data['topics'] ) || ! is_array( $data['topics'] ) ) {
		return array( 'topics' => array() );
	}
	return $data;
}

function bootg_topics_catalog_write( $data ) {
	$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	return false !== file_put_contents( BOOTG_TOPICS_FILE, $json ); // phpcs:ignore
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

/* ---------------------------------------------------------------------
 * Form handling — plain admin-post.php POSTs, same pattern as the rest
 * of this plugin's admin tools. All writes go back to the JSON file.
 * ------------------------------------------------------------------- */

add_action( 'admin_post_bootg_save_topic', function () {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'bootg_save_topic' );

	$data   = bootg_topics_catalog_read();
	$topics = $data['topics'];

	$id       = isset( $_POST['topic_id'] ) ? sanitize_title( wp_unslash( $_POST['topic_id'] ) ) : '';
	$title    = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
	$category = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
	$facts    = isset( $_POST['facts'] ) ? sanitize_textarea_field( wp_unslash( $_POST['facts'] ) ) : '';

	$usage = array();
	if ( ! empty( $_POST['usage_site'] ) && is_array( $_POST['usage_site'] ) ) {
		$sites    = wp_unslash( $_POST['usage_site'] );
		$angles   = wp_unslash( $_POST['usage_angle'] ?? array() );
		$statuses = wp_unslash( $_POST['usage_status'] ?? array() );
		$urls     = wp_unslash( $_POST['usage_url'] ?? array() );
		$drafts   = wp_unslash( $_POST['usage_draft'] ?? array() );
		foreach ( $sites as $i => $site_label ) {
			$site_label = sanitize_text_field( $site_label );
			if ( '' === $site_label ) {
				continue; // Skip empty rows (e.g. the blank template row if left unfilled).
			}
			$usage[] = array(
				'site'   => $site_label,
				'angle'  => sanitize_textarea_field( $angles[ $i ] ?? '' ),
				'status' => in_array( $statuses[ $i ] ?? '', array_keys( bootg_topic_status_options() ), true ) ? $statuses[ $i ] : '',
				'url'    => esc_url_raw( $urls[ $i ] ?? '' ),
				'draft'  => sanitize_textarea_field( $drafts[ $i ] ?? '' ),
			);
		}
	}

	$existing_ids = wp_list_pluck( $topics, 'id' );
	$is_new       = ( '' === $id || false === bootg_topics_find( $topics, $id ) );

	if ( $is_new ) {
		$id      = bootg_topic_slug( $title ?: 'topic', $existing_ids );
		$topics[] = array(
			'id'       => $id,
			'title'    => $title,
			'category' => $category,
			'facts'    => $facts,
			'usage'    => $usage,
		);
	} else {
		$index                    = bootg_topics_find( $topics, $id );
		$topics[ $index ]['title']    = $title;
		$topics[ $index ]['category'] = $category;
		$topics[ $index ]['facts']    = $facts;
		$topics[ $index ]['usage']    = $usage;
	}

	$data['topics'] = $topics;
	bootg_topics_catalog_write( $data );

	wp_safe_redirect( add_query_arg(
		array( 'page' => 'bootg-topics-catalog', 'action' => 'edit', 'topic' => $id, 'saved' => 1 ),
		admin_url( 'admin.php' )
	) );
	exit;
} );

add_action( 'admin_post_bootg_delete_topic', function () {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'bootg_delete_topic' );

	$id     = isset( $_GET['topic'] ) ? sanitize_title( wp_unslash( $_GET['topic'] ) ) : '';
	$data   = bootg_topics_catalog_read();
	$topics = $data['topics'];
	$index  = bootg_topics_find( $topics, $id );
	if ( false !== $index ) {
		array_splice( $topics, $index, 1 );
		$data['topics'] = $topics;
		bootg_topics_catalog_write( $data );
	}

	wp_safe_redirect( add_query_arg( array( 'page' => 'bootg-topics-catalog', 'deleted' => 1 ), admin_url( 'admin.php' ) ) );
	exit;
} );

/* ---------------------------------------------------------------------
 * Admin screens
 * ------------------------------------------------------------------- */

function bootg_render_topics_catalog_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';
	if ( 'edit' === $action || 'new' === $action ) {
		bootg_render_topic_edit_screen();
		return;
	}
	bootg_render_topics_list_screen();
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

function bootg_render_topics_list_screen() {
	$data      = bootg_topics_catalog_read();
	$topics    = $data['topics'];
	$statuses  = bootg_topic_status_options();
	$per_page  = 12;

	$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$cat    = isset( $_GET['cat'] ) ? sanitize_text_field( wp_unslash( $_GET['cat'] ) ) : '';
	$paged  = max( 1, (int) ( $_GET['paged'] ?? 1 ) );

	$filtered = array_filter( $topics, function ( $topic ) use ( $search, $cat ) {
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
	} );
	$filtered   = array_values( $filtered );
	$total      = count( $filtered );
	$total_pages = max( 1, (int) ceil( $total / $per_page ) );
	$paged      = min( $paged, $total_pages );
	$page_items = array_slice( $filtered, ( $paged - 1 ) * $per_page, $per_page );
	$categories = bootg_topics_catalog_categories( $topics );
	$base_url   = admin_url( 'admin.php?page=bootg-topics-catalog' );
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline">Topics Catalog</h1>
		<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'bootg-topics-catalog', 'action' => 'new' ), admin_url( 'admin.php' ) ) ); ?>" class="page-title-action">Add New Topic</a>
		<hr class="wp-header-end">

		<?php if ( isset( $_GET['deleted'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Topic deleted.</p></div>
		<?php endif; ?>

		<p class="description" style="max-width:760px;">One card per topic. "Facts" are the shared source of truth; each site's own write-up should be its own wording, not a copy — open a topic and use "Copy AI brief" on a site's row to get a prompt that explicitly asks for different phrasing, structure, and examples than the other site(s) already using this topic.</p>

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
		</form>

		<?php if ( empty( $topics ) ) : ?>
			<p>No topics yet. <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'bootg-topics-catalog', 'action' => 'new' ), admin_url( 'admin.php' ) ) ); ?>">Add your first one</a>.</p>
		<?php elseif ( empty( $page_items ) ) : ?>
			<p>No topics match that search. <a href="<?php echo esc_url( $base_url ); ?>">Clear filters</a>.</p>
		<?php else : ?>
			<p class="description"><?php echo esc_html( $total ); ?> topic<?php echo 1 === $total ? '' : 's'; ?><?php echo $search || $cat ? ' matching your filters' : ''; ?>.</p>

			<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;margin:16px 0;">
				<?php foreach ( $page_items as $topic ) : ?>
					<div style="border:1px solid #dcdcde;border-radius:6px;background:#fff;padding:16px;display:flex;flex-direction:column;">
						<?php if ( ! empty( $topic['category'] ) ) : ?>
							<span style="align-self:flex-start;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.03em;color:#2271b1;background:#f0f6fc;border-radius:999px;padding:3px 10px;margin-bottom:8px;"><?php echo esc_html( $topic['category'] ); ?></span>
						<?php endif; ?>
						<strong style="font-size:15px;margin-bottom:6px;">
							<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'bootg-topics-catalog', 'action' => 'edit', 'topic' => $topic['id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $topic['title'] ?: '(untitled)' ); ?></a>
						</strong>
						<p style="color:#50575e;font-size:13px;line-height:1.5;flex-grow:1;margin:0 0 10px;"><?php echo esc_html( wp_trim_words( $topic['facts'] ?? '', 20 ) ); ?></p>
						<div style="font-size:12px;color:#50575e;">
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

function bootg_render_topic_edit_screen() {
	$data   = bootg_topics_catalog_read();
	$topics = $data['topics'];
	$id     = isset( $_GET['topic'] ) ? sanitize_title( wp_unslash( $_GET['topic'] ) ) : '';
	$index  = $id ? bootg_topics_find( $topics, $id ) : false;
	$topic  = false !== $index ? $topics[ $index ] : array( 'id' => '', 'title' => '', 'category' => '', 'facts' => '', 'usage' => array() );

	// Always keep at least one usage row in the form, plus one blank template row for adding another.
	$usage_rows = $topic['usage'];
	$statuses   = bootg_topic_status_options();
	?>
	<div class="wrap">
		<h1><?php echo $topic['id'] ? 'Edit Topic' : 'Add New Topic'; ?></h1>

		<?php if ( isset( $_GET['saved'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Saved.</p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'bootg_save_topic' ); ?>
			<input type="hidden" name="action" value="bootg_save_topic">
			<input type="hidden" name="topic_id" value="<?php echo esc_attr( $topic['id'] ); ?>">

			<table class="form-table">
				<tr>
					<th><label for="title">Title</label></th>
					<td><input type="text" id="title" name="title" value="<?php echo esc_attr( $topic['title'] ); ?>" class="large-text" placeholder="e.g. What is a break-even point?" required></td>
				</tr>
				<tr>
					<th><label for="category">Category</label></th>
					<td>
						<input type="text" id="category" name="category" value="<?php echo esc_attr( $topic['category'] ?? '' ); ?>" class="regular-text" list="bootg-topic-categories" placeholder="e.g. Cash flow">
						<datalist id="bootg-topic-categories">
							<?php foreach ( bootg_topics_catalog_categories( $topics ) as $c ) : ?>
								<option value="<?php echo esc_attr( $c ); ?>">
							<?php endforeach; ?>
						</datalist>
						<p class="description">Free text — type an existing category or a new one. Used for the filter dropdown on the catalog list.</p>
					</td>
				</tr>
				<tr>
					<th><label for="facts">Facts / outline</label></th>
					<td>
						<textarea id="facts" name="facts" rows="8" class="large-text" style="font-family:monospace;" placeholder="Write what's actually true about this topic in plain notes -- the process, the numbers, the rules -- not finished sentences. Every site writes its own wording from this."><?php echo esc_textarea( $topic['facts'] ); ?></textarea>
						<p class="description">Kept as notes on purpose — each site's write-up should turn these into its own sentences, not reuse this wording.</p>
					</td>
				</tr>
			</table>

			<h2>Sites using this topic</h2>
			<div id="bootg-usage-rows">
				<?php foreach ( $usage_rows as $i => $u ) : ?>
					<?php bootg_render_usage_row( $i, $u, $statuses ); ?>
				<?php endforeach; ?>
			</div>
			<p><button type="button" class="button" id="bootg-add-usage-row">Add another site</button></p>

			<p class="submit">
				<button type="submit" class="button button-primary">Save Topic</button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bootg-topics-catalog' ) ); ?>" class="button">Cancel</a>
				<?php if ( $topic['id'] ) : ?>
					<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'bootg_delete_topic', 'topic' => $topic['id'] ), admin_url( 'admin-post.php' ) ), 'bootg_delete_topic' ) ); ?>" class="button" style="color:#b32d2e;" onclick="return confirm('Delete this topic? This cannot be undone.');">Delete topic</a>
				<?php endif; ?>
			</p>
		</form>
	</div>

	<template id="bootg-usage-row-template">
		<?php bootg_render_usage_row( '__INDEX__', array( 'site' => '', 'angle' => '', 'status' => '', 'url' => '', 'draft' => '' ), $statuses, true ); ?>
	</template>

	<script>
	(function () {
		var wrap = document.getElementById('bootg-usage-rows');
		var addBtn = document.getElementById('bootg-add-usage-row');
		var template = document.getElementById('bootg-usage-row-template');
		var nextIndex = <?php echo (int) count( $usage_rows ); ?>;

		addBtn.addEventListener('click', function () {
			var html = template.innerHTML.replace(/__INDEX__/g, nextIndex);
			var div = document.createElement('div');
			div.innerHTML = html;
			wrap.appendChild(div.firstElementChild);
			nextIndex++;
		});

		wrap.addEventListener('click', function (e) {
			if (e.target.matches('[data-remove-row]')) {
				e.target.closest('.bootg-usage-row').remove();
			}
			if (e.target.matches('[data-copy-brief]')) {
				bootgCopyBrief(e.target.closest('.bootg-usage-row'));
			}
		});

		function bootgCopyBrief(row) {
			var facts = document.getElementById('facts').value.trim();
			var thisSite = row.querySelector('[data-field="site"]').value.trim() || 'this site';
			var thisAngle = row.querySelector('[data-field="angle"]').value.trim() || '(no angle set yet)';

			var others = [];
			wrap.querySelectorAll('.bootg-usage-row').forEach(function (r) {
				if (r === row) { return; }
				var site = r.querySelector('[data-field="site"]').value.trim();
				var angle = r.querySelector('[data-field="angle"]').value.trim();
				if (site) { others.push(site + (angle ? ' (angle: ' + angle + ')' : '')); }
			});

			var brief = 'Write content for "' + thisSite + '" on this topic.\n\n'
				+ 'FACTS (use these, but put them in your own words -- do not copy this phrasing directly):\n' + facts + '\n\n'
				+ 'ANGLE FOR THIS SITE:\n' + thisAngle + '\n\n'
				+ (others.length
					? 'IMPORTANT -- this same topic is already used on: ' + others.join('; ') + '. Write this version with different structure, different examples, and different phrasing throughout -- same underlying facts, genuinely different piece of writing, not a reworded copy.'
					: 'This is the first site using this topic -- write it naturally; later versions for other sites will be asked to differ from this one.');

			var textarea = document.createElement('textarea');
			textarea.value = brief;
			textarea.style.position = 'fixed';
			textarea.style.opacity = '0';
			document.body.appendChild(textarea);
			textarea.select();
			try {
				navigator.clipboard.writeText(brief);
			} catch (err) {
				document.execCommand('copy');
			}
			document.body.removeChild(textarea);

			var flag = row.querySelector('[data-copied-flag]');
			if (flag) {
				flag.style.display = 'inline';
				setTimeout(function () { flag.style.display = 'none'; }, 2000);
			}
		}
	})();
	</script>
	<?php
}

function bootg_render_usage_row( $i, $u, $statuses, $is_template = false ) {
	?>
	<div class="bootg-usage-row" style="border:1px solid #dcdcde;border-radius:4px;padding:12px 16px;margin-bottom:12px;background:#fff;max-width:720px;">
		<table class="form-table" style="margin:0;">
			<tr>
				<th style="width:140px;">Site name</th>
				<td><input type="text" name="usage_site[<?php echo esc_attr( $i ); ?>]" data-field="site" value="<?php echo esc_attr( $u['site'] ); ?>" class="regular-text" placeholder="e.g. Bookkeeping On The Go"></td>
			</tr>
			<tr>
				<th>Angle</th>
				<td><textarea name="usage_angle[<?php echo esc_attr( $i ); ?>]" data-field="angle" rows="2" class="large-text" placeholder="Target reader, tone, structure..."><?php echo esc_textarea( $u['angle'] ); ?></textarea></td>
			</tr>
			<tr>
				<th>Status</th>
				<td>
					<select name="usage_status[<?php echo esc_attr( $i ); ?>]">
						<?php foreach ( $statuses as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $u['status'], $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th>Published URL</th>
				<td><input type="url" name="usage_url[<?php echo esc_attr( $i ); ?>]" value="<?php echo esc_attr( $u['url'] ); ?>" class="regular-text" placeholder="https://..."></td>
			</tr>
			<tr>
				<th>Full draft <span style="font-weight:400;color:#787c82;">(optional)</span></th>
				<td>
					<textarea name="usage_draft[<?php echo esc_attr( $i ); ?>]" rows="6" class="large-text" placeholder="Paste the finished article for this site here once it's written, so this card becomes the record of what was actually published."><?php echo esc_textarea( $u['draft'] ?? '' ); ?></textarea>
				</td>
			</tr>
		</table>
		<p style="margin:10px 0 0;">
			<button type="button" class="button" data-copy-brief>Copy AI brief for this site</button>
			<span data-copied-flag style="display:none;margin-left:8px;color:#2271b1;">Copied!</span>
			<button type="button" class="button-link" style="color:#b32d2e;margin-left:12px;" data-remove-row>Remove this row</button>
		</p>
	</div>
	<?php
}
