<?php
/**
 * Plugin Name: Weavit Engine
 * Plugin URI: https://vinceorodazo.com
 * Description: Core reusable engine — custom post types, meta boxes, SEO fields, native SMTP, Site Options, and a custom Forms engine (builder, entries, notifications) — that Weavit-built themes are built on top of. No page builder, no ACF.
 * Version: 0.1.2
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Author: Weavit | Vince O. Dazo
 * Author URI: https://vinceorodazo.com
 * Text Domain: weavit-engine
 * Update URI: https://github.com/BSICTVince/weavit-engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WEAVIT_ENGINE_VERSION', '0.1.2' );
define( 'WEAVIT_ENGINE_DIR', plugin_dir_path( __FILE__ ) );
define( 'WEAVIT_ENGINE_URI', plugin_dir_url( __FILE__ ) );

require_once WEAVIT_ENGINE_DIR . 'inc/cpts.php';
require_once WEAVIT_ENGINE_DIR . 'inc/meta-boxes.php';
require_once WEAVIT_ENGINE_DIR . 'inc/seo-meta.php';
require_once WEAVIT_ENGINE_DIR . 'inc/smtp-settings.php';
require_once WEAVIT_ENGINE_DIR . 'inc/site-options.php';
require_once WEAVIT_ENGINE_DIR . 'inc/importer.php';
require_once WEAVIT_ENGINE_DIR . 'inc/forms/forms-cpt.php';
require_once WEAVIT_ENGINE_DIR . 'inc/forms/forms-render.php';
require_once WEAVIT_ENGINE_DIR . 'inc/forms/forms-submit.php';
require_once WEAVIT_ENGINE_DIR . 'inc/forms/forms-admin.php';
require_once WEAVIT_ENGINE_DIR . 'inc/recaptcha.php';
require_once WEAVIT_ENGINE_DIR . 'inc/seo-tools.php';
require_once WEAVIT_ENGINE_DIR . 'inc/redirects.php';
require_once WEAVIT_ENGINE_DIR . 'inc/redirects-admin.php';
require_once WEAVIT_ENGINE_DIR . 'inc/htaccess-editor.php';
require_once WEAVIT_ENGINE_DIR . 'inc/admin-notice-card.php';
require_once WEAVIT_ENGINE_DIR . 'inc/updates.php';

/**
 * A theme built for this engine should declare `add_theme_support( 'weavit-engine' )`
 * in its functions.php. If it doesn't (or the theme wasn't built for Weavit
 * at all), nothing breaks — every function here still works standalone —
 * this notice is just a heads-up for whoever is developing the theme.
 */
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) || current_theme_supports( 'weavit-engine' ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || 'plugins' !== $screen->id ) {
		return;
	}
	echo '<div class="notice notice-warning"><p>Weavit Engine is active, but the current theme doesn\'t declare <code>add_theme_support( \'weavit-engine\' )</code>. The engine still works standalone, but form field markup will use its plain fallback styling instead of the theme\'s own.</p></div>';
} );
