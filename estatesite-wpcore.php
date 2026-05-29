<?php
/**
 * Plugin Name: EstateSite Core
 * Plugin URI:  https://estatesite.eu
 * Description: Real estate property management core — CPTs, search, agents, options, metaboxes, payments. Powers the EstateSite Classic theme and Elementor packages.
 * Version:     1.0.4
 * Author:      Estate Site
 * Author URI:  https://estatesite.eu
 * Text Domain: estatesite-wpcore
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

define( 'ESCORE_VERSION',  '1.0.4' );
define( 'ESCORE_FILE',     __FILE__ );
define( 'ESCORE_DIR',      plugin_dir_path( __FILE__ ) );
define( 'ESCORE_URL',      plugin_dir_url( __FILE__ ) );
define( 'ESCORE_BASENAME', plugin_basename( __FILE__ ) );

// Composer autoload (PSR-4 namespace EstateSite\Core).
// Falls back gracefully if vendor/ missing so plain unzip-and-activate works.
if ( file_exists( ESCORE_DIR . 'vendor/autoload.php' ) ) {
	require_once ESCORE_DIR . 'vendor/autoload.php';
} else {
	// Hand-rolled minimal autoloader (Phase 0 — before Composer is required).
	spl_autoload_register( function ( $class ) {
		if ( strpos( $class, 'EstateSite\\Core\\' ) !== 0 ) {
			return;
		}
		$relative = substr( $class, strlen( 'EstateSite\\Core\\' ) );
		$parts    = explode( '\\', $relative );
		$last     = array_pop( $parts );
		$file     = ESCORE_DIR . 'includes/';
		if ( $parts ) {
			$file .= strtolower( implode( '/', $parts ) ) . '/';
		}
		$file .= 'class-' . strtolower( str_replace( '_', '-', $last ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	} );
}

// CodeStar Framework (bundled — not a separate plugin install).
// Guard against double-load if user has CodeStar as standalone plugin too.
if ( ! class_exists( 'CSF' ) ) {
	require_once ESCORE_DIR . 'codestar-framework/codestar-framework.php';
}

// Meta Box framework (bundled — used by all Houzez-derived metabox files).
// Guard against double-load if user has Meta Box as standalone plugin too.
if ( ! defined( 'RWMB_VER' ) ) {
	require_once ESCORE_DIR . 'lib/meta-box/meta-box.php';
}

// ---------------------------------------------------------------------------
// Update pipeline — native WP filter pointing at our own JSON manifest.
//
// The manifest at the endpoint URL describes the latest version + a download
// URL pointing at the corresponding GitHub release zip. WP's standard
// Dashboard → Updates UI handles the install. No vendored library, no
// auth token in plugin source — we control the manifest, customers see
// updates appear automatically.
//
// Override the endpoint per-install via wp-config.php:
//     define( 'ESTATESITE_UPDATE_ENDPOINT_WPCORE', 'https://staging.example/updates/wpcore.json' );
// ---------------------------------------------------------------------------
require_once ESCORE_DIR . 'includes/class-update-checker.php';
new \EstateSite\Core\Update_Checker(
	'plugin',
	ESCORE_BASENAME,
	ESCORE_VERSION,
	defined( 'ESTATESITE_UPDATE_ENDPOINT_WPCORE' )
		? ESTATESITE_UPDATE_ENDPOINT_WPCORE
		: 'https://dev.estatesite.eu/updates/estatesite-wpcore.json'
);

// Activation / deactivation hooks (registered before bootstrap so they fire on first activate).
register_activation_hook(   __FILE__, [ '\EstateSite\Core\Activator',   'activate' ] );
register_deactivation_hook( __FILE__, [ '\EstateSite\Core\Deactivator', 'deactivate' ] );

// Bootstrap. Priority 5 so Elementor package (default 10) sees Core ready.
add_action( 'plugins_loaded', function () {
	\EstateSite\Core\Plugin::instance();
}, 5 );
