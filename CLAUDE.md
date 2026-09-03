# CLAUDE.md

Guidance for Claude Code when working in this repository.

## What this is

`ernilambar/optiz` is a PHP library (not a plugin) that WordPress plugins bundle to generate admin settings pages from a PHP array schema. Requires PHP 8.0+; no PHP dependencies beyond WordPress core. Node 22 / pnpm 12 to build assets.

`docs/DOCS.md` is the developer-facing schema and API reference — keep it in sync when the schema changes, and read it rather than restating the schema here.

## Commands

```bash
composer install     # install deps / regenerate autoloader after adding a class
composer lint        # parallel-lint syntax check + PHPCS
composer test        # PHPUnit (tests/bootstrap.php stubs WP functions)
pnpm install
pnpm build           # resources/ → assets/optiz.{css,js}
pnpm dev             # watch mode
pnpm format          # Prettier
pnpm run version     # sync $version in init.php from package.json (easy-replace.json)
```

**Version bumping:** `package.json` `version` is the source of truth. Bump it, run `pnpm run version` to rewrite `$version` in `init.php`, then `composer install` to regenerate the autoloader.

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

### Parser guarantees

`Parser::parse()` normalises the schema so downstream classes never null-check optional keys. It returns `WP_Error` (never throws) for an unsupported field type, a missing label, a duplicate page `id` or `menu_slug`, or a field ID duplicated across pages.

- One registration holds N pages under a single `option_key`. Field IDs are unique across all pages; `field_page_map` (`field_id => page_id`) is built to prove it.
- `conditions` is always an array of condition arrays, each `['field' => …, 'value' => …, 'compare' => '===' | '!==']`. A flat shorthand is wrapped automatically.
- `position` defaults to the page's index; `Manager::register_page()` sorts by it before `add_menu_page`/`add_submenu_page`.
- Type-specific options are **flat top-level keys** on the field (`mode`, `layout`, `placeholder`, `rows`, `readonly`, …), added only for the types that use them. `attributes` is reserved for raw HTML attributes. The `*_TYPES` constants at the top of `Parser` are the authoritative list of which option applies where.

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

### Frontend asset pipeline

`resources/` is never loaded by WordPress — only the compiled `assets/` output is enqueued. Vite 8 bundles JS as IIFE and drives PostCSS (`postcss-nested` before `postcss-preset-env`); `browserslist-to-esbuild` feeds the `browserslist` query in `package.json` to Vite's `build.target` so JS and CSS share one browser matrix. Config: `vite.config.mjs`, `postcss.config.cjs` (both `export-ignore`d from Composer archives).

`resources/js/index.js` imports the CSS and calls each module's init: `conditional`, `buttonset`, `color-picker` (Coloris, bundled — not `wp-color-picker`), `code-editor` (CodeMirror via `wp_enqueue_code_editor`), `image-picker`, `file-picker`.

`Assets::enqueue()` passes everything the JS needs as one `window.optiz` object via `wp_add_inline_script` — `conditional.rules` always, `codeEditor.{settings,mimeMap}` when a `code` field is present. `wp_enqueue_media()` runs when an `image` or `file` field is present.

### Adding a new field type

1. `Parser::FIELD_TYPES` — add the type, plus any relevant `*_TYPES` constant.
2. `Validator::apply_sanitizer()` — add a `case`.
3. `FieldRenderer` — add `render_{type}_field()`.
4. `resources/css/optiz.css` and/or a `resources/js/` module (init it from `index.js`) if the type needs custom UI.
5. Document it in `docs/DOCS.md`.

## Quality Gate

- `composer lint` exits clean.
- `pnpm build` to bundle assets, `pnpm format` to format JS/CSS/JSON.
