<?php
/**
 * Theme template action wiring.
 *
 * Ported verbatim from Houzez framework/template-hooks.php into Core so that
 * EstateSite Classic (and any theme that calls do_action('houzez_header') /
 * do_action('houzez_footer') in its header.php/footer.php) actually renders
 * the chrome. Without these hooks the header/footer do_action() calls fire
 * but no callbacks are registered — visible symptom is a blank navigation
 * area and a blank footer.
 *
 * The callback functions themselves live in
 * includes/functions/template-functions.php (also ported from Houzez).
 *
 * Lives in Core (not in the theme) for two reasons:
 *   1. The callback functions live in Core — keeping the action wiring with
 *      the callbacks reduces split-brain risk if a future refactor moves
 *      one or the other.
 *   2. Any theme using the houzez_* action vocabulary picks these up for
 *      free — Classic today, custom themes tomorrow.
 *
 * @package EstateSite\Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Header.
 *
 * @see houzez_template_header()
 */
add_action( 'houzez_header',        'houzez_template_header',        10 );
add_action( 'houzez_after_header',  'houzez_search_after_header',    10 );
add_action( 'houzez_after_banner',  'houzez_search_after_banner',    10 );

/**
 * Footer.
 *
 * @see houzez_template_footer()
 */
add_action( 'houzez_footer',        'houzez_template_footer',        10 );

add_action( 'houzez_after_footer',  'houzez_backtotop_compare' );
add_action( 'houzez_after_footer',  'houzez_login_password_reset' );
add_action( 'houzez_after_footer',  'houzez_listing_preview' );
add_action( 'houzez_after_footer',  'houzez_realtor_contact_form' );
