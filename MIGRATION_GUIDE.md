# Migrating an existing Houzez site to EstateSite

This guide is for customers running the Houzez theme today who want to switch to EstateSite.

## What's preserved

✅ **All property data** — listings, prices, sizes, bedrooms, bathrooms, etc.
✅ **All agents and agencies** — profiles, contact info, properties they're linked to
✅ **All theme settings** — 1,300+ Houzez options carry over
✅ **All page configurations** — page headers, listing templates, breadcrumb settings
✅ **All taxonomy data** — cities, areas, countries, types, statuses, features
✅ **All custom tables** — favorites, saved searches, messages, invoices, reviews
✅ **All translations** — .po/.mo files in 29 languages
✅ **All Elementor pages built with Houzez widgets** — widget IDs preserved (transparent compatibility)

## What's NOT carried over

❌ **WPBakery (Visual Composer) pages** — EstateSite doesn't support WPBakery. Affected pages must be rebuilt in Elementor or the block editor.
❌ **Houzez-branded admin pages** — Mobile App promo, AI Promo, Feedback survey, License Manager are removed (these were upsells, not functional features).

## Before you start — checklist

1. **Take a database backup.** Whatever method you trust (UpdraftPlus, your host's snapshots, `wp db export`). The migration is reversible, but a backup is always good practice.
2. **Audit your site** — visit `EstateSite → Migration` in WordPress admin OR run `wp estatesite migrate audit` from CLI. The audit shows you exactly what would be touched: how many properties, agents, pages with custom headers, users with profile fields, etc.
3. **Have ~30 minutes** for a mid-size site (a few hundred properties). Smaller sites finish in under 5 minutes.

## The migration workflow

The migration has **3 phases** plus a rollback option. Each is reversible.

### Phase 1: Prepare

**What it does**: switches the site into `MIGRATING` mode. From this point onward, every property/agent/agency write goes to BOTH the old `fave_*` keys and the new `esc_*` keys. This protects you against any concurrent activity (sync plugins, admin edits) during the bulk-copy.

**Effect on visitors**: zero. The site reads the same data via the same code paths. Visitors see no change.

**How to run**:
- **Admin UI**: `EstateSite → Migration → click "Prepare migration"`
- **CLI**: `wp estatesite migrate prepare`

### Phase 2: Run

**What it does**: walks every property, agent, agency, project, partner, testimonial post; every page; every user; every taxonomy term. For each, copies any existing `fave_*` meta values into matching `esc_*` keys. Also copies `houzez_options` → `estatesite_options`.

**Effect on visitors**: zero. The site is still reading from `fave_*` (we haven't cut over yet).

**Performance**: ~100 records per batch by default. A site with 1,000 properties + 50 agents + 100 pages typically finishes in 1–2 minutes. Resumable — if the connection drops, just run again.

**How to run**:
- **Admin UI**: click "Run migration" — progress is shown live as batches process
- **CLI**:
  ```bash
  wp estatesite migrate run --batch=500 --all
  ```

**Verify before cutover**: spot-check a property post in the admin. Both `fave_property_price` and `esc_property_price` should now be present in postmeta (you can use a plugin like "WP Meta and Date Remover" or `wp post meta list <id>` to inspect).

### Phase 3: Cutover

**What it does**: switches the compat mode to `NATIVE_ESC`. From this point, **reads use `esc_*` keys** (with `fave_*` fallback for any unmigrated data — a safety net). New writes go to `esc_*` only (with the compat shim dropped).

**Effect on visitors**: visit a property page. Should look identical to before — you're now reading EstateSite-native data.

**How to run**:
- **Admin UI**: click "Cutover to native" (with confirmation)
- **CLI**: `wp estatesite migrate cutover`

### Rollback (if anything looks wrong)

**What it does**: switches mode back to `LEGACY_FAVE`. Reads return to `fave_*`. The `esc_*` meta is **preserved** — you can re-attempt the cutover later without re-running the migration.

**Effect on visitors**: returns to pre-cutover state. No data is destroyed.

**How to run**:
- **Admin UI**: click "Rollback to legacy"
- **CLI**: `wp estatesite migrate rollback`

## Recommended deployment sequence

The safest order for a production site:

1. **Backup** the database.
2. **Install EstateSite plugins WITHOUT activating the theme**:
   - Upload + activate `estatesite-wpcore`
   - Upload + activate `estatesite-wpelementor`
   - Houzez theme stays active for now.
3. **Run the audit** (`wp estatesite migrate audit`) — confirm counts match expectations.
4. **Run prepare + run** — site still uses Houzez theme, just dual-writing meta.
5. **Verify** by editing a property in admin — should work normally.
6. **Switch the theme to EstateSite Classic.**
7. **Visit the front-end** — site should look the same as with Houzez. If broken, switch back to Houzez instantly (`wp theme activate houzez`).
8. **Cutover** to native_esc.
9. **Verify front-end again** — read paths now use `esc_*` keys.
10. **Deactivate the old Houzez companion plugins** (`houzez-theme-functionality`, `houzez-login-register`, `houzez-studio`). Their features live in `estatesite-wpcore` now.

If anything looks wrong at step 9, run `wp estatesite migrate rollback`, fix the issue, re-cutover. If anything looks wrong at step 7, switch back to Houzez (`wp theme activate houzez`) and re-test.

## Common gotchas

### Q: I'm using EstateAssistant Sync — will it keep working?

**Yes.** EA Sync writes `fave_*` keys directly. In `legacy_fave` and `migrating` modes, the compat shim mirrors every EA Sync write to the matching `esc_*` key automatically. In `native_esc` mode (post-cutover), EA Sync still writes `fave_*` keys and those are read via the legacy fallback in `Property::get()`. No EA Sync update required.

If you want EA Sync to write `esc_*` natively in the future (more performant), we'll provide an updated EA Sync release in a later phase.

### Q: I have custom code that calls `get_post_meta($id, 'fave_property_price', true)`

**Works as-is in `legacy_fave` mode.** Will continue to work in `native_esc` mode too — the compat shim ensures `fave_*` keys are written alongside `esc_*` during MIGRATING phase, so existing data is there.

For **future-proof code**, switch to:
```php
$price = \EstateSite\Core\Property::get( $post_id, 'price' );
```
This automatically uses whichever key is correct for the active mode and falls back gracefully.

### Q: How big is the database impact?

The migration doubles the postmeta rows for ported keys (e.g., each property gains ~20 new `esc_*` rows). On a site with 1,000 properties, that's ~20,000 new postmeta rows — typically <5MB of database growth. Negligible for any modern host.

### Q: Can I undo the cutover later?

**Yes, anytime.** `wp estatesite migrate rollback` switches back to `legacy_fave` mode. The `esc_*` meta stays in the database (it's harmless when unused). You can re-cutover later.

### Q: How long does the migration take?

Rough numbers:
- 100 properties: <30 seconds
- 1,000 properties: ~2 minutes
- 10,000 properties: ~20 minutes

Pages, users, and terms are typically fast (smaller record counts). The admin UI shows progress live; the CLI prints per-batch logs.

### Q: What about EstateSite-bundled CodeStar conflicts with other plugins that use CodeStar?

`estatesite-wpcore` bundles CodeStar v2.3.1 but guards with `class_exists('CSF')` so it only loads if no other plugin has loaded CodeStar first. If you have another plugin (like EstateAssistant Sync) that bundles CodeStar, it wins the class race — both plugins coexist.

## Getting help

- **Inspect migration status**: `wp estatesite migrate status` or visit the admin Migration page
- **CLI verbosity**: `wp estatesite migrate run --batch=10` (smaller batches show more granular progress)
- **Compatibility mode override**: `wp eval '\EstateSite\Core\Compat_Mode::set("legacy_fave");'` if you need to force a mode switch outside the normal workflow
- **Database inspection**: `wp db query "SELECT meta_key, meta_value FROM wp_postmeta WHERE post_id = 39826 AND meta_key LIKE 'esc_property_%' LIMIT 10"` to see migrated data on a specific property
