<?php
/**
 * Weavit admin menu groups. A group folds several Weavit submenu entries into
 * one entry with a flyout to the side, like Elementor's "Editor" item:
 *
 *   Weavit > Forms      ▸  All Forms / Add New / Entries / reCAPTCHA
 *   Weavit > Bookkeeping ▸  Overview / Calculators / Topics Catalog / ...
 *
 * WordPress has no nested menus, so the flyout is drawn with a little CSS/JS.
 * The folded entries stay registered exactly as before (their pages, URLs and
 * permissions don't change); they're only hidden from the list. If scripts
 * don't run, the group's own page still opens normally.
 *
 * Bookkeeping holds everything that only makes sense on a bookkeeping site (the
 * "industry pack"), so on a different kind of site it simply isn't there.
 *
 * Another feature can add a group with the `weavit_menu_groups` filter:
 *   array( 'hub' => menu-slug, 'label' => ..., 'children' => array( menu-slugs ),
 *          'items' => array( array( label, url, query-fragment ) ) ).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------------------------------------------------------------------
 * Bookkeeping group: which items, and when it exists
 * ------------------------------------------------------------------- */

/** True while any bookkeeping industry module is switched on. */
function weavit_bk_active() {
	foreach ( bootg_industry_modules( 'bookkeeping' ) as $id ) {
		if ( bootg_module_enabled( $id ) ) {
			return true;
		}
	}
	return false;
}

/** Items in the Bookkeeping flyout: array( label, url, query fragment that marks it current ). */
function weavit_bk_items() {
	$items = array( array( 'Overview', admin_url( 'admin.php?page=weavit-bookkeeping' ), 'page=weavit-bookkeeping' ) );

	if ( bootg_module_enabled( 'calculators' ) ) {
		$page = get_page_by_path( 'calculators' );
		if ( $page ) {
			$items[] = array( 'Calculators', get_edit_post_link( $page->ID, 'raw' ), 'post=' . $page->ID . '&action=edit' );
		}
	}
	if ( bootg_module_enabled( 'topics-catalog' ) ) {
		$items[] = array( 'Topics Catalog', admin_url( 'admin.php?page=bootg-topics-catalog' ), 'page=bootg-topics-catalog' );
		$items[] = array( 'Topics: Add New', admin_url( 'admin.php?page=bootg-topics-catalog-new' ), 'page=bootg-topics-catalog-new' );
		$items[] = array( 'Topics: Settings', admin_url( 'admin.php?page=bootg-topics-catalog-settings' ), 'page=bootg-topics-catalog-settings' );
	}
	return (array) apply_filters( 'weavit_bookkeeping_menu_items', $items );
}

/* ---------------------------------------------------------------------
 * The groups
 * ------------------------------------------------------------------- */

function weavit_menu_groups() {
	$groups = array();

	$groups['forms'] = array(
		'hub'      => 'bootg-forms',
		'label'    => 'Forms',
		'children' => array( 'bootg-form-builder', 'bootg-form-entries', 'bootg-recaptcha-settings' ),
		'items'    => array(
			array( 'All Forms', admin_url( 'admin.php?page=bootg-forms' ), 'page=bootg-forms' ),
			array( 'Add New', admin_url( 'admin.php?page=bootg-form-builder' ), 'page=bootg-form-builder' ),
			array( 'Entries', admin_url( 'admin.php?page=bootg-form-entries' ), 'page=bootg-form-entries' ),
			array( 'reCAPTCHA', admin_url( 'admin.php?page=bootg-recaptcha-settings' ), 'page=bootg-recaptcha-settings' ),
		),
	);

	if ( weavit_bk_active() ) {
		$groups['bookkeeping'] = array(
			'hub'      => 'weavit-bookkeeping',
			'label'    => 'Bookkeeping',
			'children' => (array) apply_filters( 'weavit_bookkeeping_child_slugs', array( 'bootg-topics-catalog', 'bootg-topics-catalog-new', 'bootg-topics-catalog-settings' ) ),
			'items'    => weavit_bk_items(),
		);
	}

	return (array) apply_filters( 'weavit_menu_groups', $groups );
}

// The Bookkeeping group has its own overview page to open.
add_action( 'admin_menu', function () {
	if ( weavit_bk_active() ) {
		add_submenu_page( 'weavit', 'Bookkeeping', 'Bookkeeping', 'edit_posts', 'weavit-bookkeeping', 'weavit_bk_render_page', 3 );
	}
}, 20 );

// Tag the entries so the CSS can fold them into each group's flyout, and give each hub its plain name.
add_action( 'admin_menu', function () {
	global $submenu;
	if ( empty( $submenu['weavit'] ) ) {
		return;
	}
	foreach ( weavit_menu_groups() as $id => $group ) {
		foreach ( $submenu['weavit'] as $i => $item ) {
			if ( $group['hub'] === $item[2] ) {
				$submenu['weavit'][ $i ][0] = $group['label'];
				$submenu['weavit'][ $i ][4] = trim( ( $item[4] ?? '' ) . ' weavit-fly-hub weavit-fly-' . sanitize_html_class( $id ) );
			} elseif ( in_array( $item[2], $group['children'], true ) ) {
				$submenu['weavit'][ $i ][4] = trim( ( $item[4] ?? '' ) . ' weavit-fly-child' );
			}
		}
	}
}, 9999 );

add_action( 'admin_head', function () {
	?>
	<style>
		#adminmenu li.weavit-fly-child { display: none !important; }
		#adminmenu li.weavit-fly-hub { position: relative; }
		#adminmenu li.weavit-fly-hub > a::after { content: "\f345"; font: normal 16px/1 dashicons; float: right; margin-top: -1px; opacity: .65; }
		#adminmenu .weavit-flyout { display: none; position: absolute; left: 100%; top: -8px; z-index: 9999; min-width: 190px; margin: 0; padding: 6px 0; list-style: none; background: #2c3338; border-radius: 0 4px 4px 0; box-shadow: 4px 4px 14px rgba(0, 0, 0, .35); }
		#adminmenu li.weavit-fly-hub:hover > .weavit-flyout,
		#adminmenu li.weavit-fly-hub:focus-within > .weavit-flyout { display: block; }
		#adminmenu .weavit-flyout li { margin: 0; padding: 0; }
		#adminmenu .weavit-flyout a { display: block; padding: 7px 16px; color: rgba(240, 246, 252, .7); text-decoration: none; white-space: nowrap; }
		#adminmenu .weavit-flyout a:hover, #adminmenu .weavit-flyout a:focus { color: #72aee6; }
		#adminmenu .weavit-flyout li.current > a { color: #fff; font-weight: 600; }
		@media screen and (max-width: 782px) {
			#adminmenu li.weavit-fly-hub > a::after { display: none; }
			#adminmenu .weavit-flyout { display: block; position: static; background: transparent; box-shadow: none; padding: 0 0 0 14px; }
		}
	</style>
	<?php
} );

add_action( 'admin_footer', function () {
	$data = array();
	foreach ( weavit_menu_groups() as $id => $group ) {
		$data[ sanitize_html_class( $id ) ] = array_values( $group['items'] );
	}
	?>
	<script>
	(function () {
		var groups = <?php echo wp_json_encode( $data ); ?>;
		var here = window.location.href.replace(/&amp;/g, '&');
		Object.keys(groups).forEach(function (id) {
			var hub = document.querySelector('#adminmenu li.weavit-fly-hub.weavit-fly-' + id);
			if (!hub || hub.querySelector('.weavit-flyout')) { return; }
			var ul = document.createElement('ul');
			ul.className = 'weavit-flyout';
			groups[id].forEach(function (item) {
				var li = document.createElement('li');
				var a = document.createElement('a');
				a.href = item[1];
				a.textContent = item[0];
				li.appendChild(a);
				// "page=x" must not also count as current on "page=x-new": the match has to end the value.
				var m = here.indexOf(item[2]);
				var next = m > -1 ? here.charAt(m + item[2].length) : null;
				if (m > -1 && (next === '' || next === '&' || next === '#')) {
					li.className = 'current';
					hub.classList.add('current');
				}
				ul.appendChild(li);
			});
			hub.appendChild(ul);
		});
	})();
	</script>
	<?php
} );

/* ---------------------------------------------------------------------
 * Bookkeeping overview page
 * ------------------------------------------------------------------- */

function weavit_bk_render_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	$groups = (array) apply_filters( 'weavit_bookkeeping_pages', array(
		'Compliance &amp; resources' => array(
			'calculators'       => 'Calculators',
			'ato-compliance'    => 'ATO Compliance',
			'dates-to-remember' => 'Dates to Remember',
			'key-dates'         => 'Key Dates',
			'7-steps'           => '7 Steps to Increasing Profit',
			'resources'         => 'Templates &amp; Checklists',
		),
		'Specialist pages'           => array(
			'payroll-specialists-perth'       => 'Payroll Specialists in Perth',
			'public-trustee-reporting'        => 'Public Trustee Reporting',
			'nonprofit-compliance-accounting' => 'Nonprofit Compliance Accounting',
		),
	) );
	?>
	<div class="wrap">
		<h1>Bookkeeping</h1>
		<p style="max-width:760px;">Everything that's specific to a bookkeeping business lives here: the content-planning library, calculators and the compliance and specialist pages. General features (Services, Forms, Email Templates, SEO and so on) stay in the main Weavit menu, so a different kind of business can use this plugin without any of it. Turn the whole pack on or off from <a href="<?php echo esc_url( admin_url( 'admin.php?page=weavit' ) ); ?>">Weavit &rarr; Home</a>.</p>

		<?php if ( bootg_module_enabled( 'topics-catalog' ) ) : ?>
			<h2>Topics Catalog</h2>
			<p>Shared content-planning library, so several sites can cover the same topics in different words.</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=bootg-topics-catalog' ) ); ?>">Open the catalog</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=bootg-topics-catalog-new' ) ); ?>">Add a topic</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=bootg-topics-catalog-settings' ) ); ?>">Settings</a>
			</p>
		<?php endif; ?>

		<?php foreach ( $groups as $heading => $pages ) : ?>
			<?php
			$rows = array();
			foreach ( $pages as $slug => $label ) {
				if ( 'calculators' === $slug && ! bootg_module_enabled( 'calculators' ) ) {
					continue;
				}
				$page = get_page_by_path( $slug );
				if ( $page ) {
					$rows[] = array( $label, get_edit_post_link( $page->ID, 'raw' ), get_permalink( $page ) );
				}
			}
			if ( ! $rows ) {
				continue;
			}
			?>
			<h2><?php echo wp_kses_post( $heading ); ?></h2>
			<table class="widefat striped" style="max-width:760px;">
				<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><strong><?php echo wp_kses_post( $row[0] ); ?></strong></td>
						<td style="width:150px;text-align:right;"><a href="<?php echo esc_url( $row[1] ); ?>">Edit</a> &nbsp;|&nbsp; <a href="<?php echo esc_url( $row[2] ); ?>" target="_blank" rel="noopener">View</a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endforeach; ?>
	</div>
	<?php
}
