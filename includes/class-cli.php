<?php
/**
 * WP-CLI commands for EstateSite Core.
 *
 * Registered only when WP-CLI is available.
 *
 * @package EstateSite\Core
 */

namespace EstateSite\Core;

defined( 'ABSPATH' ) || exit;

if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	return;
}

/**
 * Migrate property data from Houzez-compatible storage to EstateSite-native storage.
 */
class CLI_Migrate_Command {

	/**
	 * Prepare for migration: switch to MIGRATING mode (starts dual-writing).
	 *
	 * ## EXAMPLES
	 *     wp estatesite migrate prepare
	 *
	 * @when after_wp_load
	 */
	public function prepare( $args, $assoc_args ) {
		$result = Migrator::prepare();
		if ( $result['status'] === 'ok' ) {
			\WP_CLI::success( $result['message'] );
		} elseif ( $result['status'] === 'noop' ) {
			\WP_CLI::warning( $result['message'] );
		} else {
			\WP_CLI::error( $result['message'] );
		}
	}

	/**
	 * Run the migration in batches across all entity types (posts/pages/users/terms).
	 *
	 * ## OPTIONS
	 *
	 * [--batch=<n>]
	 * : Records per batch (default 100).
	 *
	 * [--all]
	 * : Run repeatedly until all entities are done.
	 *
	 * [--dry-run]
	 * : Count what would migrate without writing.
	 *
	 * ## EXAMPLES
	 *     wp estatesite migrate run
	 *     wp estatesite migrate run --batch=500 --all
	 *     wp estatesite migrate run --dry-run
	 *
	 * @when after_wp_load
	 */
	public function run( $args, $assoc_args ) {
		$batch = (int) ( $assoc_args['batch'] ?? 100 );
		$all   = ! empty( $assoc_args['all'] );
		$dry   = ! empty( $assoc_args['dry-run'] );

		$total_processed = 0;
		$done = false;
		do {
			$r = Migrator::run( $batch, $dry );
			if ( $r['status'] !== 'ok' ) {
				\WP_CLI::error( $r['message'] ?? 'Migration error' );
			}
			$total_processed += $r['processed'];
			\WP_CLI::log( sprintf(
				'[%s] processed=%d remaining=%d last_id=%d%s',
				$r['entity'],
				$r['processed'],
				$r['remaining'],
				$r['last_id'],
				$dry ? ' [dry-run]' : ''
			) );

			$done = $r['done'] && $r['entity'] === 'all';
			if ( ! $all || $done || $r['processed'] === 0 ) {
				break;
			}
		} while ( true );

		\WP_CLI::success( sprintf(
			'Migration %s. Total processed this run: %d.',
			$done ? 'complete' : 'paused',
			$total_processed
		) );
	}

	/**
	 * Audit existing data to inventory what would migrate.
	 *
	 * ## EXAMPLES
	 *     wp estatesite migrate audit
	 *
	 * @when after_wp_load
	 */
	public function audit( $args, $assoc_args ) {
		$a = Migrator::audit();
		\WP_CLI::log( 'Compat mode:                ' . $a['mode'] );
		\WP_CLI::log( 'houzez_options exists:      ' . ( $a['options_legacy_present'] ? 'yes' : 'no' ) );
		\WP_CLI::log( 'estatesite_options exists:  ' . ( $a['options_native_present'] ? 'yes' : 'no' ) );
		\WP_CLI::log( '' );
		\WP_CLI::log( 'Posts by type:' );
		foreach ( $a['posts_by_type'] as $pt => $count ) {
			\WP_CLI::log( sprintf( '  %-30s %d', $pt, $count ) );
		}
		\WP_CLI::log( '' );
		\WP_CLI::log( sprintf( 'Pages with fave_page_* meta:   %d', $a['pages_with_fave_meta'] ) );
		\WP_CLI::log( sprintf( 'Users with fave_author_* meta: %d', $a['users_with_fave_meta'] ) );
		\WP_CLI::log( sprintf( 'Terms with fave_* meta:        %d', $a['terms_with_fave_meta'] ) );
	}

	/**
	 * Cutover to native_esc mode (writes now use esc_* keys exclusively).
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Cutover even if migration hasn't run.
	 *
	 * ## EXAMPLES
	 *     wp estatesite migrate cutover
	 *     wp estatesite migrate cutover --force
	 *
	 * @when after_wp_load
	 */
	public function cutover( $args, $assoc_args ) {
		$force = ! empty( $assoc_args['force'] );
		$result = Migrator::cutover( $force );
		if ( $result['status'] === 'ok' ) {
			\WP_CLI::success( $result['message'] );
		} else {
			\WP_CLI::error( $result['message'] );
		}
	}

	/**
	 * Roll back to legacy_fave mode. Preserves esc_* meta on existing posts.
	 *
	 * ## EXAMPLES
	 *     wp estatesite migrate rollback
	 *
	 * @when after_wp_load
	 */
	public function rollback( $args, $assoc_args ) {
		$result = Migrator::rollback();
		\WP_CLI::success( $result['message'] );
	}

	/**
	 * Show migration status.
	 *
	 * ## EXAMPLES
	 *     wp estatesite migrate status
	 *
	 * @when after_wp_load
	 */
	public function status( $args, $assoc_args ) {
		$s = Migrator::status();
		\WP_CLI::log( 'Mode:            ' . $s['mode'] );
		\WP_CLI::log( 'Phase:           ' . $s['phase'] );
		\WP_CLI::log( 'Options copied:  ' . ( $s['options_copied'] ? 'yes' : 'no' ) );
		\WP_CLI::log( '' );
		\WP_CLI::log( 'Per-entity progress (cursor=last_id, processed=records done):' );
		foreach ( $s['cursors'] as $entity => $cursor ) {
			$processed = $s['processed'][ $entity ];
			\WP_CLI::log( sprintf( '  %-8s cursor=%d processed=%d', $entity, $cursor, $processed ) );
		}
		if ( $s['started_at'] ) {
			\WP_CLI::log( '' );
			\WP_CLI::log( 'Started at:      ' . $s['started_at'] );
		}
		if ( $s['cutover_at'] ) {
			\WP_CLI::log( 'Cutover at:      ' . $s['cutover_at'] );
		}
	}
}

\WP_CLI::add_command( 'estatesite migrate', CLI_Migrate_Command::class );

/**
 * Manage nomenclature data (estate types, cities, districts, etc.) fetched from
 * the EstateAssistant platform.
 */
class CLI_Nomenclatures_Command {

	/**
	 * Refresh nomenclatures by re-fetching from the EA API.
	 *
	 * ## EXAMPLES
	 *     wp estatesite nomenclatures refresh
	 *
	 * @when after_wp_load
	 */
	public function refresh( $args, $assoc_args ) {
		$r = Nomenclatures::refresh( 'cli' );
		if ( $r['ok'] ) {
			\WP_CLI::success( $r['message'] );
		} else {
			\WP_CLI::error( $r['message'] );
		}
	}

	/**
	 * Show nomenclature cache status and last-fetch info.
	 *
	 * ## EXAMPLES
	 *     wp estatesite nomenclatures status
	 *
	 * @when after_wp_load
	 */
	public function status( $args, $assoc_args ) {
		$meta = Nomenclatures::meta();
		$data = Nomenclatures::all();

		\WP_CLI::log( 'Token configured: ' . ( Nomenclatures::get_token() ? 'yes' : 'NO (set EA token first)' ) );
		\WP_CLI::log( 'Cache present:    ' . ( $data ? 'yes' : 'no' ) );
		\WP_CLI::log( 'Freshness:        ' . Nomenclatures::freshness() );
		\WP_CLI::log( '' );

		if ( ! empty( $meta['last_success_at'] ) ) {
			\WP_CLI::log( 'Last successful fetch: ' . gmdate( 'Y-m-d H:i:s', $meta['last_success_at'] ) . ' UTC (' . ( $meta['last_success_via'] ?? '?' ) . ')' );
		}
		if ( ! empty( $meta['last_error_at'] ) ) {
			\WP_CLI::log( 'Last error:            ' . gmdate( 'Y-m-d H:i:s', $meta['last_error_at'] ) . ' UTC' );
			\WP_CLI::log( '  message: ' . ( $meta['last_error'] ?? '' ) );
		}

		$next = wp_next_scheduled( Nomenclatures::CRON_HOOK );
		if ( $next ) {
			\WP_CLI::log( 'Next scheduled run:    ' . gmdate( 'Y-m-d H:i:s', $next ) . ' UTC' );
		} else {
			\WP_CLI::log( 'Next scheduled run:    not scheduled' );
		}

		if ( ! empty( $meta['counts'] ) ) {
			\WP_CLI::log( '' );
			\WP_CLI::log( 'Category counts:' );
			foreach ( $meta['counts'] as $key => $count ) {
				\WP_CLI::log( sprintf( '  %-30s %d', $key, $count ) );
			}
		}
	}

	/**
	 * Show entries for a specific nomenclature category.
	 *
	 * ## OPTIONS
	 *
	 * <category>
	 * : Category key (e.g. estate_types, currency, countries, cities, districts)
	 *
	 * [--country=<id>]
	 * : When listing cities, filter by country_id.
	 *
	 * [--city=<id>]
	 * : When listing districts, filter by parent (city) id.
	 *
	 * [--limit=<n>]
	 * : Limit output rows (default 20).
	 *
	 * ## EXAMPLES
	 *     wp estatesite nomenclatures show estate_types
	 *     wp estatesite nomenclatures show cities --country=37 --limit=5
	 *     wp estatesite nomenclatures show districts --city=1 --limit=10
	 *
	 * @when after_wp_load
	 */
	public function show( $args, $assoc_args ) {
		[ $category ] = $args;
		$limit   = (int) ( $assoc_args['limit'] ?? 20 );
		$filters = [];
		if ( isset( $assoc_args['country'] ) ) $filters['country_id'] = (int) $assoc_args['country'];
		if ( isset( $assoc_args['city'] ) )    $filters['city_id']    = (int) $assoc_args['city'];

		$rows = Nomenclatures::get( $category, $filters );
		if ( ! $rows ) {
			\WP_CLI::warning( 'No entries found.' );
			return;
		}
		\WP_CLI::log( sprintf( 'Total: %d (showing first %d)', count( $rows ), min( $limit, count( $rows ) ) ) );
		\WP_CLI::log( '' );
		$shown = 0;
		foreach ( $rows as $row ) {
			if ( $shown >= $limit ) break;
			\WP_CLI::log( sprintf(
				'  [%d] %s',
				$row['id'] ?? 0,
				$row['name'] ?? '?'
			) );
			$shown++;
		}
	}
}

\WP_CLI::add_command( 'estatesite nomenclatures', CLI_Nomenclatures_Command::class );
