<?php
/**
 * Weavit > Bookkeeping — one place in the admin menu for everything that only
 * makes sense on a bookkeeping site (the "industry pack": Topics Catalog,
 * Calculators, compliance and specialist pages). Reusable features (Services,
 * Forms, SMTP, Email Templates ...) stay in the main Weavit list, so on a
 * non-bookkeeping site this group simply isn't there.
 *
 * It looks like Elementor's "Editor" item: a "Bookkeeping" entry in the Weavit
 * submenu that opens a flyout to the side. WordPress has no nested menus, so
 * the flyout is drawn with a little CSS/JS from the list below; the Bookkeeping
 * overview page links to the same things, so nothing depends on the flyout.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** True while any bookkeeping industry module is switched on. */
function weavit_bk_active() {
	foreach ( bootg_industry_modules( 'bookkeeping' ) as $id ) {
		if ( bootg_module_enabled( $id ) ) {
			return true;
		}
	}
	return false;
}

/** Items shown in the flyout: array( label, url, slug-or-query fragment used to mark the current one ). */
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

/** Menu slugs that already sit in the Weavit list and move into the flyout. */
function weavit_bk_child_slugs() {
	return (array) apply_filters( 'weavit_bookkeeping_child_slugs', array( 'bootg-topics-catalog', 'bootg-topics-catalog-new', 'bootg-topics-catalog-settings' ) );
}

add_action( 'admin_menu', function () {
	if ( weavit_bk_active() ) {
		add_submenu_page( 'weavit', 'Bookkeeping', 'Bookkeeping', 'edit_posts', 'weavit-bookkeeping', 'weavit_bk_render_page', 3 );
	}
}, 20 );

// Tag the entries so CSS can fold them into the flyout (they stay registered, so their pages keep working exactly as before).
add_action( 'admin_menu', function () {
	global $submenu;
	if ( empty( $submenu['weavit'] ) || ! weavit_bk_active() ) {
		return;
	}
	$children = weavit_bk_child_slugs();
	foreach ( $submenu['weavit'] as $i => $item ) {
		if ( 'weavit-bookkeeping' === $item[2] ) {
			$submenu['weavit'][ $i ][4] = trim( ( $item[4] ?? '' ) . ' weavit-bk-hub' );
		} elseif ( in_array( $item[2], $children, true ) ) {
			$submenu['weavit'][ $i ][4] = trim( ( $item[4] ?? '' ) . ' weavit-bk-child' );
		}
	}
}, 9999 );

add_action( 'admin_head', function () {
	if ( ! weavit_bk_active() ) {
		return;
	}
	?>
	<style>
		#adminmenu li.weavit-bk-child { display: none !important; }
		#adminmenu li.weavit-bk-hub { position: relative; }
		#adminmenu li.weavit-bk-hub > a::after { content: "\f345"; font: normal 16px/1 dashicons; float: right; margin-top: -1px; opacity: .65; }
		#adminmenu .weavit-bk-flyout { display: none; position: absolute; left: 100%; top: -8px; z-index: 9999; min-width: 190px; margin: 0; padding: 6px 0; list-style: none; background: #2c3338; border-radius: 0 4px 4px 0; box-shadow: 4px 4px 14px rgba(0, 0, 0, .35); }
		#adminmenu li.weavit-bk-hub:hover > .weavit-bk-flyout,
		#adminmenu li.weavit-bk-hub:focus-within > .weavit-bk-flyout { display: block; }
		#adminmenu .weavit-bk-flyout li { margin: 0; padding: 0; }
		#adminmenu .weavit-bk-flyout a { display: block; padding: 7px 16px; color: rgba(240, 246, 252, .7); text-decoration: none; white-space: nowrap; }
		#adminmenu .weavit-bk-flyout a:hover, #adminmenu .weavit-bk-flyout a:focus { color: #72aee6; }
		#adminmenu .weavit-bk-flyout li.current > a { color: #fff; font-weight: 600; }
		@media screen and (max-width: 782px) {
			#adminmenu li.weavit-bk-hub > a::after { display: none; }
			#adminmenu .weavit-bk-flyout { display: block; position: static; background: transparent; box-shadow: none; padding: 0 0 0 14px; }
		}
	</style>
	<?php
} );

add_action( 'admin_footer', function () {
	if ( ! weavit_bk_active() ) {
		return;
	}
	?>
	<script>
	(function () {
		var hub = document.querySelector('#adminmenu li.weavit-bk-hub');
		if (!hub || hub.querySelector('.weavit-bk-flyout')) { return; }
		var items = <?php echo wp_json_encode( array_values( weavit_bk_items() ) ); ?>;
		var here = window.location.href.replace(/&amp;/g, '&');
		var ul = document.createElement('ul');
		ul.className = 'weavit-bk-flyout';
		items.forEach(function (item) {
			var li = document.createElement('li');
			var a = document.createElement('a');
			a.href = item[1];
			a.textContent = item[0];
			li.appendChild(a);
			// "page=bootg-topics-catalog" must not also match "...-new": compare to the end of the query value.
			var m = here.indexOf(item[2]);
			if (m > -1 && (here.charAt(m + item[2].length) === '' || here.charAt(m + item[2].length) === '&' || here.charAt(m + item[2].length) === '#')) {
				li.className = 'current';
				hub.classList.add('current');
			}
			ul.appendChild(li);
		});
		hub.appendChild(ul);
	})();
	</script>
	<?php
} );

/** The Bookkeeping overview page. */
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
			'payroll-specialists-perth'        => 'Payroll Specialists in Perth',
			'public-trustee-reporting'         => 'Public Trustee Reporting',
			'nonprofit-compliance-accounting'  => 'Nonprofit Compliance Accounting',
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
