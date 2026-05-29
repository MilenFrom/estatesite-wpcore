# EstateSite Core

The functional backbone for [EstateSite](https://estatesite.eu) — a WordPress real-estate stack forked from Houzez 4.1.6.

This plugin provides:
- Custom post types (`property`, `houzez_agent`, `houzez_agency`) and 8 supporting taxonomies
- Property meta accessor (`\EstateSite\Core\Property::get/set`) with dual-mode compat shim (`legacy_fave` ↔ `native_esc`)
- Search, submit-listing, user-verification, query, and data-source engines
- 24 procedural helper modules (price formatting, emails, cron, schema, etc.)
- 27 metabox files (CodeStar-based)
- 40 Redux option panels ported to CodeStar Framework
- Login / register / social-auth (Facebook, Google, Twitter, OpenID) + 6 user roles
- 27 shortcodes
- 14 classic WordPress widgets

## Companion packages

- **[EstateSite Classic](https://github.com/MilenFrom/estatesite-classic)** (theme) — presentation layer
- **[EstateSite Elementor](https://github.com/MilenFrom/estatesite-wpelementor)** (plugin) — Elementor widgets + theme builder + templates library

Core is the dependency of the other two. Install Core first.

## Install

1. Download the latest `.zip` from the [Releases](https://github.com/MilenFrom/estatesite-wpcore/releases) page.
2. WordPress Admin → Plugins → Add New → Upload Plugin → choose the zip.
3. Activate. Core auto-detects compatibility mode:
   - **`legacy_fave`** (default for existing Houzez sites): `fave_*` meta keys preserved, full Houzez compat.
   - **`native_esc`** (fresh installs): `esc_*` namespaced keys.

Once installed, future updates appear automatically in Dashboard → Updates.

## Requirements

- WordPress 6.4+
- PHP 7.4+ (8.1+ recommended)
- The bundled CodeStar Framework v2.3.1 (no separate install)
- The bundled Meta Box library (no separate install)

## Development

```bash
git clone git@github.com:MilenFrom/estatesite-wpcore.git
cd estatesite-wpcore
# Symlink into your local WordPress install
ln -s "$PWD" /path/to/wp-content/plugins/estatesite-wpcore
```

PHP lint runs on every push via [GitHub Actions](.github/workflows/ci.yml). Tag a `vX.Y.Z` to trigger a release build.

## Release process

See [RELEASE_PROCESS_ESTATESITE.md](https://github.com/MilenFrom/estatesite-wpcore/blob/main/RELEASE_PROCESS_ESTATESITE.md) in the dev repo.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
