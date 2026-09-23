<?php
/**
 * Wires this plugin into GitHub Releases for update checks (instead of
 * wordpress.org, which this plugin will never be submitted to), via the
 * Plugin Update Checker library. Also shows a dismissible "update available"
 * card in the same visual style as third-party plugin notices (Elementor,
 * Complianz, etc.) rather than relying only on the plain wp-admin row.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once WEAVIT_ENGINE_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

add_action( 'init', function () {
	$update_checker = PucFactory::buildUpdateChecker(
		'https://github.com/BSICTVince/weavit-engine',
		WEAVIT_ENGINE_DIR . 'weavit-engine.php',
		'weavit-engine'
	);
	// Tag-based checking: PUC finds the highest version-numbered git tag and
	// builds its download URL automatically -- a plain `git tag vX.Y.Z` + push
	// is enough. (No GitHub Release object or manually-uploaded zip needed --
	// that's a separate, stricter mode this plugin doesn't use.)
	$update_checker->setBranch( 'main' );
} );

/**
 * Elementor-style dismissible admin notice card. Only appears when the
 * update checker above has actually found a newer GitHub release; "Maybe
 * later" hides it for a week, "Don't show again" hides it until the next
 * update after this one.
 */
add_action( 'admin_notices', function () {
	bootg_render_update_notice_card(
		'plugin',
		plugin_basename( WEAVIT_ENGINE_DIR . 'weavit-engine.php' ),
		'Weavit Engine',
		WEAVIT_ENGINE_URI . 'assets/images/weavit-icon.png'
	);
} );
