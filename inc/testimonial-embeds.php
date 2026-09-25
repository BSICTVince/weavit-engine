<?php
/**
 * Testimonials, redesigned as embeddable widgets rather than one-review-per-
 * entry: each Testimonial post is either a structured single review (the
 * original fields — quote/author/rating) or a block of arbitrary custom
 * HTML (which can itself contain shortcodes, e.g. a third-party review
 * widget's own shortcode, or a hand-built block with several reviews in
 * it). Either way the post gets its own [bootg_testimonial id="123"]
 * shortcode to paste anywhere, shown in a dedicated column on the
 * Testimonials list screen — same "here's your shortcode" pattern as
 * third-party review-widget plugins, but editable in our own codebase.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bootg_testimonial_fields() {
	return array(
		'content_mode'    => array( 'Content mode', 'select' ),
		'quote'           => array( 'Quote', 'textarea' ),
		'author_name'     => array( 'Author name', 'text' ),
		'author_business' => array( 'Author business / location', 'text' ),
		'rating'          => array( 'Rating (1-5)', 'number' ),
		'is_spotlight'    => array( 'Show in homepage spotlight', 'checkbox' ),
		'custom_html'     => array( 'Custom HTML', 'code' ),
	);
}

add_action( 'init', function () {
	foreach ( bootg_testimonial_fields() as $key => $field ) {
		register_post_meta( 'testimonial', $key, array(
			'single'            => true,
			'type'              => 'checkbox' === $field[1] ? 'boolean' : 'string',
			'show_in_rest'      => true,
			'sanitize_callback' => bootg_testimonial_meta_sanitizer( $field[1] ),
			'auth_callback'     => function () {
				return current_user_can( 'edit_posts' );
			},
		) );
	}
	add_shortcode( 'bootg_testimonial', 'bootg_render_testimonial_shortcode' );
} );

function bootg_testimonial_meta_sanitizer( $type ) {
	switch ( $type ) {
		case 'textarea':
			return 'sanitize_textarea_field';
		case 'number':
			return 'absint';
		case 'checkbox':
			return 'rest_sanitize_boolean';
		case 'code':
			// Deliberately allows shortcode brackets and post-safe HTML (an
			// embedded review widget's own markup) but not raw <script> --
			// the widget itself should come from an activated shortcode,
			// not an inline script pasted here.
			return 'wp_kses_post';
		case 'select':
			return function ( $value ) {
				return 'custom_code' === $value ? 'custom_code' : 'fields';
			};
		default:
			return 'sanitize_text_field';
	}
}

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'bootg_testimonial_fields', 'Content', 'bootg_render_testimonial_meta_box', 'testimonial', 'normal', 'high' );
	add_meta_box( 'bootg_testimonial_shortcode', 'Shortcode', 'bootg_render_testimonial_shortcode_box', 'testimonial', 'side', 'default' );
} );

function bootg_render_testimonial_meta_box( $post ) {
	wp_nonce_field( 'bootg_save_testimonial_meta', 'bootg_testimonial_meta_nonce' );
	$mode      = get_post_meta( $post->ID, 'content_mode', true ) ?: 'fields';
	$quote     = get_post_meta( $post->ID, 'quote', true );
	$name      = get_post_meta( $post->ID, 'author_name', true );
	$business  = get_post_meta( $post->ID, 'author_business', true );
	$rating    = get_post_meta( $post->ID, 'rating', true );
	$spotlight = get_post_meta( $post->ID, 'is_spotlight', true );
	$custom    = get_post_meta( $post->ID, 'custom_html', true );
	?>
	<p>
		<label style="margin-right:20px;"><input type="radio" name="content_mode" value="fields" <?php checked( 'fields', $mode ); ?> onchange="document.getElementById('bootg-tm-fields').style.display='';document.getElementById('bootg-tm-code').style.display='none';"> Structured fields (one review, styled by the theme)</label>
		<label><input type="radio" name="content_mode" value="custom_code" <?php checked( 'custom_code', $mode ); ?> onchange="document.getElementById('bootg-tm-fields').style.display='none';document.getElementById('bootg-tm-code').style.display='';"> Custom HTML / embed code</label>
	</p>

	<div id="bootg-tm-fields" style="<?php echo 'custom_code' === $mode ? 'display:none;' : ''; ?>">
		<table class="form-table">
			<tr><th style="width:220px;"><label for="quote">Quote</label></th><td><textarea id="quote" name="quote" rows="3" class="large-text"><?php echo esc_textarea( $quote ); ?></textarea></td></tr>
			<tr><th><label for="author_name">Author name</label></th><td><input type="text" id="author_name" name="author_name" value="<?php echo esc_attr( $name ); ?>" class="regular-text"></td></tr>
			<tr><th><label for="author_business">Author business / location</label></th><td><input type="text" id="author_business" name="author_business" value="<?php echo esc_attr( $business ); ?>" class="regular-text"></td></tr>
			<tr><th><label for="rating">Rating (1-5)</label></th><td><input type="number" min="1" max="5" id="rating" name="rating" value="<?php echo esc_attr( $rating ); ?>" class="small-text"></td></tr>
			<tr><th><label for="is_spotlight">Show in homepage spotlight</label></th><td><input type="checkbox" id="is_spotlight" name="is_spotlight" value="1" <?php checked( (bool) $spotlight, true ); ?>></td></tr>
		</table>
	</div>

	<div id="bootg-tm-code" style="<?php echo 'custom_code' === $mode ? '' : 'display:none;'; ?>">
		<p class="description" style="margin-bottom:8px;">Paste any HTML here — including a shortcode, like a third-party review widget's <code>[trustindex ...]</code>, or your own hand-built block with several reviews in it. It's rendered as-is (shortcodes included) wherever this entry's shortcode is placed.</p>
		<textarea name="custom_html" rows="12" class="large-text code" style="font-family:monospace;"><?php echo esc_textarea( $custom ); ?></textarea>
	</div>
	<?php
}

function bootg_render_testimonial_shortcode_box( $post ) {
	if ( 'auto-draft' === $post->post_status ) {
		echo '<p class="description">Save or publish this testimonial to get its shortcode.</p>';
		return;
	}
	$shortcode = '[bootg_testimonial id="' . $post->ID . '"]';
	$field_id  = 'bootg-shortcode-' . $post->ID;
	?>
	<p><input type="text" readonly id="<?php echo esc_attr( $field_id ); ?>" value="<?php echo esc_attr( $shortcode ); ?>" class="widefat" onclick="this.select();" style="font-family:monospace;"></p>
	<button type="button" class="button" onclick="var f=document.getElementById('<?php echo esc_js( $field_id ); ?>');f.select();document.execCommand('copy');this.textContent='Copied!';">Copy to clipboard</button>
	<p class="description" style="margin-top:10px;">Paste this into any page, post, or widget area.</p>
	<?php
}

add_action( 'save_post_testimonial', function ( $post_id ) {
	if ( ! isset( $_POST['bootg_testimonial_meta_nonce'] ) || ! wp_verify_nonce( $_POST['bootg_testimonial_meta_nonce'], 'bootg_save_testimonial_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	foreach ( bootg_testimonial_fields() as $key => $field ) {
		$type      = $field[1];
		$sanitizer = bootg_testimonial_meta_sanitizer( $type );
		if ( 'checkbox' === $type ) {
			update_post_meta( $post_id, $key, isset( $_POST[ $key ] ) ? 1 : 0 );
			continue;
		}
		if ( isset( $_POST[ $key ] ) ) {
			update_post_meta( $post_id, $key, call_user_func( $sanitizer, wp_unslash( $_POST[ $key ] ) ) );
		}
	}
} );

/** Renders one testimonial's structured-fields card — shared by the shortcode and the homepage spotlight. */
function bootg_render_testimonial_card( $post ) {
	$quote    = get_post_meta( $post->ID, 'quote', true );
	$name     = get_post_meta( $post->ID, 'author_name', true ) ?: get_the_title( $post );
	$business = get_post_meta( $post->ID, 'author_business', true );
	$rating   = (int) get_post_meta( $post->ID, 'rating', true ) ?: 5;
	$star     = '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 16 16"><path d="M3.612 15.443c-.386.198-.824-.149-.746-.592l.83-4.73L.173 6.765c-.329-.314-.158-.888.283-.95l4.898-.696L7.538.792c.197-.39.73-.39.927 0l2.184 4.327 4.898.696c.441.062.612.636.282.95l-3.522 3.356.83 4.73c.078.443-.36.79-.746.592L8 13.187l-4.389 2.256z"/></svg>';

	ob_start();
	?>
	<div class="bg-mist rounded-xl border border-slate-200 p-8 text-center reveal">
		<div class="flex justify-center gap-1 text-action mb-4" aria-label="<?php echo esc_attr( $rating ); ?> out of 5 stars">
			<?php echo str_repeat( $star, max( 1, min( 5, $rating ) ) ); // phpcs:ignore ?>
		</div>
		<blockquote class="text-lg font-display font-bold text-navy leading-snug mb-4">&ldquo;<?php echo esc_html( $quote ); ?>&rdquo;</blockquote>
		<p class="font-bold text-charcoal mb-0"><?php echo esc_html( $name ); ?></p>
		<?php if ( $business ) : ?>
			<p class="text-sm text-slate-400"><?php echo esc_html( $business ); ?></p>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

function bootg_render_testimonial_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'id' => 0 ), $atts );
	$post = get_post( (int) $atts['id'] );
	if ( ! $post || 'testimonial' !== $post->post_type || 'publish' !== $post->post_status ) {
		return '';
	}
	$mode = get_post_meta( $post->ID, 'content_mode', true ) ?: 'fields';
	if ( 'custom_code' === $mode ) {
		return do_shortcode( get_post_meta( $post->ID, 'custom_html', true ) );
	}
	return bootg_render_testimonial_card( $post );
}

/** "Shortcode" column on the Testimonials list screen, between Title and Date. */
add_filter( 'manage_testimonial_posts_columns', function ( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['bootg_shortcode'] = 'Shortcode';
			$new['bootg_mode']      = 'Mode';
		}
	}
	return $new;
} );

add_action( 'manage_testimonial_posts_custom_column', function ( $column, $post_id ) {
	if ( 'bootg_shortcode' === $column ) {
		echo '<code>[bootg_testimonial id="' . (int) $post_id . '"]</code>';
	}
	if ( 'bootg_mode' === $column ) {
		$mode = get_post_meta( $post_id, 'content_mode', true ) ?: 'fields';
		echo 'custom_code' === $mode ? 'Custom HTML' : 'Structured fields';
	}
}, 10, 2 );
