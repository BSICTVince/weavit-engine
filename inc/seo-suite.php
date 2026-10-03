<?php
/**
 * SEO Suite — the site-wide SEO features that sit beside the per-page
 * Meta Title/Description fields (inc/seo-meta.php):
 *
 *   Titles & Social   title format, automatic meta descriptions, Open Graph +
 *                     Twitter cards, "noindex" rules
 *   Sitemap & Robots  which content goes in /wp-sitemap.xml, extra robots.txt lines
 *   (+ inc/seo-schema.php, seo-404.php, seo-analyser.php)
 *
 * Gated by the "SEO Suite" module (Weavit > Modules). It also stands down on
 * its own while Rank Math, Yoast, All in One SEO or SEOPress is active, so two
 * plugins never print the same tags twice.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WEAVIT_SEO_OPTION', 'weavit_seo_settings' );

function weavit_seo_defaults() {
	return array(
		// Titles & Social.
		'separator'           => '|',
		'append_site_name'    => 1,
		'auto_description'    => 1,
		'noindex_search'      => 1,
		'noindex_attachments' => 1,
		'social_enabled'      => 1,
		'default_image'       => '',
		'twitter_handle'      => '',
		// Schema.
		'schema_enabled'      => 1,
		'schema_type'         => 'AccountingService',
		'legal_name'          => '',
		'street'              => '',
		'locality'            => 'Perth',
		'region'              => 'WA',
		'postcode'            => '',
		'country'             => 'AU',
		'hours'               => '',
		'area_served'         => "Perth, Western Australia\nAustralia",
		'price_range'         => '',
		// Sitemap & robots.
		'sitemap_types'       => array( 'page', 'post', 'service', 'integration', 'guide', 'team_member' ),
		'sitemap_users'       => 0,
		'robots_extra'        => '',
	);
}

function weavit_seo_settings() {
	return wp_parse_args( get_option( WEAVIT_SEO_OPTION, array() ), weavit_seo_defaults() );
}

/** Name of an active SEO plugin that already does this job, or ''. */
function weavit_seo_conflict() {
	if ( defined( 'RANK_MATH_VERSION' ) ) {
		return 'Rank Math';
	}
	if ( defined( 'WPSEO_VERSION' ) ) {
		return 'Yoast SEO';
	}
	if ( defined( 'AIOSEO_VERSION' ) ) {
		return 'All in One SEO';
	}
	if ( defined( 'SEOPRESS_VERSION' ) ) {
		return 'SEOPress';
	}
	return '';
}

/** True when the SEO Suite should print tags / alter the sitemap on the front end. */
function weavit_seo_suite_on() {
	return bootg_module_enabled( 'seo-suite' ) && '' === weavit_seo_conflict();
}

/** A notice for the top of each SEO tab explaining why nothing is happening, if so. */
function weavit_seo_status_notice() {
	$conflict = weavit_seo_conflict();
	if ( $conflict ) {
		echo '<div class="notice notice-warning inline"><p><strong>' . esc_html( $conflict ) . ' is active,</strong> so the SEO Suite is paused here to avoid printing duplicate tags. Settings below are kept for if you switch it off.</p></div>';
	} elseif ( ! bootg_module_enabled( 'seo-suite' ) ) {
		echo '<div class="notice notice-warning inline"><p><strong>The SEO Suite is off.</strong> You can edit the settings below, but nothing is added to your site until you switch it on under <a href="' . esc_url( admin_url( 'admin.php?page=weavit-modules' ) ) . '">Weavit &rarr; Modules</a>.</p></div>';
	}
}

/* ---------------------------------------------------------------------
 * Saving settings (one handler, one "section" per form)
 * ------------------------------------------------------------------- */

add_action( 'admin_post_weavit_seo_save', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'weavit_seo_save' );
	$section = sanitize_key( wp_unslash( $_POST['section'] ?? '' ) );
	$s       = weavit_seo_settings();
	$text    = function ( $key ) {
		return sanitize_text_field( wp_unslash( $_POST[ $key ] ?? '' ) );
	};
	$flag    = function ( $key ) {
		return empty( $_POST[ $key ] ) ? 0 : 1;
	};

	switch ( $section ) {
		case 'titles':
			$sep                      = $text( 'separator' );
			$s['separator']           = in_array( $sep, array( '|', '-', '–', '—', '·', '•', '»', '/' ), true ) ? $sep : '|';
			$s['append_site_name']    = $flag( 'append_site_name' );
			$s['auto_description']    = $flag( 'auto_description' );
			$s['noindex_search']      = $flag( 'noindex_search' );
			$s['noindex_attachments'] = $flag( 'noindex_attachments' );
			break;
		case 'social':
			$s['social_enabled'] = $flag( 'social_enabled' );
			$s['default_image']  = esc_url_raw( wp_unslash( $_POST['default_image'] ?? '' ) );
			$s['twitter_handle'] = ltrim( $text( 'twitter_handle' ), '@' );
			break;
		case 'schema':
			$types               = array( 'AccountingService', 'ProfessionalService', 'LocalBusiness', 'Organization' );
			$s['schema_enabled'] = $flag( 'schema_enabled' );
			$s['schema_type']    = in_array( $text( 'schema_type' ), $types, true ) ? $text( 'schema_type' ) : 'AccountingService';
			foreach ( array( 'legal_name', 'street', 'locality', 'region', 'postcode', 'country', 'price_range' ) as $key ) {
				$s[ $key ] = $text( $key );
			}
			$s['hours']       = sanitize_textarea_field( wp_unslash( $_POST['hours'] ?? '' ) );
			$s['area_served'] = sanitize_textarea_field( wp_unslash( $_POST['area_served'] ?? '' ) );
			break;
		case 'sitemap':
			$public             = array_keys( get_post_types( array( 'public' => true ) ) );
			$picked             = array_map( 'sanitize_key', (array) wp_unslash( $_POST['sitemap_types'] ?? array() ) );
			$s['sitemap_types'] = array_values( array_intersect( $picked, $public ) );
			$s['sitemap_users'] = $flag( 'sitemap_users' );
			$s['robots_extra']  = sanitize_textarea_field( wp_unslash( $_POST['robots_extra'] ?? '' ) );
			break;
	}
	update_option( WEAVIT_SEO_OPTION, $s );

	$tab = 'sitemap' === $section ? 'sitemap' : ( 'schema' === $section ? 'schema' : 'titles' );
	wp_safe_redirect( add_query_arg( array( 'page' => 'bootg-seo', 'tab' => $tab, 'saved' => $section ), admin_url( 'admin.php' ) ) );
	exit;
} );

function weavit_seo_saved_notice() {
	if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success is-dismissible inline"><p>Saved.</p></div>';
	}
}

/* ---------------------------------------------------------------------
 * Front end: titles
 * ------------------------------------------------------------------- */

add_filter( 'document_title_separator', function ( $sep ) {
	return weavit_seo_suite_on() ? weavit_seo_settings()['separator'] : $sep;
} );

// Pages without their own Meta Title get "Page title | Site name" (or just the page title).
add_filter( 'pre_get_document_title', function ( $title ) {
	if ( '' !== $title || ! weavit_seo_suite_on() || ! is_singular() || is_front_page() ) {
		return $title; // A custom Meta Title from inc/seo-meta.php wins.
	}
	$s    = weavit_seo_settings();
	$page = single_post_title( '', false );
	if ( '' === $page ) {
		return $title;
	}
	return ! empty( $s['append_site_name'] ) ? $page . ' ' . $s['separator'] . ' ' . get_bloginfo( 'name' ) : $page;
}, 20 );

/** The description to use for a singular page: the one typed in, else a clean excerpt. */
function weavit_seo_description( $post_id ) {
	$custom = get_post_meta( $post_id, 'meta_description', true );
	if ( $custom ) {
		return $custom;
	}
	if ( empty( weavit_seo_settings()['auto_description'] ) ) {
		return '';
	}
	$post = get_post( $post_id );
	if ( ! $post ) {
		return '';
	}
	$text = has_excerpt( $post ) ? $post->post_excerpt : strip_shortcodes( $post->post_content );
	$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $text ) ) );
	if ( strlen( $text ) < 40 ) {
		return ''; // Shortcode-built pages have no readable text here; better no description than a junk one.
	}
	return mb_strlen( $text ) > 158 ? rtrim( mb_substr( $text, 0, 155 ) ) . '…' : $text;
}

add_action( 'wp_head', function () {
	if ( ! weavit_seo_suite_on() || ! is_singular() || is_front_page() ) {
		return;
	}
	$id = get_queried_object_id();
	if ( get_post_meta( $id, 'meta_description', true ) ) {
		return; // Already printed by inc/seo-meta.php.
	}
	$description = weavit_seo_description( $id );
	if ( $description ) {
		echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
	}
}, 2 );

/* ---------------------------------------------------------------------
 * Front end: Open Graph + Twitter cards
 * ------------------------------------------------------------------- */

add_action( 'wp_head', function () {
	$s = weavit_seo_settings();
	if ( ! weavit_seo_suite_on() || empty( $s['social_enabled'] ) ) {
		return;
	}

	$is_post = is_singular() && ! is_front_page();
	$id      = is_singular() ? get_queried_object_id() : 0;
	$title   = wp_get_document_title();
	$url     = $is_post ? get_permalink( $id ) : ( is_front_page() ? home_url( '/' ) : '' );
	if ( ! $url ) {
		return; // Archives, search and 404s: skip rather than guess.
	}
	$description = $id ? weavit_seo_description( $id ) : '';
	if ( ! $description && is_front_page() ) {
		$description = function_exists( 'bootg_get_home_seo' ) ? bootg_get_home_seo( 'description' ) : get_bloginfo( 'description' );
	}

	$image = '';
	if ( $id && has_post_thumbnail( $id ) ) {
		$image = get_the_post_thumbnail_url( $id, 'large' );
	}
	if ( ! $image && $s['default_image'] ) {
		$image = $s['default_image'];
	}
	if ( ! $image ) {
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		$image   = $logo_id ? (string) wp_get_attachment_image_url( $logo_id, 'full' ) : '';
	}

	$is_article = $is_post && 'post' === get_post_type( $id );
	$tags       = array(
		array( 'property', 'og:locale', 'en_AU' ),
		array( 'property', 'og:type', $is_article ? 'article' : 'website' ),
		array( 'property', 'og:title', $title ),
		array( 'property', 'og:url', $url ),
		array( 'property', 'og:site_name', get_bloginfo( 'name' ) ),
	);
	if ( $description ) {
		$tags[] = array( 'property', 'og:description', $description );
	}
	if ( $image ) {
		$tags[] = array( 'property', 'og:image', $image );
	}
	if ( $is_article ) {
		$tags[] = array( 'property', 'article:published_time', get_the_date( 'c', $id ) );
		$tags[] = array( 'property', 'article:modified_time', get_the_modified_date( 'c', $id ) );
	}
	$tags[] = array( 'name', 'twitter:card', $image ? 'summary_large_image' : 'summary' );
	$tags[] = array( 'name', 'twitter:title', $title );
	if ( $description ) {
		$tags[] = array( 'name', 'twitter:description', $description );
	}
	if ( $image ) {
		$tags[] = array( 'name', 'twitter:image', $image );
	}
	if ( $s['twitter_handle'] ) {
		$tags[] = array( 'name', 'twitter:site', '@' . $s['twitter_handle'] );
	}

	foreach ( $tags as $tag ) {
		echo '<meta ' . esc_attr( $tag[0] ) . '="' . esc_attr( $tag[1] ) . '" content="' . esc_attr( $tag[2] ) . '">' . "\n";
	}
}, 3 );

/* ---------------------------------------------------------------------
 * Front end: "hide from search engines" (per page, and site rules)
 * ------------------------------------------------------------------- */

add_filter( 'wp_robots', function ( $robots ) {
	if ( ! weavit_seo_suite_on() ) {
		return $robots;
	}
	$s       = weavit_seo_settings();
	$noindex = false;
	if ( is_search() && ! empty( $s['noindex_search'] ) ) {
		$noindex = true;
	} elseif ( is_attachment() && ! empty( $s['noindex_attachments'] ) ) {
		$noindex = true;
	} elseif ( is_404() ) {
		$noindex = true;
	} elseif ( is_singular() && get_post_meta( get_queried_object_id(), 'seo_noindex', true ) ) {
		$noindex = true;
	}
	if ( $noindex ) {
		unset( $robots['index'] );
		$robots['noindex'] = true;
		$robots['follow']  = true;
	}
	return $robots;
} );

/* ---------------------------------------------------------------------
 * Sitemap (WordPress's own /wp-sitemap.xml) and robots.txt
 * ------------------------------------------------------------------- */

add_filter( 'wp_sitemaps_post_types', function ( $types ) {
	if ( ! weavit_seo_suite_on() ) {
		return $types;
	}
	$keep = weavit_seo_settings()['sitemap_types'];
	foreach ( array_keys( $types ) as $name ) {
		if ( ! in_array( $name, $keep, true ) ) {
			unset( $types[ $name ] );
		}
	}
	return $types;
} );

// Pages marked "hide from search engines" also stay out of the sitemap.
add_filter( 'wp_sitemaps_posts_query_args', function ( $args ) {
	if ( weavit_seo_suite_on() ) {
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
			'relation' => 'OR',
			array( 'key' => 'seo_noindex', 'compare' => 'NOT EXISTS' ),
			array( 'key' => 'seo_noindex', 'value' => array( '', '0' ), 'compare' => 'IN' ),
		);
	}
	return $args;
} );

add_filter( 'wp_sitemaps_add_provider', function ( $provider, $name ) {
	if ( weavit_seo_suite_on() && 'users' === $name && empty( weavit_seo_settings()['sitemap_users'] ) ) {
		return false; // This site has no public author pages.
	}
	return $provider;
}, 10, 2 );

add_filter( 'robots_txt', function ( $output ) {
	if ( ! weavit_seo_suite_on() ) {
		return $output;
	}
	$extra = trim( weavit_seo_settings()['robots_extra'] );
	return $extra ? rtrim( $output ) . "\n\n# Added in Weavit > SEO > Sitemap & Robots\n" . $extra . "\n" : $output;
} );

/* ---------------------------------------------------------------------
 * Admin tabs: Titles & Social, Sitemap & Robots
 * ------------------------------------------------------------------- */

function weavit_seo_form_open( $section ) {
	echo '<form action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post">';
	wp_nonce_field( 'weavit_seo_save' );
	echo '<input type="hidden" name="action" value="weavit_seo_save"><input type="hidden" name="section" value="' . esc_attr( $section ) . '">';
}

function weavit_seo_render_titles_tab() {
	$s = weavit_seo_settings();
	weavit_seo_status_notice();
	weavit_seo_saved_notice();
	?>
	<h2>Titles &amp; descriptions</h2>
	<?php weavit_seo_form_open( 'titles' ); ?>
		<table class="form-table" role="presentation">
			<tr><th>Page titles</th><td>
				<label>Separator <select name="separator"><?php foreach ( array( '|', '-', '–', '—', '·', '•', '»', '/' ) as $sep ) : ?><option value="<?php echo esc_attr( $sep ); ?>" <?php selected( $s['separator'], $sep ); ?>><?php echo esc_html( $sep ); ?></option><?php endforeach; ?></select></label><br>
				<label><input type="checkbox" name="append_site_name" value="1" <?php checked( $s['append_site_name'] ); ?>> Add the site name after the page title (e.g. <em>About Us <?php echo esc_html( $s['separator'] ); ?> <?php echo esc_html( get_bloginfo( 'name' ) ); ?></em>)</label>
				<p class="description">A Meta Title typed on a page always wins. The homepage uses the Homepage Content SEO fields.</p>
			</td></tr>
			<tr><th>Meta descriptions</th><td>
				<label><input type="checkbox" name="auto_description" value="1" <?php checked( $s['auto_description'] ); ?>> Write a description automatically from the page text when none is set</label>
				<p class="description">Pages built entirely from shortcodes have no readable text, so they only get one if you type it in their SEO tab.</p>
			</td></tr>
			<tr><th>Keep out of search results</th><td>
				<label><input type="checkbox" name="noindex_search" value="1" <?php checked( $s['noindex_search'] ); ?>> Site search result pages</label><br>
				<label><input type="checkbox" name="noindex_attachments" value="1" <?php checked( $s['noindex_attachments'] ); ?>> Image/file attachment pages</label>
				<p class="description">404 pages are always kept out. To hide a single page, tick "Hide from search engines" in its SEO tab.</p>
			</td></tr>
		</table>
		<?php submit_button( 'Save titles & descriptions', 'primary', 'submit', false ); ?>
	</form>

	<h2 style="margin-top:34px;">Social sharing (Facebook, LinkedIn, X)</h2>
	<?php weavit_seo_form_open( 'social' ); ?>
		<table class="form-table" role="presentation">
			<tr><th>Share cards</th><td><label><input type="checkbox" name="social_enabled" value="1" <?php checked( $s['social_enabled'] ); ?>> Add Open Graph and Twitter-card tags</label>
				<p class="description">Controls the preview shown when someone shares a page: its title, summary and picture.</p></td></tr>
			<tr><th><label for="default_image">Default share picture</label></th><td><input type="url" id="default_image" name="default_image" class="large-text" value="<?php echo esc_attr( $s['default_image'] ); ?>" placeholder="https://…/uploads/share-picture.jpg">
				<p class="description">Used when a page has no featured image. Paste a Media Library address; 1200 × 630 px works best. Leave blank to fall back to your logo.</p></td></tr>
			<tr><th><label for="twitter_handle">X (Twitter) handle</label></th><td>@<input type="text" id="twitter_handle" name="twitter_handle" class="regular-text" value="<?php echo esc_attr( $s['twitter_handle'] ); ?>" placeholder="go_bookkeeping"></td></tr>
		</table>
		<?php submit_button( 'Save social settings', 'primary', 'submit', false ); ?>
	</form>
	<?php
}

function weavit_seo_render_sitemap_tab() {
	$s     = weavit_seo_settings();
	$types = get_post_types( array( 'public' => true ), 'objects' );
	unset( $types['attachment'] );
	weavit_seo_status_notice();
	weavit_seo_saved_notice();
	$sitemap = home_url( '/wp-sitemap.xml' );
	$robots  = home_url( '/robots.txt' );
	?>
	<h2>XML sitemap</h2>
	<p>Search engines read <a href="<?php echo esc_url( $sitemap ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( $sitemap ); ?></code></a> to find your pages. WordPress builds it for you; choose what goes in it. Submit that address in Google Search Console once.</p>
	<?php weavit_seo_form_open( 'sitemap' ); ?>
		<table class="form-table" role="presentation">
			<tr><th>Include</th><td>
				<?php foreach ( $types as $name => $obj ) : ?>
					<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="sitemap_types[]" value="<?php echo esc_attr( $name ); ?>" <?php checked( in_array( $name, $s['sitemap_types'], true ) ); ?>> <?php echo esc_html( $obj->labels->name ); ?> <span class="description">(<?php echo (int) wp_count_posts( $name )->publish; ?> published)</span></label>
				<?php endforeach; ?>
				<label style="display:block;margin-top:8px;"><input type="checkbox" name="sitemap_users" value="1" <?php checked( $s['sitemap_users'] ); ?>> Author pages <span class="description">(this site has none — leave off)</span></label>
				<p class="description">Pages ticked "Hide from search engines" are left out automatically.</p>
			</td></tr>
		</table>

		<h2>robots.txt</h2>
		<p>WordPress serves <a href="<?php echo esc_url( $robots ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( $robots ); ?></code></a> and already adds the sitemap line. Add your own lines below if you need them.</p>
		<?php if ( file_exists( ABSPATH . 'robots.txt' ) ) : ?>
			<div class="notice notice-warning inline"><p>A real <code>robots.txt</code> file exists in the site's root folder, so it is served instead of this one and the lines below have no effect. Delete or edit that file.</p></div>
		<?php endif; ?>
		<table class="form-table" role="presentation">
			<tr><th><label for="robots_extra">Extra lines</label></th><td><textarea id="robots_extra" name="robots_extra" rows="5" class="large-text code" placeholder="User-agent: *&#10;Disallow: /private/"><?php echo esc_textarea( $s['robots_extra'] ); ?></textarea>
				<p class="description">Careful: a wrong rule here can hide your whole site from Google. <code>Disallow: /</code> blocks everything.</p></td></tr>
		</table>
		<?php submit_button( 'Save sitemap & robots', 'primary', 'submit', false ); ?>
	</form>
	<?php
}
