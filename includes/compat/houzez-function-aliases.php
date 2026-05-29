<?php
/**
 * Houzez function aliases — declared in global scope.
 *
 * Loaded conditionally by Houzez_Compat::declare_function_aliases() when
 * we're in legacy_fave or migrating mode AND specific Houzez functions
 * aren't already defined (because the Houzez theme is not active).
 *
 * These are intentionally narrow: only the functions companion plugins call
 * from PHP runtime (not just from theme templates) get aliased here.
 *
 * @package EstateSite\Core\Compat
 */

defined( 'ABSPATH' ) || exit;

/*
 * Houzez-defined constants — only the ones our ported code references.
 *
 * HOUZEZ_IMAGE is defined LATE (on after_setup_theme priority 0) so an active
 * theme has the chance to set it to its own image dir first. If we reach this
 * point and no theme has set it, fall back to Core's placeholder dir.
 */
add_action( 'after_setup_theme', function () {
	if ( ! defined( 'HOUZEZ_IMAGE' ) ) {
		// Prefer active theme's img/ dir (Houzez convention).
		$theme_img = trailingslashit( get_template_directory_uri() ) . 'img/';
		define( 'HOUZEZ_IMAGE', $theme_img );
	}
}, 0 );

// These two have no theme-dependent value, safe to define unconditionally now.
defined( 'HOUZEZ_THEME_NAME' ) || define( 'HOUZEZ_THEME_NAME', 'EstateSite' );
defined( 'HOUZEZ_THEME_SLUG' ) || define( 'HOUZEZ_THEME_SLUG', 'estatesite' );

if ( ! function_exists( 'houzez_option' ) ) {
	/**
	 * Houzez's option reader. Companions call this everywhere.
	 * In legacy_fave mode reads from `houzez_options`, otherwise `estatesite_options`.
	 *
	 * @param string $id       Option key.
	 * @param mixed  $fallback Returned if option is unset or empty.
	 * @param string $param    If the stored value is an array (e.g. media field
	 *                         storing ['id'=>X,'url'=>Y]), extract this sub-key.
	 */
	function houzez_option( $id, $fallback = false, $param = false ) {
		$store = \EstateSite\Core\Compat_Mode::is_legacy() ? 'houzez_options' : 'estatesite_options';
		$opts  = (array) get_option( $store, [] );

		if ( $fallback === false ) {
			$fallback = '';
		}

		$value = ( isset( $opts[ $id ] ) && $opts[ $id ] !== '' ) ? $opts[ $id ] : $fallback;

		if ( ! empty( $opts[ $id ] ) && $param && is_array( $opts[ $id ] ) ) {
			$value = $opts[ $id ][ $param ] ?? $fallback;
		}

		return $value;
	}
}

if ( ! function_exists( 'fave_option' ) ) {
	/** Older Houzez option-reader name — alias of houzez_option. */
	function fave_option( $id, $fallback = false, $param = false ) {
		return houzez_option( $id, $fallback, $param );
	}
}

if ( ! function_exists( 'houzez_is_license_activated' ) ) {
	/**
	 * EstateSite is not Houzez. We don't gate features behind Favethemes
	 * license activation. Always return true so ported UIs (e.g. the Houzez
	 * Library templates modal) don't show "Activate License" walls.
	 *
	 * Strippable counterpart: tools/port-helper-file.php STRIP_FUNCTIONS
	 * removes the original Houzez houzez_is_license_activated() during port,
	 * so this stub becomes the canonical implementation.
	 */
	function houzez_is_license_activated() {
		return true;
	}
}

/*
 * Template-detection helpers — when EstateSite Classic is active, none of
 * the Houzez page templates apply, so every check is false. Companion plugins
 * (houzez-studio especially) call these from hooked enqueue/render code that
 * fires even on non-Houzez themes.
 */
if ( ! function_exists( 'houzez_is_listings_template' ) )           { function houzez_is_listings_template()           { return false; } }
if ( ! function_exists( 'houzez_is_listings_template_v7' ) )        { function houzez_is_listings_template_v7()        { return false; } }
if ( ! function_exists( 'houzez_is_listings_v3' ) )                 { function houzez_is_listings_v3()                 { return false; } }
if ( ! function_exists( 'houzez_is_fullwidth_2cols_custom_width' ) ){ function houzez_is_fullwidth_2cols_custom_width(){ return false; } }
if ( ! function_exists( 'houzez_is_fullwidth_3cols_custom_width' ) ){ function houzez_is_fullwidth_3cols_custom_width(){ return false; } }
if ( ! function_exists( 'houzez_is_fullwidth_4cols_custom_width' ) ){ function houzez_is_fullwidth_4cols_custom_width(){ return false; } }
if ( ! function_exists( 'houzez_is_half_map_template' ) )           { function houzez_is_half_map_template()           { return false; } }
if ( ! function_exists( 'houzez_is_property_search_template' ) )    { function houzez_is_property_search_template()    { return false; } }
if ( ! function_exists( 'houzez_is_property_compare_template' ) )   { function houzez_is_property_compare_template()   { return false; } }
if ( ! function_exists( 'houzez_is_property_submit_template' ) )    { function houzez_is_property_submit_template()    { return false; } }
if ( ! function_exists( 'houzez_is_user_dashboard_template' ) )     { function houzez_is_user_dashboard_template()     { return false; } }
if ( ! function_exists( 'houzez_is_user_messages_template' ) )      { function houzez_is_user_messages_template()      { return false; } }
if ( ! function_exists( 'houzez_is_membership_template' ) )         { function houzez_is_membership_template()         { return false; } }
if ( ! function_exists( 'houzez_is_agent_template' ) )              { function houzez_is_agent_template()              { return false; } }
if ( ! function_exists( 'houzez_is_agency_template' ) )             { function houzez_is_agency_template()             { return false; } }

// houzez_site_width is called by houzez_is_fullwidth_*_custom_width — stub for safety.
if ( ! function_exists( 'houzez_site_width' ) ) {
	function houzez_site_width() { return '1210px'; }
}

/*
 * Helper functions referenced by ported option files.
 * These are minimal stubs returning empty arrays — the option fields they
 * populate are inert until Phase 2 ports the real implementations.
 */

if ( ! function_exists( 'houzez_get_localization' ) ) {
	function houzez_get_localization() { return []; }
}

if ( ! function_exists( 'houzez_available_currencies' ) ) {
	function houzez_available_currencies() { return []; }
}

if ( ! function_exists( 'houzez_listing_fields_for_icons' ) ) {
	function houzez_listing_fields_for_icons() { return []; }
}

if ( ! function_exists( 'houzez_listing_fields_for_icons_luxury' ) ) {
	function houzez_listing_fields_for_icons_luxury() { return []; }
}

if ( ! function_exists( 'houzez_theme_branding' ) ) {
	function houzez_theme_branding() { return 'EstateSite'; }
}
