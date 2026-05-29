<?php
/**
 * CSF options loader.
 *
 * Replaces Houzez's `framework/options/main.php` aggregator.
 *
 * Storage strategy:
 *   - legacy_fave mode → reads/writes `wp_options['houzez_options']`
 *     (preserves existing Houzez customer settings)
 *   - native_esc mode  → uses `wp_options['estatesite_options']`
 *
 * @package EstateSite\Core\Options
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'CSF' ) ) {
	return; // CodeStar not loaded — bail.
}

// Option files reference Houzez helper functions (houzez_get_localization etc).
// Pull in the alias stubs so they exist before any setSection call runs.
require_once ESCORE_DIR . 'includes/compat/houzez-function-aliases.php';

global $estatesite_opt_prefix;

// Pick storage key based on compat mode.
$estatesite_opt_prefix = \EstateSite\Core\Compat_Mode::is_legacy()
	? 'houzez_options'
	: 'estatesite_options';

// CSF arguments for the options page itself.
CSF::createOptions( $estatesite_opt_prefix, [
	'menu_title'      => __( 'EstateSite Options', 'estatesite-wpcore' ),
	'menu_slug'       => 'estatesite_options',
	'menu_type'       => 'submenu',
	'menu_parent'     => 'estatesite',
	'framework_title' => __( 'EstateSite', 'estatesite-wpcore' ),
	'show_search'     => true,
	'show_reset_all'  => true,
	'show_reset_section' => true,
	'show_all_options'=> false,
	'save_defaults'   => true,
	'ajax_save'       => true,
	'theme'           => 'light',
	'show_bar_menu'   => false,
	'admin_bar_menu'  => false,
] );

// Pull in each ported section file. Order matters for menu sort.
$section_files = [
	'general.php',
	'translation.php',
	'logo-favicons.php',
	'header.php',
	'topbar.php',
	'login-register.php',
	'price-currency.php',
	'typography.php',
	'styling.php',
	'property-detail.php',
	'print-property.php',
	'add-new-property.php',
	'advanced-search.php',
	'map.php',
	'halfmap.php',
	'listing-options.php',
	'taxonomies.php',
	'contact-forms.php',
	'reCaptcha.php',
	'membership.php',
	'agents.php',
	'agencies.php',
	'profile-fields.php',
	'invoices.php',
	'blog.php',
	'emails.php',
	'404.php',
	'footer.php',
	'optimization.php',
	'gdpr.php',
	'custom-code.php',
];

// Helper-array defaults that some ported option files expect to be set
// externally (in Houzez they come from a fields-builder class). Set on
// $GLOBALS so they're visible from any scope including the closure that
// require_once's each option file.
$GLOBALS['custom_fields_array']         = [];
$GLOBALS['contact_fields_array']        = [];
$GLOBALS['houzez_listing_fields_array'] = [];

foreach ( $section_files as $file ) {
	$path = __DIR__ . '/' . $file;
	if ( ! is_readable( $path ) ) {
		continue;
	}

	// Each section file is wrapped so a fatal in one doesn't break the whole
	// panel. Errors get logged but the rest of the panel still loads.
	// The require runs via an anonymous closure that extracts $GLOBALS, so
	// ported files see the helper-array defaults in their local scope.
	try {
		( static function ( $__file ) {
			extract( $GLOBALS, EXTR_REFS );
			require_once $__file;
		} )( $path );
	} catch ( \Throwable $e ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[EstateSite] Option section ' . $file . ' failed to load: ' . $e->getMessage() );
		}
	}
}
