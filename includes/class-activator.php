<?php
/**
 * Activation hook handler.
 *
 * @package EstateSite\Core
 */

namespace EstateSite\Core;

defined( 'ABSPATH' ) || exit;

final class Activator {

	public static function activate(): void {
		self::set_initial_options();

		// Detect compat mode now so first page load doesn't pay the detection cost.
		Compat_Mode::get();

		// Register CPTs once so flush_rewrite_rules has something to work with.
		CPT::register();
		flush_rewrite_rules();

		// Stamp the activation time so admin can show "Hello" notice once.
		if ( ! get_option( 'estatesite_activated_at' ) ) {
			update_option( 'estatesite_activated_at', time(), false );
		}

		// Schedule weekly nomenclatures refresh (idempotent).
		Nomenclatures::register();

		// Seed nomenclatures on first activation so dropdowns work from day one.
		// If the token isn't configured yet, this no-ops gracefully and the cron
		// will pick it up after the user configures EA Sync.
		if ( ! get_transient( Nomenclatures::TRANSIENT_KEY ) && Nomenclatures::get_token() ) {
			Nomenclatures::seed();
		}
	}

	private static function set_initial_options(): void {
		if ( get_option( 'estatesite_options', false ) === false ) {
			update_option( 'estatesite_options', [
				'currency_symbol'    => '€',
				'currency_position'  => 'before',
				'thousand_separator' => ',',
				'decimal_separator'  => '.',
			], false );
		}
	}
}
