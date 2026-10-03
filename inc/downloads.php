<?php
/**
 * Downloads — a standalone "upload a file, get a link" feature, like a
 * plugin's media-download manager. Not tied to any one page: upload (or
 * pick an existing Media Library file) once in Weavit → Downloads, then
 * drop the generated [weavit_download] shortcode or copy its direct link
 * into any page, post, or template.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	if ( ! bootg_module_enabled( 'downloads' ) ) {
		return;
	}
	register_post_type( 'weavit_download', array(
		'labels'       => array(
			'name'               => 'Downloads',
			'singular_name'      => 'Download',
			'add_new_item'       => 'Add New Download',
			'edit_item'          => 'Edit Download',
			'all_items'          => 'Downloads',
			'search_items'       => 'Search Downloads',
			'not_found'          => 'No downloads yet.',
		),
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => 'weavit',
		'supports'     => array( 'title' ),
		'capability_type' => 'post',
		'map_meta_cap' => true,
	) );

	add_shortcode( 'weavit_download', function ( $atts ) {
		$atts = shortcode_atts( array(
			'id'    => 0,
			'slug'  => '',
			'label' => '',
			'class' => 'button',
		), $atts, 'weavit_download' );

		$post_id = (int) $atts['id'];
		if ( ! $post_id && $atts['slug'] ) {
			$post    = get_page_by_path( $atts['slug'], OBJECT, 'weavit_download' );
			$post_id = $post ? $post->ID : 0;
		}
		if ( ! $post_id ) {
			return '';
		}

		$url = weavit_download_file_url( $post_id );
		if ( ! $url ) {
			return '';
		}

		$label = $atts['label'] ?: get_the_title( $post_id );

		return '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $atts['class'] ) . '" download rel="noopener">' . esc_html( $label ) . '</a>';
	} );
} );

/** The attachment URL for a Download post, or '' if it has no file. */
function weavit_download_file_url( $post_id ) {
	$attachment_id = (int) get_post_meta( $post_id, '_weavit_file_id', true );
	if ( ! $attachment_id ) {
		return '';
	}
	return (string) wp_get_attachment_url( $attachment_id );
}

/** Looks up a Download by slug (post_name) and returns its file URL, or '' if not found/no file. */
function weavit_download_url_by_slug( $slug ) {
	$post = get_page_by_path( $slug, OBJECT, 'weavit_download' );
	return $post ? weavit_download_file_url( $post->ID ) : '';
}

/**
 * Idempotent "create a Download from an external URL" — sideloads the
 * file into the Media Library and creates the Download post if a
 * published Download with this slug doesn't already exist. For seeding
 * known downloads (e.g. during a theme's Starter Site import) without
 * duplicating the file on every re-run.
 */
function weavit_create_download_from_url( $slug, $title, $source_url ) {
	$existing = get_page_by_path( $slug, OBJECT, 'weavit_download' );
	if ( $existing ) {
		return $existing->ID;
	}

	$attachment_id = weavit_sideload_file( $source_url, 0, $title );
	if ( is_wp_error( $attachment_id ) ) {
		return $attachment_id;
	}

	$post_id = wp_insert_post( array(
		'post_type'   => 'weavit_download',
		'post_title'  => $title,
		'post_name'   => $slug,
		'post_status' => 'publish',
	), true );
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	update_post_meta( $post_id, '_weavit_file_id', $attachment_id );

	return $post_id;
}

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'weavit_download_file', 'File', 'weavit_render_download_file_meta_box', 'weavit_download', 'normal', 'high' );
} );

function weavit_render_download_file_meta_box( $post ) {
	wp_nonce_field( 'weavit_save_download_file', 'weavit_download_file_nonce' );
	$attachment_id = (int) get_post_meta( $post->ID, '_weavit_file_id', true );
	$filename      = $attachment_id ? basename( get_attached_file( $attachment_id ) ) : '';
	?>
	<p>
		<input type="hidden" name="weavit_file_id" id="weavit_file_id" value="<?php echo esc_attr( $attachment_id ); ?>">
		<span id="weavit_file_name"><?php echo $filename ? esc_html( $filename ) : 'No file selected.'; ?></span>
	</p>
	<p>
		<button type="button" class="button" id="weavit_file_select">Select or Upload File</button>
		<button type="button" class="button" id="weavit_file_clear" <?php echo $attachment_id ? '' : 'style="display:none;"'; ?>>Remove File</button>
	</p>
	<?php if ( $attachment_id ) : ?>
		<p class="description">Shortcode: <code>[weavit_download id="<?php echo (int) $post->ID; ?>"]</code> &middot; Direct link: <code><?php echo esc_html( weavit_download_file_url( $post->ID ) ); ?></code></p>
	<?php endif; ?>
	<script>
	( function () {
		var frame;
		document.getElementById( 'weavit_file_select' ).addEventListener( 'click', function ( e ) {
			e.preventDefault();
			if ( frame ) { frame.open(); return; }
			frame = wp.media( { title: 'Select or Upload File', button: { text: 'Use this file' }, multiple: false } );
			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				document.getElementById( 'weavit_file_id' ).value = attachment.id;
				document.getElementById( 'weavit_file_name' ).textContent = attachment.filename || attachment.title;
				document.getElementById( 'weavit_file_clear' ).style.display = '';
			} );
			frame.open();
		} );
		document.getElementById( 'weavit_file_clear' ).addEventListener( 'click', function ( e ) {
			e.preventDefault();
			document.getElementById( 'weavit_file_id' ).value = '';
			document.getElementById( 'weavit_file_name' ).textContent = 'No file selected.';
			this.style.display = 'none';
		} );
	} )();
	</script>
	<?php
}

add_action( 'save_post_weavit_download', function ( $post_id ) {
	if ( ! isset( $_POST['weavit_download_file_nonce'] ) || ! wp_verify_nonce( $_POST['weavit_download_file_nonce'], 'weavit_save_download_file' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$attachment_id = isset( $_POST['weavit_file_id'] ) ? (int) $_POST['weavit_file_id'] : 0;
	if ( $attachment_id ) {
		update_post_meta( $post_id, '_weavit_file_id', $attachment_id );
	} else {
		delete_post_meta( $post_id, '_weavit_file_id' );
	}
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	global $post_type;
	if ( 'weavit_download' === $post_type && in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		wp_enqueue_media();
	}
} );

add_filter( 'manage_weavit_download_posts_columns', function ( $columns ) {
	$columns['weavit_download_link'] = 'Link';
	return $columns;
} );

add_action( 'manage_weavit_download_posts_custom_column', function ( $column, $post_id ) {
	if ( 'weavit_download_link' !== $column ) {
		return;
	}
	$url = weavit_download_file_url( $post_id );
	if ( ! $url ) {
		echo '&mdash;';
		return;
	}
	echo '<code style="font-size:11px;">[weavit_download id="' . (int) $post_id . '"]</code><br><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener" style="font-size:12px;">' . esc_html( $url ) . '</a>';
}, 10, 2 );
