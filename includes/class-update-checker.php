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
			add_filter( 'plugin_action_links_' . $slug,  [ $this, 'plugin_action_links' ] );
		} else {
			add_filter( 'site_transient_update_themes', [ $this, 'inject_theme_update' ] );
		}

		// Handle the manual "Check for updates" click. Bypasses both our
		// own 12h transient AND WordPress's update_themes/update_plugins
		// transient so the next page load re-polls everything fresh.
		add_action( 'admin_post_estatesite_force_update_check', [ $this, 'handle_force_check' ] );

		// One-shot recovery URL — works from anywhere on the site for an
		// authenticated admin. Lets customers stuck on a stale manifest
		// (e.g. their Update_Checker pre-dates the "Check for updates"
		// button) recover with a single URL we can email them. Triggered
		// by visiting `?estatesite_clear_update_cache=1`.
		add_action( 'init', [ $this, 'maybe_handle_clear_url' ] );

		// Flash notice on the admin screen after the force-check redirects back.
		// Registered globally (not gated on $type) so we only attach it once
		// per instance — multiple instances each have their own slug-keyed
		// transient already, but the notice is a noop unless the query arg
		// is present.
		add_action( 'admin_notices', [ $this, 'maybe_render_force_check_notice' ] );

		// Theme card "Check for updates" link. We attach an admin notice
		// (rendered on themes.php only) that adds the link to our theme's
		// card — the theme_action_links filter doesn't exist for themes,
		// so we use a small inline JS+CSS shim.
		if ( $type === 'theme' ) {
			add_action( 'admin_print_footer_scripts-themes.php', [ $this, 'inject_theme_card_link' ] );
		}
	}

	/**
	 * "Check for updates" row-action on the Plugins admin screen.
	 */
	public function plugin_action_links( array $links ): array {
		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=estatesite_force_update_check&type=' . $this->type . '&slug=' . rawurlencode( $this->slug ) ),
			'estatesite_force_update_check_' . $this->slug
		);
		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			esc_html__( 'Check for updates', 'estatesite-wpcore' )
		);
		return $links;
	}

	/**
	 * Inject a "Check for updates" link into our theme's card on the Themes
	 * admin screen.
	 *
	 * WP renders theme cards client-side via Backbone, so a one-shot
	 * DOMContentLoaded listener races the render. Instead we use a
	 * MutationObserver that watches the themes container and inserts the
	 * link whenever our card appears — covers initial render, filter
	 * changes, search, and pagination.
	 *
	 * Also injects an "Update available" notice + button directly into the
	 * description, so customers see the action right next to the version.
	 */
	public function inject_theme_card_link(): void {
		// Build the action URL with real ampersands. wp_nonce_url() and esc_js()
		// both HTML-entity-encode `&` → `&amp;` (intended for HTML attributes
		// and onclick handlers respectively). Setting link.href to such a
		// string in JS produces $_GET keys named `amp;type`, `amp;slug` etc,
		// breaking nonce verification — visible as "The link you followed has
		// expired."
		//
		// We hand-build the URL, then emit it through wp_json_encode so the JS
		// literal contains real ampersands.
		$url = add_query_arg(
			[
				'action' => 'estatesite_force_update_check',
				'type'   => 'theme',
				'slug'   => rawurlencode( $this->slug ),
			],
			admin_url( 'admin-post.php' )
		);
		$url = wp_nonce_url( $url, 'estatesite_force_update_check_' . $this->slug );
		$url = html_entity_decode( $url, ENT_QUOTES, 'UTF-8' );

		$slug_json  = wp_json_encode( $this->slug );
		$label_json = wp_json_encode( __( 'Check for updates', 'estatesite-wpcore' ) );
		$href_json  = wp_json_encode( $url );

		// Pull the changelog HTML from our manifest cache so the modal can
		// render a Changelog section. Themes don't get the plugins_api
		// "View details" lightbox WP gives plugins — we inject our own.
		$manifest        = $this->fetch_manifest();
		$changelog_html  = $manifest['sections']['changelog'] ?? '';
		$changelog_json  = wp_json_encode( $changelog_html );
		$changelog_label = wp_json_encode( __( 'Changelog', 'estatesite-wpcore' ) );
		?>
		<style>
			.theme[data-slug="<?php echo esc_attr( $this->slug ); ?>"] .esc-check-updates {
				display: inline-block;
				margin-top: 4px;
				color: #2271b1;
				text-decoration: none;
				font-size: 13px;
			}
			.theme[data-slug="<?php echo esc_attr( $this->slug ); ?>"] .esc-check-updates:hover {
				color: #135e96;
				text-decoration: underline;
			}
			.theme-overlay .theme-info .esc-check-updates {
				display: inline-block;
				margin-left: 12px;
				font-size: 13px;
				color: #2271b1;
				text-decoration: none;
				vertical-align: middle;
			}
			.theme-overlay .theme-info .esc-check-updates:hover {
				color: #135e96;
				text-decoration: underline;
			}
			.esc-theme-changelog {
				margin-top: 24px;
				padding-top: 20px;
				border-top: 1px solid #dcdcde;
			}
			.esc-theme-changelog summary {
				font-weight: 600;
				font-size: 14px;
				cursor: pointer;
				color: #1d2327;
				padding: 6px 0;
				outline: none;
			}
			.esc-theme-changelog summary:hover { color: #135e96; }
			.esc-theme-changelog[open] summary { margin-bottom: 12px; }
			.esc-theme-changelog .esc-changelog-body { font-size: 13px; line-height: 1.6; }
			.esc-theme-changelog .esc-changelog-body h4 {
				margin: 16px 0 6px;
				font-size: 14px;
				color: #1d2327;
			}
			.esc-theme-changelog .esc-changelog-body h4:first-child { margin-top: 0; }
			.esc-theme-changelog .esc-changelog-body ul {
				margin: 0 0 10px 18px;
				padding: 0;
				list-style: disc;
			}
			.esc-theme-changelog .esc-changelog-body li { margin-bottom: 4px; }
			.esc-theme-changelog .esc-changelog-body code {
				background: #f0f0f1;
				padding: 1px 5px;
				border-radius: 2px;
				font-size: 12px;
			}
		</style>
		<script>
		(function () {
			var slug           = <?php echo $slug_json; ?>;
			var href           = <?php echo $href_json; ?>;
			var label          = <?php echo $label_json; ?>;
			var changelogHtml  = <?php echo $changelog_json; ?>;
			var changelogLabel = <?php echo $changelog_label; ?>;

			function makeLink() {
				var link = document.createElement('a');
				link.className = 'esc-check-updates';
				link.href = href;
				link.textContent = label;
				return link;
			}

			function injectIntoCard(card) {
				if (!card || card.querySelector('.esc-check-updates')) return;
				// Try several mount points; WP's markup changes between screens.
				var mount = card.querySelector('.theme-actions') ||
				            card.querySelector('.theme-name') ||
				            card.querySelector('.theme-author') ||
				            card;
				mount.appendChild(makeLink());
			}

			function injectIntoModal() {
				// WP renders the theme-details modal when a card is clicked.
				// `.theme-overlay` is the overlay wrapper; `.theme-info` holds
				// title, version, author + an actions row. The modal IS shared
				// across themes, so we only inject when this overlay is showing
				// OUR theme.
				var overlay = document.querySelector('.theme-overlay');
				if (!overlay) return;
				// The theme card the overlay is showing has class 'displaying-theme'
				var displayed = document.querySelector('.theme.displaying-theme');
				if (!displayed) return;
				if (displayed.getAttribute('data-slug') !== slug) return;

				var info = overlay.querySelector('.theme-info');
				if (!info) return;

				// 1. "Check for updates" link next to theme-name
				if (!overlay.querySelector('.esc-check-updates')) {
					var nameEl = info.querySelector('.theme-name');
					if (nameEl) {
						nameEl.appendChild(makeLink());
					} else {
						info.appendChild(makeLink());
					}
				}

				// 2. Changelog <details> block at the bottom of .theme-info.
				// WP doesn't show changelogs for themes natively — this is our
				// replacement for the missing themes_api modal experience.
				if (changelogHtml && !overlay.querySelector('.esc-theme-changelog')) {
					var details = document.createElement('details');
					details.className = 'esc-theme-changelog';
					var summary = document.createElement('summary');
					summary.textContent = changelogLabel;
					var body = document.createElement('div');
					body.className = 'esc-changelog-body';
					body.innerHTML = changelogHtml; // pre-sanitized server-side (h4/ul/li/strong/code only)
					details.appendChild(summary);
					details.appendChild(body);
					info.appendChild(details);
				}
			}

			function tryNow() {
				var card = document.querySelector('.theme[data-slug="' + slug + '"]');
				if (card) injectIntoCard(card);
				injectIntoModal();
				return !!card;
			}

			// Try immediately, on DOMContentLoaded, and on load — covers
			// the early/late timing of when WP's Backbone renders the card.
			if (document.readyState !== 'loading') tryNow();
			else document.addEventListener('DOMContentLoaded', tryNow);
			window.addEventListener('load', tryNow);

			// MutationObserver covers Backbone re-renders (filter, search,
			// pagination, "More info" overlay close).
			var observer = new MutationObserver(function () {
				tryNow();
			});
			var themes = document.querySelector('.themes') || document.body;
			if (themes) {
				observer.observe(themes, { childList: true, subtree: true });
			}
		})();
		</script>
		<?php
	}

	/**
	 * Force-check handler. Clears all relevant caches and bounces back.
	 * Triggered by the "Check for updates" button.
	 */
	public function handle_force_check(): void {
		if ( ! current_user_can( 'update_themes' ) && ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'estatesite-wpcore' ) );
		}
		$type = sanitize_key( $_GET['type'] ?? '' );
		$slug = wp_unslash( $_GET['slug'] ?? '' );
		check_admin_referer( 'estatesite_force_update_check_' . $slug );

		// Wipe our own manifest cache for this package.
		delete_site_transient( 'estatesite_update_' . md5( $type . '|' . $slug ) );

		// Wipe WordPress's whole update transient so its next poll re-runs
		// every plugin/theme update check, including ours. Cheap — the next
		// admin pageload rebuilds it.
		if ( $type === 'theme' ) {
			delete_site_transient( 'update_themes' );
			wp_update_themes();
			$redirect = self_admin_url( 'themes.php' );
		} else {
			delete_site_transient( 'update_plugins' );
			wp_update_plugins();
			$redirect = self_admin_url( 'plugins.php' );
		}

		// Brief flash via a query arg so the next page can show a notice.
		$redirect = add_query_arg( 'estatesite_update_checked', '1', $redirect );
		wp_safe_redirect( $redirect );
		exit;
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

	/**
	 * One-shot recovery handler. Visiting `?estatesite_clear_update_cache=1`
	 * as an authenticated admin clears all EstateSite update caches AND the
	 * WordPress update_themes/update_plugins transients, then redirects to
	 * Dashboard → Updates. Lets stuck customers recover without WP-CLI.
	 *
	 * Registered on `init` (not admin_init) so the URL works from anywhere
	 * on the site, including the front-end.
	 */
	public function maybe_handle_clear_url(): void {
		static $handled = false;
		if ( $handled ) {
			return;
		}
		if ( empty( $_GET['estatesite_clear_update_cache'] ) ) {
			return;
		}
		if ( ! current_user_can( 'update_themes' ) && ! current_user_can( 'update_plugins' ) ) {
			return; // Silent — don't tip off non-admins that the endpoint exists.
		}
		$handled = true;

		// Clear ALL EstateSite manifest caches we can find. Each Update_Checker
		// instance has its own slug-keyed transient — we don't know all the
		// slugs from inside one instance, so wipe every option starting with
		// our prefix.
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_estatesite_update_%' OR option_name LIKE '_site_transient_timeout_estatesite_update_%'" );

		// Wipe WP's own update transients.
		delete_site_transient( 'update_themes' );
		delete_site_transient( 'update_plugins' );
		wp_update_themes();
		wp_update_plugins();

		// Redirect to Updates page with a notice flag.
		wp_safe_redirect( add_query_arg( 'estatesite_update_checked', '1', self_admin_url( 'update-core.php' ) ) );
		exit;
	}

	/**
	 * Render a one-shot success notice after handle_force_check redirects.
	 * Static guard so multiple Update_Checker instances don't all render it.
	 */
	public function maybe_render_force_check_notice(): void {
		static $rendered = false;
		if ( $rendered ) {
			return;
		}
		if ( empty( $_GET['estatesite_update_checked'] ) ) {
			return;
		}
		$rendered = true;
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'EstateSite update check complete. If a new version is available, you will see it on this page.', 'estatesite-wpcore' ); ?></p>
		</div>
		<?php
	}
}
