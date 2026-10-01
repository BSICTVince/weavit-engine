<?php
/**
 * The Weavit dashboard shell — one top-level "Weavit" admin menu with
 * Blocksy-style tabs (Home / Modules / Starter Sites), replacing the
 * scattered top-level menus this plugin used to register one per
 * feature (Forms, SMTP, SEO, Topics Catalog, Site Options). Every
 * feature's own screens now live as submenus under this single parent.
 *
 * "Modules" here are feature flags, not yet separately installable
 * plugins — toggling Calculators or Topics Catalog off actually turns
 * their shortcodes/post types/admin screens off (safe: nothing on a
 * live site currently depends on either being silently always-on).
 * Forms, SMTP, and SEO Tools are shown as "Always on" for now rather
 * than real toggles, since this site's published pages already rely on
 * them running — making those safely toggle-off-able (and, eventually,
 * installed on demand from their own repo rather than bundled here) is
 * follow-up work, not done in this pass.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BOOTG_ENABLED_MODULES_OPTION', 'bootg_enabled_modules' );

/**
 * Every module this dashboard knows about. `togglable` controls whether
 * the Modules tab shows a working switch or an "Always on" badge.
 * `industry` groups modules the Home tab's industry toggle switches
 * together in one click.
 */
function bootg_module_registry() {
	return array(
		'service'        => array(
			'label'       => 'Services',
			'description' => 'The Services post type and its archive/single pages.',
			'icon'        => 'dashicons-portfolio',
			'togglable'   => true,
		),
		'integration'    => array(
			'label'       => 'Integrations',
			'description' => 'The Integrations (partner platforms) post type and its archive/single pages.',
			'icon'        => 'dashicons-admin-plugins',
			'togglable'   => true,
		),
		'testimonial'    => array(
			'label'       => 'Testimonials',
			'description' => 'The Testimonials post type and its archive/single pages.',
			'icon'        => 'dashicons-format-quote',
			'togglable'   => true,
		),
		'team_member'    => array(
			'label'       => 'Team Members',
			'description' => 'The Team Members post type and its archive/single pages.',
			'icon'        => 'dashicons-groups',
			'togglable'   => true,
		),
		'guide'          => array(
			'label'       => 'Guides',
			'description' => 'The Guides post type and its archive/single pages.',
			'icon'        => 'dashicons-book-alt',
			'togglable'   => true,
		),
		'calculators'    => array(
			'label'       => 'Calculators',
			'description' => '16 business + personal calculators with their own URLs, built in-house.',
			'icon'        => 'dashicons-calculator',
			'togglable'   => true,
			'industry'    => 'bookkeeping',
		),
		'topics-catalog' => array(
			'label'       => 'Topics Catalog',
			'description' => 'Shared content-planning library (GitHub-backed) so multiple sites can cover the same topics with different wording.',
			'icon'        => 'dashicons-networking',
			'togglable'   => true,
			'industry'    => 'bookkeeping',
		),
		'forms'          => array(
			'label'       => 'Forms',
			'description' => 'Form builder, entries, and notifications.',
			'icon'        => 'dashicons-feedback',
			'togglable'   => false,
		),
		'smtp'           => array(
			'label'       => 'SMTP',
			'description' => 'Reliable outgoing mail via your own SMTP provider.',
			'icon'        => 'dashicons-email',
			'togglable'   => false,
		),
		'seo-tools'      => array(
			'label'       => 'SEO Tools',
			'description' => 'Meta tags, redirects, and .htaccess editing.',
			'icon'        => 'dashicons-chart-line',
			'togglable'   => false,
		),
	);
}

/** Every module defaults to ON — installing this update must never silently disable something a live site already depends on. */
function bootg_enabled_modules() {
	$defaults = array();
	foreach ( bootg_module_registry() as $id => $module ) {
		$defaults[ $id ] = true;
	}
	return wp_parse_args( get_option( BOOTG_ENABLED_MODULES_OPTION, array() ), $defaults );
}

function bootg_module_enabled( $id ) {
	$registry = bootg_module_registry();
	if ( isset( $registry[ $id ] ) && ! $registry[ $id ]['togglable'] ) {
		return true; // Not-yet-toggle-safe modules always report enabled.
	}
	$enabled = bootg_enabled_modules();
	return ! empty( $enabled[ $id ] );
}

function bootg_industry_modules( $industry ) {
	$ids = array();
	foreach ( bootg_module_registry() as $id => $module ) {
		if ( ( $module['industry'] ?? '' ) === $industry ) {
			$ids[] = $id;
		}
	}
	return $ids;
}

function bootg_industry_enabled( $industry ) {
	foreach ( bootg_industry_modules( $industry ) as $id ) {
		if ( ! bootg_module_enabled( $id ) ) {
			return false;
		}
	}
	return true;
}

add_action( 'admin_menu', function () {
	add_menu_page( 'Weavit', 'Weavit', 'edit_posts', 'weavit', 'bootg_render_weavit_home_page', 'dashicons-admin-generic', 3 );
	add_submenu_page( 'weavit', 'Weavit', 'Home', 'edit_posts', 'weavit', 'bootg_render_weavit_home_page' );
	add_submenu_page( 'weavit', 'Modules', 'Modules', 'manage_options', 'weavit-modules', 'bootg_render_weavit_modules_page' );
	add_submenu_page( 'weavit', 'Starter Sites', 'Starter Sites', 'manage_options', 'weavit-starter-sites', 'bootg_render_weavit_starter_sites_page' );
}, 1 ); // Priority 1: registers the parent before every feature file's admin_menu hook tries to attach to it.

add_action( 'admin_post_bootg_save_modules', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'bootg_save_modules' );

	$enabled = array();
	foreach ( bootg_module_registry() as $id => $module ) {
		if ( ! $module['togglable'] ) {
			continue;
		}
		$enabled[ $id ] = ! empty( $_POST[ 'module_' . $id ] );
	}
	update_option( BOOTG_ENABLED_MODULES_OPTION, wp_parse_args( $enabled, bootg_enabled_modules() ) );

	wp_safe_redirect( add_query_arg( array( 'page' => 'weavit-modules', 'saved' => 1 ), admin_url( 'admin.php' ) ) );
	exit;
} );

add_action( 'admin_post_bootg_save_industry', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'bootg_save_industry' );

	$on       = ! empty( $_POST['bookkeeping_enabled'] );
	$enabled  = bootg_enabled_modules();
	foreach ( bootg_industry_modules( 'bookkeeping' ) as $id ) {
		$enabled[ $id ] = $on;
	}
	update_option( BOOTG_ENABLED_MODULES_OPTION, $enabled );

	wp_safe_redirect( add_query_arg( array( 'page' => 'weavit', 'saved' => 1 ), admin_url( 'admin.php' ) ) );
	exit;
} );

function bootg_weavit_tabs_nav( $current ) {
	$tabs = array(
		'weavit'                => 'Home',
		'weavit-modules'        => 'Modules',
		'weavit-starter-sites'  => 'Starter Sites',
	);
	echo '<h2 class="nav-tab-wrapper" style="margin-bottom:24px;">';
	foreach ( $tabs as $slug => $label ) {
		$class = $current === $slug ? 'nav-tab nav-tab-active' : 'nav-tab';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</a>';
	}
	echo '</h2>';
}

function bootg_render_weavit_home_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	$bookkeeping_on = bootg_industry_enabled( 'bookkeeping' );
	?>
	<div class="wrap">
		<h1>Weavit</h1>
		<?php bootg_weavit_tabs_nav( 'weavit' ); ?>

		<?php if ( isset( $_GET['saved'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Saved.</p></div>
		<?php endif; ?>

		<p class="description" style="max-width:700px;">Weavit is a reusable engine, not a bookkeeping-specific plugin — the industry pack below is what makes <em>this</em> site a bookkeeping site. Reuse the same plugin on a different kind of site by leaving it off.</p>

		<div style="border:1px solid #dcdcde;border-radius:8px;background:#fff;padding:24px;max-width:600px;margin-top:20px;">
			<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;">
				<div>
					<h2 style="margin:0 0 6px;">Bookkeeping industry pack</h2>
					<p style="margin:0;color:#50575e;">Turns on Calculators and the Topics Catalog — the bookkeeping-specific extras this engine ships. Everything else (Forms, SMTP, SEO Tools) stays on regardless, since every site needs those.</p>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="flex-shrink:0;">
					<?php wp_nonce_field( 'bootg_save_industry' ); ?>
					<input type="hidden" name="action" value="bootg_save_industry">
					<input type="hidden" name="bookkeeping_enabled" value="<?php echo $bookkeeping_on ? '0' : '1'; ?>">
					<button type="submit" class="button <?php echo $bookkeeping_on ? '' : 'button-primary'; ?>"><?php echo $bookkeeping_on ? 'Turn off' : 'Turn on'; ?></button>
				</form>
			</div>
			<p style="margin:16px 0 0;"><strong>Status:</strong> <?php echo $bookkeeping_on ? '<span style="color:#1a7f37;">On</span>' : '<span style="color:#787c82;">Off</span>'; ?></p>
		</div>

		<p style="margin-top:24px;"><a href="<?php echo esc_url( admin_url( 'admin.php?page=weavit-modules' ) ); ?>">See every module individually &rarr;</a></p>
	</div>
	<?php
}

function bootg_render_weavit_modules_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$enabled = bootg_enabled_modules();
	?>
	<div class="wrap">
		<h1>Weavit</h1>
		<?php bootg_weavit_tabs_nav( 'weavit-modules' ); ?>

		<?php if ( isset( $_GET['saved'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Saved.</p></div>
		<?php endif; ?>

		<p class="description" style="max-width:700px;">Each card is one feature. Togglable ones turn their shortcodes, post types, and admin screens off completely when switched off. "Always on" modules are core infrastructure this version of the plugin doesn't yet support safely disabling.</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'bootg_save_modules' ); ?>
			<input type="hidden" name="action" value="bootg_save_modules">

			<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin:20px 0;">
				<?php foreach ( bootg_module_registry() as $id => $module ) : ?>
					<div style="border:1px solid #dcdcde;border-radius:8px;background:#fff;padding:18px;">
						<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:10px;">
							<span class="dashicons <?php echo esc_attr( $module['icon'] ); ?>" style="font-size:22px;width:22px;height:22px;color:#2271b1;"></span>
							<?php if ( $module['togglable'] ) : ?>
								<label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;">
									<input type="checkbox" name="module_<?php echo esc_attr( $id ); ?>" value="1" <?php checked( ! empty( $enabled[ $id ] ) ); ?>>
									Enabled
								</label>
							<?php else : ?>
								<span style="font-size:11px;font-weight:600;color:#787c82;background:#f0f0f1;border-radius:999px;padding:3px 10px;">Always on</span>
							<?php endif; ?>
						</div>
						<strong style="display:block;margin-bottom:4px;"><?php echo esc_html( $module['label'] ); ?></strong>
						<p style="margin:0;color:#50575e;font-size:13px;line-height:1.5;"><?php echo esc_html( $module['description'] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>

			<?php submit_button( 'Save Modules' ); ?>
		</form>
	</div>
	<?php
}

function bootg_render_weavit_starter_sites_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1>Weavit</h1>
		<?php bootg_weavit_tabs_nav( 'weavit-starter-sites' ); ?>
		<p class="description" style="max-width:700px;">Not built yet — this will let a brand-new site import a starter layout the same way Blocksy's Starter Sites work. Coming in a later pass.</p>
	</div>
	<?php
}
