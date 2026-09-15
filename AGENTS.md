# Optiz — Agent Guide

## Overview

`optiz` is a **PHP library** (not a WordPress plugin) that generates admin settings pages from a PHP array schema. Requires PHP 8.0+; no PHP dependencies beyond WordPress core. JS/CSS assets are compiled via Vite 8 + PostCSS and shipped as pre-built files under `assets/`.

## Setup

```bash
composer install   # PHP deps + autoloader
pnpm install       # JS/CSS deps (Node ≥22, pnpm ≥12)
```

After adding or removing PHP classes run `composer install` again to regenerate the autoloader.

## Commands

```
composer lint     # parallel-lint syntax check + PHPCS
composer test     # PHPUnit (bootstrap stubs WP functions)
pnpm build        # compile resources/ → assets/optiz.{js,css}
pnpm dev          # watch mode
pnpm format       # Prettier (uses @wordpress/prettier-config)
pnpm run version  # sync $version in init.php from package.json
```

## Architecture

### Entry point and version election

Plugins include `init.php`, never a class file. Every bundled copy registers itself in a global candidates map; one `plugins_loaded` hook (guarded by `OPTIZ_ELECTION_HOOKED`) picks the highest version via `uksort + version_compare` and loads only that copy's autoloader — so multiple plugins can each bundle the library. Election defines `OPTIZ_LOADED_VERSION`, `OPTIZ_DIR`, `OPTIZ_URL`.

### Data flow

```
schema array → Parser::parse() → Registry → Manager
                (WP_Error on              ├── Renderer      ← page/tab/<tr> wrapper HTML
                 failure)                 │   └── FieldRenderer ← per-type input HTML
                                          ├── Validator     ← sanitizes POST before update_option()
                                          ├── Conditions    ← server-side initial visibility
                                          └── Assets        ← enqueues CSS/JS on the matching page
```

### Manager lifecycle

```php
Manager::register( 'my_plugin', $schema );              // before admin_menu
Manager::instance( 'my_plugin' )->get( 'field_id' );    // anywhere
```

`register()` hooks `admin_menu` → `register_page()` (registers every page, stores a `page_id → hook` map, hooks `admin_enqueue_scripts` once) plus one `admin_post_optiz_save_{key}_{page_id}` action per page. `instance()` throws `\RuntimeException` for an unregistered key.

`get()` calls `get_option()` once per request (cached), reading one flat option row regardless of which page owns the field. Priority: saved value → schema `default` → `$fallback`.

### Form submission

Each page posts its own form to `admin-post.php` with `action=optiz_save_{key}_{page_id}`; fields are named `{option_key}[{field_id}]`. `handle_save()` verifies the nonce, sanitizes **only that page's fields**, merges into the existing option row (so one page never resets another's values), then redirects back preserving `?tab=`. Notices go in the `optiz_notices_{key}_{page_id}` transient.

`checkbox` and `toggle` render a hidden `value="0"` input before the `value="1"` checkbox; `Validator` casts with `(bool)`. Stored as PHP booleans. `hidden` fields render outside the `form-table`.

### Conditional visibility

Two engines that must stay in agreement:

- **PHP** — `Conditions::evaluate()` computes initial visibility so the correct rows render server-side without flicker. Hidden rows get the `hidden` attribute.
- **JS** — `resources/js/conditional.js` translates rules into the [`showmo`](https://github.com/ernilambar/showmo) library's format. No animation: showing and hiding is instant.

Both use a **fixpoint loop** (max 10 passes): a field whose source field is itself hidden fails its condition, so chained dependencies cascade regardless of rule order. Fields with `conditions` get `data-field-id` and `data-conditions` (JSON) on their `<tr>`; fields without get neither.

## Conventions

- **Entry point is `init.php`**. Never import class files directly. Plugins bundle this file, never individual sources.
- **Field IDs must be globally unique** across all pages within a single registration. The parser enforces this via `field_page_map`.
- **`Parser::parse()` normalizes every optional key** so downstream classes never null-check. Optional fields always carry defaults (e.g. empty `conditions` array, `position` index).
- **Add a new field type** by updating exactly five places: `Parser::FIELD_TYPES`, `Validator::apply_sanitizer()`, `FieldRenderer::render_{type}_field()`, the asset pipeline (`resources/css/` + `resources/js/`), and `docs/DOCS.md`.
- **Conditional visibility** uses a dual engine: PHP (`Conditions::evaluate()`) for server-side initial rendering and JS (`showmo`) for client-side toggling. Keep them in sync; chained conditions resolve via a fixpoint loop (max 10 passes).

## Quality Gate

Run these commands and verify exit code 0 before declaring a task complete:

```
composer lint && composer test && pnpm build && pnpm format
```

If you add or remove PHP classes, re-run `composer install` first to regenerate the autoloader.
