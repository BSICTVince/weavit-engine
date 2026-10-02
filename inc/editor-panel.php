<?php
/**
 * The Weavit toolbar icon + tabbed sidebar panel in the block editor (same
 * spot RankMath puts its SEO icon — see assets/js/editor-panel.js). Other
 * files contribute a tab via the `weavit_editor_panel_tabs` filter instead
 * of registering their own classic meta box, so there's one editing
 * surface per post instead of several stacked below the content.
 *
 * A tab: array( 'label' => string, 'fields' => array( meta_key => array(
 * 'label' => string, 'type' => text|textarea|url|checkbox, 'help'? => string ) ) ).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'enqueue_block_editor_assets', function () {
	$screen    = get_current_screen();
	$post_type = $screen ? $screen->post_type : '';
	$tabs      = apply_filters( 'weavit_editor_panel_tabs', array(), $post_type );
	if ( ! $tabs ) {
		return;
	}

	wp_enqueue_script(
		'weavit-editor-panel',
		WEAVIT_ENGINE_URI . 'assets/js/editor-panel.js',
		array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-block-editor' ),
		WEAVIT_ENGINE_VERSION,
		true
	);
	wp_add_inline_script(
		'weavit-editor-panel',
		'window.weavitEditorPanel = ' . wp_json_encode( array( 'tabs' => $tabs ) ) . ';',
		'before'
	);
} );
