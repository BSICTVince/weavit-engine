<?php
/**
 * SEO Analyser — one click checks the site the way a search engine sees it:
 * site-wide basics (is the site visible to Google, sitemap, robots.txt, HTTPS,
 * homepage title...) and every public page (title and description length, one
 * H1, image alt text, canonical link, accidental "noindex", duplicate titles).
 * Results are saved so you can come back to them; run it again after fixing.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WEAVIT_SEO_AUDIT_OPTION', 'weavit_seo_audit' );

/** Fetches a page from this site: array( status code, body ) or array( 0, error text ). */
function weavit_seo_fetch( $url ) {
	$r = wp_remote_get( $url, array(
		'timeout'    => 15,
		'sslverify'  => false,
		'redirection' => 3,
		'user-agent' => 'Mozilla/5.0 (compatible; WeavitSEOAnalyser/1.0)',
	) );
	if ( is_wp_error( $r ) ) {
		return array( 0, $r->get_error_message() );
	}
	return array( (int) wp_remote_retrieve_response_code( $r ), (string) wp_remote_retrieve_body( $r ) );
}

function weavit_seo_check( $id, $label, $status, $detail, $fix = '' ) {
	return array( 'id' => $id, 'label' => $label, 'status' => $status, 'detail' => $detail, 'fix' => $fix );
}

/** What a search engine reads from one page's HTML. */
function weavit_seo_parse_page( $html ) {
	$out = array( 'title' => '', 'description' => '', 'h1' => 0, 'img_no_alt' => 0, 'canonical' => false, 'noindex' => false, 'schema' => false, 'og' => false );
	if ( '' === trim( $html ) ) {
		return $out;
	}
	$out['schema'] = false !== stripos( $html, 'application/ld+json' );

	$prev = libxml_use_internal_errors( true );
	$dom  = new DOMDocument();
	$dom->loadHTML( '<?xml encoding="utf-8"?>' . $html );
	libxml_clear_errors();
	libxml_use_internal_errors( $prev );
	$x = new DOMXPath( $dom );

	$t = $x->query( '//title' );
	if ( $t->length ) {
		$out['title'] = trim( preg_replace( '/\s+/', ' ', $t->item( 0 )->textContent ) );
	}
	$d = $x->query( '//meta[translate(@name,"DESCRIPTION","description")="description"]/@content' );
	if ( $d->length ) {
		$out['description'] = trim( $d->item( 0 )->nodeValue );
	}
	$out['h1']        = $x->query( '//h1' )->length;
	$out['img_no_alt'] = $x->query( '//body//img[not(@alt)]' )->length;
	$out['canonical'] = $x->query( '//link[@rel="canonical"]' )->length > 0;
	$robots           = $x->query( '//meta[translate(@name,"ROBOTS","robots")="robots"]/@content' );
	$out['noindex']   = $robots->length && false !== stripos( $robots->item( 0 )->nodeValue, 'noindex' );
	$out['og']        = $x->query( '//meta[@property="og:title"]' )->length > 0;
	return $out;
}

function weavit_seo_audit_site() {
	$checks = array();
	$home   = home_url( '/' );

	// 1. Visible to search engines?
	$visible  = (bool) get_option( 'blog_public' );
	$checks[] = weavit_seo_check( 'visible', 'Search engines are allowed to index the site', $visible ? 'pass' : 'fail',
		$visible ? 'Good — the "discourage search engines" setting is off.' : 'The "Discourage search engines" box is ticked, so Google is asked to ignore this site. Right for a staging copy, but it must be unticked before launch.',
		admin_url( 'options-reading.php' ) );

	// 2. HTTPS.
	$https    = 0 === strpos( $home, 'https://' );
	$checks[] = weavit_seo_check( 'https', 'The site address uses HTTPS', $https ? 'pass' : 'fail',
		$https ? 'Good.' : 'The site address starts with http://. Search engines prefer secure (https://) sites.', admin_url( 'options-general.php' ) );

	// 3. Permalinks.
	$pretty   = (bool) get_option( 'permalink_structure' );
	$checks[] = weavit_seo_check( 'permalinks', 'Page addresses are readable (pretty permalinks)', $pretty ? 'pass' : 'fail',
		$pretty ? 'Good.' : 'Addresses look like ?p=123. Choose "Post name" under Settings → Permalinks.', admin_url( 'options-permalink.php' ) );

	// 4. Site title and tagline.
	$title_ok = '' !== trim( get_bloginfo( 'name' ) );
	$tag_ok   = '' !== trim( get_bloginfo( 'description' ) );
	$checks[] = weavit_seo_check( 'sitename', 'Site title and tagline are set', ( $title_ok && $tag_ok ) ? 'pass' : ( $title_ok ? 'warn' : 'fail' ),
		( $title_ok && $tag_ok ) ? 'Good.' : ( $title_ok ? 'The site title is set but the tagline is empty (Settings → General).' : 'The site title is empty.' ), admin_url( 'options-general.php' ) );

	// 5. Favicon.
	$checks[] = weavit_seo_check( 'icon', 'Site icon (favicon) is set', has_site_icon() ? 'pass' : 'warn',
		has_site_icon() ? 'Good.' : 'No site icon — it appears in browser tabs and Google results. Set one under Appearance → Customize → Site Identity.', admin_url( 'customize.php?autofocus[section]=title_tagline' ) );

	// 6. Sitemap.
	list( $code, $body ) = weavit_seo_fetch( home_url( '/wp-sitemap.xml' ) );
	$ok                  = 200 === $code && false !== stripos( $body, '<sitemapindex' );
	$checks[]            = weavit_seo_check( 'sitemap', 'The XML sitemap works', $ok ? 'pass' : 'fail',
		$ok ? 'Found at /wp-sitemap.xml. Submit it in Google Search Console.' : 'Could not load /wp-sitemap.xml (' . ( $code ?: $body ) . '). Check Weavit → SEO → Sitemap & Robots.', admin_url( 'admin.php?page=bootg-seo&tab=sitemap' ) );

	// 7. robots.txt.
	list( $code, $body ) = weavit_seo_fetch( home_url( '/robots.txt' ) );
	$blocks_all          = (bool) preg_match( '/User-agent:\s*\*\s*[\r\n]+(?:(?:Allow|Crawl-delay|#)[^\r\n]*[\r\n]+)*Disallow:\s*\/\s*(?:[\r\n]|$)/i', $body );
	if ( 200 !== $code ) {
		$checks[] = weavit_seo_check( 'robots', 'robots.txt is available', 'warn', 'Could not load /robots.txt (' . ( $code ?: $body ) . ').', admin_url( 'admin.php?page=bootg-seo&tab=sitemap' ) );
	} elseif ( $blocks_all ) {
		$checks[] = weavit_seo_check( 'robots', 'robots.txt does not block the whole site', 'fail', 'robots.txt contains "Disallow: /" for all robots, which hides the entire site from Google.', admin_url( 'admin.php?page=bootg-seo&tab=sitemap' ) );
	} else {
		$checks[] = weavit_seo_check( 'robots', 'robots.txt is fine', false !== stripos( $body, 'sitemap:' ) ? 'pass' : 'warn', false !== stripos( $body, 'sitemap:' ) ? 'Good — it allows crawling and lists the sitemap.' : 'It allows crawling but does not list the sitemap.', admin_url( 'admin.php?page=bootg-seo&tab=sitemap' ) );
	}

	// 8. SEO Suite.
	$conflict = weavit_seo_conflict();
	if ( $conflict ) {
		$checks[] = weavit_seo_check( 'suite', 'SEO tags come from one plugin', 'pass', $conflict . ' is handling SEO tags, and the Weavit SEO Suite is paused so nothing is duplicated.' );
	} else {
		$on       = bootg_module_enabled( 'seo-suite' );
		$checks[] = weavit_seo_check( 'suite', 'The SEO Suite is switched on', $on ? 'pass' : 'warn', $on ? 'Titles, share cards, schema, sitemap rules and the 404 Monitor are active.' : 'It is off, so no share cards or schema are added. Switch it on under Weavit → Modules.', admin_url( 'admin.php?page=weavit-modules' ) );
	}

	// 9. Homepage as Google sees it.
	list( $code, $html ) = weavit_seo_fetch( $home );
	if ( 200 === $code ) {
		$p        = weavit_seo_parse_page( $html );
		$checks[] = weavit_seo_check( 'home_title', 'Homepage has a title', '' !== $p['title'] ? 'pass' : 'fail', '' !== $p['title'] ? '"' . $p['title'] . '" (' . mb_strlen( $p['title'] ) . ' characters)' : 'The homepage has no <title>.', admin_url( 'themes.php?page=bootg-homepage-sections' ) );
		$checks[] = weavit_seo_check( 'home_desc', 'Homepage has a meta description', '' !== $p['description'] ? 'pass' : 'fail', '' !== $p['description'] ? mb_strlen( $p['description'] ) . ' characters.' : 'No description — it is the text shown under your title in Google. Set it under Appearance → Homepage Content.', admin_url( 'themes.php?page=bootg-homepage-sections' ) );
		$checks[] = weavit_seo_check( 'home_schema', 'Homepage sends business schema to Google', $p['schema'] ? 'pass' : 'warn', $p['schema'] ? 'Found structured data on the homepage.' : 'No structured data found. Turn on the SEO Suite and Schema (Weavit → SEO → Schema).', admin_url( 'admin.php?page=bootg-seo&tab=schema' ) );
		$checks[] = weavit_seo_check( 'home_social', 'Homepage has social share tags', $p['og'] ? 'pass' : 'warn', $p['og'] ? 'Open Graph tags found.' : 'No share card tags. Turn on the SEO Suite (Weavit → SEO → Titles & Social).', admin_url( 'admin.php?page=bootg-seo&tab=titles' ) );
	} else {
		$checks[] = weavit_seo_check( 'home_fetch', 'The analyser can read the homepage', 'warn', 'Could not load the homepage from the server (' . ( $code ?: $html ) . '), so page-level checks were skipped. Hosts that block a site from calling itself cause this.' );
	}

	// 10. Missing pages.
	$recent   = function_exists( 'weavit_404_recent_count' ) ? weavit_404_recent_count( 30 ) : 0;
	$checks[] = weavit_seo_check( 'notfound', 'No broken links reported in the last 30 days', 0 === $recent ? 'pass' : 'warn', 0 === $recent ? 'The 404 Monitor has nothing to report.' : $recent . ' address(es) returned "page not found". Redirect them from the 404 Monitor.', admin_url( 'admin.php?page=bootg-seo&tab=notfound' ) );

	return $checks;
}

function weavit_seo_audit_pages( $deadline ) {
	$types = (array) apply_filters( 'weavit_seo_audit_post_types', array( 'page', 'post', 'service', 'integration', 'guide', 'team_member' ) );
	$posts = get_posts( array(
		'post_type'      => $types,
		'post_status'    => 'publish',
		'posts_per_page' => 60,
		'orderby'        => 'type title',
		'order'          => 'ASC',
	) );

	$results = array();
	$titles  = array();
	foreach ( $posts as $post ) {
		if ( microtime( true ) > $deadline ) {
			$results[] = array( 'id' => 0, 'title' => '(stopped early to stay within the time limit — run it again for the rest)', 'url' => '', 'edit' => '', 'issues' => array() );
			break;
		}
		list( $code, $html ) = weavit_seo_fetch( get_permalink( $post ) );
		$issues              = array();
		if ( 200 !== $code ) {
			$issues[] = array( 'fail', 'The page could not be loaded (' . ( $code ?: $html ) . ').' );
		} else {
			$p = weavit_seo_parse_page( $html );
			$n = mb_strlen( $p['title'] );
			if ( '' === $p['title'] ) {
				$issues[] = array( 'fail', 'No title tag.' );
			} elseif ( $n < 25 ) {
				$issues[] = array( 'warn', 'Title is short (' . $n . ' characters) — aim for 30–60.' );
			} elseif ( $n > 65 ) {
				$issues[] = array( 'warn', 'Title is long (' . $n . ' characters) — Google cuts it off after about 60.' );
			}
			$titles[ strtolower( $p['title'] ) ][] = $post->ID;

			$d = mb_strlen( $p['description'] );
			if ( 0 === $d ) {
				$issues[] = array( 'warn', 'No meta description — add one in the page\'s SEO tab.' );
			} elseif ( $d < 70 ) {
				$issues[] = array( 'warn', 'Meta description is short (' . $d . ' characters) — aim for 120–160.' );
			} elseif ( $d > 165 ) {
				$issues[] = array( 'warn', 'Meta description is long (' . $d . ' characters) — it will be cut off.' );
			}
			if ( 0 === $p['h1'] ) {
				$issues[] = array( 'fail', 'No main heading (H1).' );
			} elseif ( $p['h1'] > 1 ) {
				$issues[] = array( 'warn', $p['h1'] . ' main headings (H1) — use one.' );
			}
			if ( $p['img_no_alt'] > 0 ) {
				$issues[] = array( 'warn', $p['img_no_alt'] . ' image(s) with no alt text.' );
			}
			if ( ! $p['canonical'] ) {
				$issues[] = array( 'warn', 'No canonical link.' );
			}
			if ( $p['noindex'] ) {
				$issues[] = array( 'warn', 'Hidden from search engines (noindex) — fine if intended.' );
			}
		}
		$results[ $post->ID ] = array(
			'id'     => $post->ID,
			'title'  => get_the_title( $post ),
			'type'   => get_post_type_object( $post->post_type )->labels->singular_name,
			'url'    => get_permalink( $post ),
			'edit'   => get_edit_post_link( $post->ID, 'raw' ),
			'issues' => $issues,
		);
	}
	foreach ( $titles as $text => $ids ) {
		if ( '' !== $text && count( $ids ) > 1 ) {
			foreach ( $ids as $id ) {
				if ( isset( $results[ $id ] ) ) {
					$results[ $id ]['issues'][] = array( 'warn', 'Same title as ' . ( count( $ids ) - 1 ) . ' other page(s) — each page needs its own.' );
				}
			}
		}
	}
	return array_values( $results );
}

add_action( 'admin_post_weavit_seo_audit', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'weavit_seo_audit' );
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 150 ); // phpcs:ignore
	}
	$site  = weavit_seo_audit_site();
	$pages = weavit_seo_audit_pages( microtime( true ) + 60 );
	update_option( WEAVIT_SEO_AUDIT_OPTION, array( 'time' => time(), 'site' => $site, 'pages' => $pages ), false );
	wp_safe_redirect( add_query_arg( array( 'page' => 'bootg-seo', 'tab' => 'analyser', 'ran' => 1 ), admin_url( 'admin.php' ) ) );
	exit;
} );

/** Score 0-100: pass = full credit, warning = half. */
function weavit_seo_audit_score( $audit ) {
	$points = 0;
	$total  = 0;
	foreach ( $audit['site'] as $c ) {
		++$total;
		$points += 'pass' === $c['status'] ? 1 : ( 'warn' === $c['status'] ? 0.5 : 0 );
	}
	foreach ( $audit['pages'] as $p ) {
		if ( ! $p['id'] ) {
			continue;
		}
		++$total;
		$worst   = 'pass';
		foreach ( $p['issues'] as $i ) {
			if ( 'fail' === $i[0] ) {
				$worst = 'fail';
				break;
			}
			$worst = 'warn';
		}
		$points += 'pass' === $worst ? 1 : ( 'warn' === $worst ? 0.5 : 0 );
	}
	return $total ? (int) round( 100 * $points / $total ) : 0;
}

function weavit_seo_dot( $status ) {
	$colors = array( 'pass' => '#00a32a', 'warn' => '#dba617', 'fail' => '#d63638' );
	return '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:' . esc_attr( $colors[ $status ] ?? '#999' ) . ';margin-right:8px;"></span>';
}

function weavit_seo_render_analyser_tab() {
	weavit_seo_status_notice();
	$audit = get_option( WEAVIT_SEO_AUDIT_OPTION );
	?>
	<h2>SEO Analyser</h2>
	<p style="max-width:780px;">Checks your site the way a search engine does — site-wide basics, then every public page. It takes up to a minute.</p>
	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Analysing… please wait';">
		<?php wp_nonce_field( 'weavit_seo_audit' ); ?>
		<input type="hidden" name="action" value="weavit_seo_audit">
		<button class="button button-primary button-hero" type="submit"><?php echo $audit ? 'Run the analysis again' : 'Analyse my site'; ?></button>
	</form>

	<?php if ( ! is_array( $audit ) || empty( $audit['site'] ) ) : return; endif; ?>
	<?php
	$score  = weavit_seo_audit_score( $audit );
	$counts = array( 'pass' => 0, 'warn' => 0, 'fail' => 0 );
	foreach ( $audit['site'] as $c ) {
		++$counts[ $c['status'] ];
	}
	$page_issues = 0;
	$page_fails  = 0;
	foreach ( $audit['pages'] as $p ) {
		foreach ( $p['issues'] as $i ) {
			++$page_issues;
			if ( 'fail' === $i[0] ) {
				++$page_fails;
			}
		}
	}
	$color = $score >= 85 ? '#00a32a' : ( $score >= 60 ? '#dba617' : '#d63638' );
	?>
	<div style="display:flex;gap:28px;align-items:center;margin:22px 0;flex-wrap:wrap;">
		<div style="width:110px;height:110px;border-radius:50%;border:10px solid <?php echo esc_attr( $color ); ?>;display:flex;align-items:center;justify-content:center;font-size:34px;font-weight:700;"><?php echo (int) $score; ?></div>
		<div>
			<p style="margin:0 0 4px;font-size:16px;"><strong>SEO score: <?php echo (int) $score; ?> / 100</strong></p>
			<p style="margin:0;color:#646970;">Site checks: <?php echo (int) $counts['pass']; ?> good, <?php echo (int) $counts['warn']; ?> to improve, <?php echo (int) $counts['fail']; ?> need fixing &middot; Pages: <?php echo (int) $page_issues; ?> issue(s) (<?php echo (int) $page_fails; ?> serious)<br>Last run <?php echo esc_html( human_time_diff( $audit['time'] ) ); ?> ago.</p>
		</div>
	</div>

	<?php if ( ! empty( $_GET['ran'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
		<div class="notice notice-success is-dismissible inline"><p>Analysis finished.</p></div>
	<?php endif; ?>

	<h3>Site-wide checks</h3>
	<table class="widefat striped" style="max-width:1000px;">
		<tbody>
		<?php foreach ( $audit['site'] as $c ) : ?>
			<tr>
				<td style="width:300px;"><?php echo weavit_seo_dot( $c['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?><strong><?php echo esc_html( $c['label'] ); ?></strong></td>
				<td><?php echo esc_html( $c['detail'] ); ?><?php echo $c['fix'] && 'pass' !== $c['status'] ? ' <a href="' . esc_url( $c['fix'] ) . '">Fix &rarr;</a>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<h3 style="margin-top:28px;">Pages</h3>
	<?php
	$with = array_filter( $audit['pages'], function ( $p ) {
		return $p['issues'] || ! $p['id'];
	} );
	$clean = count( $audit['pages'] ) - count( $with );
	?>
	<p><?php echo (int) $clean; ?> of <?php echo (int) count( $audit['pages'] ); ?> pages have no issues.</p>
	<?php if ( $with ) : ?>
		<table class="widefat striped" style="max-width:1000px;">
			<thead><tr><th style="width:280px;">Page</th><th>What to fix</th></tr></thead>
			<tbody>
			<?php foreach ( $with as $p ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $p['title'] ); ?></strong><?php echo ! empty( $p['type'] ) ? '<br><span class="description">' . esc_html( $p['type'] ) . '</span>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<?php if ( $p['edit'] ) : ?><br><a href="<?php echo esc_url( $p['edit'] ); ?>">Edit</a> | <a href="<?php echo esc_url( $p['url'] ); ?>" target="_blank" rel="noopener">View</a><?php endif; ?></td>
					<td>
						<?php foreach ( $p['issues'] as $i ) : ?>
							<div><?php echo weavit_seo_dot( $i[0] ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( $i[1] ); ?></div>
						<?php endforeach; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
	<?php
}
