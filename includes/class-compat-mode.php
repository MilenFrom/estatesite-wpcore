<?php
/**
 * Compat mode — legacy_fave (Houzez) vs native_esc (fresh install).
 *
 * Mode is decided once at activation (or first access), cached in wp_options.
 * Toggling requires admin action; this class is read-mostly.
 *
 * @package EstateSite\Core
 */

namespace EstateSite\Core;

defined( 'ABSPATH' ) || exit;

final class Compat_Mode {

	public const LEGACY_FAVE = 'legacy_fave';
	public const NATIVE_ESC  = 'native_esc';
	public const MIGRATING   = 'migrating';

	private const OPTION_KEY = 'estatesite_compat_mode';

	/** @var string|null In-process cache to avoid repeated get_option calls. */
	private static $cached = null;

	/**
	 * Get the current mode. Detects + persists on first call if missing.
	 */
	public static function get(): string {
		if ( self::$cached !== null ) {
			return self::$cached;
		}
		$mode = get_option( self::OPTION_KEY );
		if ( ! $mode ) {
			$mode = self::detect_and_persist();
		}
		self::$cached = $mode;
		return $mode;
	}

	/**
	 * Force-set the mode (admin override, migration controller, tests).
	 */
	public static function set( string $mode ): bool {
		if ( ! in_array( $mode, [ self::LEGACY_FAVE, self::NATIVE_ESC, self::MIGRATING ], true ) ) {
			return false;
		}
		update_option( self::OPTION_KEY, $mode, true );
		self::$cached = $mode;
		return true;
	}

	/**
	 * Reset the cache. Used in tests; rarely needed in production.
	 */
	public static function reset_cache(): void {
		self::$cached = null;
	}

	/**
	 * Convenience helpers.
	 */
	public static function is_legacy(): bool {
		return self::get() === self::LEGACY_FAVE;
	}

	public static function is_native(): bool {
		return self::get() === self::NATIVE_ESC;
	}

	public static function is_migrating(): bool {
		return self::get() === self::MIGRATING;
	}

	/**
	 * Heuristic detection (runs ONCE):
	 *   1. If Houzez `houzez_options` exists → legacy
	 *   2. If any post has `fave_property_price` meta → legacy
	 *   3. Otherwise → native
	 */
	private static function detect_and_persist(): string {
		global $wpdb;

		$mode = self::NATIVE_ESC;

		if ( get_option( 'houzez_options' ) !== false ) {
			$mode = self::LEGACY_FAVE;
		} else {
			$found = $wpdb->get_var(
				"SELECT 1 FROM {$wpdb->postmeta} WHERE meta_key = 'fave_property_price' LIMIT 1"
			);
			if ( $found ) {
				$mode = self::LEGACY_FAVE;
			}
		}

		update_option( self::OPTION_KEY, $mode, true );
		return $mode;
	}
}
