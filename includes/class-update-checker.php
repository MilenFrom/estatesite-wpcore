<?php
/**
 * Native WordPress update checker for EstateSite packages.
 *
 * Polls a static JSON file (one per package) for the latest version,
 * compares with what's currently installed, and injects an update record
 * into WP's transient cache. WP's standard Dashboard → Updates UI handles
 * the rest — clicks the download link, replaces the files, reactivates.
 *
 * Why this instead of a vendored library (e.g. plugin-update-checker):
 *   - No bundled third-party code (~39 files saved per package).
 *   - No hardcoded auth token in plugin source.
 *   - We control the endpoint, so we can stage rollouts / push forced updates.
 *   - Repos can be public (GPL anyway); zips hosted on public GitHub releases.
 *
 * JSON shape expected at the endpoint URL:
 *
 *     {
 *       "version":      "1.0.0",
 *       "download_url": "https://github.com/.../releases/download/v1.0.0/estatesite-wpcore.zip",
 *       "tested":       "6.5",
 *       "requires":     "6.4",
 *       "requires_php": "7.4",
 *       "homepage":     "https://estatesite.eu",
 *       "last_updated": "2026-05-29",
 *       "sections": {
 *         "description": "...",
 *         "changelog":   "..."
 *       }
 *     }
 *
 * Plugins use $type='plugin' + their basename (e.g. `wpcore/wpcore.php`);
 * themes use $type='theme' + their slug (the directory name).
 *
 * @package EstateSite\Core
 */

namespace EstateSite\Core;

defined( 'ABSPATH' ) || exit;

final class Update_Checker {

	private string $type;       // 'plugin' | 'theme'
	private string $slug;       // plugin basename (e.g. estatesite-wpcore/estatesite-wpcore.php) OR theme slug
	private string $version;    // currently installed version
	private string $endpoint;   // URL of the JSON manifest
	private string $cache_key;  // transient cache key (avoids hammering the endpoint)
	private int $cache_ttl;     // seconds — default 12 hours, matches WP's update poll cadence

	public function __construct( string $type, string $slug, string $version, string $endpoint, int $cache_ttl = HOUR_IN_SECONDS * 12 ) {
		$this->type      = $type;
		$this->slug      = $slug;
		$this->version   = $version;
		$this->endpoint  = $endpoint;
		$this->cache_ttl = $cache_ttl;
		$this->cache_key = 'estatesite_update_' . md5( $type . '|' . $slug );

		if ( $type === 'plugin' ) {
			add_filter( 'site_transient_update_plugins', [ $this, 'inject_plugin_update' ] );
			add_filter( 'plugins_api',                   [ $this, 'plugins_api' ], 10, 3 );
		} else {
			add_filter( 'site_transient_update_themes', [ $this, 'inject_theme_update' ] );
		}
	}

	/**
	 * Hooked to `site_transient_update_plugins`. Adds our update record if
	 * the remote version is newer than what's installed.
	 */
	public function inject_plugin_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$remote = $this->fetch_manifest();
		if ( ! $remote || empty( $remote['version'] ) ) {
			return $transient;
		}

		if ( version_compare( $remote['version'], $this->version, '<=' ) ) {
			// Up to date — make sure we don't leave a stale entry under
			// our slug from a previous check.
			unset( $transient->response[ $this->slug ] );
			return $transient;
		}

		// Shape required by WP's update UI for plugins.
		$transient->response[ $this->slug ] = (object) [
			'id'           => $this->slug,
			'slug'         => dirname( $this->slug ),
			'plugin'       => $this->slug,
			'new_version'  => $remote['version'],
			'url'          => $remote['homepage']     ?? '',
			'package'      => $remote['download_url'] ?? '',
			'tested'       => $remote['tested']       ?? '',
			'requires_php' => $remote['requires_php'] ?? '',
			'compatibility'=> new \stdClass(),
		];

		return $transient;
	}

	/**
	 * Hooked to `site_transient_update_themes`. Adds our update record for
	 * themes (different array shape than plugins).
	 */
	public function inject_theme_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$remote = $this->fetch_manifest();
		if ( ! $remote || empty( $remote['version'] ) ) {
			return $transient;
		}

		if ( version_compare( $remote['version'], $this->version, '<=' ) ) {
			unset( $transient->response[ $this->slug ] );
			return $transient;
		}

		$transient->response[ $this->slug ] = [
			'theme'        => $this->slug,
			'new_version'  => $remote['version'],
			'url'          => $remote['homepage']     ?? '',
			'package'      => $remote['download_url'] ?? '',
			'requires'     => $remote['requires']     ?? '',
			'requires_php' => $remote['requires_php'] ?? '',
		];

		return $transient;
	}

	/**
	 * Hooked to `plugins_api`. Powers the "View details" lightbox on the
	 * Plugins screen. WP calls plugins_api( 'plugin_information', ['slug' => ...] )
	 * which falls through to wp.org by default; we intercept for our slug.
	 */
	public function plugins_api( $result, $action, $args ) {
		if ( $action !== 'plugin_information' ) {
			return $result;
		}
		if ( ! isset( $args->slug ) || $args->slug !== dirname( $this->slug ) ) {
			return $result;
		}

		$remote = $this->fetch_manifest();
		if ( ! $remote ) {
			return $result;
		}

		return (object) [
			'name'          => $remote['name']         ?? dirname( $this->slug ),
			'slug'          => dirname( $this->slug ),
			'version'       => $remote['version']      ?? '',
			'author'        => $remote['author']       ?? '',
			'homepage'      => $remote['homepage']     ?? '',
			'last_updated'  => $remote['last_updated'] ?? '',
			'tested'        => $remote['tested']       ?? '',
			'requires'      => $remote['requires']     ?? '',
			'requires_php'  => $remote['requires_php'] ?? '',
			'download_link' => $remote['download_url'] ?? '',
			'sections'      => $remote['sections']     ?? [],
		];
	}

	/**
	 * Fetch + cache the remote manifest. Returns null on failure (network
	 * error, bad JSON, 404, etc) — caller treats it as "no update available"
	 * so a temporary endpoint outage never blocks the dashboard.
	 *
	 * @return array|null
	 */
	private function fetch_manifest(): ?array {
		$cached = get_site_transient( $this->cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get( $this->endpoint, [
			'timeout'   => 10,
			'sslverify' => true,
			'headers'   => [
				'Accept' => 'application/json',
			],
		] );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			// Cache the failure too, briefly, so a broken endpoint doesn't
			// fire 10x in the next minute as different code paths trigger
			// update checks. 10 min is short enough that recovery is fast.
			set_site_transient( $this->cache_key, 'fail', MINUTE_IN_SECONDS * 10 );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['version'] ) ) {
			set_site_transient( $this->cache_key, 'fail', MINUTE_IN_SECONDS * 10 );
			return null;
		}

		set_site_transient( $this->cache_key, $data, $this->cache_ttl );
		return $data;
	}

	/**
	 * Force-clear the cache. Useful from a debug button or after a manual
	 * version push. Not wired to any UI by default.
	 */
	public function clear_cache(): void {
		delete_site_transient( $this->cache_key );
	}
}
