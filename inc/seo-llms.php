<?php
/**
 * /llms.txt — a plain-text summary of the site written for AI assistants
 * (ChatGPT, Claude, Perplexity, Gemini, ...), so they can describe the business
 * accurately and find its key pages.
 *
 * Nothing here is hardcoded: the business details come from Site Options and
 * Weavit > SEO > Schema, and the page lists are built live from published
 * content, so editing the site updates the file automatically.
 *
 * Plugin-conflict rules (so we never double up):
 *   - Gated by the SEO Suite module, which already stands down while Rank Math,
 *     Yoast, All in One SEO or SEOPress is active (they publish their own).
 *   - Stands down if a real llms.txt file exists in the site root (the web
 *     server would serve that file first anyway).
 *   - Filter `weavit_llms_enabled` lets another plugin or a site switch it off.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** True when this plugin should answer /llms.txt. */
function weavit_llms_on() {
	if ( ! weavit_seo_suite_on() || empty( weavit_seo_settings()['llms_enabled'] ) ) {
		return false;
	}
	if ( file_exists( ABSPATH . 'llms.txt' ) ) {
		return false;
	}
	return (bool) apply_filters( 'weavit_llms_enabled', true );
}

/** A post title as plain text (no HTML tags or entities such as &amp;). */
function weavit_llms_title( $post ) {
	return trim( html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES, 'UTF-8' ) );
}

/** One line for a post: "- [Title](url): short description". */
function weavit_llms_item( $post ) {
	$desc = get_post_meta( $post->ID, 'llms_summary', true ); // Written by hand on the page.
	if ( ! $desc ) {
		$desc = get_post_meta( $post->ID, 'card_summary', true );
	}
	if ( ! $desc && function_exists( 'weavit_seo_description' ) ) {
		$desc = weavit_seo_description( $post->ID );
	}
	$desc = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $desc ) ) );
	$line = '- [' . weavit_llms_title( $post ) . '](' . weavit_llms_md_url( $post ) . ')';
	return $desc ? $line . ': ' . $desc : $line;
}

/** Published, indexable posts of a type (newest/menu order first). */
function weavit_llms_posts( $post_type, $limit = 50, $args = array() ) {
	if ( ! post_type_exists( $post_type ) ) {
		return array();
	}
	return get_posts( array_merge( array(
		'post_type'        => $post_type,
		'post_status'      => 'publish',
		'posts_per_page'   => $limit,
		'orderby'          => 'menu_order title',
		'order'            => 'ASC',
		'has_password'     => false,
		'suppress_filters' => false,
		'meta_query'       => array(
			'relation' => 'AND',
			array(
				'relation' => 'OR',
				array( 'key' => 'seo_noindex', 'compare' => 'NOT EXISTS' ),
				array( 'key' => 'seo_noindex', 'value' => array( '', '0' ), 'compare' => 'IN' ),
			),
			array(
				'relation' => 'OR',
				array( 'key' => 'llms_exclude', 'compare' => 'NOT EXISTS' ),
				array( 'key' => 'llms_exclude', 'value' => array( '', '0' ), 'compare' => 'IN' ),
			),
		),
	), $args ) );
}

/** The whole llms.txt document. */
function weavit_llms_content() {
	$s     = weavit_seo_settings();
	$name  = '' !== trim( $s['legal_name'] ) ? $s['legal_name'] : get_bloginfo( 'name' );
	$home  = home_url( '/' );
	$out   = array( '# ' . $name, '' );
	$tag   = trim( wp_strip_all_tags( get_bloginfo( 'description' ) ) );
	if ( $tag ) {
		$out[] = '> ' . html_entity_decode( $tag, ENT_QUOTES, 'UTF-8' );
		$out[] = '';
	}
	if ( '' !== trim( $s['llms_summary'] ) ) {
		$out[] = trim( $s['llms_summary'] );
		$out[] = '';
	}

	// Business details.
	$details = array( '- Website: ' . $home );
	$phone   = function_exists( 'weavit_schema_phone' ) ? weavit_schema_phone() : (string) bootg_get_option( 'phone' );
	if ( $phone ) {
		$details[] = '- Phone: ' . $phone;
	}
	if ( is_email( bootg_get_option( 'email' ) ) ) {
		$details[] = '- Email: ' . bootg_get_option( 'email' );
	}
	$address = trim( implode( ', ', array_filter( array( $s['street'], $s['locality'], $s['region'], $s['postcode'], $s['country'] ), 'strlen' ) ) );
	if ( $address ) {
		$details[] = '- Location: ' . $address;
	}
	$areas = function_exists( 'weavit_schema_lines' ) ? weavit_schema_lines( $s['area_served'] ) : array();
	if ( $areas ) {
		$details[] = '- Areas served: ' . implode( '; ', $areas );
	}
	if ( '' !== trim( $s['hours'] ) ) {
		$details[] = '- Opening hours: ' . implode( '; ', weavit_schema_lines( $s['hours'] ) );
	}
	foreach ( array( 'gbp_url' => 'Google Business Profile', 'map_url' => 'Google Maps' ) as $key => $label ) {
		if ( ! empty( $s[ $key ] ) ) {
			$details[] = '- ' . $label . ': ' . $s[ $key ];
		}
	}
	foreach ( array( 'facebook_url' => 'Facebook', 'twitter_url' => 'X', 'instagram_url' => 'Instagram', 'linkedin_url' => 'LinkedIn' ) as $key => $label ) {
		$url = bootg_get_option( $key );
		if ( $url && preg_match( '#^https?://#i', $url ) ) {
			$details[] = '- ' . $label . ': ' . $url;
		}
	}
	$out[] = '## Business details';
	$out   = array_merge( $out, $details, array( '' ) );

	// One section per content type, built live from what is published.
	$front    = (int) get_option( 'page_on_front' );
	$excluded = array_filter( array( $front, (int) get_option( 'page_for_posts' ), (int) get_option( 'wp_page_for_privacy_policy' ) ) );
	$sections = array();
	foreach ( weavit_llms_types() as $type => $cfg ) {
		if ( empty( $cfg['on'] ) ) {
			continue;
		}
		$args = array();
		if ( 'post' === $type ) {
			$args = array( 'orderby' => 'date', 'order' => 'DESC' );
		} elseif ( 'page' === $type ) {
			$args = array( 'post__not_in' => $excluded );
		}
		$found = weavit_llms_posts( $type, max( 1, (int) $cfg['limit'] ), $args );
		if ( $found ) {
			$sections[ $cfg['label'] ] = $found;
		}
	}
	foreach ( (array) apply_filters( 'weavit_llms_sections', $sections ) as $title => $items ) {
		$out[] = '## ' . $title;
		foreach ( $items as $item ) {
			$out[] = weavit_llms_item( $item );
		}
		$out[] = '';
	}

	return implode( "\n", $out );
}

add_action( 'template_redirect', function () {
	if ( ! weavit_llms_on() ) {
		return;
	}
	$want = untrailingslashit( (string) wp_parse_url( home_url( '/llms.txt' ), PHP_URL_PATH ) );
	$got  = untrailingslashit( (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( $got !== $want ) {
		return;
	}
	status_header( 200 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'Cache-Control: public, max-age=3600' );
	echo weavit_llms_content(); // phpcs:ignore WordPress.Security.EscapeOutput
	exit;
}, 0 );

/* ---------------------------------------------------------------------
 * Templates: which content types appear in /llms.txt, and how
 * ------------------------------------------------------------------- */

/** Content types and their llms.txt template (heading, how many, on/off). Saved choices override these defaults. */
function weavit_llms_types() {
	$first = array( 'service', 'guide', 'integration', 'team_member', 'page', 'post' );
	$out   = array();
	$types = array_diff( array_keys( get_post_types( array( 'public' => true ) ) ), array( 'attachment' ) );
	$types = array_merge( array_values( array_intersect( $first, $types ) ), array_values( array_diff( $types, $first ) ) );
	$saved = (array) ( weavit_seo_settings()['llms_types'] ?? array() );
	foreach ( $types as $type ) {
		$obj = get_post_type_object( $type );
		$cfg = array(
			'label' => 'post' === $type ? 'Latest articles' : $obj->labels->name,
			'limit' => 'post' === $type ? 10 : ( 'page' === $type ? 60 : 50 ),
			'on'    => in_array( $type, $first, true ),
		);
		if ( isset( $saved[ $type ] ) ) {
			$cfg = array_merge( $cfg, array_intersect_key( (array) $saved[ $type ], $cfg ) );
		}
		$out[ $type ] = $cfg;
	}
	return $out;
}

/** Clean the submitted template table. */
function weavit_llms_sanitize_types( $input ) {
	$out = array();
	foreach ( array_keys( weavit_llms_types() ) as $type ) {
		$row = (array) ( $input[ $type ] ?? array() );
		$out[ $type ] = array(
			'on'    => empty( $row['on'] ) ? 0 : 1,
			'label' => sanitize_text_field( $row['label'] ?? '' ),
			'limit' => min( 200, max( 1, (int) ( $row['limit'] ?? 50 ) ) ),
		);
		if ( '' === $out[ $type ]['label'] ) {
			unset( $out[ $type ]['label'] );
		}
	}
	return $out;
}

/* ---------------------------------------------------------------------
 * A clean Markdown copy of every page: /contact.md, /services/payroll.md,
 * and /index.md for the homepage. /llms.txt links to these.
 * ------------------------------------------------------------------- */

function weavit_llms_md_url( $post ) {
	if ( ! get_option( 'permalink_structure' ) ) {
		return get_permalink( $post ); // No pretty URLs, so no .md route.
	}
	if ( (int) get_option( 'page_on_front' ) === (int) $post->ID ) {
		return home_url( '/index.md' );
	}
	return untrailingslashit( get_permalink( $post ) ) . '.md';
}

/** Can this post have a Markdown copy? Same rules as the llms.txt lists. */
function weavit_llms_post_allowed( $post ) {
	$types = weavit_llms_types();
	return $post
		&& 'publish' === $post->post_status
		&& ! post_password_required( $post )
		&& ! empty( $types[ $post->post_type ]['on'] )
		&& ! get_post_meta( $post->ID, 'seo_noindex', true )
		&& ! get_post_meta( $post->ID, 'llms_exclude', true );
}

function weavit_llms_node_md( $node ) {
	$out = '';
	foreach ( $node->childNodes as $c ) {
		if ( XML_TEXT_NODE === $c->nodeType ) {
			$out .= preg_replace( '/\s+/', ' ', $c->nodeValue );
			continue;
		}
		if ( XML_ELEMENT_NODE !== $c->nodeType ) {
			continue;
		}
		$tag   = strtolower( $c->nodeName );
		$inner = weavit_llms_node_md( $c );
		if ( preg_match( '/^h([1-6])$/', $tag, $m ) ) {
			$out .= "\n\n" . str_repeat( '#', (int) $m[1] ) . ' ' . trim( $inner ) . "\n\n";
		} elseif ( 'a' === $tag ) {
			$href = $c->getAttribute( 'href' );
			$text = trim( preg_replace( '/\s+/', ' ', $inner ) );
			$out .= ( $href && '' !== $text && 0 !== strpos( $href, '#' ) ) ? '[' . $text . '](' . $href . ')' : $inner;
		} elseif ( 'li' === $tag ) {
			$out .= "\n- " . trim( preg_replace( '/\s*\n\s*/', ' ', $inner ) );
		} elseif ( in_array( $tag, array( 'ul', 'ol' ), true ) ) {
			$out .= "\n" . $inner . "\n\n";
		} elseif ( in_array( $tag, array( 'strong', 'b' ), true ) && '' !== trim( $inner ) ) {
			$out .= '**' . trim( $inner ) . '**';
		} elseif ( in_array( $tag, array( 'em', 'i' ), true ) && '' !== trim( $inner ) ) {
			$out .= '*' . trim( $inner ) . '*';
		} elseif ( 'br' === $tag ) {
			$out .= "\n";
		} elseif ( in_array( $tag, array( 'p', 'div', 'section', 'article', 'header', 'footer', 'main', 'blockquote', 'table', 'tr', 'figure', 'details', 'summary' ), true ) ) {
			$out .= "\n\n" . $inner . "\n\n";
		} elseif ( in_array( $tag, array( 'td', 'th' ), true ) ) {
			$out .= $inner . ' | ';
		} elseif ( ! in_array( $tag, array( 'img', 'button', 'input', 'select', 'textarea', 'nav', 'script', 'style' ), true ) ) {
			$out .= $inner;
		}
	}
	return $out;
}

function weavit_llms_html_to_md( $html ) {
	$html = preg_replace( '#<(script|style|noscript|svg|form|iframe|nav)\b.*?</\1>#is', '', (string) $html );
	if ( '' === trim( $html ) ) {
		return '';
	}
	$prev = libxml_use_internal_errors( true );
	$dom  = new DOMDocument();
	$dom->loadHTML( '<?xml encoding="utf-8" ?><div>' . $html . '</div>' );
	libxml_clear_errors();
	libxml_use_internal_errors( $prev );
	$root = $dom->getElementsByTagName( 'div' )->item( 0 );
	$md   = $root ? weavit_llms_node_md( $root ) : '';
	$md   = preg_replace( '/[ \t]+\n/', "\n", $md );
	return trim( preg_replace( "/\n{3,}/", "\n\n", $md ) );
}

/** The Markdown page for a post: title, summary, then the rendered content. */
function weavit_llms_page_md( $post ) {
	global $wp_query;
	$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	setup_postdata( $post );
	$summary = get_post_meta( $post->ID, 'llms_summary', true );
	if ( ! $summary && function_exists( 'weavit_seo_description' ) ) {
		$summary = weavit_seo_description( $post->ID );
	}
	$body = weavit_llms_html_to_md( apply_filters( 'the_content', $post->post_content ) );
	wp_reset_postdata();
	$out = '# ' . weavit_llms_title( $post ) . "\n\n";
	if ( $summary ) {
		$out .= '> ' . trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $summary ) ) ) . "\n\n";
	}
	$out .= 'Source: ' . get_permalink( $post ) . "\n\n" . $body . "\n";
	return $out;
}

add_action( 'template_redirect', function () {
	if ( ! weavit_llms_on() || ! get_option( 'permalink_structure' ) ) {
		return;
	}
	$got  = untrailingslashit( (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$base = untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
	if ( '.md' !== substr( $got, -3 ) || 0 !== strpos( $got, $base . '/' ) ) {
		return;
	}
	$rel = substr( substr( $got, strlen( $base ) + 1 ), 0, -3 );
	if ( 'index' === $rel ) {
		$id = (int) get_option( 'page_on_front' );
		if ( ! $id ) {
			$out = weavit_llms_content(); // Latest-posts homepage: the llms.txt overview stands in.
		}
	} else {
		$id = (int) url_to_postid( home_url( '/' . $rel . '/' ) );
	}
	if ( empty( $out ) ) {
		$post = $id ? get_post( $id ) : null;
		if ( ! weavit_llms_post_allowed( $post ) ) {
			return; // Falls through to the normal 404.
		}
		$out = weavit_llms_page_md( $post );
	}
	status_header( 200 );
	header( 'Content-Type: text/markdown; charset=utf-8' );
	header( 'Cache-Control: public, max-age=3600' );
	echo $out; // phpcs:ignore WordPress.Security.EscapeOutput
	exit;
}, 0 );

/** Tell AI crawlers a Markdown copy exists. */
add_action( 'wp_head', function () {
	if ( ! is_singular() || ! weavit_llms_on() || ! get_option( 'permalink_structure' ) ) {
		return;
	}
	$post = get_queried_object();
	if ( weavit_llms_post_allowed( $post ) ) {
		echo '<link rel="alternate" type="text/markdown" href="' . esc_url( weavit_llms_md_url( $post ) ) . '">' . "\n";
	}
}, 6 );

/* ---------------------------------------------------------------------
 * Per-page fields (same SEO tab in the editor as Meta Title/Description)
 * ------------------------------------------------------------------- */

add_action( 'init', function () {
	foreach ( bootg_seo_post_types() as $post_type ) {
		register_post_meta( $post_type, 'llms_summary', array(
			'single'            => true,
			'type'              => 'string',
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => function () {
				return current_user_can( 'edit_posts' );
			},
		) );
		register_post_meta( $post_type, 'llms_exclude', array(
			'single'            => true,
			'type'              => 'boolean',
			'show_in_rest'      => true,
			'sanitize_callback' => 'rest_sanitize_boolean',
			'auth_callback'     => function () {
				return current_user_can( 'edit_posts' );
			},
		) );
	}
} );

add_filter( 'weavit_editor_panel_tabs', function ( $tabs, $post_type ) {
	if ( ! isset( $tabs['seo'] ) || ! weavit_llms_on() ) {
		return $tabs;
	}
	$tabs['seo']['fields']['llms_summary'] = array(
		'label' => 'AI summary (llms.txt)',
		'type'  => 'textarea',
		'help'  => 'One or two plain sentences for AI assistants. Leave blank to use the card summary or meta description automatically.',
	);
	$tabs['seo']['fields']['llms_exclude'] = array(
		'label' => 'Leave out of llms.txt',
		'type'  => 'checkbox',
		'help'  => 'Pages hidden from search engines are already left out.',
	);
	return $tabs;
}, 20, 2 );

/* ---------------------------------------------------------------------
 * Weavit > SEO Tools > AI / llms.txt
 * ------------------------------------------------------------------- */

function weavit_seo_render_llms_tab() {
	$s = weavit_seo_settings();
	weavit_seo_status_notice();
	weavit_seo_saved_notice();
	$file = file_exists( ABSPATH . 'llms.txt' );
	?>
	<h2>AI visibility (llms.txt)</h2>
	<p style="max-width:780px;">/llms.txt is a plain-text summary of your business for AI assistants such as ChatGPT, Claude, Perplexity and Gemini. It is built automatically from your business details and published pages, so it stays current as you edit the site. Each page can add its own one-line AI summary from the <strong>SEO</strong> tab in the page editor; without one, the page's card summary or meta description is used.</p>
	<?php if ( $file ) : ?>
		<div class="notice notice-warning inline"><p>A real <code>llms.txt</code> file already exists on the server, so it is being served instead and this setting has no effect. Delete that file to use the automatic one.</p></div>
	<?php endif; ?>
	<?php weavit_seo_form_open( 'llms' ); ?>
		<table class="form-table" role="presentation">
			<tr><th>/llms.txt</th><td>
				<label><input type="checkbox" name="llms_enabled" value="1" <?php checked( $s['llms_enabled'] ); ?>> Publish /llms.txt</label>
				<p class="description"><?php echo weavit_llms_on() ? '<a href="' . esc_url( home_url( '/llms.txt' ) ) . '" target="_blank" rel="noopener">Open /llms.txt</a>' : 'Not being published right now: the SEO Suite is off, another SEO plugin is active, or the box above is unticked.'; ?></p>
			</td></tr>
			<tr><th><label for="llms_summary">Business summary</label></th><td><textarea id="llms_summary" name="llms_summary" rows="4" class="large-text" placeholder="Optional: who you are, who you help and what you do, in two or three sentences."><?php echo esc_textarea( $s['llms_summary'] ); ?></textarea><p class="description">Shown at the top of the file. The name, phone, email, areas served, profile links and page lists come from your other settings.</p></td></tr>
		</table>
		<h3>Templates by content type</h3>
		<p class="description" style="max-width:780px;">Choose which kinds of content are listed in /llms.txt and under what heading. Every listed page also gets its own Markdown copy (for example <code>/contact.md</code>). The homepage's copy is <code>/index.md</code>.</p>
		<table class="widefat striped" style="max-width:780px;margin-bottom:16px;">
			<thead><tr><th>Include</th><th>Content type</th><th>Heading in the file</th><th>Max items</th></tr></thead>
			<tbody>
			<?php foreach ( weavit_llms_types() as $type => $cfg ) : $obj = get_post_type_object( $type ); ?>
				<tr>
					<td><input type="checkbox" name="llms_types[<?php echo esc_attr( $type ); ?>][on]" value="1" <?php checked( $cfg['on'] ); ?>></td>
					<td><?php echo esc_html( $obj->labels->name ); ?></td>
					<td><input type="text" class="regular-text" name="llms_types[<?php echo esc_attr( $type ); ?>][label]" value="<?php echo esc_attr( $cfg['label'] ); ?>"></td>
					<td><input type="number" class="small-text" min="1" max="200" name="llms_types[<?php echo esc_attr( $type ); ?>][limit]" value="<?php echo esc_attr( $cfg['limit'] ); ?>"></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php submit_button( 'Save', 'primary', 'submit', false ); ?>
	</form>
	<h3 style="margin-top:30px;">What AI assistants will read</h3>
	<pre style="max-width:900px;max-height:420px;overflow:auto;background:#f6f7f7;border:1px solid #dcdcde;padding:12px;font-size:12px;white-space:pre-wrap;"><?php echo esc_html( weavit_llms_content() ); ?></pre>
	<?php
}
