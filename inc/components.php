<?php
/**
 * Weavit Page Designer — the mechanism for native-block "components"
 * (Section, Hero, Button, …). Each component is a real WordPress block;
 * this file only provides the shared bits every component needs:
 * - weavit_register_component(): registers the block, gated behind the
 *   "Page Designer" module toggle, under a dedicated "Weavit" inserter
 *   category.
 * - The "Component" tab in the Weavit editor panel (inc/editor-panel.php)
 *   — a live navigator listing weavit/* blocks on the current page.
 * Themes supply the actual blocks (markup/styling is theme-specific) by
 * calling weavit_register_component() from their own inc/components/*.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers one Page Designer component as a block. $args is exactly
 * what register_block_type() accepts, with 'category' defaulted to the
 * dedicated "weavit" inserter category. No-ops entirely when the
 * "Page Designer" module is switched off (Weavit → Modules).
 */
function weavit_register_component( $name, $args ) {
	if ( ! bootg_module_enabled( 'page-designer' ) ) {
		return;
	}
	$args = wp_parse_args( $args, array( 'category' => 'weavit' ) );
	register_block_type( $name, $args );
}

add_filter( 'block_categories_all', function ( $categories ) {
	return array_merge(
		array( array( 'slug' => 'weavit', 'title' => 'Weavit', 'icon' => 'admin-generic' ) ),
		$categories
	);
} );

/**
 * A "Component" tab in the Weavit sidebar panel — not a field form like
 * Details/SEO, but a live list of weavit/* blocks on the current page
 * (assets/js/editor-panel.js renders it by reading the block tree
 * directly via wp.data, not from this PHP data). Click an entry to
 * select/scroll to it on canvas; actual settings stay in WordPress's own
 * native block Inspector — no point duplicating what WP gives for free.
 */
add_filter( 'weavit_editor_panel_tabs', function ( $tabs, $post_type ) {
	if ( ! bootg_module_enabled( 'page-designer' ) || ! post_type_supports( $post_type, 'editor' ) ) {
		return $tabs;
	}
	$tabs['components'] = array( 'label' => 'Component', 'type' => 'navigator' );
	return $tabs;
}, 10, 2 );
