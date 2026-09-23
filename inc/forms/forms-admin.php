<?php
/**
 * Forms engine — admin UI. A top-level "Forms" menu (positioned directly
 * below SMTP) with three screens: All Forms (list), a drag-and-drop field
 * builder, and per-form Entries. Custom UI throughout — the two CPTs are
 * deliberately hidden from the default WP post-list screens.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_menu_page(
		'Forms',
		'Forms',
		'manage_options',
		'bootg-forms',
		'bootg_render_forms_list_page',
		'dashicons-feedback',
		'58.7' // Between SMTP (58) and Appearance (60). String, not float — PHP truncates float array keys to int.
	);
	add_submenu_page( 'bootg-forms', 'All Forms', 'All Forms', 'manage_options', 'bootg-forms', 'bootg_render_forms_list_page' );
	add_submenu_page( 'bootg-forms', 'Add New', 'Add New', 'manage_options', 'bootg-form-builder', 'bootg_render_form_builder_page' );
	add_submenu_page( 'bootg-forms', 'Entries', 'Entries', 'manage_options', 'bootg-form-entries', 'bootg_render_form_entries_page' );
} );

add_action( 'admin_enqueue_scripts', function () {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	if ( strpos( $page, 'bootg-form' ) !== 0 ) {
		return;
	}
	wp_enqueue_style( 'bootg-forms-admin', WEAVIT_ENGINE_URI . '/assets/css/forms-admin.css', array(), WEAVIT_ENGINE_VERSION );
	if ( 'bootg-form-builder' === $page ) {
		wp_enqueue_script( 'bootg-forms-admin', WEAVIT_ENGINE_URI . '/assets/js/forms-admin.js', array( 'jquery', 'jquery-ui-sortable' ), WEAVIT_ENGINE_VERSION, true );
		wp_localize_script( 'bootg-forms-admin', 'bootgFormFieldTypes', bootg_form_field_types() );
	}
} );

/* ---------------------------------------------------------------------
 * All Forms
 * ------------------------------------------------------------------- */

function bootg_render_forms_list_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	$forms = bootg_get_all_forms();
	?>
	<div class="wrap bootg-forms-wrap">
		<div class="bootg-forms-header">
			<h1>Forms</h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=bootg-form-builder' ) ); ?>" class="bootg-btn bootg-btn-primary">+ Add New</a>
		</div>

		<?php if ( isset( $_GET['bootg_deleted'] ) ) : ?>
			<div class="bootg-notice bootg-notice-success">Form deleted.</div>
		<?php endif; ?>

		<?php if ( ! $forms ) : ?>
			<div class="bootg-empty-state">
				<p>No forms yet. Build your first form — contact forms, signups, anything — with a drag-and-drop field builder, and drop it anywhere with a shortcode.</p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bootg-form-builder' ) ); ?>" class="bootg-btn bootg-btn-primary">+ Add New Form</a>
			</div>
		<?php else : ?>
			<div class="bootg-card-grid">
				<?php foreach ( $forms as $form ) :
					$field_count = count( bootg_get_form_schema( $form->ID ) );
					$entry_count = bootg_count_form_entries( $form->ID );
					$tag         = bootg_form_shortcode_tag( $form->ID, $form->post_title );
					?>
					<div class="bootg-form-card">
						<div class="bootg-form-card-body">
							<h2><a href="<?php echo esc_url( admin_url( 'admin.php?page=bootg-form-builder&form_id=' . $form->ID ) ); ?>"><?php echo esc_html( $form->post_title ); ?></a></h2>
							<p class="bootg-form-card-meta"><?php echo esc_html( $field_count ); ?> field<?php echo 1 === $field_count ? '' : 's'; ?> &middot; <?php echo esc_html( $entry_count ); ?> entr<?php echo 1 === $entry_count ? 'y' : 'ies'; ?></p>
							<code class="bootg-shortcode" title="Click to copy" data-shortcode="[<?php echo esc_attr( $tag ); ?>]">[<?php echo esc_html( $tag ); ?>]</code>
						</div>
						<div class="bootg-form-card-actions">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=bootg-form-builder&form_id=' . $form->ID ) ); ?>" class="bootg-btn bootg-btn-ghost">Edit</a>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=bootg-form-entries&form_id=' . $form->ID ) ); ?>" class="bootg-btn bootg-btn-ghost">Entries (<?php echo esc_html( $entry_count ); ?>)</a>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bootg_delete_form&form_id=' . $form->ID ), 'bootg_delete_form_' . $form->ID ) ); ?>" class="bootg-btn bootg-btn-ghost bootg-btn-danger" onclick="return confirm('Delete this form and all of its entries? This cannot be undone.');">Delete</a>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<script>
	document.querySelectorAll('.bootg-shortcode').forEach(function (el) {
		el.addEventListener('click', function () {
			navigator.clipboard.writeText(el.getAttribute('data-shortcode')).then(function () {
				var prev = el.textContent;
				el.textContent = 'Copied!';
				setTimeout(function () { el.textContent = prev; }, 1200);
			});
		});
	});
	</script>
	<?php
}

add_action( 'admin_post_bootg_delete_form', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	$form_id = absint( $_GET['form_id'] ?? 0 );
	check_admin_referer( 'bootg_delete_form_' . $form_id );

	$entries = bootg_get_form_entries( $form_id, array( 'posts_per_page' => -1, 'fields' => 'ids' ) );
	foreach ( $entries->posts as $entry_id ) {
		wp_delete_post( $entry_id, true );
	}
	wp_delete_post( $form_id, true );

	wp_safe_redirect( add_query_arg( array( 'page' => 'bootg-forms', 'bootg_deleted' => 1 ), admin_url( 'admin.php' ) ) );
	exit;
} );

/* ---------------------------------------------------------------------
 * Builder (Add New / Edit)
 * ------------------------------------------------------------------- */

function bootg_render_form_builder_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	$form_id  = absint( $_GET['form_id'] ?? 0 );
	$form     = $form_id ? get_post( $form_id ) : null;
	$is_edit  = $form && 'bootg_form' === $form->post_type;
	$title    = $is_edit ? $form->post_title : '';
	$fields   = $is_edit ? bootg_get_form_schema( $form_id ) : array();
	$settings = $is_edit ? bootg_get_form_settings( $form_id ) : bootg_get_form_settings( 0 );
	$tag      = $is_edit ? bootg_form_shortcode_tag( $form_id, $title ) : '';
	?>
	<div class="wrap bootg-forms-wrap">
		<div class="bootg-forms-header">
			<h1><?php echo $is_edit ? 'Edit Form' : 'Add New Form'; ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=bootg-forms' ) ); ?>" class="bootg-btn bootg-btn-ghost">&larr; All Forms</a>
		</div>

		<?php if ( isset( $_GET['bootg_saved'] ) ) : ?>
			<div class="bootg-notice bootg-notice-success">Form saved.</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="bootg-form-builder-form">
			<?php wp_nonce_field( 'bootg_save_form' ); ?>
			<input type="hidden" name="action" value="bootg_save_form">
			<input type="hidden" name="form_id" value="<?php echo esc_attr( $form_id ); ?>">
			<input type="hidden" name="schema_json" id="bootg-schema-json" value="">

			<div class="bootg-builder-top">
				<div class="bootg-field-row">
					<label for="bootg-form-title">Form Name</label>
					<input type="text" id="bootg-form-title" name="form_title" value="<?php echo esc_attr( $title ); ?>" placeholder="e.g. Quick Enquiry" required class="bootg-input bootg-input-lg">
				</div>
				<?php if ( $is_edit ) : ?>
					<div class="bootg-field-row">
						<label>Shortcode</label>
						<code class="bootg-shortcode" title="Click to copy" data-shortcode="[<?php echo esc_attr( $tag ); ?>]">[<?php echo esc_html( $tag ); ?>]</code>
					</div>
				<?php else : ?>
					<p class="bootg-hint">Save the form once to generate its shortcode.</p>
				<?php endif; ?>
			</div>

			<div class="bootg-builder-layout">
				<aside class="bootg-builder-palette">
					<h3>Add Field</h3>
					<?php foreach ( bootg_form_field_types() as $type => $label ) : ?>
						<button type="button" class="bootg-palette-btn" data-field-type="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $label ); ?></button>
					<?php endforeach; ?>
				</aside>

				<div class="bootg-builder-canvas">
					<div id="bootg-fields-list" class="bootg-fields-list"></div>
					<p id="bootg-fields-empty" class="bootg-hint" style="display:none;">No fields yet — add one from the panel on the left.</p>
				</div>
			</div>

			<div class="bootg-builder-settings">
				<h3>Notifications</h3>
				<label class="bootg-toggle-row">
					<span class="bootg-toggle">
						<input type="checkbox" name="notify_enabled" value="1" <?php checked( ! empty( $settings['notify_enabled'] ) ); ?>>
						<span class="bootg-toggle-slider"></span>
					</span>
					Email me when this form is submitted
				</label>
				<div class="bootg-field-row">
					<label for="bootg-notify-email">Send to (leave blank to use the site Email in Site Options)</label>
					<input type="email" id="bootg-notify-email" name="notify_email" value="<?php echo esc_attr( $settings['notify_email'] ); ?>" placeholder="you@business.com.au" class="bootg-input">
				</div>
				<div class="bootg-field-row">
					<label for="bootg-success-message">Success message</label>
					<input type="text" id="bootg-success-message" name="success_message" value="<?php echo esc_attr( $settings['success_message'] ); ?>" class="bootg-input">
				</div>
				<div class="bootg-field-row">
					<label for="bootg-submit-label">Submit button text</label>
					<input type="text" id="bootg-submit-label" name="submit_label" value="<?php echo esc_attr( $settings['submit_label'] ); ?>" class="bootg-input">
				</div>
			</div>

			<div class="bootg-builder-footer">
				<button type="submit" class="bootg-btn bootg-btn-primary bootg-btn-lg">Save Form</button>
			</div>
		</form>
	</div>

	<script id="bootg-existing-fields" type="application/json"><?php echo wp_json_encode( $fields ); ?></script>
	<?php
}

add_action( 'admin_post_bootg_save_form', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'bootg_save_form' );

	$form_id = absint( $_POST['form_id'] ?? 0 );
	$title   = sanitize_text_field( wp_unslash( $_POST['form_title'] ?? '' ) ) ?: 'Untitled Form';

	if ( $form_id ) {
		wp_update_post( array( 'ID' => $form_id, 'post_title' => $title ) );
	} else {
		$form_id = wp_insert_post( array(
			'post_type'   => 'bootg_form',
			'post_status' => 'publish',
			'post_title'  => $title,
		), true );
		if ( is_wp_error( $form_id ) ) {
			wp_die( 'Could not create form.' );
		}
	}

	$schema = json_decode( wp_unslash( $_POST['schema_json'] ?? '[]' ), true );
	bootg_save_form_schema( $form_id, is_array( $schema ) ? $schema : array() );

	bootg_save_form_settings( $form_id, array(
		'notify_enabled'  => ! empty( $_POST['notify_enabled'] ),
		'notify_email'    => wp_unslash( $_POST['notify_email'] ?? '' ),
		'success_message' => wp_unslash( $_POST['success_message'] ?? '' ),
		'submit_label'    => wp_unslash( $_POST['submit_label'] ?? '' ),
	) );

	wp_safe_redirect( add_query_arg(
		array( 'page' => 'bootg-form-builder', 'form_id' => $form_id, 'bootg_saved' => 1 ),
		admin_url( 'admin.php' )
	) );
	exit;
} );

/* ---------------------------------------------------------------------
 * Entries
 * ------------------------------------------------------------------- */

function bootg_render_form_entries_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	$form_id  = absint( $_GET['form_id'] ?? 0 );
	$entry_id = absint( $_GET['entry_id'] ?? 0 );

	if ( $entry_id ) {
		bootg_render_entry_detail( $entry_id );
		return;
	}

	if ( $form_id ) {
		bootg_render_entries_list( $form_id );
		return;
	}

	bootg_render_entries_forms_index();
}

function bootg_render_entries_forms_index() {
	$forms = bootg_get_all_forms();
	?>
	<div class="wrap bootg-forms-wrap">
		<div class="bootg-forms-header">
			<h1>Entries</h1>
		</div>
		<?php if ( ! $forms ) : ?>
			<div class="bootg-empty-state"><p>No forms yet — <a href="<?php echo esc_url( admin_url( 'admin.php?page=bootg-form-builder' ) ); ?>">create one</a> first.</p></div>
		<?php else : ?>
			<div class="bootg-card-grid">
				<?php foreach ( $forms as $form ) :
					$entry_count = bootg_count_form_entries( $form->ID );
					?>
					<div class="bootg-form-card">
						<div class="bootg-form-card-body">
							<h2><a href="<?php echo esc_url( admin_url( 'admin.php?page=bootg-form-entries&form_id=' . $form->ID ) ); ?>"><?php echo esc_html( $form->post_title ); ?></a></h2>
							<p class="bootg-form-card-meta"><?php echo esc_html( $entry_count ); ?> entr<?php echo 1 === $entry_count ? 'y' : 'ies'; ?></p>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

function bootg_render_entries_list( $form_id ) {
	$form = get_post( $form_id );
	if ( ! $form || 'bootg_form' !== $form->post_type ) {
		wp_die( 'Form not found.' );
	}
	$paged = max( 1, absint( $_GET['paged'] ?? 1 ) );
	$q     = bootg_get_form_entries( $form_id, array( 'paged' => $paged ) );
	?>
	<div class="wrap bootg-forms-wrap">
		<div class="bootg-forms-header">
			<h1>Entries &mdash; <?php echo esc_html( $form->post_title ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=bootg-form-entries' ) ); ?>" class="bootg-btn bootg-btn-ghost">&larr; All Entries</a>
		</div>

		<?php if ( isset( $_GET['bootg_entry_deleted'] ) ) : ?>
			<div class="bootg-notice bootg-notice-success">Entry deleted.</div>
		<?php endif; ?>

		<?php if ( ! $q->have_posts() ) : ?>
			<div class="bootg-empty-state"><p>No submissions yet for this form.</p></div>
		<?php else : ?>
			<table class="bootg-entries-table">
				<thead>
					<tr><th>Submitted</th><th>Preview</th><th></th></tr>
				</thead>
				<tbody>
					<?php foreach ( $q->posts as $entry ) :
						$data    = bootg_get_entry_data( $entry->ID );
						$preview = $data ? ( $data[0]['value'] ?? '' ) : '';
						?>
						<tr>
							<td><?php echo esc_html( get_the_date( 'M j, Y g:ia', $entry ) ); ?></td>
							<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=bootg-form-entries&form_id=' . $form_id . '&entry_id=' . $entry->ID ) ); ?>"><?php echo esc_html( wp_trim_words( $preview, 8 ) ?: 'View entry' ); ?></a></td>
							<td class="bootg-entries-table-actions">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=bootg-form-entries&form_id=' . $form_id . '&entry_id=' . $entry->ID ) ); ?>" class="bootg-btn bootg-btn-ghost">View</a>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bootg_delete_entry&entry_id=' . $entry->ID . '&form_id=' . $form_id ), 'bootg_delete_entry_' . $entry->ID ) ); ?>" class="bootg-btn bootg-btn-ghost bootg-btn-danger" onclick="return confirm('Delete this entry?');">Delete</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php
			$total_pages = $q->max_num_pages;
			if ( $total_pages > 1 ) :
				?>
				<div class="bootg-pagination">
					<?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
						<a href="<?php echo esc_url( add_query_arg( 'paged', $i ) ); ?>" class="bootg-btn bootg-btn-ghost <?php echo $i === $paged ? 'is-active' : ''; ?>"><?php echo esc_html( $i ); ?></a>
					<?php endfor; ?>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}

function bootg_render_entry_detail( $entry_id ) {
	$entry = get_post( $entry_id );
	if ( ! $entry || 'bootg_form_entry' !== $entry->post_type ) {
		wp_die( 'Entry not found.' );
	}
	$form_id = (int) get_post_meta( $entry_id, '_bootg_entry_form_id', true );
	$form    = get_post( $form_id );
	$data    = bootg_get_entry_data( $entry_id );
	?>
	<div class="wrap bootg-forms-wrap">
		<div class="bootg-forms-header">
			<h1>Entry Detail</h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=bootg-form-entries&form_id=' . $form_id ) ); ?>" class="bootg-btn bootg-btn-ghost">&larr; <?php echo esc_html( $form ? $form->post_title : 'Entries' ); ?></a>
		</div>
		<div class="bootg-entry-detail">
			<p class="bootg-form-card-meta">Submitted <?php echo esc_html( get_the_date( 'F j, Y \a\t g:ia', $entry ) ); ?></p>
			<dl class="bootg-entry-fields">
				<?php foreach ( $data as $row ) : ?>
					<dt><?php echo esc_html( $row['label'] ); ?></dt>
					<dd><?php echo nl2br( esc_html( $row['value'] ) ); // phpcs:ignore ?></dd>
				<?php endforeach; ?>
			</dl>
		</div>
	</div>
	<?php
}

add_action( 'admin_post_bootg_delete_entry', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	$entry_id = absint( $_GET['entry_id'] ?? 0 );
	$form_id  = absint( $_GET['form_id'] ?? 0 );
	check_admin_referer( 'bootg_delete_entry_' . $entry_id );

	wp_delete_post( $entry_id, true );

	wp_safe_redirect( add_query_arg(
		array( 'page' => 'bootg-form-entries', 'form_id' => $form_id, 'bootg_entry_deleted' => 1 ),
		admin_url( 'admin.php' )
	) );
	exit;
} );
