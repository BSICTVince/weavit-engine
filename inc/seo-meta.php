<?php
/**
 * Per-page/post SEO fields — Meta Title, Meta Description, Keywords.
 * Core Meta Boxes API only, no SEO plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bootg_seo_post_types() {
	return array( 'page', 'post', 'service', 'integration', 'guide', 'testimonial', 'team_member' );
}

add_action( 'init', function () {
	foreach ( bootg_seo_post_types() as $post_type ) {
		foreach ( array( 'meta_title', 'meta_description', 'meta_keywords' ) as $key ) {
			register_post_meta( $post_type, $key, array(
				'single'            => true,
				'type'              => 'string',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			) );
		}
	}
} );

add_action( 'add_meta_boxes', function () {
	foreach ( bootg_seo_post_types() as $post_type ) {
		add_meta_box( 'bootg_seo', 'SEO', 'bootg_render_seo_meta_box', $post_type, 'normal', 'high' );
	}
} );

function bootg_render_seo_meta_box( $post ) {
	wp_nonce_field( 'bootg_save_seo', 'bootg_seo_nonce' );
	$title       = get_post_meta( $post->ID, 'meta_title', true );
	$description = get_post_meta( $post->ID, 'meta_description', true );
	$keywords    = get_post_meta( $post->ID, 'meta_keywords', true );
	?>
	<table class="form-table">
		<tr>
			<th style="width:220px;"><label for="meta_title">Meta Title</label></th>
			<td>
				<input type="text" id="meta_title" name="meta_title" value="<?php echo esc_attr( $title ); ?>" class="large-text">
				<p class="description">Leave blank to use the page title. Shown in the browser tab and search results.</p>
			</td>
		</tr>
		<tr>
			<th><label for="meta_description">Meta Description</label></th>
			<td>
				<textarea id="meta_description" name="meta_description" rows="3" class="large-text"><?php echo esc_textarea( $description ); ?></textarea>
				<p class="description">Shown under the title in search results. Aim for ~150–160 characters.</p>
			</td>
		</tr>
		<tr>
			<th><label for="meta_keywords">Keywords</label></th>
			<td>
				<input type="text" id="meta_keywords" name="meta_keywords" value="<?php echo esc_attr( $keywords ); ?>" class="large-text">
				<p class="description">Comma-separated. Largely ignored by modern search engines, kept for completeness.</p>
			</td>
		</tr>
	</table>
	<?php
}

add_action( 'save_post', function ( $post_id ) {
	if ( ! isset( $_POST['bootg_seo_nonce'] ) || ! wp_verify_nonce( $_POST['bootg_seo_nonce'], 'bootg_save_seo' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! in_array( get_post_type( $post_id ), bootg_seo_post_types(), true ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	foreach ( array( 'meta_title', 'meta_keywords' ) as $key ) {
		if ( isset( $_POST[ $key ] ) ) {
			update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
		}
	}
	if ( isset( $_POST['meta_description'] ) ) {
		update_post_meta( $post_id, 'meta_description', sanitize_textarea_field( wp_unslash( $_POST['meta_description'] ) ) );
	}
} );

/** Output: <title> override */
add_filter( 'pre_get_document_title', function ( $title ) {
	if ( ! is_singular() ) {
		return $title;
	}
	$custom = get_post_meta( get_queried_object_id(), 'meta_title', true );
	return $custom ?: $title;
} );

/** Output: meta description + keywords tags */
add_action( 'wp_head', function () {
	if ( ! is_singular() ) {
		return;
	}
	$id          = get_queried_object_id();
	$description = get_post_meta( $id, 'meta_description', true );
	$keywords    = get_post_meta( $id, 'meta_keywords', true );

	if ( $description ) {
		echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
	}
	if ( $keywords ) {
		echo '<meta name="keywords" content="' . esc_attr( $keywords ) . '">' . "\n";
	}
}, 1 );
