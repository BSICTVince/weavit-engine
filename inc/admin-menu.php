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
		'downloads'      => array(
			'label'       => 'Downloads',
			'description' => 'Upload a file (PDF, image, doc, anything) once, get a shortcode and a direct link you can drop into any page.',
			'icon'        => 'dashicons-media-default',
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
			'description' => 'Redirects and .htaccess editing.',
			'icon'        => 'dashicons-chart-line',
			'togglable'   => false,
		),
		'seo-meta'       => array(
			'label'       => 'SEO Meta Fields',
			'description' => 'Per-page Meta Title/Description/Keywords fields (Weavit tab) and the matching <title>/meta tag output. Turn off if the client is using Yoast, RankMath, or another SEO plugin instead — avoids both writing the same tags.',
			'icon'        => 'dashicons-search',
			'togglable'   => true,
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

/**
 * Starter Sites — a theme registers itself via the `weavit_starter_sites`
 * filter (id, title, description, preview_url, ordered list of step
 * keys/labels) and handles each step via the `weavit_starter_site_run_step`
 * filter, returning `array( 'message' => '...' )` or a WP_Error. This file
 * only owns the generic UI + AJAX runner; the actual import logic for
 * Bookkeeping On The Go lives in the theme (inc/starter-site-import.php),
 * same separation as Content Tools.
 *
 * A theme can only register its own steps once IT is the active theme (its
 * functions.php has to have loaded for the filter to even be hooked) — so
 * on a site where the theme isn't installed/active yet, `weavit_starter_sites`
 * alone would have nothing to show. `weavit_starter_site_catalog()` is the
 * plugin's own, theme-independent list of installable starter sites (just
 * id/title/description + which theme to fetch from GitHub) so the card and
 * an "Install theme" step always exist; once that step switches the active
 * theme, the very next AJAX request boots with the new theme loaded and its
 * `weavit_starter_sites` steps become available to merge in.
 */
function weavit_starter_site_catalog() {
	return array(
		'bootg' => array(
			'id'          => 'bootg',
			'title'       => 'Bookkeeping On The Go',
			'description' => 'The full starter site this theme ships with — services, partners, team, testimonials, blog posts, and every core page, wired up and ready to edit.',
			'preview_url' => home_url( '/' ),
			'theme'       => array(
				'slug' => 'bookkeeping-on-the-go',
				'repo' => 'BSICTVince/bookkeeping-on-the-go',
			),
			'steps'       => array(),
		),
	);
}

/** Catalog entries, merged with whatever the active theme contributes via `weavit_starter_sites` (steps, live preview_url, etc.), keyed by id. */
function weavit_starter_sites() {
	$sites = weavit_starter_site_catalog();

	foreach ( apply_filters( 'weavit_starter_sites', array() ) as $site ) {
		if ( empty( $site['id'] ) ) {
			continue;
		}
		$sites[ $site['id'] ] = array_merge( $sites[ $site['id'] ] ?? array(), $site );
	}

	foreach ( $sites as $id => $site ) {
		$sites[ $id ]['theme_active'] = empty( $site['theme']['slug'] ) || get_stylesheet() === $site['theme']['slug'];
	}

	return array_values( $sites );
}

function weavit_starter_site_by_id( $id ) {
	foreach ( weavit_starter_sites() as $site ) {
		if ( $site['id'] === $id ) {
			return $site;
		}
	}
	return null;
}

add_action( 'wp_ajax_weavit_starter_site_install_theme', function () {
	if ( ! current_user_can( 'install_themes' ) ) {
		wp_send_json_error( array( 'message' => 'Not allowed.' ), 403 );
	}
	check_ajax_referer( 'weavit_starter_site_import', 'nonce' );

	$site_id = isset( $_POST['site'] ) ? sanitize_key( wp_unslash( $_POST['site'] ) ) : '';
	$site    = weavit_starter_site_by_id( $site_id );

	if ( ! $site || empty( $site['theme']['slug'] ) || empty( $site['theme']['repo'] ) ) {
		wp_send_json_error( array( 'message' => 'Unknown starter site.' ) );
	}

	$slug = $site['theme']['slug'];
	$repo = $site['theme']['repo'];

	if ( ! wp_get_theme( $slug )->exists() ) {
		$tag = weavit_github_latest_tag( $repo );
		if ( is_wp_error( $tag ) ) {
			wp_send_json_error( array( 'message' => $tag->get_error_message() ) );
		}
		$installed = weavit_install_github_package( $repo, $tag, 'theme', $slug );
		if ( is_wp_error( $installed ) ) {
			wp_send_json_error( array( 'message' => $installed->get_error_message() ) );
		}
	}

	if ( get_stylesheet() !== $slug ) {
		switch_theme( $slug );
	}

	wp_send_json_success( array( 'message' => 'Theme installed and activated.' ) );
} );

/** Re-reads weavit_starter_sites() — called right after the install step, in a fresh request where a just-activated theme's functions.php (and its step list) has now actually loaded. */
add_action( 'wp_ajax_weavit_starter_site_refresh_steps', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Not allowed.' ), 403 );
	}
	check_ajax_referer( 'weavit_starter_site_import', 'nonce' );

	$site_id = isset( $_POST['site'] ) ? sanitize_key( wp_unslash( $_POST['site'] ) ) : '';
	$site    = weavit_starter_site_by_id( $site_id );

	if ( ! $site ) {
		wp_send_json_error( array( 'message' => 'Unknown starter site.' ) );
	}

	wp_send_json_success( array( 'steps' => $site['steps'] ) );
} );

add_action( 'wp_ajax_weavit_starter_site_step', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Not allowed.' ), 403 );
	}
	check_ajax_referer( 'weavit_starter_site_import', 'nonce' );

	$site = isset( $_POST['site'] ) ? sanitize_key( wp_unslash( $_POST['site'] ) ) : '';
	$step = isset( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : '';

	$result = apply_filters( 'weavit_starter_site_run_step', null, $site, $step );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}
	if ( null === $result ) {
		wp_send_json_error( array( 'message' => 'Unknown starter site or step.' ) );
	}

	wp_send_json_success( $result );
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

		<style>
			.weavit-toggle{display:inline-flex;align-items:center;cursor:pointer;gap:0;}
			.weavit-toggle input{position:absolute;opacity:0;width:1px;height:1px;}
			.weavit-toggle-track{width:36px;height:20px;background:#dcdcde;border-radius:999px;position:relative;transition:background-color .15s ease;flex-shrink:0;}
			.weavit-toggle-thumb{position:absolute;top:2px;left:2px;width:16px;height:16px;background:#fff;border-radius:50%;transition:transform .15s ease;box-shadow:0 1px 2px rgba(0,0,0,.25);}
			.weavit-toggle input:checked + .weavit-toggle-track{background:#2271b1;}
			.weavit-toggle input:checked + .weavit-toggle-track .weavit-toggle-thumb{transform:translateX(16px);}
			.weavit-toggle input:focus-visible + .weavit-toggle-track{outline:2px solid #2271b1;outline-offset:2px;}
		</style>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'bootg_save_modules' ); ?>
			<input type="hidden" name="action" value="bootg_save_modules">

			<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin:20px 0;">
				<?php foreach ( bootg_module_registry() as $id => $module ) : ?>
					<div style="border:1px solid #dcdcde;border-radius:8px;background:#fff;padding:18px;">
						<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:10px;">
							<span class="dashicons <?php echo esc_attr( $module['icon'] ); ?>" style="font-size:22px;width:22px;height:22px;color:#2271b1;"></span>
							<?php if ( $module['togglable'] ) : ?>
								<label class="weavit-toggle" aria-label="Enabled">
									<input type="checkbox" name="module_<?php echo esc_attr( $id ); ?>" value="1" <?php checked( ! empty( $enabled[ $id ] ) ); ?>>
									<span class="weavit-toggle-track"><span class="weavit-toggle-thumb"></span></span>
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
	$sites = weavit_starter_sites();
	?>
	<div class="wrap">
		<h1>Weavit</h1>
		<?php bootg_weavit_tabs_nav( 'weavit-starter-sites' ); ?>
		<p class="description" style="max-width:700px;">One-click setup for a starter site — installs its theme from GitHub if it isn't already active, then imports its pages, services, menus, and blog posts in order. Safe to run more than once; anything already there is skipped.</p>

		<?php if ( empty( $sites ) ) : ?>
			<p class="description" style="margin-top:20px;">No starter sites are registered yet.</p>
		<?php else : ?>
			<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;margin-top:24px;max-width:900px;">
				<?php foreach ( $sites as $site ) : ?>
					<div class="weavit-starter-site-card" style="border:1px solid #dcdcde;border-radius:8px;background:#fff;overflow:hidden;">
						<div style="aspect-ratio:4/3;background:linear-gradient(135deg,#2271b1,#1a3a5c);display:flex;align-items:center;justify-content:center;">
							<span class="dashicons dashicons-admin-site-alt3" style="font-size:48px;width:48px;height:48px;color:rgba(255,255,255,.85);"></span>
						</div>
						<div style="padding:16px;">
							<strong style="display:block;margin-bottom:4px;font-size:14px;"><?php echo esc_html( $site['title'] ); ?></strong>
							<p style="margin:0 0 14px;color:#50575e;font-size:12px;line-height:1.5;"><?php echo esc_html( $site['description'] ); ?></p>
							<?php if ( empty( $site['theme_active'] ) ) : ?>
								<p style="margin:0 0 10px;font-size:11px;font-weight:600;color:#9a6700;">Theme not installed yet — Import will install it from GitHub first.</p>
							<?php endif; ?>
							<div style="display:flex;gap:8px;">
								<a href="<?php echo esc_url( $site['preview_url'] ); ?>" target="_blank" class="button">Preview</a>
								<button type="button" class="button button-primary weavit-import-starter-site" data-site="<?php echo esc_attr( $site['id'] ); ?>">Import</button>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

	<div id="weavit-import-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:100000;align-items:center;justify-content:center;">
		<div style="background:#fff;border-radius:8px;max-width:480px;width:92%;max-height:80vh;overflow:auto;padding:24px;">
			<h2 id="weavit-import-modal-title" style="margin-top:0;">Importing&hellip;</h2>
			<ul id="weavit-import-steps" style="list-style:none;margin:0 0 20px;padding:0;"></ul>
			<button type="button" class="button" id="weavit-import-modal-close" style="display:none;">Close &amp; refresh</button>
		</div>
	</div>

	<script>
	( function () {
		var sites   = <?php echo wp_json_encode( $sites ); ?>;
		var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		var nonce   = <?php echo wp_json_encode( wp_create_nonce( 'weavit_starter_site_import' ) ); ?>;

		function byId( id ) { return document.getElementById( id ); }

		document.querySelectorAll( '.weavit-import-starter-site' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var siteId = btn.getAttribute( 'data-site' );
				var site = sites.filter( function ( s ) { return s.id === siteId; } )[0];
				if ( site ) {
					runImport( site );
				}
			} );
		} );

		function addStepRow( list, key, label ) {
			var li = document.createElement( 'li' );
			li.id = 'weavit-step-' + key;
			li.style.cssText = 'padding:8px 0;border-bottom:1px solid #f0f0f1;display:flex;gap:10px;align-items:flex-start;';
			li.innerHTML = '<span class="weavit-step-icon" style="flex-shrink:0;">○</span><span><strong>' + label + '</strong><div class="weavit-step-message" style="color:#787c82;font-size:12px;"></div></span>';
			list.appendChild( li );
			return li;
		}

		function runStep( action, extra ) {
			var body = new URLSearchParams();
			body.append( 'action', action );
			body.append( 'nonce', nonce );
			Object.keys( extra ).forEach( function ( k ) { body.append( k, extra[ k ] ); } );

			return fetch( ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( r ) { return r.json(); } )
				.catch( function () { return { success: false, data: { message: 'Request failed.' } }; } );
		}

		function markStep( li, res ) {
			var icon = li.querySelector( '.weavit-step-icon' );
			var msg  = li.querySelector( '.weavit-step-message' );
			if ( res.success ) {
				icon.textContent = '✅';
				msg.textContent = ( res.data && res.data.message ) || 'Done.';
			} else {
				icon.textContent = '⚠';
				msg.textContent = ( res.data && res.data.message ) || 'Something went wrong.';
			}
		}

		function runImport( site ) {
			var modal = byId( 'weavit-import-modal' );
			var list  = byId( 'weavit-import-steps' );
			var title = byId( 'weavit-import-modal-title' );
			var close = byId( 'weavit-import-modal-close' );

			title.textContent = 'Importing ' + site.title + '…';
			close.style.display = 'none';
			list.innerHTML = '';
			modal.style.display = 'flex';

			var steps = site.steps.slice();
			var needsInstall = ! site.theme_active;
			if ( needsInstall ) {
				steps.unshift( { key: '__install_theme', label: 'Install & activate the ' + site.title + ' theme from GitHub' } );
			}
			steps.forEach( function ( step ) { addStepRow( list, step.key, step.label ); } );

			var i = 0;
			function next() {
				if ( i >= steps.length ) {
					title.textContent = site.title + ' is ready.';
					close.style.display = 'inline-block';
					return;
				}
				var step = steps[ i ];
				var li   = byId( 'weavit-step-' + step.key );
				li.querySelector( '.weavit-step-icon' ).textContent = '⏳';

				if ( '__install_theme' === step.key ) {
					runStep( 'weavit_starter_site_install_theme', { site: site.id } ).then( function ( res ) {
						markStep( li, res );
						if ( ! res.success ) {
							title.textContent = 'Could not install the theme.';
							close.style.display = 'inline-block';
							return;
						}
						runStep( 'weavit_starter_site_refresh_steps', { site: site.id } ).then( function ( refreshRes ) {
							var newSteps = ( refreshRes.success && refreshRes.data && refreshRes.data.steps ) || [];
							newSteps.forEach( function ( s ) { addStepRow( list, s.key, s.label ); } );
							steps = steps.slice( 0, i + 1 ).concat( newSteps );
							i++;
							next();
						} );
					} );
					return;
				}

				runStep( 'weavit_starter_site_step', { site: site.id, step: step.key } ).then( function ( res ) {
					markStep( li, res );
					i++;
					next();
				} );
			}
			next();
		}

		var closeBtn = byId( 'weavit-import-modal-close' );
		if ( closeBtn ) {
			closeBtn.addEventListener( 'click', function () {
				window.location.reload();
			} );
		}
	} )();
	</script>
	<?php
}
