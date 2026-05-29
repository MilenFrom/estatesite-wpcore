<?php
/**
 * Property meta accessor — the keystone of dual-mode compatibility.
 *
 * All property meta access across Core, Classic theme, and Elementor pkg
 * MUST go through this class. Direct get_post_meta($id, 'fave_*') calls
 * are forbidden in our own code — they break the migration path.
 *
 * Logical keys (e.g., 'price') are stable; physical keys (fave_/esc_)
 * are swapped per mode.
 *
 * Same instance handles non-property meta too: agent, agency, user, term,
 * page, package, testimonial, partner. Use the `for_agent()`, `for_user()`,
 * etc. helpers — they route to the right entity in the map.
 *
 * @package EstateSite\Core
 */

namespace EstateSite\Core;

defined( 'ABSPATH' ) || exit;

final class Property {

	public const ENTITY_PROPERTY    = 'property';
	public const ENTITY_AGENT       = 'agent';
	public const ENTITY_AGENCY      = 'agency';
	public const ENTITY_USER        = 'user';
	public const ENTITY_TERM        = 'term';
	public const ENTITY_PAGE        = 'page';
	public const ENTITY_PACKAGE     = 'package';
	public const ENTITY_TESTIMONIAL = 'testimonial';
	public const ENTITY_PARTNER     = 'partner';

	/** @var array|null Cached key map loaded from meta-key-map.php. */
	private static $map = null;

	/** Load the map lazily. */
	private static function map(): array {
		if ( self::$map === null ) {
			$file = ESCORE_DIR . 'includes/compat/meta-key-map.php';
			self::$map = is_readable( $file ) ? (array) require $file : [];
		}
		return self::$map;
	}

	/** Test/reset helper. */
	public static function reload_map(): void {
		self::$map = null;
	}

	/**
	 * Get the physical meta key for a logical key in the active mode.
	 *
	 * @return string|null Null if unknown logical key (caller may treat as raw).
	 */
	public static function key( string $logical, string $entity = self::ENTITY_PROPERTY ): ?string {
		$map = self::map();
		if ( ! isset( $map[ $entity ][ $logical ] ) ) {
			return null;
		}
		[$fave, $esc] = $map[ $entity ][ $logical ];
		$mode = Compat_Mode::get();
		// Native writes esc_*; legacy + migrating prefer fave_* for compatibility.
		return $mode === Compat_Mode::NATIVE_ESC ? $esc : $fave;
	}

	/**
	 * Read meta by logical key. Falls back to legacy key in native mode if value missing.
	 *
	 * @param int|null $object_id   Post ID (or user ID for entity=user, term ID for entity=term).
	 *                              Null/0 returns $default without touching the DB — lets editor
	 *                              previews and other "no current object" callers render safely
	 *                              instead of fataling on `$post->ID` when $post is null.
	 * @param string   $logical     Logical field name.
	 * @param mixed    $default     Returned if no value found (or $object_id is null/0).
	 * @param string   $entity      Entity scope (default: property).
	 * @return mixed
	 */
	public static function get( ?int $object_id, string $logical, $default = null, string $entity = self::ENTITY_PROPERTY ) {
		if ( ! $object_id ) {
			return $default;
		}

		$key = self::key( $logical, $entity );

		// Unknown logical key — allow raw access (escape hatch).
		if ( $key === null ) {
			$raw = self::_raw_get( $object_id, $logical, $entity );
			return ( $raw === '' || $raw === null ) ? $default : $raw;
		}

		$value = self::_raw_get( $object_id, $key, $entity );

		// Native-mode fallback: try legacy key for unmigrated objects.
		if ( $value === '' && Compat_Mode::is_native() ) {
			$map      = self::map();
			$legacy   = $map[ $entity ][ $logical ][0];
			$value    = self::_raw_get( $object_id, $legacy, $entity );
		}

		return ( $value === '' || $value === null ) ? $default : $value;
	}

	/**
	 * Write meta by logical key. Dual-writes in migrating mode.
	 *
	 * @param int|null $object_id Null/0 returns false without writing.
	 */
	public static function set( ?int $object_id, string $logical, $value, string $entity = self::ENTITY_PROPERTY ): bool {
		if ( ! $object_id ) {
			return false;
		}

		$key = self::key( $logical, $entity );

		if ( $key === null ) {
			return (bool) self::_raw_set( $object_id, $logical, $value, $entity );
		}

		$result = (bool) self::_raw_set( $object_id, $key, $value, $entity );

		// Migrating mode: keep both keys in sync.
		if ( Compat_Mode::is_migrating() ) {
			$map        = self::map();
			[ $fave, $esc ] = $map[ $entity ][ $logical ];
			$partner    = $key === $fave ? $esc : $fave;
			self::_raw_set( $object_id, $partner, $value, $entity );
		}

		return $result;
	}

	/**
	 * Delete meta by logical key.
	 *
	 * @param int|null $object_id Null/0 returns false without touching the DB.
	 */
	public static function delete( ?int $object_id, string $logical, string $entity = self::ENTITY_PROPERTY ): bool {
		if ( ! $object_id ) {
			return false;
		}

		$key = self::key( $logical, $entity );

		if ( $key === null ) {
			return (bool) self::_raw_delete( $object_id, $logical, $entity );
		}

		$result = (bool) self::_raw_delete( $object_id, $key, $entity );

		if ( Compat_Mode::is_migrating() ) {
			$map = self::map();
			[ $fave, $esc ] = $map[ $entity ][ $logical ];
			$partner = $key === $fave ? $esc : $fave;
			self::_raw_delete( $object_id, $partner, $entity );
		}

		return $result;
	}

	/**
	 * Bulk read — fetches the entire meta blob in a single query, then plucks fields.
	 * Critical for list views (archive grids, search results) to avoid N+1.
	 *
	 * @param int|null $object_id    Object ID. Null/0 returns [logical => null, ...].
	 * @param string[] $logical_keys List of logical keys to fetch.
	 * @param string   $entity       Entity scope.
	 * @return array<string,mixed>   Logical key => value (missing keys → null).
	 */
	public static function get_many( ?int $object_id, array $logical_keys, string $entity = self::ENTITY_PROPERTY ): array {
		if ( ! $object_id ) {
			return array_fill_keys( $logical_keys, null );
		}

		$raw = self::_raw_get_all( $object_id, $entity );
		$out = [];

		foreach ( $logical_keys as $logical ) {
			$key = self::key( $logical, $entity );

			if ( $key === null ) {
				// Unknown logical key — try as raw key.
				$out[ $logical ] = isset( $raw[ $logical ][0] ) ? maybe_unserialize( $raw[ $logical ][0] ) : null;
				continue;
			}

			$value = isset( $raw[ $key ][0] ) ? maybe_unserialize( $raw[ $key ][0] ) : null;

			// Native-mode fallback for unmigrated data.
			if ( ( $value === null || $value === '' ) && Compat_Mode::is_native() ) {
				$map    = self::map();
				$legacy = $map[ $entity ][ $logical ][0];
				$value  = isset( $raw[ $legacy ][0] ) ? maybe_unserialize( $raw[ $legacy ][0] ) : null;
			}

			$out[ $logical ] = $value;
		}

		return $out;
	}

	/**
	 * Return the full key map (for tools, REST schemas, audits).
	 */
	public static function key_map(): array {
		return self::map();
	}

	// ---------------------------------------------------------------------
	// Routing helpers — pick the right WP API per entity type.
	// ---------------------------------------------------------------------

	private static function _raw_get( int $id, string $key, string $entity ) {
		if ( $entity === self::ENTITY_USER ) {
			return get_user_meta( $id, $key, true );
		}
		if ( $entity === self::ENTITY_TERM ) {
			return get_term_meta( $id, $key, true );
		}
		return get_post_meta( $id, $key, true );
	}

	private static function _raw_set( int $id, string $key, $value, string $entity ) {
		if ( $entity === self::ENTITY_USER ) {
			return update_user_meta( $id, $key, $value );
		}
		if ( $entity === self::ENTITY_TERM ) {
			return update_term_meta( $id, $key, $value );
		}
		return update_post_meta( $id, $key, $value );
	}

	private static function _raw_delete( int $id, string $key, string $entity ) {
		if ( $entity === self::ENTITY_USER ) {
			return delete_user_meta( $id, $key );
		}
		if ( $entity === self::ENTITY_TERM ) {
			return delete_term_meta( $id, $key );
		}
		return delete_post_meta( $id, $key );
	}

	private static function _raw_get_all( int $id, string $entity ): array {
		if ( $entity === self::ENTITY_USER ) {
			return get_user_meta( $id );
		}
		if ( $entity === self::ENTITY_TERM ) {
			return get_term_meta( $id );
		}
		return get_post_meta( $id );
	}
}
