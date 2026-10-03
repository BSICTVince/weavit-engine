<?php
/**
 * Schema markup (JSON-LD) — tells Google who the business is and what each
 * page is, which is what powers rich results for a local business:
 *
 *   every page   the business (AccountingService by default), the website, the page
 *   pages        breadcrumb trail
 *   blog posts   BlogPosting
 *   services     Service
 *
 * Business details come from Site Options (phone, email, social links) plus
 * the address/hours/area fields on Weavit > SEO > Schema. Gated by the SEO
 * Suite module (inc/seo-suite.php).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Australian-style phone number as an international one for schema, e.g. "+61 8 6249 0115". */
function weavit_schema_phone() {
	$raw    = bootg_get_option( 'phone_link' ) ?: bootg_get_option( 'phone' );
	$digits = preg_replace( '/\D/', '', (string) $raw );
	if ( 10 === strlen( $digits ) && '0' === $digits[0] ) {
		return '+61 ' . substr( $digits, 1, 1 ) . ' ' . substr( $digits, 2, 4 ) . ' ' . substr( $digits, 6 );
	}
	return (string) bootg_get_option( 'phone' );
}

function weavit_schema_lines( $text ) {
	return array_values( array_filter( array_map( 'trim', preg_split( '/\R/', (string) $text ) ) ) );
}

function weavit_schema_org_node() {
	$s     = weavit_seo_settings();
	$id    = home_url( '/#organization' );
	$local = in_array( $s['schema_type'], array( 'AccountingService', 'ProfessionalService', 'LocalBusiness' ), true );

	$node = array(
		'@type' => $s['schema_type'],
		'@id'   => $id,
		'name'  => '' !== trim( $s['legal_name'] ) ? $s['legal_name'] : get_bloginfo( 'name' ),
		'url'   => home_url( '/' ),
	);
	if ( get_bloginfo( 'description' ) ) {
		$node['description'] = get_bloginfo( 'description' );
	}
	$logo_id = (int) get_theme_mod( 'custom_logo' );
	$logo    = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';
	if ( $logo ) {
		$node['logo']  = array( '@type' => 'ImageObject', '@id' => home_url( '/#logo' ), 'url' => $logo, 'contentUrl' => $logo );
		$node['image'] = array( '@id' => home_url( '/#logo' ) );
	}
	if ( weavit_schema_phone() ) {
		$node['telephone'] = weavit_schema_phone();
	}
	if ( is_email( bootg_get_option( 'email' ) ) ) {
		$node['email'] = bootg_get_option( 'email' );
	}

	$address = array_filter( array(
		'streetAddress'   => $s['street'],
		'addressLocality' => $s['locality'],
		'addressRegion'   => $s['region'],
		'postalCode'      => $s['postcode'],
		'addressCountry'  => $s['country'],
	), 'strlen' );
	if ( $address ) {
		$node['address'] = array_merge( array( '@type' => 'PostalAddress' ), $address );
	}

	$areas = weavit_schema_lines( $s['area_served'] );
	if ( $areas ) {
		$node['areaServed'] = 1 === count( $areas ) ? $areas[0] : $areas;
	}
	if ( $local ) {
		$hours = weavit_schema_lines( $s['hours'] );
		if ( $hours ) {
			$node['openingHours'] = $hours;
		}
		if ( '' !== trim( $s['price_range'] ) ) {
			$node['priceRange'] = $s['price_range'];
		}
	}

	$same = array();
	foreach ( array( 'facebook_url', 'twitter_url', 'instagram_url', 'linkedin_url' ) as $key ) {
		$url = bootg_get_option( $key );
		if ( $url && preg_match( '#^https?://#i', $url ) ) {
			$same[] = $url;
		}
	}
	if ( $same ) {
		$node['sameAs'] = $same;
	}
	return $node;
}

/** The breadcrumb trail for a singular post: array of array( name, url ). */
function weavit_schema_trail( $post ) {
	$trail = array( array( 'Home', home_url( '/' ) ) );
	$type  = get_post_type( $post );

	if ( 'page' === $type ) {
		foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor ) {
			$trail[] = array( get_the_title( $ancestor ), get_permalink( $ancestor ) );
		}
	} else {
		$blog = (int) get_option( 'page_for_posts' ) ? get_permalink( (int) get_option( 'page_for_posts' ) ) : home_url( '/blog/' );
		$map  = array(
			'post'        => array( 'Blog', $blog ),
			'service'     => array( 'Services', get_post_type_archive_link( 'service' ) ?: home_url( '/services/' ) ),
			'integration' => array( 'Partners', get_post_type_archive_link( 'integration' ) ?: home_url( '/partners/' ) ),
			'guide'       => array( 'Business Guides', get_post_type_archive_link( 'guide' ) ?: home_url( '/guides/' ) ),
			'team_member' => array( 'Meet Our Team', function_exists( 'bootg_page_url' ) ? bootg_page_url( 'team' ) : home_url( '/team/' ) ),
		);
		$map  = (array) apply_filters( 'weavit_schema_breadcrumb_parents', $map );
		if ( isset( $map[ $type ] ) && $map[ $type ][1] ) {
			$trail[] = $map[ $type ];
		}
	}
	$trail[] = array( get_the_title( $post ), get_permalink( $post ) );
	return $trail;
}

/** Everything for one page. $post_id 0 = the homepage / site-level markup only. */
function weavit_schema_graph( $post_id = 0 ) {
	$org_id  = home_url( '/#organization' );
	$site_id = home_url( '/#website' );
	$graph   = array(
		weavit_schema_org_node(),
		array(
			'@type'           => 'WebSite',
			'@id'             => $site_id,
			'url'             => home_url( '/' ),
			'name'            => get_bloginfo( 'name' ),
			'publisher'       => array( '@id' => $org_id ),
			'inLanguage'      => 'en-AU',
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array( '@type' => 'EntryPoint', 'urlTemplate' => home_url( '/?s={search_term_string}' ) ),
				'query-input' => 'required name=search_term_string',
			),
		),
	);

	if ( ! $post_id ) {
		$graph[] = array(
			'@type'      => 'WebPage',
			'@id'        => home_url( '/#webpage' ),
			'url'        => home_url( '/' ),
			'name'       => get_bloginfo( 'name' ),
			'isPartOf'   => array( '@id' => $site_id ),
			'about'      => array( '@id' => $org_id ),
			'inLanguage' => 'en-AU',
		);
		return $graph;
	}

	$post = get_post( $post_id );
	if ( ! $post ) {
		return $graph;
	}
	$url      = get_permalink( $post );
	$page_id  = $url . '#webpage';
	$crumb_id = $url . '#breadcrumb';
	$type     = get_post_type( $post );

	$page = array(
		'@type'         => 'WebPage',
		'@id'           => $page_id,
		'url'           => $url,
		'name'          => get_the_title( $post ),
		'isPartOf'      => array( '@id' => $site_id ),
		'datePublished' => get_the_date( 'c', $post ),
		'dateModified'  => get_the_modified_date( 'c', $post ),
		'inLanguage'    => 'en-AU',
		'breadcrumb'    => array( '@id' => $crumb_id ),
	);
	$description = function_exists( 'weavit_seo_description' ) ? weavit_seo_description( $post->ID ) : '';
	if ( $description ) {
		$page['description'] = $description;
	}
	if ( has_post_thumbnail( $post ) ) {
		$page['primaryImageOfPage'] = array( '@type' => 'ImageObject', 'url' => get_the_post_thumbnail_url( $post, 'large' ) );
	}
	$graph[] = $page;

	$items = array();
	foreach ( weavit_schema_trail( $post ) as $i => $crumb ) {
		$items[] = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => wp_strip_all_tags( $crumb[0] ), 'item' => $crumb[1] );
	}
	$graph[] = array( '@type' => 'BreadcrumbList', '@id' => $crumb_id, 'itemListElement' => $items );

	if ( 'post' === $type ) {
		$byline  = get_post_meta( $post->ID, 'byline', true );
		$article = array(
			'@type'            => 'BlogPosting',
			'@id'              => $url . '#article',
			'headline'         => get_the_title( $post ),
			'datePublished'    => get_the_date( 'c', $post ),
			'dateModified'     => get_the_modified_date( 'c', $post ),
			'mainEntityOfPage' => array( '@id' => $page_id ),
			'isPartOf'         => array( '@id' => $page_id ),
			'author'           => $byline ? array( '@type' => 'Person', 'name' => $byline ) : array( '@id' => $org_id ),
			'publisher'        => array( '@id' => $org_id ),
			'inLanguage'       => 'en-AU',
		);
		if ( $description ) {
			$article['description'] = $description;
		}
		if ( has_post_thumbnail( $post ) ) {
			$article['image'] = get_the_post_thumbnail_url( $post, 'large' );
		}
		$graph[] = $article;
	} elseif ( 'service' === $type ) {
		$service = array(
			'@type'    => 'Service',
			'@id'      => $url . '#service',
			'name'     => get_the_title( $post ),
			'url'      => $url,
			'provider' => array( '@id' => $org_id ),
		);
		$summary = get_post_meta( $post->ID, 'card_summary', true ) ?: $description;
		if ( $summary ) {
			$service['description'] = $summary;
		}
		$areas = weavit_schema_lines( weavit_seo_settings()['area_served'] );
		if ( $areas ) {
			$service['areaServed'] = 1 === count( $areas ) ? $areas[0] : $areas;
		}
		$graph[] = $service;
	}

	return (array) apply_filters( 'weavit_schema_graph', $graph, $post );
}

function weavit_schema_json( $graph ) {
	return wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );
}

add_action( 'wp_head', function () {
	if ( ! weavit_seo_suite_on() || empty( weavit_seo_settings()['schema_enabled'] ) ) {
		return;
	}
	if ( is_front_page() ) {
		$graph = weavit_schema_graph( 0 );
	} elseif ( is_singular() ) {
		$graph = weavit_schema_graph( get_queried_object_id() );
	} else {
		$graph = array( weavit_schema_org_node() ); // Archives and the like: just who the business is.
	}
	echo '<script type="application/ld+json">' . weavit_schema_json( $graph ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
}, 5 );

/* ---------------------------------------------------------------------
 * Weavit > SEO > Schema
 * ------------------------------------------------------------------- */

function weavit_seo_render_schema_tab() {
	$s = weavit_seo_settings();
	weavit_seo_status_notice();
	weavit_seo_saved_notice();
	$types = array(
		'AccountingService'   => 'Accounting service (best fit for a bookkeeping business)',
		'ProfessionalService' => 'Professional service',
		'LocalBusiness'       => 'Local business',
		'Organization'        => 'Organisation (no address or opening hours)',
	);
	?>
	<h2>Business details for Google</h2>
	<p style="max-width:780px;">This adds hidden "schema" data to your pages so Google can understand who you are, where you work and what each page is, which helps your business show up properly in search and maps. Your phone, email and social links come from <a href="<?php echo esc_url( admin_url( 'admin.php?page=bootg-site-options' ) ); ?>">Site Options</a>.</p>
	<?php weavit_seo_form_open( 'schema' ); ?>
		<table class="form-table" role="presentation">
			<tr><th>Schema markup</th><td><label><input type="checkbox" name="schema_enabled" value="1" <?php checked( $s['schema_enabled'] ); ?>> Add schema markup to the site</label></td></tr>
			<tr><th><label for="schema_type">Business type</label></th><td><select id="schema_type" name="schema_type"><?php foreach ( $types as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['schema_type'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr>
			<tr><th><label for="legal_name">Business name</label></th><td><input type="text" id="legal_name" name="legal_name" class="regular-text" value="<?php echo esc_attr( $s['legal_name'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"> <span class="description">Leave blank to use the site name.</span></td></tr>
			<tr><th>Address</th><td>
				<input type="text" name="street" class="regular-text" value="<?php echo esc_attr( $s['street'] ); ?>" placeholder="Street address (optional)"><br>
				<input type="text" name="locality" value="<?php echo esc_attr( $s['locality'] ); ?>" placeholder="Suburb / city" style="width:180px;">
				<input type="text" name="region" value="<?php echo esc_attr( $s['region'] ); ?>" placeholder="State" style="width:70px;">
				<input type="text" name="postcode" value="<?php echo esc_attr( $s['postcode'] ); ?>" placeholder="Postcode" style="width:90px;">
				<input type="text" name="country" value="<?php echo esc_attr( $s['country'] ); ?>" placeholder="Country" style="width:70px;">
				<p class="description">A street address is optional if you work from home or online. Suburb, state and country alone are fine.</p>
			</td></tr>
			<tr><th><label for="area_served">Areas you serve</label></th><td><textarea id="area_served" name="area_served" rows="3" class="regular-text"><?php echo esc_textarea( $s['area_served'] ); ?></textarea><p class="description">One per line.</p></td></tr>
			<tr><th><label for="hours">Opening hours</label></th><td><textarea id="hours" name="hours" rows="3" class="regular-text" placeholder="Mo-Fr 09:00-17:00"><?php echo esc_textarea( $s['hours'] ); ?></textarea><p class="description">One per line in this format: <code>Mo-Fr 09:00-17:00</code> (days Mo Tu We Th Fr Sa Su, 24-hour times). Leave blank if you don't want to list hours.</p></td></tr>
			<tr><th><label for="price_range">Price range</label></th><td><input type="text" id="price_range" name="price_range" class="small-text" value="<?php echo esc_attr( $s['price_range'] ); ?>" placeholder="$$"> <span class="description">Optional, e.g. $$.</span></td></tr>
		</table>
		<?php submit_button( 'Save schema settings', 'primary', 'submit', false ); ?>
	</form>

	<h3 style="margin-top:30px;">What your homepage will send to Google</h3>
	<pre style="max-width:900px;max-height:360px;overflow:auto;background:#f6f7f7;border:1px solid #dcdcde;padding:12px;font-size:12px;"><?php echo esc_html( wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => weavit_schema_graph( 0 ) ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) ); ?></pre>
	<p><a class="button" href="<?php echo esc_url( 'https://search.google.com/test/rich-results?url=' . rawurlencode( home_url( '/' ) ) ); ?>" target="_blank" rel="noopener">Test it with Google's Rich Results Test</a> <span class="description">(works once the site is live)</span></p>
	<?php
}
