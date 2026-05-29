# Porting Notes — what gets ported, what gets stripped

This file tracks the Phase 2 porting effort: which Houzez source files become EstateSite Core, and which are intentionally skipped.

## Skipped — ad surface, upsells, license-manager

These files are **NOT** ported. They exist only to push Houzez's commercial products onto customers.

| Source path | Reason |
|---|---|
| `wp-content/themes/houzez/framework/admin/ai-promo.php` | Velisto.ai product promo (352 lines) |
| `wp-content/themes/houzez/framework/admin/mobile-app.php` | Mobile app upsell (244 lines) |
| `wp-content/themes/houzez/framework/admin/feedback.php` | Feedback form mailing `houzez@favethemes.com` (777 lines) |
| `wp-content/themes/houzez/framework/admin/verification-requests.php` | Function kept conceptually, branding stripped |
| `wp-content/themes/houzez/framework/favethemes-license-manager/` | Whole license-nag subsystem |
| `wp-content/themes/houzez/framework/flm-loader.php` | License-manager loader |
| `wp-content/themes/houzez/framework/admin/menu/css/` (favethemes branding) | Visual branding only |
| All "Mobile App" / "Feedback" / "License" admin menu items in `class-admin.php` | Removed from our admin menu |

**Estimated lines stripped**: ~2,500 from theme + license-manager subdirectory.

## Skipped — WPBakery (decided in FORK_PLAN.md)

| Source path | Reason |
|---|---|
| `wp-content/themes/houzez/framework/vc_extend.php` | 115,936 bytes of WPBakery shortcode definitions — we don't support WPBakery |
| `wp-content/themes/houzez/vc_templates/` | WPBakery template snippets |

## Skipped — WooCommerce templates (decided in FORK_PLAN.md)

| Source path | Reason |
|---|---|
| `wp-content/themes/houzez/woocommerce/` | We're not a real-estate + shop theme |

## Skipped — companion plugin features we own elsewhere

| Source feature | Where it lives now |
|---|---|
| `houzez-theme-functionality/elementor/widgets/` (66 widgets) | Will be ported into `estatesite-wpelementor` in Phase 4 |
| `houzez-studio/admin/` (template builder + demo importer) | Demo importer deferred to v2 per FORK_PLAN; template builder absorbed into Elementor pkg later |
| `houzez-login-register/social/` (social login) | Will be ported into Core in this phase |

## Ported — what makes it into Core

### Phase 2 scope

| Source | Target | Status |
|---|---|---|
| `framework/options/*.php` (40 files, 16K LOC) | `includes/options/*.php` (CSF) | **DONE** — 31 files ported, 9 skipped (ad surface). 113 CSF sections registered against `houzez_options` storage in legacy mode. Panel renders at `/wp-admin/admin.php?page=estatesite_options`. |
| `framework/functions/*.php` (22 files, 30K LOC) | `includes/functions/*.php` | **DONE** — all 22 helpers ported via `tools/port-helper-file.php`. Loaded in priority order from `class-plugin.php::load_helpers()`. |
| `framework/metaboxes/*.php` (27 files, 6K LOC) | `includes/metaboxes/*.php` | **DONE** — bundled Meta Box 5.9.3 at `lib/meta-box/`. 47 metabox files ported, 23 metaboxes register via `rwmb_meta_boxes` filter (property, agent, agency, project, packages, partners, testimonials, reviews, advanced search, page settings). |
| `framework/class-houzez-data-source.php` (439 lines) | `includes/classes/class-houzez-data-source.php` | **DONE** — `Houzez_Data_Source::init()` runs at file end. |
| `framework/class-houzez-query.php` (893 lines) | `includes/classes/class-houzez-query.php` | **DONE** — 23 methods available. |
| `framework/class-houzez-lazy-load.php` (111 lines) | `includes/classes/class-houzez-lazy-load.php` | **DONE** — hooks into init. |
| `framework/class-houzez-property-search.php` (1,575 lines) | `includes/classes/class-houzez-property-search.php` | **DONE** — 39 static methods, all hooks registered into `houzez20_search_filters`, `houzez_taxonomy_search_filter`, `houzez_meta_search_filter`. |
| `framework/class-houzez-property-submit.php` (902 lines) | `includes/classes/class-houzez-property-submit.php` | **DONE** — submit-listing AJAX handler. |
| `framework/class-houzez-user-verification.php` (2,021 lines) | `includes/classes/class-houzez-user-verification.php` | **DONE** — instance lives in `$GLOBALS['houzez_user_verification']` matching Houzez convention. |
| `framework/widgets/*.php` (14 files, 2.4K LOC) | `includes/widgets/*.php` | **DONE** — all 14 widgets register on `widgets_init`. |
| `houzez-theme-functionality/shortcodes/*.php` (23 files, 2.7K LOC) | `includes/shortcodes/*.php` | **DONE** — 19 shortcodes registered. |
| `framework/class-houzez-property-search.php` (61KB) | `includes/class-property-search.php` | Pending — thorniest |
| `framework/class-houzez-property-submit.php` (38KB) | `includes/class-property-submit.php` | Pending |
| `framework/class-houzez-user-verification.php` (95KB) | `includes/class-user-verification.php` | Pending |
| `framework/class-houzez-query.php` (29KB) | `includes/class-query.php` | Pending |
| `framework/class-houzez-data-source.php` (17KB) | `includes/class-data-source.php` | Pending |
| `framework/widgets/*.php` (14 files, 2.4K LOC) | `includes/widgets/*.php` | Pending |
| `houzez-theme-functionality/shortcodes/*.php` (25 files, 2.7K LOC) | `includes/shortcodes/*.php` | Pending |
| `houzez-login-register/social/` | `includes/auth/social.php` | Pending |

### Storage strategy

- **In legacy mode**: CSF reads/writes `wp_options['houzez_options']` — preserves the existing 1,324 settings on a Houzez site.
- **In native mode**: CSF uses `wp_options['estatesite_options']`.
- Migration tool (Phase 5) copies `houzez_options` → `estatesite_options` on opt-in.

### Options porter — how it works

The `tools/port-redux-to-csf.php` script converts Houzez Redux option files to CSF equivalents:

- `Redux::setSection( $houzez_opt_name, ... )` → `CSF::createSection( $prefix, ... )`
- `'type' => 'switch'` → `'type' => 'switcher'`
- `'type' => 'editor'` → `'type' => 'wp_editor'`
- `'type' => 'ace_editor'` → `'type' => 'code_editor'`
- `'subtitle' =>` → `'desc' =>` (empty `'desc' => ''` lines dropped first to avoid duplicate-key collisions)
- Redux-only keys stripped: `compiler`, `force_output`, `output`, `output_variables`, `permissions`

Re-run with `php tools/port-redux-to-csf.php --all` if you change the porter or Houzez updates upstream.

Files skipped (in SKIP_FILES): `houzez-option.php`, `houzez-options.php`, `main.php` (Redux loaders we don't need), `splash.php`, `webhooks.php`, `insights.php`, `projects-options.php`, `cache-options.php`, `remove-tracking-class.php`.

### Options loader

`includes/options/loader.php` is hooked at `csf_loaded`. It:
1. Loads `houzez-function-aliases.php` so ported files don't fatal on missing Houzez helpers
2. Sets `$estatesite_opt_prefix` = `houzez_options` (legacy) or `estatesite_options` (native)
3. Calls `CSF::createOptions()` to register the panel as a submenu under our `estatesite` menu
4. Initializes helper arrays (`$custom_fields_array`, etc.) on `$GLOBALS` for ported files to consume
5. Requires each section file inside a closure that `extract($GLOBALS, EXTR_REFS)` so files see the globals in their local scope
6. Wraps each require in try/catch so a single broken file doesn't break the whole panel

### Helper-function porter

The `tools/port-helper-file.php` script ports framework/functions/*.php files. Transformations:

1. `get_post_meta($id, 'fave_*', true)` → `\EstateSite\Core\Property::get($id, 'logical')`
2. `get_post_meta($id, 'fave_*', false)` → `get_post_meta($id, Property::key('logical'), false)` (preserves array-return contract)
3. `update_post_meta($id, 'fave_*', $v)` → `\EstateSite\Core\Property::set($id, 'logical', $v)`
4. `delete_post_meta($id, 'fave_*')` → `\EstateSite\Core\Property::delete($id, 'logical')`
5. `delete_post_meta($id, 'fave_*', $v)` → `delete_post_meta($id, Property::key('logical'), $v)`
6. `get_user_meta($id, 'fave_*', true)` → `Property::get($id, 'logical', null, 'user')`
7. `get_the_author_meta('fave_*', $id)` → `Property::get($id, 'logical', null, 'user')`
8. `get_term_meta($id, 'fave_*', true)` → `Property::get($id, 'logical', null, 'term')`
9. `'meta_key' => 'fave_*'` (in WP_Query) → `'meta_key' => Property::key('logical')`
10. `$args['meta_key'] = 'fave_*'` → `$args['meta_key'] = Property::key('logical')`

Unmapped keys (not in the property/user/term entity for that transform shape) get a `/* TODO unmapped fave_ key */` comment. These are manual triage — most are cross-entity references (e.g., a property function reading package or page meta).

### Direct meta access elimination

Every ported file's direct `get_post_meta($id, 'fave_*')` calls must be rewritten to `\EstateSite\Core\Property::get($id, $logical)`. The porter script handles the trivial cases; manual review catches the rest.

### Porter-driven promotional scrubs

The helper porter (`tools/port-helper-file.php`) now has a `STRIP_FUNCTIONS` const that lists Houzez functions to remove during port. Currently:
- `houzez_flm_migration_notice` — the "Houzez License Migration" admin banner

Each stripped function is replaced with a `// REMOVED (Phase 2): functionName() — promotional/license content stripped at port time.` comment in the ported file, so we have an audit trail. **All `add_action`/`add_filter` calls hooking these functions are also stripped.**

If you discover another promotional Houzez function during testing, add it to `STRIP_FUNCTIONS` and re-run the porter — the scrub survives re-ports.

### Phase 5 — Integration testing & migration (DONE)

**EA Sync compatibility verified**:
- EA Sync remains active alongside our wpcore + wpelementor packages
- 163 properties in the live site work end-to-end
- When EA Sync writes `update_post_meta($pid, 'fave_property_price', $v)`, the Houzez_Compat mirror shim automatically writes `esc_property_price` too (verified via direct test)
- `Property::get()` reads the correct value through the active-mode key

**Migration tool**: `includes/class-migrator.php` + `includes/class-cli.php`. Three explicit phases, fully reversible:

| Phase | Command | What it does |
|---|---|---|
| 1. Prepare | `wp estatesite migrate prepare` | Switches Compat_Mode to MIGRATING — new writes dual-write to fave_* + esc_* |
| 2. Run | `wp estatesite migrate run [--batch=100] [--all] [--dry-run]` | Walks all property/agent/agency/project/partner/testimonial posts, copies fave_* meta to esc_* keys. Resumable via last_post_id tracking. Idempotent. Also copies `houzez_options` → `estatesite_options`. |
| 3. Cutover | `wp estatesite migrate cutover [--force]` | Switches mode to NATIVE_ESC. Reads now prefer esc_* (with legacy fave_* fallback for safety). |
| Rollback | `wp estatesite migrate rollback` | Switches back to LEGACY_FAVE. Preserves esc_* meta (unused but available for next cutover attempt). |
| Status | `wp estatesite migrate status` | Show current mode, last processed post ID, batch counts, timestamps. |

**Smoke-tested end-to-end on the dev site** (2026-05-25):
1. Prepared from legacy_fave → MIGRATING ✓
2. Ran full migration: 200 posts processed, options_copied=true, 0 errors ✓
3. Cutover to NATIVE_ESC: `Property::key('price')` switched from `fave_property_price` → `esc_property_price`. Property 39484 (warehouse, 26,400,000) and 39826 (Банкя, 895,000) both read correctly ✓
4. Rolled back to LEGACY_FAVE: mode reverted, accessor returns to fave_ keys, esc_ meta preserved ✓

**Site current state**: `legacy_fave` mode (rolled back), data unchanged but every property now has both fave_ and esc_ meta keys (esc_ unused but available).

### Phase 4 — Elementor widget package (DONE)

The entire HTF (`houzez-theme-functionality/elementor/`) was copied into `estatesite-wpelementor/elementor/`:

| What | Count | Source |
|---|---|---|
| Widgets | 201 PHP files (66 logical widgets + variants/sub-files) | `houzez-theme-functionality/elementor/widgets/` |
| Traits | 7 files (244 KB) | `houzez-theme-functionality/elementor/traits/` |
| Controls | 5 files (32 KB) | `houzez-theme-functionality/elementor/controls/` |
| Dynamic tags | 4 files (20 KB) | `houzez-theme-functionality/elementor/tags/` |
| Template parts | 57 files (292 KB) | `houzez-theme-functionality/elementor/template-part/` |
| Frontend assets | 12 KB | `houzez-theme-functionality/elementor/assets/` |
| Absorbed sunset widgets | 12 files | `estatesite-houzez-master/elementor-widgets/` |
| Bootstrap loader | `includes/elementor-loader.php` | Adapted from `houzez-theme-functionality/elementor/elementor.php` (520 lines) — sed-rewrote `HOUZEZ_PLUGIN_PATH/DIR . 'elementor/'` → `ESELE_DIR . 'elementor/'` |

**Path constant aliasing** in `estatesite-wpelementor.php` bootstrap:
- `HOUZEZ_PLUGIN_URL`, `HOUZEZ_PLUGIN_DIR`, `HOUZEZ_PLUGIN_PATH`, `HOUZEZ_PLUGIN_IMAGES_URL` defined unless already set
- These let ported widget code reference asset paths like `HOUZEZ_PLUGIN_URL . 'elementor/assets/css/author-box.css'` without changes
- Guarded with `defined()` so a still-active HTF plugin would win (defensive — in our deployment HTF is deactivated)

**Bulk meta-access port** (200 widget files): used `tools/port-helper-file.php` with namespace-aware fix — the porter now detects existing `namespace Elementor;` declarations and keeps them at the top, inserting the auto-port docblock AFTER the namespace (PHP requires `namespace` to be the first statement).

**Registration results** (full WP admin boot):
- 312 total Elementor widgets registered (built-ins + Pro + ours)
- **171 Houzez/EstateSite widgets** (8 property card variants, 7 carousel variants, header-footer, single-property/agent/agency builders, sidebar widgets, blog widgets, etc.)
- 11 widget categories registered: houzez-elements, houzez-header-footer, houzez-single-property, houzez-single-agent, houzez-single-agency, houzez-loop-builder, houzez-single-post, houzez-sidebar-widgets, etc.

**Smoke test**: property page 200KB HTML, no fatals, all data renders correctly. Editor would show widgets in the panel.

### Phase 3 — Theme template port (DONE)

The entire Houzez visual layer was ported into `wp-content/themes/estatesite-classic/`:

| What | Where | Notes |
|---|---|---|
| 446 template-parts | `template-parts/` | Banners, blog, dashboard, footer, header, listing, login-register, membership, page, project, realtors, reviews, search, taxonomy, testimonials, topbar |
| 104 property-details fragments | `property-details/` | Top-area variants v1-v7, single-property layouts (tabs, tabs-vertical, simple, luxury), mobile views, partials |
| 42 top-level templates | theme root | `single-property.php`, `archive-property.php`, `taxonomy-*.php`, `single-houzez_agent.php`, etc. |
| 6.1MB CSS | `css/` | All Houzez stylesheets including dashboard, halfmap, FontAwesome |
| 5.8MB JS | `js/` | All Houzez frontend scripts incl. dashboard, sliders, maps, gallery |
| 2.0MB images | `img/` | Houzez UI icons, placeholders, demo images |
| 1.4MB fonts | `fonts/` | Webfonts referenced by CSS |
| 25MB languages | `languages/` | 29 locale .po/.mo files preserved |
| 790-line script registrar | `inc/register-scripts.php` | Houzez's enqueue logic, self-hooks into wp_enqueue_scripts |
| 1864-line styling-options | `inc/styling-options.php` | Outputs inline CSS based on CSF option values |
| Yelp OAuth | `inc/yelpauth/` | External integration loader |

**Path constants** are aliased in the theme so ported templates resolve to OUR asset dir, not the Houzez source theme. Core's `houzez-function-aliases.php` defines `HOUZEZ_IMAGE` lazily on `after_setup_theme` priority 0 — picks up the active theme's `img/` dir automatically.

**Smoke test results** (with full template stack loaded):
- Single property page (39826 / Банкя): 200KB HTML, `895,000€` price, no fatals
- Home page: 71KB HTML, no fatals
- Property archive: 188KB HTML, no fatals
- Property edit admin: 2 metaboxes attached (houzez-property-meta-box + Additional Features)

**Text-domain**: kept as `'houzez'` for ported templates so existing .mo translations continue to work. Renaming deferred to a cleanup phase.

### Phase 2 known issues

- **Data shape sensitivity**: Some Houzez meta fields are stored as arrays (e.g. `fave_author_custom_picture` is `[id => X, url => Y]` from a media-field widget). Code that calls `esc_url($value)` expects a string. Manual normalization needed in helpers — done in `houzez_get_author_picture()` and `houzez_option()`.
- **3-arg houzez_option**: `houzez_option($id, $fallback, $param)` — the third arg `$param` extracts a sub-key when the stored value is an array. Our stub handles this.
- **Unmapped TODO count**: 197 across all helpers. These are cross-entity references and array-blob accesses. None block the property page from rendering, but each needs manual review when extending features.
