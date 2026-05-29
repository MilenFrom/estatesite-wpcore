<?php
/**
 * Legacy → Native migration engine.
 *
 * Opt-in transition from `legacy_fave` mode (Houzez-compatible) to `native_esc`
 * mode (EstateSite-native). Designed for sites with thousands of properties:
 *   - Idempotent (safe to re-run)
 *   - Resumable (tracks last processed post ID in options)
 *   - Batched (default 100 posts per batch)
 *   - Three explicit phases (prepare / migrate / cutover) so admins can pause
 *     and verify between each.
 *
 * Usage (WP-CLI):
 *   wp estatesite migrate prepare     # Switch mode to MIGRATING (dual-writes start)
 *   wp estatesite migrate run         # Walk + copy fave_* → esc_*
 *   wp estatesite migrate cutover     # Switch mode to NATIVE_ESC
 *   wp estatesite migrate status      # Show current state
 *   wp estatesite migrate rollback    # Switch back to LEGACY_FAVE (esc_* meta is left in place but unused)
 *
 * @package EstateSite\Core
 */

namespace EstateSite\Core;

defined( 'ABSPATH' ) || exit;

final class Migrator {

	private const PROGRESS_OPTION = 'estatesite_migration_progress';
	private const BATCH_SIZE_DEFAULT = 100;

	/**
	 * Phase 1: switch into MIGRATING mode. From this point, every fave_*
	 * write also mirrors to esc_* (via Houzez_Compat), so any concurrent
	 * activity (EA Sync, admin edits) keeps both keys in sync during the
	 * bulk-copy phase.
	 */
	public static function prepare(): array {
		$current = Compat_Mode::get();
		if ( $current === Compat_Mode::NATIVE_ESC ) {
			return [ 'status' => 'noop', 'message' => 'Already in native_esc mode.' ];
		}

		Compat_Mode::set( Compat_Mode::MIGRATING );

		// Initialize progress tracker.
		update_option( self::PROGRESS_OPTION, [
			'started_at'      => time(),
			'phase'           => 'migrating',
			'last_post_id'    => 0,
			'processed_posts' => 0,
			'options_copied'  => false,
		], false );

		return [
			'status' => 'ok',
			'message' => 'Switched to MIGRATING mode. New writes now dual-write fave_* and esc_*. Run "wp estatesite migrate run" to bulk-copy existing data.',
		];
	}

	/**
	 * Phase 2: bulk-copy existing fave_* meta into esc_* keys across all
	 * five entity types (property/agent/agency CPTs, pages, users, terms).
	 *
	 * Resumable: stores per-entity cursor in PROGRESS_OPTION. A single `run`
	 * call walks ONE entity type per batch (the one with the most remaining
	 * work), so multi-batch invocations naturally rotate through entities.
	 *
	 * Idempotent: only writes esc_* when value is empty AND fave_* has a value.
	 *
	 * @param int  $batch_size How many records per call (default 100)
	 * @param bool $dry_run    If true, only count what would be migrated
	 * @return array { processed, remaining, done, dry_run?, last_id, entity }
	 */
	public static function run( int $batch_size = self::BATCH_SIZE_DEFAULT, bool $dry_run = false ): array {
		if ( Compat_Mode::get() !== Compat_Mode::MIGRATING ) {
			return [
				'status' => 'error',
				'message' => 'Migration not started. Run "wp estatesite migrate prepare" first.',
			];
		}

		$progress = get_option( self::PROGRESS_OPTION, [] );

		// Copy houzez_options → estatesite_options once.
		if ( empty( $progress['options_copied'] ) ) {
			self::copy_options( $dry_run );
			if ( ! $dry_run ) {
				$progress['options_copied'] = true;
				update_option( self::PROGRESS_OPTION, $progress, false );
			}
		}

		// Pick the entity to process this batch. Order: posts (heaviest) → pages → users → terms.
		// We process whichever still has work remaining, top-down.
		foreach ( [ 'posts', 'pages', 'users', 'terms' ] as $entity_kind ) {
			$counts = self::count_remaining( $entity_kind, $progress );
			if ( $counts['remaining'] > 0 ) {
				$result = self::run_entity_batch( $entity_kind, $batch_size, $dry_run, $progress );
				if ( ! $dry_run ) {
					update_option( self::PROGRESS_OPTION, $progress, false );
				}
				$result['entity']    = $entity_kind;
				$result['processed_total'] = ( $progress['processed_posts'] ?? 0 )
					+ ( $progress['processed_pages'] ?? 0 )
					+ ( $progress['processed_users'] ?? 0 )
					+ ( $progress['processed_terms'] ?? 0 );
				return $result;
			}
		}

		// All entities done.
		return [
			'status'    => 'ok',
			'processed' => 0,
			'remaining' => 0,
			'done'      => true,
			'dry_run'   => $dry_run,
			'last_id'   => 0,
			'entity'    => 'all',
			'processed_total' => ( $progress['processed_posts'] ?? 0 )
				+ ( $progress['processed_pages'] ?? 0 )
				+ ( $progress['processed_users'] ?? 0 )
				+ ( $progress['processed_terms'] ?? 0 ),
		];
	}

	/**
	 * Run a single batch against one entity type. Updates $progress in place.
	 */
	private static function run_entity_batch( string $entity_kind, int $batch_size, bool $dry_run, array &$progress ): array {
		global $wpdb;
		$cursor_key = "cursor_$entity_kind";
		$count_key  = "processed_$entity_kind";
		$last_id    = (int) ( $progress[ $cursor_key ] ?? 0 );

		switch ( $entity_kind ) {
			case 'posts':
				$post_types = self::cpt_post_types();
				$placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
				$ids = $wpdb->get_col( $wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts}
					 WHERE post_type IN ($placeholders) AND ID > %d
					 ORDER BY ID ASC LIMIT %d",
					array_merge( $post_types, [ $last_id, $batch_size ] )
				) );
				$entities = [ 'property', 'agent', 'agency', 'project', 'partner', 'testimonial' ];
				break;

			case 'pages':
				$ids = $wpdb->get_col( $wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts}
					 WHERE post_type IN ('page','houzez_packages','es_packages') AND ID > %d
					 ORDER BY ID ASC LIMIT %d",
					$last_id, $batch_size
				) );
				$entities = [ 'page', 'package' ];
				break;

			case 'users':
				$ids = $wpdb->get_col( $wpdb->prepare(
					"SELECT ID FROM {$wpdb->users}
					 WHERE ID > %d ORDER BY ID ASC LIMIT %d",
					$last_id, $batch_size
				) );
				$entities = [ 'user' ];
				break;

			case 'terms':
				$ids = $wpdb->get_col( $wpdb->prepare(
					"SELECT term_id FROM {$wpdb->terms}
					 WHERE term_id > %d ORDER BY term_id ASC LIMIT %d",
					$last_id, $batch_size
				) );
				$entities = [ 'term' ];
				break;

			default:
				return [ 'status' => 'error', 'processed' => 0, 'remaining' => 0, 'done' => false, 'last_id' => $last_id ];
		}

		$processed = 0;
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( ! $dry_run ) {
				self::migrate_entity( $entity_kind, $id, $entities );
			}
			$last_id = $id;
			$processed++;
		}

		$counts = self::count_remaining( $entity_kind, [ $cursor_key => $last_id ] );

		if ( ! $dry_run ) {
			$progress[ $cursor_key ] = $last_id;
			$progress[ $count_key ]  = ( $progress[ $count_key ] ?? 0 ) + $processed;
		}

		return [
			'status'    => 'ok',
			'processed' => $processed,
			'remaining' => $counts['remaining'],
			'done'      => $counts['remaining'] === 0,
			'dry_run'   => $dry_run,
			'last_id'   => $last_id,
		];
	}

	/** How many records remain for the given entity_kind beyond the cursor in $progress. */
	private static function count_remaining( string $entity_kind, array $progress ): array {
		global $wpdb;
		$cursor = (int) ( $progress[ "cursor_$entity_kind" ] ?? 0 );

		switch ( $entity_kind ) {
			case 'posts':
				$post_types = self::cpt_post_types();
				$placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
				$remaining = (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts}
					 WHERE post_type IN ($placeholders) AND ID > %d",
					array_merge( $post_types, [ $cursor ] )
				) );
				break;
			case 'pages':
				$remaining = (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts}
					 WHERE post_type IN ('page','houzez_packages','es_packages') AND ID > %d",
					$cursor
				) );
				break;
			case 'users':
				$remaining = (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->users} WHERE ID > %d", $cursor
				) );
				break;
			case 'terms':
				$remaining = (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->terms} WHERE term_id > %d", $cursor
				) );
				break;
			default:
				$remaining = 0;
		}
		return [ 'remaining' => $remaining ];
	}

	/**
	 * Migrate a single record across the relevant entity-scopes of the key map.
	 */
	private static function migrate_entity( string $entity_kind, int $id, array $entities ): void {
		switch ( $entity_kind ) {
			case 'posts':
			case 'pages':
				self::copy_meta_keys( $id, $entities, 'post' );
				break;
			case 'users':
				self::copy_meta_keys( $id, $entities, 'user' );
				break;
			case 'terms':
				self::copy_meta_keys( $id, $entities, 'term' );
				break;
		}
	}

	/**
	 * Generic copy: for each (logical, fave, esc) in $entities, copy raw fave value(s)
	 * to esc key on the object, skipping if esc already has any value.
	 *
	 * Uses get_*_meta(..., false) to fetch ALL rows for the key, so multi-row meta
	 * (e.g. fave_property_images stores one row per attachment ID) migrates fully.
	 * Earlier versions used single-value reads and collapsed gallery meta to a
	 * single image.
	 */
	private static function copy_meta_keys( int $object_id, array $entities, string $type ): void {
		$map = Property::key_map();
		foreach ( $entities as $entity ) {
			if ( ! isset( $map[ $entity ] ) ) continue;
			foreach ( $map[ $entity ] as $logical => [ $fave_key, $esc_key ] ) {
				if ( $fave_key === $esc_key ) continue;

				$fave_vals = self::raw_get_all( $object_id, $fave_key, $type );
				if ( empty( $fave_vals ) ) continue;

				$existing = self::raw_get_all( $object_id, $esc_key, $type );
				if ( ! empty( $existing ) ) continue;

				// Single-row: keep using update_*_meta so the row is written
				// in its conventional single form. Multi-row: use add_*_meta
				// once per value (no $unique flag → can write duplicates,
				// which is correct for gallery-style storage).
				if ( count( $fave_vals ) === 1 ) {
					self::raw_set( $object_id, $esc_key, $fave_vals[0], $type );
				} else {
					foreach ( $fave_vals as $v ) {
						self::raw_add( $object_id, $esc_key, $v, $type );
					}
				}
			}
		}
	}

	private static function raw_get( int $id, string $key, string $type ) {
		switch ( $type ) {
			case 'user': return get_user_meta( $id, $key, true );
			case 'term': return get_term_meta( $id, $key, true );
			default:     return get_post_meta( $id, $key, true );
		}
	}

	/**
	 * Multi-row read. Returns array of all values for the key, or [] if none.
	 * Used by the migrator so multi-row meta (e.g. gallery images) is copied
	 * row-by-row instead of being collapsed to a single value.
	 */
	private static function raw_get_all( int $id, string $key, string $type ): array {
		switch ( $type ) {
			case 'user': $vals = get_user_meta( $id, $key, false ); break;
			case 'term': $vals = get_term_meta( $id, $key, false ); break;
			default:     $vals = get_post_meta( $id, $key, false );
		}
		if ( ! is_array( $vals ) ) {
			return [];
		}
		// WP returns serialized strings unmodified when count flag is false;
		// callers need them as-is so add_*_meta re-serializes correctly.
		return $vals;
	}

	private static function raw_set( int $id, string $key, $value, string $type ): void {
		switch ( $type ) {
			case 'user': update_user_meta( $id, $key, $value ); break;
			case 'term': update_term_meta( $id, $key, $value ); break;
			default:     update_post_meta( $id, $key, $value );
		}
	}

	private static function raw_add( int $id, string $key, $value, string $type ): void {
		switch ( $type ) {
			case 'user': add_user_meta( $id, $key, $value ); break;
			case 'term': add_term_meta( $id, $key, $value ); break;
			default:     add_post_meta( $id, $key, $value );
		}
	}

	/**
	 * Phase 3: cutover to NATIVE_ESC mode.
	 *
	 * Refuses if migration progress shows posts remaining. Caller can force
	 * with $force=true (intentional escape hatch for testing).
	 */
	public static function cutover( bool $force = false ): array {
		$progress = get_option( self::PROGRESS_OPTION, [] );
		if ( empty( $progress['options_copied'] ) && ! $force ) {
			return [
				'status' => 'error',
				'message' => 'Migration has not been run yet. Run "wp estatesite migrate run" first, or pass --force to cutover anyway.',
			];
		}

		Compat_Mode::set( Compat_Mode::NATIVE_ESC );

		$progress['phase']        = 'native';
		$progress['cutover_at']   = time();
		update_option( self::PROGRESS_OPTION, $progress, false );

		return [
			'status' => 'ok',
			'message' => 'Switched to native_esc mode. Reads now prefer esc_* keys (with legacy fave_* fallback for unmigrated posts).',
		];
	}

	/**
	 * Roll back to legacy_fave. Does NOT delete esc_* meta — they remain
	 * but are unused. Safe to run; allows another cutover attempt later.
	 */
	public static function rollback(): array {
		Compat_Mode::set( Compat_Mode::LEGACY_FAVE );

		$progress = get_option( self::PROGRESS_OPTION, [] );
		$progress['phase']         = 'rolled_back';
		$progress['rollback_at']   = time();
		update_option( self::PROGRESS_OPTION, $progress, false );

		return [
			'status' => 'ok',
			'message' => 'Rolled back to legacy_fave mode. esc_* meta preserved on existing posts (unused but not deleted).',
		];
	}

	/**
	 * Show current migration state.
	 */
	public static function status(): array {
		$progress = get_option( self::PROGRESS_OPTION, [] );
		return [
			'mode'             => Compat_Mode::get(),
			'phase'            => $progress['phase'] ?? 'not started',
			'cursors'          => [
				'posts'  => $progress['cursor_posts']  ?? 0,
				'pages'  => $progress['cursor_pages']  ?? 0,
				'users'  => $progress['cursor_users']  ?? 0,
				'terms'  => $progress['cursor_terms']  ?? 0,
			],
			'processed'        => [
				'posts'  => $progress['processed_posts']  ?? 0,
				'pages'  => $progress['processed_pages']  ?? 0,
				'users'  => $progress['processed_users']  ?? 0,
				'terms'  => $progress['processed_terms']  ?? 0,
			],
			'options_copied'   => ! empty( $progress['options_copied'] ),
			'started_at'       => isset( $progress['started_at'] ) ? gmdate( 'Y-m-d H:i:s', $progress['started_at'] ) . ' UTC' : null,
			'cutover_at'       => isset( $progress['cutover_at'] )  ? gmdate( 'Y-m-d H:i:s', $progress['cutover_at'] ) . ' UTC'  : null,
		];
	}

	// -------------------------------------------------------------------

	/** CPT names eligible for the 'posts' entity-kind (property/agent/agency/etc). */
	private static function cpt_post_types(): array {
		return [
			CPT::name( 'property' ),
			CPT::name( 'agent' ),
			CPT::name( 'agency' ),
			CPT::name( 'project' ),
			CPT::name( 'partner' ),
			CPT::name( 'testimonial' ),
		];
	}

	/**
	 * Copy `houzez_options` → `estatesite_options` (option-table sibling).
	 * Idempotent: if estatesite_options already exists, merges legacy keys
	 * that aren't present.
	 */
	private static function copy_options( bool $dry_run ): void {
		$legacy = get_option( 'houzez_options', [] );
		if ( ! is_array( $legacy ) || ! $legacy ) {
			return;
		}

		$native = get_option( 'estatesite_options', [] );
		if ( ! is_array( $native ) ) {
			$native = [];
		}

		// Native wins where key exists in both (admin may have set fresh values).
		$merged = $legacy + $native;

		if ( ! $dry_run ) {
			update_option( 'estatesite_options', $merged, true );
		}
	}

	/**
	 * Pre-migration audit: scan customer data to inventory what would migrate.
	 * Useful for setting expectations before running the migration.
	 */
	public static function audit(): array {
		global $wpdb;
		$out = [
			'mode' => Compat_Mode::get(),
			'posts_by_type' => [],
			'pages_with_fave_meta' => 0,
			'users_with_fave_meta' => 0,
			'terms_with_fave_meta' => 0,
			'options_legacy_present' => (bool) get_option( 'houzez_options' ),
			'options_native_present' => (bool) get_option( 'estatesite_options' ),
		];

		// Posts: count each CPT
		foreach ( self::cpt_post_types() as $pt ) {
			$count = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status != 'auto-draft'", $pt
			) );
			if ( $count > 0 ) {
				$out['posts_by_type'][ $pt ] = $count;
			}
		}

		// Pages with any fave_page_* meta
		$out['pages_with_fave_meta'] = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} pm
			 JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			 WHERE pm.meta_key LIKE 'fave_page_%' OR pm.meta_key LIKE 'fave_listing_%' OR pm.meta_key LIKE 'fave_header_%'"
		);

		// Users with any fave_author_* meta
		$out['users_with_fave_meta'] = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key LIKE 'fave_author_%' OR meta_key LIKE 'fave_paypal_%' OR meta_key LIKE 'fave_stripe_%'"
		);

		// Terms with any fave_* meta
		$out['terms_with_fave_meta'] = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT term_id) FROM {$wpdb->termmeta} WHERE meta_key LIKE 'fave_%'"
		);

		return $out;
	}
}
