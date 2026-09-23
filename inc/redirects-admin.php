<?php
/**
 * Admin UI for Redirects — the "Redirects" tab on the SEO menu
 * (inc/seo-tools.php). List view + Add/Edit view, list view built on
 * Bootg_Redirects_Table (inc/class-bootg-redirects-table.php).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-bootg-redirects-table.php';

function bootg_render_redirects_tab() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$view = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( isset( $_GET['bootg_redirect_notice'] ) ) {
		$messages = array(
			'saved'      => 'Redirect saved.',
			'activated'  => 'Redirect activated.',
			'deactivated' => 'Redirect deactivated.',
			'trashed'    => 'Redirect moved to Trash.',
			'restored'   => 'Redirect restored.',
			'deleted'    => 'Redirect permanently deleted.',
			'bulk'       => 'Redirects updated.',
		);
		$key = sanitize_key( $_GET['bootg_redirect_notice'] );
		if ( isset( $messages[ $key ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $key ] ) . '</p></div>';
		}
	}

	if ( 'edit' === $view ) {
		bootg_render_redirect_form( isset( $_GET['redirect_id'] ) ? absint( $_GET['redirect_id'] ) : 0 );
		return;
	}

	bootg_render_redirects_list();
}

function bootg_render_redirects_list() {
	$table = new Bootg_Redirects_Table();
	$table->prepare_items();
	$add_url = add_query_arg( array( 'view' => 'edit', 'redirect_id' => 0 ) );
	?>
	<a href="<?php echo esc_url( $add_url ); ?>" class="page-title-action">Add New</a>
	<?php $table->views(); ?>
	<?php
	// The form posts to a URL that already carries page/tab as real query
	// params (not just hidden fields) — WP's own admin routing reads
	// $_GET['page'] specifically, so a bare admin.php action with page only
	// in the POST body fails to route here at all.
	$form_action = add_query_arg( array( 'page' => 'bootg-seo', 'tab' => 'redirects' ), admin_url( 'admin.php' ) );
	if ( isset( $_GET['redirect_status'] ) ) {
		$form_action = add_query_arg( 'redirect_status', sanitize_key( $_GET['redirect_status'] ), $form_action );
	}
	?>
	<form method="post" action="<?php echo esc_url( $form_action ); ?>">
		<?php $table->search_box( 'Search redirects', 'bootg-redirect-search' ); ?>
		<?php $table->display(); ?>
	</form>
	<?php
}

/**
 * Processes the list table's bulk-action dropdown submission (a plain POST
 * back to this same admin page — the table's own "action"/"action2" select
 * fields can't route through admin-post.php without colliding, so this
 * follows the same pattern WP core's own edit.php uses: handle it on the
 * load-{hook} action, before any output, then redirect.
 */
function bootg_handle_redirects_bulk_action_early() {
	if ( ! isset( $_REQUEST['tab'] ) || 'redirects' !== $_REQUEST['tab'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$table  = new Bootg_Redirects_Table();
	$action = $table->current_action();
	if ( ! $action ) {
		return;
	}
	check_admin_referer( 'bulk-redirects' );

	$ids = array_map( 'absint', (array) ( $_POST['redirect_ids'] ?? array() ) );
	foreach ( $ids as $id ) {
		switch ( $action ) {
			case 'activate':
				wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
				break;
			case 'deactivate':
				wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
				break;
			case 'trash':
				wp_trash_post( $id );
				break;
			case 'restore':
				wp_untrash_post( $id );
				break;
			case 'delete':
				wp_delete_post( $id, true );
				break;
		}
	}

	wp_safe_redirect( add_query_arg(
		array( 'page' => 'bootg-seo', 'tab' => 'redirects', 'bootg_redirect_notice' => 'bulk' ),
		admin_url( 'admin.php' )
	) );
	exit;
}

function bootg_render_redirect_form( $redirect_id ) {
	$is_edit  = $redirect_id > 0;
	$redirect = $is_edit ? bootg_get_redirect( $redirect_id ) : null;
	$sources  = $redirect ? $redirect['sources'] : array( array( 'url' => '', 'match' => 'exact' ) );
	if ( ! $sources ) {
		$sources = array( array( 'url' => '', 'match' => 'exact' ) );
	}
	$destination = $redirect['destination'] ?? '';
	$type        = $redirect['type'] ?? 301;
	$status      = $redirect['status'] ?? 'publish';
	$list_url    = remove_query_arg( array( 'view', 'redirect_id' ) );
	?>
	<h2><?php echo $is_edit ? 'Edit Redirection' : 'Add Redirection'; ?></h2>
	<a href="<?php echo esc_url( $list_url ); ?>" class="page-title-action" style="margin-bottom:16px;display:inline-block;">&larr; All Redirects</a>

	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="bootg-redirect-form">
		<?php wp_nonce_field( 'bootg_save_redirect' ); ?>
		<input type="hidden" name="action" value="bootg_save_redirect">
		<input type="hidden" name="redirect_id" value="<?php echo esc_attr( $redirect_id ); ?>">

		<table class="form-table" role="presentation">
			<tr>
				<th>Source URLs</th>
				<td>
					<div id="bootg-redirect-sources">
						<?php foreach ( $sources as $i => $s ) : ?>
							<div class="bootg-redirect-source-row" style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
								<input type="text" name="sources[<?php echo (int) $i; ?>][url]" value="<?php echo esc_attr( $s['url'] ); ?>" class="regular-text" placeholder="/old-path">
								<select name="sources[<?php echo (int) $i; ?>][match]">
									<option value="exact" <?php selected( $s['match'], 'exact' ); ?>>Exact</option>
									<option value="contains" <?php selected( $s['match'], 'contains' ); ?>>Contains</option>
								</select>
								<a href="#" class="bootg-remove-source" style="color:#b32d2e;">Remove</a>
							</div>
						<?php endforeach; ?>
					</div>
					<button type="button" class="button" id="bootg-add-source">Add another</button>
				</td>
			</tr>
			<tr>
				<th><label for="bootg-destination">Destination URL</label></th>
				<td><input type="text" id="bootg-destination" name="destination" value="<?php echo esc_attr( $destination ); ?>" class="regular-text" placeholder="/new-path or https://example.com/"></td>
			</tr>
			<tr>
				<th>Redirection Type</th>
				<td>
					<div class="bootg-btn-group" data-name="type">
						<?php foreach ( bootg_redirect_types() as $code => $label ) : ?>
							<label class="button <?php echo (int) $type === $code ? 'button-primary' : ''; ?>">
								<input type="radio" name="type" value="<?php echo (int) $code; ?>" <?php checked( (int) $type, $code ); ?> style="display:none;">
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
					</div>
				</td>
			</tr>
			<tr>
				<th>Status</th>
				<td>
					<div class="bootg-btn-group" data-name="status">
						<label class="button <?php echo 'publish' === $status ? 'button-primary' : ''; ?>">
							<input type="radio" name="status" value="publish" <?php checked( $status, 'publish' ); ?> style="display:none;">
							Activate
						</label>
						<label class="button <?php echo 'draft' === $status ? 'button-primary' : ''; ?>">
							<input type="radio" name="status" value="draft" <?php checked( $status, 'draft' ); ?> style="display:none;">
							Deactivate
						</label>
					</div>
				</td>
			</tr>
		</table>

		<?php submit_button( $is_edit ? 'Save Redirect' : 'Add Redirection' ); ?>
	</form>

	<script>
	(function () {
		var wrap = document.getElementById('bootg-redirect-sources');
		document.getElementById('bootg-add-source').addEventListener('click', function () {
			var i = wrap.children.length;
			var row = document.createElement('div');
			row.className = 'bootg-redirect-source-row';
			row.style.cssText = 'display:flex;gap:8px;align-items:center;margin-bottom:8px;';
			row.innerHTML = '<input type="text" name="sources[' + i + '][url]" class="regular-text" placeholder="/old-path">'
				+ '<select name="sources[' + i + '][match]"><option value="exact">Exact</option><option value="contains">Contains</option></select>'
				+ '<a href="#" class="bootg-remove-source" style="color:#b32d2e;">Remove</a>';
			wrap.appendChild(row);
		});
		wrap.addEventListener('click', function (e) {
			if (e.target.classList.contains('bootg-remove-source')) {
				e.preventDefault();
				if (wrap.children.length > 1) { e.target.closest('.bootg-redirect-source-row').remove(); }
			}
		});
		document.querySelectorAll('.bootg-btn-group').forEach(function (group) {
			group.addEventListener('click', function (e) {
				var label = e.target.closest('label.button');
				if (!label) { return; }
				group.querySelectorAll('label.button').forEach(function (l) { l.classList.remove('button-primary'); });
				label.classList.add('button-primary');
			});
		});
	})();
	</script>
	<?php
}

/* ---------------------------------------------------------------------
 * Handlers
 * ------------------------------------------------------------------- */

add_action( 'admin_post_bootg_save_redirect', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'bootg_save_redirect' );

	$redirect_id = absint( $_POST['redirect_id'] ?? 0 );
	$status      = 'draft' === ( $_POST['status'] ?? 'publish' ) ? 'draft' : 'publish';

	if ( $redirect_id ) {
		wp_update_post( array( 'ID' => $redirect_id, 'post_status' => $status ) );
	} else {
		$redirect_id = wp_insert_post( array(
			'post_type'   => 'bootg_redirect',
			'post_status' => $status,
			'post_title'  => 'Untitled redirect',
		), true );
		if ( is_wp_error( $redirect_id ) ) {
			wp_die( 'Could not create redirect.' );
		}
	}

	$sources = array();
	foreach ( (array) ( $_POST['sources'] ?? array() ) as $s ) {
		$sources[] = array(
			'url'   => sanitize_text_field( wp_unslash( $s['url'] ?? '' ) ),
			'match' => sanitize_key( $s['match'] ?? 'exact' ),
		);
	}

	bootg_save_redirect( $redirect_id, array(
		'sources'     => $sources,
		'destination' => sanitize_text_field( wp_unslash( $_POST['destination'] ?? '' ) ),
		'type'        => absint( $_POST['type'] ?? 301 ),
	) );

	wp_safe_redirect( add_query_arg(
		array( 'page' => 'bootg-seo', 'tab' => 'redirects', 'bootg_redirect_notice' => 'saved' ),
		admin_url( 'admin.php' )
	) );
	exit;
} );

add_action( 'admin_post_bootg_redirect_row_action', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	$redirect_id = absint( $_GET['redirect_id'] ?? 0 );
	check_admin_referer( 'bootg_redirect_row_action_' . $redirect_id );

	$action  = sanitize_key( $_GET['row_action'] ?? '' );
	$notices = array( 'activate' => 'activated', 'deactivate' => 'deactivated', 'trash' => 'trashed', 'restore' => 'restored', 'delete' => 'deleted' );
	switch ( $action ) {
		case 'activate':
			wp_update_post( array( 'ID' => $redirect_id, 'post_status' => 'publish' ) );
			break;
		case 'deactivate':
			wp_update_post( array( 'ID' => $redirect_id, 'post_status' => 'draft' ) );
			break;
		case 'trash':
			wp_trash_post( $redirect_id );
			break;
		case 'restore':
			wp_untrash_post( $redirect_id );
			break;
		case 'delete':
			wp_delete_post( $redirect_id, true );
			break;
	}

	$back = wp_get_referer() ?: admin_url( 'admin.php?page=bootg-seo&tab=redirects' );
	wp_safe_redirect( add_query_arg( 'bootg_redirect_notice', $notices[ $action ] ?? 'bulk', $back ) );
	exit;
} );
