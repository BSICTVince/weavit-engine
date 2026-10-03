<?php
/**
 * Image Optimizer — an on-the-fly WebP image server for big JPG/PNG files.
 *
 * Front-end HTML: any local <img>/<source>/inline-style image over the size
 * threshold (default 250 KB) is rewritten to
 *   .../name.webp?sw=1082&sh=1082&sm=cut&sfrm=jpg&q=85
 * That URL doesn't exist on disk, so the web server hands it to WordPress,
 * which (below) resizes + converts the original once, caches the result in
 * wp-content/cache/weavit-img/, and serves it with far-future cache headers.
 * Take the ".webp?..." part off and you get the untouched original file
 * (name.jpg / name.png) exactly as uploaded.
 *
 * Query parameters:
 *   sw, sh  target width / height in px (never upscaled)
 *   sm      "cut" = fill sw x sh and crop the overflow, "fit" = fit inside it
 *   sfrm    source extension to look for (jpg | jpeg | png); optional
 *   q       WebP quality 30-95
 *   v       the original file's last-modified time; changes the URL whenever the image is replaced, so browsers never show a stale copy
 *
 * The URL-rewriting is the module toggle (Weavit > Modules); the endpoint
 * itself always answers, so pages cached while the module was on don't break.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WEAVIT_IMGOPT_OPTION', 'weavit_image_optimizer' );

function weavit_imgopt_settings() {
	$s = wp_parse_args( get_option( WEAVIT_IMGOPT_OPTION, array() ), array(
		'threshold_kb' => 250,
		'quality'      => 85,
		'max_width'    => 1600,
	) );
	$s['threshold_kb'] = min( 5000, max( 50, (int) $s['threshold_kb'] ) );
	$s['quality']      = min( 95, max( 30, (int) $s['quality'] ) );
	$s['max_width']    = min( 4000, max( 320, (int) $s['max_width'] ) );
	return $s;
}

function weavit_imgopt_cache_dir() {
	return WP_CONTENT_DIR . '/cache/weavit-img';
}

/**
 * Which image engine on this server can actually WRITE WebP: "imagick", "gd",
 * or '' if neither can. The optimizer always outputs WebP, so this decides
 * whether it can run at all. When both engines exist, whichever one can do
 * WebP is used (even if WordPress would normally pick the other one first).
 */
function weavit_imgopt_engine() {
	static $engine = null;
	if ( null !== $engine ) {
		return $engine;
	}
	$engine = '';
	require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
	require_once ABSPATH . WPINC . '/class-wp-image-editor-gd.php';
	require_once ABSPATH . WPINC . '/class-wp-image-editor-imagick.php';
	$args = array( 'mime_type' => 'image/webp' );

	if ( class_exists( 'Imagick' ) && WP_Image_Editor_Imagick::test( $args ) && WP_Image_Editor_Imagick::supports_mime_type( 'image/webp' ) ) {
		$engine = 'imagick';
	} elseif ( function_exists( 'imagewebp' ) && WP_Image_Editor_GD::test( $args ) && WP_Image_Editor_GD::supports_mime_type( 'image/webp' ) ) {
		$engine = 'gd';
	}
	return $engine;
}

function weavit_imgopt_webp_supported() {
	return '' !== weavit_imgopt_engine();
}

/** Absolute path of a file inside wp-content/{uploads,themes,plugins}, or '' if it isn't one (blocks path tricks). */
function weavit_imgopt_resolve( $rel ) {
	$base = realpath( WP_CONTENT_DIR );
	$path = $base ? realpath( $base . '/' . ltrim( $rel, '/' ) ) : false;
	if ( ! $path || ! is_file( $path ) || 0 !== strpos( $path, $base . DIRECTORY_SEPARATOR ) ) {
		return '';
	}
	$top = strtok( ltrim( substr( $path, strlen( $base ) ), '/\\' ), '/\\' );
	return in_array( $top, array( 'uploads', 'themes', 'plugins' ), true ) ? $path : '';
}

/* ---------------------------------------------------------------------
 * 1. The image endpoint
 * ------------------------------------------------------------------- */

add_action( 'init', 'weavit_imgopt_maybe_serve', 0 );

function weavit_imgopt_maybe_serve() {
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( false === stripos( $uri, '.webp' ) ) {
		return;
	}

	$parts = wp_parse_url( $uri );
	$path  = isset( $parts['path'] ) ? rawurldecode( $parts['path'] ) : '';
	$base  = (string) wp_parse_url( content_url(), PHP_URL_PATH );
	if ( ! preg_match( '/\.webp$/i', $path ) || 0 !== strpos( $path, $base . '/' ) ) {
		return;
	}

	$query = array();
	if ( ! empty( $parts['query'] ) ) {
		parse_str( $parts['query'], $query );
	}

	$candidates = array( 'jpg', 'jpeg', 'png', 'JPG', 'JPEG', 'PNG' );
	if ( ! empty( $query['sfrm'] ) ) {
		$sfrm = (string) $query['sfrm'];
		if ( ! preg_match( '/^(jpe?g|png)$/i', $sfrm ) ) {
			return;
		}
		$candidates = array( $sfrm );
	}

	$rel_webp = substr( $path, strlen( $base ) );
	$file     = '';
	$ext      = '';
	foreach ( $candidates as $candidate ) {
		$found = weavit_imgopt_resolve( preg_replace( '/\.webp$/i', '.' . $candidate, $rel_webp ) );
		if ( $found ) {
			$file = $found;
			$ext  = $candidate;
			break;
		}
	}
	if ( ! $file ) {
		return; // Not ours — let WordPress carry on (normal 404).
	}

	$sw   = isset( $query['sw'] ) ? min( 4000, max( 0, (int) $query['sw'] ) ) : 0;
	$sh   = isset( $query['sh'] ) ? min( 4000, max( 0, (int) $query['sh'] ) ) : 0;
	$mode = ( isset( $query['sm'] ) && 'cut' === $query['sm'] ) ? 'cut' : 'fit';
	$q    = isset( $query['q'] ) ? min( 95, max( 30, (int) $query['q'] ) ) : weavit_imgopt_settings()['quality'];

	$cache = weavit_imgopt_cache_path( $file, $sw, $sh, $mode, $q );
	$key   = basename( $cache, '.webp' );

	if ( ! is_file( $cache ) && ! weavit_imgopt_generate( $file, $cache, $sw, $sh, $mode, $q ) ) {
		// Can't convert on this server — send the visitor to the untouched original.
		wp_redirect( content_url( preg_replace( '/\.webp$/i', '.' . $ext, $rel_webp ) ), 302 ); // phpcs:ignore WordPress.Security.SafeRedirect
		exit;
	}

	$etag = '"' . $key . '"';
	if ( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) && trim( (string) $_SERVER['HTTP_IF_NONE_MATCH'] ) === $etag ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		status_header( 304 );
		exit;
	}

	status_header( 200 );
	header( 'Content-Type: image/webp' );
	header( 'Content-Length: ' . filesize( $cache ) );
	// A URL carrying the version stamp (v=) changes whenever the image does, so browsers can keep it for a year.
	// Without it (old cached HTML, a hand-typed link) we can't know it's current, so only allow an hour.
	$versioned = isset( $query['v'] ) && ctype_digit( (string) $query['v'] ) && (int) $query['v'] === (int) filemtime( $file );
	header( 'Cache-Control: public, ' . ( $versioned ? 'max-age=31536000, immutable' : 'max-age=3600, must-revalidate' ) );
	header( 'ETag: ' . $etag );
	header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', filemtime( $cache ) ) . ' GMT' );
	header( 'X-Weavit-Image: webp' );
	readfile( $cache ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}

/** Resize + convert $source into a WebP at $dest. Returns true on success. */
function weavit_imgopt_generate( $source, $dest, $sw, $sh, $mode, $quality ) {
	$engine = weavit_imgopt_engine();
	if ( ! $engine ) {
		return false;
	}
	wp_raise_memory_limit( 'image' );

	$only = array( 'imagick' === $engine ? 'WP_Image_Editor_Imagick' : 'WP_Image_Editor_GD' );
	$pick = function () use ( $only ) {
		return $only;
	};
	add_filter( 'wp_image_editors', $pick, PHP_INT_MAX );
	$editor = wp_get_image_editor( $source, array( 'mime_type' => 'image/webp' ) );
	remove_filter( 'wp_image_editors', $pick, PHP_INT_MAX );
	if ( is_wp_error( $editor ) ) {
		return false;
	}

	if ( $sw || $sh ) {
		$size = $editor->get_size();
		$w    = min( $sw ?: $size['width'], $size['width'] );
		$h    = min( $sh ?: $size['height'], $size['height'] );
		$editor->resize( $w, $h, 'cut' === $mode && $sw && $sh ); // A WP_Error here just means "nothing to resize".
	}
	$editor->set_quality( $quality );

	$dir = dirname( $dest );
	if ( ! wp_mkdir_p( $dir ) ) {
		return false;
	}
	$tmp   = $dir . '/' . wp_generate_password( 12, false ) . '.tmp.webp';
	$saved = $editor->save( $tmp, 'image/webp' );
	if ( is_wp_error( $saved ) || empty( $saved['path'] ) || ! is_file( $saved['path'] ) ) {
		return false;
	}
	if ( ! rename( $saved['path'], $dest ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
		return false;
	}
	weavit_imgopt_purge_file( $source, filemtime( $source ) ); // Drop copies made from an older version of this file.
	return true;
}

/** Where the converted WebP for these exact settings lives (whether or not it exists yet). */
function weavit_imgopt_cache_path( $file, $sw, $sh, $mode, $quality ) {
	$file = realpath( $file ) ?: $file;
	$fid  = substr( md5( $file ), 0, 12 );
	$name = $fid . '-' . filemtime( $file ) . '-' . substr( md5( $sw . 'x' . $sh . '|' . $mode . '|' . $quality ), 0, 10 ) . '.webp';
	return weavit_imgopt_cache_dir() . '/' . substr( $fid, 0, 2 ) . '/' . $name;
}

/**
 * Deletes the cached WebP copies of one original file (every size/quality).
 * $keep_mtime: leave the copies made from this version of the file alone, so
 * only stale copies of an overwritten image are removed. Returns the count.
 */
function weavit_imgopt_purge_file( $file, $keep_mtime = null ) {
	$file    = realpath( $file ) ?: $file;
	$fid     = substr( md5( $file ), 0, 12 );
	$removed = 0;
	foreach ( (array) glob( weavit_imgopt_cache_dir() . '/' . substr( $fid, 0, 2 ) . '/' . $fid . '-*.webp' ) as $copy ) {
		if ( null !== $keep_mtime && 0 === strpos( basename( $copy ), $fid . '-' . $keep_mtime . '-' ) ) {
			continue;
		}
		if ( @unlink( $copy ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			++$removed;
		}
	}
	return $removed;
}

/** Empties the whole WebP cache. Returns how many files were removed. */
function weavit_imgopt_clear_cache() {
	$dir     = weavit_imgopt_cache_dir();
	$removed = 0;
	if ( is_dir( $dir ) ) {
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST ) as $f ) {
			if ( $f->isDir() ) {
				@rmdir( $f->getPathname() ); // phpcs:ignore
			} elseif ( @unlink( $f->getPathname() ) ) { // phpcs:ignore
				++$removed;
			}
		}
	}
	return $removed;
}

/**
 * The [width, height] an image will be served at, or null when it doesn't
 * need optimizing (not a JPG/PNG, or not over the size limit). This is the one
 * place that decides "too heavy", so page rewriting, the upload hook, the
 * Media Library column and the scan all agree.
 */
function weavit_imgopt_target( $file ) {
	if ( ! is_file( $file ) || ! preg_match( '/\.(jpe?g|png)$/i', $file ) ) {
		return null;
	}
	$set = weavit_imgopt_settings();
	if ( filesize( $file ) <= $set['threshold_kb'] * 1024 ) {
		return null;
	}
	$dim = @getimagesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	if ( ! $dim || $dim[0] < 1 || $dim[1] < 1 ) {
		return null;
	}
	$w = (int) $dim[0];
	$h = (int) $dim[1];
	if ( $w > $set['max_width'] ) {
		$h = (int) round( $h * $set['max_width'] / $w );
		$w = $set['max_width'];
	}
	return array( $w, $h );
}

/** Converts one file now (instead of on its first visit). Returns 'skipped', 'cached', 'created' or 'failed'. */
function weavit_imgopt_warm( $file ) {
	$target = weavit_imgopt_target( $file );
	if ( ! $target ) {
		return 'skipped';
	}
	$q     = weavit_imgopt_settings()['quality'];
	$cache = weavit_imgopt_cache_path( $file, $target[0], $target[1], 'cut', $q );
	if ( is_file( $cache ) ) {
		return 'cached';
	}
	return weavit_imgopt_generate( $file, $cache, $target[0], $target[1], 'cut', $q ) ? 'created' : 'failed';
}

/** Every JPG/PNG over the size limit in the Media Library folder and the active theme's assets, biggest first. */
function weavit_imgopt_scan() {
	$set   = weavit_imgopt_settings();
	$limit = $set['threshold_kb'] * 1024;
	$rows  = array();
	$seen  = 0;
	$roots = array(
		'Media Library' => WP_CONTENT_DIR . '/uploads',
		'Theme'         => get_template_directory() . '/assets',
	);
	foreach ( $roots as $label => $root ) {
		if ( ! is_dir( $root ) ) {
			continue;
		}
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) ) as $f ) {
			if ( ++$seen > 30000 ) {
				break 2;
			}
			if ( ! $f->isFile() || $f->getSize() <= $limit || ! preg_match( '/\.(jpe?g|png)$/i', $f->getFilename() ) || false !== strpos( $f->getPathname(), DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'weavit-img' ) ) {
				continue;
			}
			$file   = $f->getPathname();
			$dim    = @getimagesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			$target = weavit_imgopt_target( $file );
			$webp   = 0;
			if ( $target ) {
				$cache = weavit_imgopt_cache_path( $file, $target[0], $target[1], 'cut', $set['quality'] );
				$webp  = is_file( $cache ) ? filesize( $cache ) : 0;
			}
			$rows[] = array(
				'file'  => $file,
				'rel'   => str_replace( '\\', '/', str_replace( WP_CONTENT_DIR, '', $file ) ),
				'where' => $label,
				'size'  => $f->getSize(),
				'dim'   => $dim ? $dim[0] . ' × ' . $dim[1] : '?',
				'webp'  => $webp,
			);
		}
	}
	usort( $rows, function ( $a, $b ) {
		return $b['size'] - $a['size'];
	} );
	return $rows;
}

/* ---------------------------------------------------------------------
 * 2. Rewriting front-end HTML
 * ------------------------------------------------------------------- */

add_action( 'template_redirect', function () {
	if (
		! bootg_module_enabled( 'image-optimizer' )
		|| ! weavit_imgopt_webp_supported()
		|| is_admin() || is_feed() || is_robots() || is_trackback() || is_customize_preview()
		|| ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_doing_ajax()
		|| ! empty( $_GET['weavit_noopt'] ) // phpcs:ignore WordPress.Security.NonceVerification
	) {
		return;
	}
	ob_start( 'weavit_imgopt_filter_html' );
}, 1 );

/**
 * Returns the optimized URL for a local JPG/PNG over the threshold, or the URL
 * unchanged. $html_context: true when the URL sits inside an HTML attribute
 * (query separators are written as &amp;), false inside a raw <style> block.
 */
function weavit_imgopt_convert_url( $url, $html_context = true ) {
	static $memo = array();
	$key = ( $html_context ? 'h|' : 'c|' ) . $url;
	if ( isset( $memo[ $key ] ) ) {
		return $memo[ $key ];
	}
	$memo[ $key ] = $url;

	if ( ! preg_match( '#\.(jpe?g|png)$#i', $url, $m ) || false !== strpos( $url, '?' ) || 0 === stripos( $url, 'data:' ) ) {
		return $url;
	}
	$ext  = $m[1];
	$host = wp_parse_url( $url, PHP_URL_HOST );
	if ( $host && 0 !== strcasecmp( $host, (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ) {
		return $url;
	}
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	$base = (string) wp_parse_url( content_url(), PHP_URL_PATH );
	if ( ! $host && 0 !== strpos( $path, '/' ) && 0 === strpos( $path, ltrim( $base, '/' ) . '/' ) ) {
		$path = '/' . $path; // "wp-content/uploads/x.png" written without the leading slash.
	}
	if ( 0 !== strpos( $path, $base . '/' ) ) {
		return $url;
	}

	$file   = weavit_imgopt_resolve( rawurldecode( substr( $path, strlen( $base ) ) ) );
	$set    = weavit_imgopt_settings();
	$target = $file ? weavit_imgopt_target( $file ) : null;
	if ( ! $target ) {
		return $url;
	}
	list( $w, $h ) = $target;

	$amp = $html_context ? '&amp;' : '&';
	$memo[ $key ] = preg_replace( '#\.(jpe?g|png)$#i', '.webp', $url )
		. '?sw=' . $w . $amp . 'sh=' . $h . $amp . 'sm=cut' . $amp . 'sfrm=' . strtolower( $ext ) . $amp . 'q=' . $set['quality'] . $amp . 'v=' . filemtime( $file );
	return $memo[ $key ];
}

/** Rewrites every url(...) in a chunk of CSS: quoted, unquoted, or with HTML-escaped quotes (&quot;). */
function weavit_imgopt_filter_css( $css, $html_context ) {
	if ( false === stripos( $css, 'url(' ) ) {
		return $css;
	}
	return preg_replace_callback(
		'/url\(\s*(?:(&quot;|&#0*39;|&#x27;|"|\')\s*([^\'"()\s&]+?)\s*\1|([^\'"()\s&]+?))\s*\)/i',
		function ( $m ) use ( $html_context ) {
			$quoted = isset( $m[2] ) && '' !== $m[2];
			$url    = $quoted ? $m[2] : ( $m[3] ?? '' );
			if ( '' === $url ) {
				return $m[0];
			}
			$new = weavit_imgopt_convert_url( $url, $html_context );
			if ( $new === $url ) {
				return $m[0];
			}
			return 'url(' . ( $quoted ? $m[1] . $new . $m[1] : $new ) . ')';
		},
		$css
	);
}

/** Rewrites one srcset-style value ("a.jpg 1x, b.jpg 2x"). */
function weavit_imgopt_filter_srcset( $value ) {
	$items = array_map( function ( $item ) {
		$bits = preg_split( '/\s+/', trim( $item ), 2 );
		return weavit_imgopt_convert_url( $bits[0] ) . ( isset( $bits[1] ) ? ' ' . $bits[1] : '' );
	}, explode( ',', $value ) );
	return implode( ', ', $items );
}

/**
 * The whole page, after WordPress has finished building it. Covers:
 *   - <img>/<source>/<video poster>/lazy-load attributes (data-src, data-bg, ...)
 *   - <link rel="preload" as="image">
 *   - inline style="background-image:url(...)" on any element
 *   - <style> blocks (CSS that a theme/page builder prints into the page)
 * Whatever produced the markup (theme, page builder, shortcode), it all
 * passes through here, so it works on any WordPress site. External .css
 * files are not touched (they're static files WordPress never sees).
 */
function weavit_imgopt_filter_html( $html ) {
	if ( ! is_string( $html ) || '' === $html || ( false === stripos( $html, '.jpg' ) && false === stripos( $html, '.jpeg' ) && false === stripos( $html, '.png' ) ) ) {
		return $html;
	}

	// Every start tag: pick out the attributes that can hold an image.
	$html = preg_replace_callback( '/<[a-z][a-z0-9-]*\b[^>]*>/i', function ( $tag ) {
		$is_image_preload = (bool) preg_match( '/^<link\b/i', $tag[0] ) && (bool) preg_match( '/\bas\s*=\s*["\']?image\b/i', $tag[0] );

		return preg_replace_callback( '/(?<![\w-])([a-z][a-z0-9_:-]*)\s*=\s*(["\'])(.*?)\2/is', function ( $a ) use ( $is_image_preload ) {
			$name  = strtolower( $a[1] );
			$value = $a[3];
			$quote = $a[2];

			if ( 'style' === $name ) {
				return $a[1] . '=' . $quote . weavit_imgopt_filter_css( $value, true ) . $quote;
			}
			if ( false !== strpos( $name, 'srcset' ) ) {
				return $a[1] . '=' . $quote . weavit_imgopt_filter_srcset( $value ) . $quote;
			}
			if ( 'src' === $name || 'poster' === $name || 0 === strpos( $name, 'data-' ) || ( 'href' === $name && $is_image_preload ) ) {
				if ( false !== stripos( $value, 'url(' ) ) {
					return $a[1] . '=' . $quote . weavit_imgopt_filter_css( $value, true ) . $quote; // e.g. data-bg="url(image.png)"
				}
				$trimmed = trim( $value );
				if ( $trimmed === $value && ! preg_match( '/\s/', $value ) ) {
					return $a[1] . '=' . $quote . weavit_imgopt_convert_url( $value ) . $quote;
				}
			}
			return $a[0];
		}, $tag[0] );
	}, $html );

	// CSS printed straight into the page: <style> ... </style>.
	$html = preg_replace_callback( '/(<style\b[^>]*>)(.*?)(<\/style>)/is', function ( $m ) {
		return $m[1] . weavit_imgopt_filter_css( $m[2], false ) . $m[3];
	}, $html );

	return $html;
}

/* ---------------------------------------------------------------------
 * 3. Settings page: threshold / quality / max width, status, big-file list
 * ------------------------------------------------------------------- */

add_action( 'admin_menu', function () {
	add_submenu_page( 'weavit', 'Image Optimizer', 'Image Optimizer', 'manage_options', 'weavit-image-optimizer', 'weavit_imgopt_render_page' );
} );

add_action( 'admin_post_weavit_imgopt_save', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'weavit_imgopt_save' );
	$want                       = ! empty( $_POST['enabled'] );
	$blocked                    = $want && ! weavit_imgopt_webp_supported();
	$modules                    = get_option( BOOTG_ENABLED_MODULES_OPTION, array() );
	$modules['image-optimizer'] = $want && ! $blocked;
	update_option( BOOTG_ENABLED_MODULES_OPTION, $modules );
	update_option( WEAVIT_IMGOPT_OPTION, array(
		'threshold_kb' => min( 5000, max( 50, absint( $_POST['threshold_kb'] ?? 250 ) ) ),
		'quality'      => min( 95, max( 30, absint( $_POST['quality'] ?? 85 ) ) ),
		'max_width'    => min( 4000, max( 320, absint( $_POST['max_width'] ?? 1600 ) ) ),
	) );
	wp_safe_redirect( add_query_arg( array( 'page' => 'weavit-image-optimizer', ( $blocked ? 'unsupported' : 'saved' ) => 1 ), admin_url( 'admin.php' ) ) );
	exit;
} );

add_action( 'admin_post_weavit_imgopt_clear', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'weavit_imgopt_clear' );
	weavit_imgopt_clear_cache();
	wp_safe_redirect( add_query_arg( array( 'page' => 'weavit-image-optimizer', 'cleared' => 1 ), admin_url( 'admin.php' ) ) );
	exit;
} );

function weavit_imgopt_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s       = weavit_imgopt_settings();
	$limit   = $s['threshold_kb'] * 1024;
	$enabled = bootg_module_enabled( 'image-optimizer' );
	$engine  = weavit_imgopt_engine();
	$webp_ok = '' !== $engine;

	$cache_files = 0;
	$cache_bytes = 0;
	$dir         = weavit_imgopt_cache_dir();
	if ( is_dir( $dir ) ) {
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) ) as $f ) {
			if ( $f->isFile() ) {
				++$cache_files;
				$cache_bytes += $f->getSize();
			}
		}
	}

	$big = weavit_imgopt_scan();
	$todo = 0;
	foreach ( $big as $row ) {
		if ( ! $row['webp'] ) {
			++$todo;
		}
	}
	?>
	<div class="wrap">
		<h1>Image Optimizer</h1>
		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
		<?php elseif ( isset( $_GET['unsupported'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-error"><p><strong>Couldn't switch the Image Optimizer on:</strong> this server can't create WebP images (see "WebP conversion" below). Settings were saved, but the optimizer stays off.</p></div>
		<?php elseif ( isset( $_GET['warmed'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success is-dismissible"><p>Converted <?php echo (int) $_GET['warmed']; // phpcs:ignore WordPress.Security.NonceVerification ?> image(s)<?php echo ! empty( $_GET['left'] ) ? '. ' . (int) $_GET['left'] . ' still to go — click "Optimize all now" again.' : '.'; // phpcs:ignore WordPress.Security.NonceVerification ?></p></div>
		<?php elseif ( isset( $_GET['cleared'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success is-dismissible"><p>Image cache cleared — images are re-converted on their next visit.</p></div>
		<?php endif; ?>

		<p style="max-width:720px;">Serves JPG/PNG files over the size limit as resized WebP on the front-end, e.g. <code>photo.webp?sw=1082&amp;sh=1082&amp;sm=cut&amp;sfrm=jpg&amp;q=85</code>. Remove the <code>.webp?…</code> part and you get the untouched original (<code>photo.jpg</code>). Originals are never modified.</p>

		<table class="widefat striped" style="max-width:720px;margin-bottom:20px;">
			<tr><th style="width:220px;">Module</th><td><?php echo $enabled ? '<strong style="color:#00a32a;">On</strong>' : '<strong style="color:#b32d2e;">Off</strong> — turn it on under <a href="' . esc_url( admin_url( 'admin.php?page=weavit-modules' ) ) . '">Weavit &rarr; Modules</a>'; ?></td></tr>
			<tr><th>WebP conversion on this server</th><td><?php echo $webp_ok ? '<strong style="color:#00a32a;">Supported</strong> via ' . ( 'imagick' === $engine ? 'Imagick' : 'GD' ) . ' — the optimizer always outputs WebP.' : '<strong style="color:#b32d2e;">Not supported</strong> — the optimizer can\'t run here, so visitors keep getting the original images. Ask your host to enable WebP in PHP (the GD extension compiled with WebP, or Imagick with WebP), then come back and switch it on.'; ?></td></tr>
			<tr><th>Cached conversions</th><td><?php echo (int) $cache_files; ?> file(s), <?php echo esc_html( size_format( $cache_bytes ) ); ?></td></tr>
		</table>

		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<?php wp_nonce_field( 'weavit_imgopt_save' ); ?>
			<input type="hidden" name="action" value="weavit_imgopt_save">
			<table class="form-table" role="presentation">
				<tr><th>Image Optimizer</th><td><label><input type="checkbox" name="enabled" value="1" <?php checked( $enabled ); ?> <?php disabled( ! $webp_ok ); ?>> Enable — serve big images as WebP on the front-end</label><p class="description">Untick and save to switch it off: pages go back to the original images straight away. Already-cached WebP links keep working.</p></td></tr>
				<tr><th><label for="threshold_kb">Optimize images larger than</label></th><td><input type="number" id="threshold_kb" name="threshold_kb" value="<?php echo (int) $s['threshold_kb']; ?>" min="50" max="5000" class="small-text"> KB</td></tr>
				<tr><th><label for="quality">WebP quality</label></th><td><input type="number" id="quality" name="quality" value="<?php echo (int) $s['quality']; ?>" min="30" max="95" class="small-text"> <span class="description">(85 is a good balance)</span></td></tr>
				<tr><th><label for="max_width">Maximum width</label></th><td><input type="number" id="max_width" name="max_width" value="<?php echo (int) $s['max_width']; ?>" min="320" max="4000" class="small-text"> px <span class="description">(wider images are scaled down, never up)</span></td></tr>
			</table>
			<?php submit_button( 'Save Settings', 'primary', 'submit', false ); ?>
		</form>

		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="margin-top:12px;">
			<?php wp_nonce_field( 'weavit_imgopt_clear' ); ?>
			<input type="hidden" name="action" value="weavit_imgopt_clear">
			<?php submit_button( 'Clear image cache', 'secondary', 'submit', false ); ?>
		</form>

		<h2 style="margin-top:32px;">Images over <?php echo (int) $s['threshold_kb']; ?> KB</h2>
		<p class="description" style="max-width:760px;">Every JPG/PNG in your Media Library folder and the theme's assets is checked against the limit. Heavy ones are converted the first time a visitor loads them — or all at once with the button below. New uploads over the limit are converted automatically.</p>
		<?php if ( ! $big ) : ?>
			<p><strong>None found — nothing needs optimizing.</strong></p>
		<?php else : ?>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="margin:10px 0;">
				<?php wp_nonce_field( 'weavit_imgopt_warm' ); ?>
				<input type="hidden" name="action" value="weavit_imgopt_warm">
				<?php submit_button( 'Optimize all now' . ( $todo ? ' (' . $todo . ' waiting)' : '' ), 'secondary', 'submit', false, $webp_ok && $todo ? array() : array( 'disabled' => 'disabled' ) ); ?>
			</form>
			<table class="widefat striped" style="max-width:1000px;">
				<thead><tr><th>File</th><th style="width:90px;">Where</th><th style="width:110px;">Dimensions</th><th style="width:90px;">Size</th><th style="width:170px;">WebP version</th></tr></thead>
				<tbody>
				<?php foreach ( array_slice( $big, 0, 200 ) as $row ) : ?>
					<tr>
						<td><code><?php echo esc_html( $row['rel'] ); ?></code></td>
						<td><?php echo esc_html( $row['where'] ); ?></td>
						<td><?php echo esc_html( $row['dim'] ); ?></td>
						<td><?php echo esc_html( size_format( $row['size'] ) ); ?></td>
						<td><?php echo $row['webp'] ? '<strong style="color:#00a32a;">' . esc_html( size_format( $row['webp'] ) ) . '</strong> (' . (int) round( 100 - $row['webp'] / $row['size'] * 100 ) . '% smaller)' : '<span style="color:#b32d2e;">not converted yet</span>'; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) || ! bootg_module_enabled( 'image-optimizer' ) || weavit_imgopt_webp_supported() ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || ( false === strpos( (string) $screen->id, 'weavit' ) && 'plugins' !== $screen->id ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p><strong>Image Optimizer is on, but this server can\'t create WebP images,</strong> so it is doing nothing and visitors get the original images. Ask your host to enable WebP in PHP (GD with WebP, or Imagick with WebP).</p></div>';
} );

add_action( 'admin_post_weavit_imgopt_warm', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'weavit_imgopt_warm' );
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 120 ); // phpcs:ignore
	}
	$done     = 0;
	$left     = 0;
	$deadline = microtime( true ) + 45; // Stay well inside a typical PHP time limit; click again for the rest.
	foreach ( weavit_imgopt_scan() as $row ) {
		if ( $row['webp'] ) {
			continue;
		}
		if ( microtime( true ) > $deadline ) {
			++$left;
			continue;
		}
		if ( 'created' === weavit_imgopt_warm( $row['file'] ) ) {
			++$done;
		}
	}
	wp_safe_redirect( add_query_arg( array( 'page' => 'weavit-image-optimizer', 'warmed' => $done, 'left' => $left ), admin_url( 'admin.php' ) ) );
	exit;
} );

/* ---------------------------------------------------------------------
 * 4. Detect heavy images as they're uploaded, and flag them in the Media Library
 * ------------------------------------------------------------------- */

// A new upload over the limit is converted right away (the full image and any generated size that's still heavy).
add_filter( 'wp_generate_attachment_metadata', function ( $meta, $attachment_id ) {
	if ( ! bootg_module_enabled( 'image-optimizer' ) || ! weavit_imgopt_webp_supported() ) {
		return $meta;
	}
	$file = get_attached_file( $attachment_id );
	if ( ! $file ) {
		return $meta;
	}
	weavit_imgopt_warm( $file );
	if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
		foreach ( $meta['sizes'] as $size ) {
			if ( ! empty( $size['file'] ) ) {
				weavit_imgopt_warm( dirname( $file ) . '/' . $size['file'] );
			}
		}
	}
	return $meta;
}, 20, 2 );

add_filter( 'manage_media_columns', function ( $columns ) {
	$columns['weavit_imgopt'] = 'Image Optimizer';
	return $columns;
} );

add_action( 'manage_media_custom_column', function ( $column, $attachment_id ) {
	if ( 'weavit_imgopt' !== $column ) {
		return;
	}
	$file = get_attached_file( $attachment_id );
	if ( ! $file || ! is_file( $file ) || ! preg_match( '/\.(jpe?g|png)$/i', $file ) ) {
		echo '&mdash;';
		return;
	}
	$size   = filesize( $file );
	$target = weavit_imgopt_target( $file );
	if ( ! $target ) {
		echo '<span style="color:#646970;">OK &middot; ' . esc_html( size_format( $size ) ) . '</span>';
		return;
	}
	$cache = weavit_imgopt_cache_path( $file, $target[0], $target[1], 'cut', weavit_imgopt_settings()['quality'] );
	if ( is_file( $cache ) ) {
		echo '<strong style="color:#00a32a;">WebP ' . esc_html( size_format( filesize( $cache ) ) ) . '</strong><br><small>original ' . esc_html( size_format( $size ) ) . '</small>';
	} else {
		echo '<strong style="color:#b32d2e;">Heavy ' . esc_html( size_format( $size ) ) . '</strong><br><small>not converted yet</small>';
	}
}, 10, 2 );
