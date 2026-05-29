# EstateSite Core — Plugin Notes

## Status

Phase 0 scaffold. Bootstrap + autoloader + CodeStar bundled. No business logic yet.

## Architecture

- **PSR-4 namespace**: `EstateSite\Core\` → `includes/`
- **Class prefix** for legacy-style classes: `ESCore_` (rare; new code uses namespaces)
- **Constants**: `ESCORE_VERSION`, `ESCORE_FILE`, `ESCORE_DIR`, `ESCORE_URL`, `ESCORE_BASENAME`
- **Hand-rolled autoloader** in main file (Composer optional; vendor/ if present is preferred)
- **CodeStar Framework v2.3.1** bundled at `codestar-framework/`. Guarded with `class_exists('CSF')` to coexist with standalone CodeStar.
- **Update pipeline**: native WP filter reads manifest JSON from `https://dev.estatesite.eu/updates/estatesite-wpcore.json`, downloads zip from same server. See [/plugins-release-docs.md](../../../plugins-release-docs.md) at the dev site root for the full release/update flow.

## Files of note

- `estatesite-wpcore.php` — plugin bootstrap, constants, autoloader, hooks
- `includes/class-plugin.php` — singleton, boots subsystems
- `includes/class-activator.php` — runs on activation: seeds default options, flushes rewrite rules
- `includes/class-deactivator.php` — runs on deactivation: flushes rewrite rules (does NOT delete data)
- `includes/admin/class-admin.php` — admin menu, placeholder status page
- `uninstall.php` — runs on plugin delete: removes own options/transients. Does NOT delete property posts.

## Phase 1 work (next)

- `includes/class-compat-mode.php` — legacy_fave vs native_esc detection
- `includes/class-property.php` — meta accessor (THE keystone)
- `includes/class-cpt.php` — register property/agent/agency CPTs and 8 taxonomies
- `includes/compat/class-houzez-compat.php` — fave_* ↔ esc_* mirror shim
- Auto-generated `includes/compat/meta-key-map.php` (257 keys)

See `/home/estatesite-dev/htdocs/dev.estatesite.eu/PHASE_0_1_DESIGN.md` for full design.

## Dependencies

- WordPress 6.4+
- PHP 7.4+
- (Bundled) CodeStar Framework 2.3.1

## Companion packages

- **EstateSite Classic** (theme) — `wp-content/themes/estatesite-classic/`. Hard depends on Core.
- **EstateSite Elementor** (plugin) — `wp-content/plugins/estatesite-wpelementor/`. Hard depends on Core + Elementor.
