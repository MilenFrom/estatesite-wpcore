=== EstateSite Core ===
Contributors: estatesite
Tags: real estate, property listings, agents, agencies
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.7
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Real estate property management core for WordPress. Powers the EstateSite Classic theme and Elementor add-on.

== Description ==

EstateSite Core provides the foundation for a real estate site:

* Property, agent, and agency custom post types
* Property search and filtering
* CodeStar-powered admin options
* Metaboxes for property details
* Multi-currency price formatting
* Dual-mode operation: works on fresh installs (native_esc) or migrates from existing Houzez installs (legacy_fave)

This is the engine. The Classic theme is the visual layer. The Elementor add-on is for page builders.

== Changelog ==

= 1.0.7 =
* Refactor: Removed theme-specific UI code from Update_Checker. The theme card "Check for updates" link and the Theme Details overlay Changelog block were living inside Core, which incorrectly coupled Core's generic update infrastructure to a specific theme's presentation. Core now exposes a small public API (manifest(), get_force_check_url(), get_slug(), get_type(), get_version()); EstateSite Classic owns its own admin UI in inc/class-update-ui.php. This release pairs with estatesite-classic v1.0.4 which contains the relocated code. Functional behavior is unchanged for customers.

= 1.0.6 =
* New: Theme Details overlay now shows a collapsible Changelog section with the full version history. WP doesn't have a themes_api equivalent of the plugin "View details" modal, so theme update notifications historically gave customers no visibility into what changed between releases. Update_Checker now reads the rich changelog HTML from the manifest and injects it as a `<details>` block at the bottom of `.theme-info` in WP's theme overlay.
* Tweak: Changelog content for both plugin and theme manifests pulled from readme.txt's `== Changelog ==` section by bin/release.sh, converted from wiki markup (`= 1.0.6 =` headings, `* bullets`, `**bold**`, `` `code` ``) to HTML.

= 1.0.5 =
* Fix: "Check for updates" link on theme card produced "The link you followed has expired" when clicked. wp_nonce_url + esc_js chain HTML-encoded the ampersands to &amp; — the browser then received $_GET keys named amp;type, amp;slug, amp;_wpnonce. Now uses wp_json_encode + html_entity_decode so the URL has real ampersands in the emitted JS.
* New: "Check for updates" link also appears inside the theme details modal (the overlay that opens when you click a theme card).

= 1.0.4 =
* Fix: EstateSite Classic theme rendered blank header and blank footer because houzez/framework/template-hooks.php was never ported. Header.php called do_action('houzez_header') but no callbacks were registered. Ported template-hooks.php into Core's includes/ directory and wired into load order after template-functions.php.
* Fix: Property::get/set/delete/get_many strict int signatures caused silent TypeError fatals when widgets dereferenced $post->ID where $post is null (Single Property Theme Builder editing path). Signatures now ?int with null/0 short-circuit returning sensible defaults.
* Fix: Migrator gallery images collapsed from many rows to one during migration. copy_meta_keys() used get_post_meta(..., true) which only fetched the first row. Now uses get_post_meta(..., false) + add_post_meta() loop for multi-row meta. Verified: 765 fave_property_images rows now migrate to 765 esc_property_images rows.
* Fix: Migration Page buttons stayed disabled on installs where auto-detection cached the wrong compat mode. Added data-aware enablement: buttons key off whether legacy fave_* data actually exists in the DB, not just the cached compat-mode flag. Submit_button('disabled' => false) bug also fixed (was rendering disabled="" which the browser treated as disabled).

= 1.0.3 =
* Fix: "Check for updates" link on theme card never appeared, and theme update notifications never fired on customer sites. JS was a one-shot DOMContentLoaded handler but WP renders theme cards client-side via Backbone — the card didn't exist when DOMContentLoaded fired. Switched to: try-immediately + DOMContentLoaded + load + MutationObserver on .themes. Covers initial Backbone render, filter changes, search, pagination.

= 1.0.2 =
* New: "Check for updates" link in every EstateSite plugin row on Plugins → Installed Plugins, and on every EstateSite theme card on Appearance → Themes. Click clears our 12h manifest cache + WP's update_themes/update_plugins transient, force-polls fresh, redirects back with a success notice.
* New: One-shot recovery URL ?estatesite_clear_update_cache=1 for any authenticated admin to nuke all EstateSite update caches + WP transients. Lets customers stuck on a stale manifest recover with a single URL we can email them.

= 1.0.1 =
* Fix: Customers installing v1.0.0 saw multiple "EstateSite Core" rows in the Plugins list — one for the real plugin and several phantom entries for bundled CodeStar Framework + Meta Box (and 8 addons). Activating any phantom row hit "The plugin does not have a valid header." bin/release.sh now rewrites `Plugin Name:` to `Plugin Name (bundled):` in every bundled .php under the package root, with the entry file exempted.

= 1.0.0 =
* First production release.
* Property, agent, agency, project, partner, testimonial CPTs registered with full Houzez-compatible taxonomies (property_type, property_status, property_label, property_city, property_area, property_country, property_state, property_feature).
* \EstateSite\Core\Property accessor — single entry point for all meta reads/writes across the dual-mode lifecycle.
* Houzez_Property_Search engine ported (1,566 lines) — handles ?search-listings=true via pre_get_posts with full taxonomy + meta filter chain.
* Advanced Search admin settings page (1,115 lines) ported into Core options.
* Compat_Mode: auto-detects legacy_fave (Houzez data present) vs native_esc (fresh install). Three modes: legacy_fave / migrating / native_esc.
* Migration Page (admin UI) with Prepare → Run → Cutover → Rollback flow, batch-walking entities (posts, pages, users, terms), idempotent + resumable, copies houzez_options → estatesite_options.
* Update_Checker — native WP integration polls manifest JSON at https://dev.estatesite.eu/updates/ on the standard 12h cadence. No third-party libraries; no hardcoded auth tokens.
* CodeStar Framework v2.3.1 bundled at codestar-framework/ (guarded with class_exists to coexist with standalone CodeStar).
* Meta Box v5.x + 8 addons bundled at lib/meta-box/ for property/agent/agency metabox declarations.
* Houzez framework helper functions (helper_functions.php, price_functions.php, property_functions.php, profile_functions.php, agency_agents.php, taxonomy-helper.php, template-functions.php, etc.) ported into includes/functions/ — fave_*/esc_* meta-key handling rewired to use Property accessor.
* Houzez admin metaboxes ported into includes/metaboxes/.

= 0.1.0 =
* Initial scaffolding (Phase 0). Bootstrap, autoloader, CodeStar bundled, admin placeholder.
