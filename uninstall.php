<?php
/**
 * Uninstall handler — removes all EstateSite Core data on plugin delete.
 *
 * Runs when the user clicks "Delete" in the Plugins screen (not on deactivate).
 *
 * @package EstateSite\Core
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Options.
delete_option( 'estatesite_options' );
delete_option( 'estatesite_compat_mode' );
delete_option( 'estatesite_activated_at' );

// User meta dismissals.
delete_metadata( 'user', 0, 'estatesite_dismissed_welcome', '', true );

// Transients (anything we cached).
delete_transient( 'estatesite_nomenclatures_data' );

// Intentionally do NOT delete property/agent/agency posts or their meta —
// that's destructive and irreversible. If the user wants a full wipe they
// can use a separate "Reset all data" admin action (not built yet).
