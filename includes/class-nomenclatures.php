<?php
/**
 * EstateAssistant nomenclatures — single source of truth for dropdown data.
 *
 * Fetches the master list of listing types, estate types, cities, districts,
 * currencies, etc. from the EA platform. Cached in a transient (24h+ TTL),
 * refreshed weekly via WP-Cron.
 *
 * Storage: `wp_options['_transient_estatesite_nomenclatures_data']`.
 * Same key estatesite-houzez plugin v1.6.0+ uses for its cascading location
 * dropdowns, so this plugin's data is transparently consumed by it.
 *
 * @package EstateSite\Core
 */

namespace EstateSite\Core;

defined( 'ABSPATH' ) || exit;

final class Nomenclatures {

	/** Same key the estatesite-houzez plugin's contact form uses. */
	public const TRANSIENT_KEY = 'estatesite_nomenclatures_data';

	/** Track when the last refresh happened (and outcome) for the admin UI. */
	public const META_KEY = 'estatesite_nomenclatures_meta';

	public const CRON_HOOK = 'estatesite_nomenclatures_refresh';

	private const API_BASE = 'http://exportdata.estateassistant.info/api/export/GetNomenclaturies/';

	/** Cache life: 8 days. Cron refreshes every 7 — small overlap for safety. */
	private const TRANSIENT_TTL = 8 * DAY_IN_SECONDS;

	/**
	 * Register WordPress hooks. Called from Plugin::boot().
	 */
	public static function register(): void {
		// WP-Cron schedule registration.
		add_filter( 'cron_schedules', [ self::class, 'register_cron_schedule' ] );
		add_action( self::CRON_HOOK, [ self::class, 'cron_refresh' ] );

		// Schedule the cron if not already scheduled.
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event(
				time() + HOUR_IN_SECONDS, // first run an hour after init (avoid stampede on activation)
				'weekly',
				self::CRON_HOOK
			);
		}
	}

	/**
	 * Register a `weekly` recurrence in case WP doesn't have it on the host.
	 * (WP added 'weekly' as built-in in 5.4, but be defensive.)
	 */
	public static function register_cron_schedule( $schedules ) {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = [
				'interval' => 7 * DAY_IN_SECONDS,
				'display'  => __( 'Once Weekly', 'estatesite-wpcore' ),
			];
		}
		return $schedules;
	}

	/**
	 * Activation seed: fetch nomenclatures immediately so the site has data
	 * from day one. Called from Activator::activate().
	 */
	public static function seed(): array {
		return self::refresh( 'activation' );
	}

	/**
	 * Cron callback. Wraps refresh() with error tolerance — a failed fetch
	 * must not crash the cron tick.
	 */
	public static function cron_refresh(): void {
		try {
			self::refresh( 'cron' );
		} catch ( \Throwable $e ) {
			self::set_meta( [
				'last_attempt_at'   => time(),
				'last_attempt_via'  => 'cron',
				'last_status'       => 'error',
				'last_error'        => $e->getMessage(),
			], true /* merge */ );
		}
	}

	/**
	 * Fetch nomenclatures from the EA API and cache them.
	 *
	 * @param string $source 'activation' | 'cron' | 'manual' | 'cli'
	 * @return array { ok: bool, message: string, count?: array }
	 */
	public static function refresh( string $source = 'manual' ): array {
		$token = self::get_token();
		if ( ! $token ) {
			$result = [
				'ok'      => false,
				'message' => __( 'No EstateAssistant token configured. Set "estate_assistant_token" option or configure EA Sync.', 'estatesite-wpcore' ),
			];
			self::record_attempt( $source, $result, [] );
			return $result;
		}

		$url = self::API_BASE . '?token=' . rawurlencode( $token );

		$resp = wp_remote_get( $url, [
			'timeout' => 30,
			'headers' => [ 'Accept' => 'application/json' ],
		] );

		if ( is_wp_error( $resp ) ) {
			$result = [
				'ok'      => false,
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'API request failed: %s', 'estatesite-wpcore' ),
					$resp->get_error_message()
				),
			];
			self::record_attempt( $source, $result, [] );
			return $result;
		}

		$code = wp_remote_retrieve_response_code( $resp );
		$body = wp_remote_retrieve_body( $resp );

		if ( $code !== 200 ) {
			$result = [
				'ok'      => false,
				'message' => sprintf(
					/* translators: %d: HTTP status code */
					__( 'API returned HTTP %d.', 'estatesite-wpcore' ),
					$code
				),
			];
			self::record_attempt( $source, $result, [] );
			return $result;
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			$result = [
				'ok'      => false,
				'message' => __( 'API response was not valid JSON.', 'estatesite-wpcore' ),
			];
			self::record_attempt( $source, $result, [] );
			return $result;
		}

		// Sanity check — minimum expected keys
		$required = [ 'listing_types', 'estate_types', 'countries', 'address' ];
		foreach ( $required as $key ) {
			if ( ! isset( $data[ $key ] ) ) {
				$result = [
					'ok'      => false,
					'message' => sprintf(
						/* translators: %s: missing key */
						__( 'API response missing expected key: %s', 'estatesite-wpcore' ),
						$key
					),
				];
				self::record_attempt( $source, $result, $data );
				return $result;
			}
		}

		// Persist.
		set_transient( self::TRANSIENT_KEY, $data, self::TRANSIENT_TTL );

		$counts = self::collect_counts( $data );
		$result = [
			'ok'      => true,
			'message' => sprintf(
				/* translators: %1$d: address entries, %2$d: estate types */
				__( 'Nomenclatures refreshed (%1$d address entries, %2$d estate types).', 'estatesite-wpcore' ),
				$counts['address'] ?? 0,
				$counts['estate_types'] ?? 0
			),
			'counts'  => $counts,
		];
		self::record_attempt( $source, $result, $data );
		return $result;
	}

	/**
	 * Get the full nomenclatures dataset. Returns cached array or null.
	 *
	 * If never fetched, returns null — caller can decide to refresh inline.
	 */
	public static function all(): ?array {
		$data = get_transient( self::TRANSIENT_KEY );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Get a specific nomenclature category (e.g. 'estate_types', 'cities').
	 *
	 * Special derived keys:
	 *   'cities'    — filtered from 'address' where country_id matches
	 *   'districts' — filtered from 'address' where parent_id matches
	 *
	 * @param string $key      Category key.
	 * @param array  $filters  Optional filter: ['country_id' => 37] for cities, ['city_id' => X] for districts.
	 */
	public static function get( string $key, array $filters = [] ): array {
		$data = self::all();
		if ( ! $data ) {
			return [];
		}

		// Derived: cities are address entries with country_id but no parent_id (top-level under country)
		if ( $key === 'cities' && isset( $data['address'] ) ) {
			$country_filter = $filters['country_id'] ?? null;
			return array_values( array_filter( $data['address'], function ( $row ) use ( $country_filter ) {
				$is_city = empty( $row['parent_id'] );
				if ( ! $is_city ) return false;
				if ( $country_filter !== null && (int) ( $row['country_id'] ?? 0 ) !== (int) $country_filter ) return false;
				return true;
			} ) );
		}

		// Derived: districts are address entries with parent_id set (sub-locations)
		if ( $key === 'districts' && isset( $data['address'] ) ) {
			$city_filter = $filters['city_id'] ?? null;
			return array_values( array_filter( $data['address'], function ( $row ) use ( $city_filter ) {
				$is_district = ! empty( $row['parent_id'] );
				if ( ! $is_district ) return false;
				if ( $city_filter !== null && (int) ( $row['parent_id'] ?? 0 ) !== (int) $city_filter ) return false;
				return true;
			} ) );
		}

		return $data[ $key ] ?? [];
	}

	/**
	 * Get the timestamp + status of the last refresh attempt.
	 */
	public static function meta(): array {
		return (array) get_option( self::META_KEY, [] );
	}

	/**
	 * Check freshness — returns one of: 'fresh', 'stale', 'expired', 'never'.
	 */
	public static function freshness(): string {
		$meta = self::meta();
		if ( empty( $meta['last_success_at'] ) ) {
			return 'never';
		}
		$age = time() - (int) $meta['last_success_at'];
		if ( $age < 8 * DAY_IN_SECONDS ) {
			return 'fresh';
		}
		if ( $age < 30 * DAY_IN_SECONDS ) {
			return 'stale';
		}
		return 'expired';
	}

	/**
	 * Get the EA token. Reads the same option EA Sync writes to.
	 */
	public static function get_token(): string {
		$token = get_option( 'estate_assistant_token', '' );
		return is_string( $token ) ? trim( $token ) : '';
	}

	// ---------------------------------------------------------------------

	private static function collect_counts( array $data ): array {
		$out = [];
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$out[ $key ] = count( $value );
			}
		}
		return $out;
	}

	private static function record_attempt( string $source, array $result, array $data ): void {
		$now  = time();
		$meta = self::meta();

		$meta['last_attempt_at']  = $now;
		$meta['last_attempt_via'] = $source;
		$meta['last_status']      = $result['ok'] ? 'success' : 'error';
		$meta['last_message']     = $result['message'];

		if ( $result['ok'] ) {
			$meta['last_success_at']  = $now;
			$meta['last_success_via'] = $source;
			$meta['counts']           = $result['counts'] ?? [];
		} else {
			$meta['last_error_at']  = $now;
			$meta['last_error']     = $result['message'];
		}

		update_option( self::META_KEY, $meta, false );
	}

	private static function set_meta( array $partial, bool $merge = true ): void {
		$current = $merge ? self::meta() : [];
		update_option( self::META_KEY, array_merge( $current, $partial ), false );
	}
}
