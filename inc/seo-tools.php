<?php
/**
 * A single "SEO" menu with tabs for the site-level SEO tools (Redirects,
 * .htaccess) — one top-level entry instead of several, and no menu-position
 * juggling for tools that will keep growing over time.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bootg_seo_tabs() {
	return array(
		'analyser'  => array( 'label' => 'Analyser', 'render' => 'weavit_seo_render_analyser_tab' ),
		'titles'    => array( 'label' => 'Titles & Social', 'render' => 'weavit_seo_render_titles_tab' ),
		'schema'    => array( 'label' => 'Schema', 'render' => 'weavit_seo_render_schema_tab' ),
		'sitemap'   => array( 'label' => 'Sitemap & Robots', 'render' => 'weavit_seo_render_sitemap_tab' ),
		'notfound'  => array( 'label' => '404 Monitor', 'render' => 'weavit_seo_render_notfound_tab' ),
		'redirects' => array( 'label' => 'Redirects', 'render' => 'bootg_render_redirects_tab' ),
		'htaccess'  => array( 'label' => '.htaccess', 'render' => 'bootg_render_htaccess_tab' ),
	);
}

add_action( 'admin_menu', function () {
	$hook = add_submenu_page( 'weavit', 'SEO', 'SEO Tools', 'manage_options', 'bootg-seo', 'bootg_render_seo_page' );
	add_action( "load-$hook", 'bootg_handle_redirects_bulk_action_early' );
} );

function bootg_seo_tab_url( $tab ) {
	return admin_url( 'admin.php?page=bootg-seo&tab=' . $tab );
}

function bootg_render_seo_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$tabs    = bootg_seo_tabs();
	$current = isset( $_GET['tab'] ) && isset( $tabs[ $_GET['tab'] ] ) ? sanitize_key( $_GET['tab'] ) : 'analyser'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab selector.
	?>
	<div class="wrap">
		<h1>SEO</h1>
		<h2 class="nav-tab-wrapper">
			<?php foreach ( $tabs as $key => $tab ) : ?>
				<a href="<?php echo esc_url( bootg_seo_tab_url( $key ) ); ?>" class="nav-tab <?php echo $current === $key ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $tab['label'] ); ?></a>
			<?php endforeach; ?>
		</h2>
		<?php $is_redirects_list = 'redirects' === $current && 'edit' !== ( $_GET['view'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div style="margin-top:24px;<?php echo $is_redirects_list ? '' : 'max-width:900px;'; ?>">
			<?php call_user_func( $tabs[ $current ]['render'] ); ?>
		</div>
	</div>
	<?php
}
