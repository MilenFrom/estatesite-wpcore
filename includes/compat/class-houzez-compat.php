<?php
/**
 * Houzez compatibility shim.
 *
 * Active only in legacy_fave + migrating modes.
 *
 * Mirrors meta writes: when a `fave_*` key is written, the corresponding
 * `esc_*` key is updated too (and vice versa). Lets v2-aware code and
 * legacy Houzez code coexist on the same site without dual-write logic
 * scattered everywhere.
 *
 * Infinite-loop guard: maintains a per-request set of "in-flight" writes
 * so the mirror's own update_post_meta doesn't fire another mirror.
 *
 * @package EstateSite\Core\Compat
 */

namespace EstateSite\Core\Compat;

use EstateSite\Core\Compat_Mode;
use EstateSite\Core\Property;

defined( 'ABSPATH' ) || exit;

final class Houzez_Compat {

	/** @var array<string,bool> "{$object_id}:{$meta_key}" → true means a mirror write is in progress. */
	private static $in_flight = [];

	/** @var array<string,string>|null Reverse map: physical_key → logical_key:entity. Lazy. */
	private static $reverse = null;

	public static function register(): void {
		add_action( 'added_post_meta',   [ self::class, 'mirror_post_write' ],  10, 4 );
		add_action( 'updated_post_meta', [ self::class, 'mirror_post_write' ],  10, 4 );
		add_action( 'deleted_post_meta', [ self::class, 'mirror_post_delete' ], 10, 3 );

		add_action( 'added_user_meta',   [ self::class, 'mirror_user_write' ],  10, 4 );
		add_action( 'updated_user_meta', [ self::class, 'mirror_user_write' ],  10, 4 );
		add_action( 'deleted_user_meta', [ self::class, 'mirror_user_delete' ], 10, 3 );

		add_action( 'added_term_meta',   [ self::class, 'mirror_term_write' ],  10, 4 );
		add_action( 'updated_term_meta', [ self::class, 'mirror_term_write' ],  10, 4 );
		add_action( 'deleted_term_meta', [ self::class, 'mirror_term_delete' ], 10, 3 );

		self::declare_function_aliases();
	}

	/** Reverse-lookup table: physical_key → [logical, entity, fave_or_esc]. Built once. */
	private static function reverse(): array {
		if ( self::$reverse !== null ) {
			return self::$reverse;
		}
		self::$reverse = [];
		$map = Property::key_map();
		foreach ( $map as $entity => $logicals ) {
			foreach ( $logicals as $logical => [ $fave, $esc ] ) {
				self::$reverse[ $fave ] = [ $logical, $entity, 'fave' ];
				if ( $esc !== $fave ) {
					self::$reverse[ $esc ] = [ $logical, $entity, 'esc' ];
				}
			}
		}
		return self::$reverse;
	}

	/** Get the partner key (the OTHER side) for a given meta key. */
	private static function partner_key( string $meta_key, string $entity_filter ): ?string {
		$reverse = self::reverse();
		if ( ! isset( $reverse[ $meta_key ] ) ) {
			return null;
		}
		[ $logical, $entity, $side ] = $reverse[ $meta_key ];
		if ( $entity !== $entity_filter ) {
			return null;
		}
		$map  = Property::key_map();
		[ $fave, $esc ] = $map[ $entity ][ $logical ];
		return $side === 'fave' ? $esc : $fave;
	}

	// -------------------------------------------------------------------
	// POST meta hooks
	// -------------------------------------------------------------------

	public static function mirror_post_write( $meta_id, $object_id, $meta_key, $meta_value ): void {
		self::mirror_write( (int) $object_id, (string) $meta_key, $meta_value, [
			Property::ENTITY_PROPERTY, Property::ENTITY_AGENT, Property::ENTITY_AGENCY,
			Property::ENTITY_PAGE, Property::ENTITY_PACKAGE, Property::ENTITY_TESTIMONIAL, Property::ENTITY_PARTNER,
		], 'post' );
	}

	public static function mirror_post_delete( $meta_ids, $object_id, $meta_key ): void {
		self::mirror_delete( (int) $object_id, (string) $meta_key, [
			Property::ENTITY_PROPERTY, Property::ENTITY_AGENT, Property::ENTITY_AGENCY,
			Property::ENTITY_PAGE, Property::ENTITY_PACKAGE, Property::ENTITY_TESTIMONIAL, Property::ENTITY_PARTNER,
		], 'post' );
	}

	// -------------------------------------------------------------------
	// USER meta hooks
	// -------------------------------------------------------------------

	public static function mirror_user_write( $meta_id, $object_id, $meta_key, $meta_value ): void {
		self::mirror_write( (int) $object_id, (string) $meta_key, $meta_value, [ Property::ENTITY_USER ], 'user' );
	}

	public static function mirror_user_delete( $meta_ids, $object_id, $meta_key ): void {
		self::mirror_delete( (int) $object_id, (string) $meta_key, [ Property::ENTITY_USER ], 'user' );
	}

	// -------------------------------------------------------------------
	// TERM meta hooks
	// -------------------------------------------------------------------

	public static function mirror_term_write( $meta_id, $object_id, $meta_key, $meta_value ): void {
		self::mirror_write( (int) $object_id, (string) $meta_key, $meta_value, [ Property::ENTITY_TERM ], 'term' );
	}

	public static function mirror_term_delete( $meta_ids, $object_id, $meta_key ): void {
		self::mirror_delete( (int) $object_id, (string) $meta_key, [ Property::ENTITY_TERM ], 'term' );
	}

	// -------------------------------------------------------------------
	// Shared write/delete handlers
	// -------------------------------------------------------------------

	private static function mirror_write( int $object_id, string $meta_key, $value, array $entities, string $type ): void {
		$partner = self::find_partner_for_entities( $meta_key, $entities );
		if ( $partner === null || $partner === $meta_key ) {
			return; // Either unrelated key or fave===esc (no mirror needed).
		}

		// Infinite-loop guard.
		$lock = "$type:$object_id:$partner";
		if ( isset( self::$in_flight[ $lock ] ) ) {
			return;
		}
		self::$in_flight[ $lock ] = true;

		// Only write if value differs, to avoid spurious updated_meta firings.
		$current = self::raw_get_first( $object_id, $partner, $type );
		if ( $current !== $value ) {
			self::raw_write( $object_id, $partner, $value, $type );
		}

		unset( self::$in_flight[ $lock ] );
	}

	private static function mirror_delete( int $object_id, string $meta_key, array $entities, string $type ): void {
		$partner = self::find_partner_for_entities( $meta_key, $entities );
		if ( $partner === null || $partner === $meta_key ) {
			return;
		}

		$lock = "$type:$object_id:$partner";
		if ( isset( self::$in_flight[ $lock ] ) ) {
			return;
		}
		self::$in_flight[ $lock ] = true;

		self::raw_delete( $object_id, $partner, $type );

		unset( self::$in_flight[ $lock ] );
	}

	/** Try each candidate entity; return the first non-null partner key. */
	private static function find_partner_for_entities( string $meta_key, array $entities ): ?string {
		foreach ( $entities as $entity ) {
			$partner = self::partner_key( $meta_key, $entity );
			if ( $partner !== null ) {
				return $partner;
			}
		}
		return null;
	}

	private static function raw_get_first( int $id, string $key, string $type ) {
		switch ( $type ) {
			case 'user': return get_user_meta( $id, $key, true );
			case 'term': return get_term_meta( $id, $key, true );
			default:     return get_post_meta( $id, $key, true );
		}
	}

	private static function raw_write( int $id, string $key, $value, string $type ): void {
		switch ( $type ) {
			case 'user': update_user_meta( $id, $key, $value ); break;
			case 'term': update_term_meta( $id, $key, $value ); break;
			default:     update_post_meta( $id, $key, $value );
		}
	}

	private static function raw_delete( int $id, string $key, string $type ): void {
		switch ( $type ) {
			case 'user': delete_user_meta( $id, $key ); break;
			case 'term': delete_term_meta( $id, $key ); break;
			default:     delete_post_meta( $id, $key );
		}
	}

	// -------------------------------------------------------------------
	// Function aliases — declared only if Houzez companions aren't loaded.
	// -------------------------------------------------------------------

	private static function declare_function_aliases(): void {
		// Defined in a separate file because PHP doesn't allow declaring
		// global-scope functions from inside a class method.
		require_once __DIR__ . '/houzez-function-aliases.php';
	}
}
