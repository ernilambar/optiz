# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`nilambar/optiz` is a PHP library (not a standalone plugin) that WordPress plugins bundle to generate admin settings pages from a PHP array schema. Minimum PHP 7.4, no external dependencies beyond WordPress core.

## Commands

```bash
# Install / regenerate the autoloader after adding a class
composer install

# Syntax-check all PHP source files
php -l src/*.php && php -l init.php

# Run ad-hoc logic tests (no test runner; stubs replace WP functions inline)
php -r "require 'vendor/autoload.php'; /* stub WP functions, then assert */"

# Bump the library version (single source of truth)
# Edit "version" in composer.json, then run:
composer install
```

There is no build step, no transpiler, and no test runner configured. Tests are written as inline PHP scripts that stub WordPress functions (see the integration test patterns established in previous development sessions).

## Architecture

### Entry point and version election

Plugins include `init.php`, never a class file directly. `init.php` implements a **version election** mechanism: every bundled copy registers itself in a global `$optiz_candidates[version] => dir` map. A single `plugins_loaded` hook (guarded by the `OPTIZ_ELECTION_HOOKED` constant) finds the highest version via `uksort + version_compare` and loads only that copy's autoloader. This is how multiple plugins can each bundle the library without fatal conflicts.

After election, three constants are defined: `OPTIZ_LOADED_VERSION`, `OPTIZ_DIR` (filesystem path), `OPTIZ_URL` (web-accessible URL via `plugin_dir_url`).

### Data flow

```
Developer schema array
      │
      ▼
Parser::parse()          ← validates & normalises; returns WP_Error on failure
      │
      ▼
Registry (set_schema)    ← holds normalised schema + saved option values
      │
      ▼
Manager                  ← orchestrates: WP hooks, get(), save()
    ├── Renderer          ← HTML output (admin page)
    ├── Validator         ← sanitizes POST data before update_option()
    └── Assets            ← enqueues CSS/JS only on the matching page
```

### Normalised schema contract

`Parser::parse()` guarantees that after it runs, the schema always has the shape below — downstream classes never null-check optional keys:

```php
[
  'option_key' => 'sanitized_string',
  'page'       => [ 'title', 'menu_slug', 'capability', 'icon_url', 'position', 'parent_slug' ],
  'tabs'       => [
    [
      'id'     => 'string',
      'label'  => 'string',
      'fields' => [
        [
          'id', 'type', 'label', 'default',      // always present
          'description', 'attributes', 'choices', // always present (empty defaults)
          'depends_on',                            // always array-of-arrays, never flat
          'sanitize_callback',                     // null or callable
        ],
      ],
    ],
  ],
]
```

A schema with a top-level `fields` key (no tabs) is silently wrapped into a single tab with `id='default'` and `label=''`.

`depends_on` is always normalised to an array of condition arrays: `[['field'=>'x','value'=>'y'], ...]`. A developer-supplied shorthand `['field'=>'x','value'=>'y']` is wrapped automatically.

### Manager lifecycle

```php
// In plugin bootstrap (fires before admin_menu):
$m = Manager::register('my_plugin', $schema);

// Anywhere in the plugin (templates, hooks, etc.):
$value = Manager::instance('my_plugin')->get('field_id');
```

`register()` hooks `admin_menu` → `register_page()` (which runs `add_menu_page`/`add_submenu_page` and then registers `admin_enqueue_scripts`), and `admin_post_optiz_save_{key}` → `handle_save()`.

`get()` lazily calls `get_option()` once per request (cached in `$option_cache`). Return priority: saved DB value → schema `default` → `$default` argument. `save()` invalidates the cache.

### Form submission flow

The form POSTs to `admin-post.php` with `action=optiz_save_{key}`. Fields are named `{option_key}[{field_id}]`. `handle_save()` verifies the nonce, extracts `$_POST[$option_key]`, passes it to `Validator::sanitize()`, calls `update_option()`, then redirects back with `?updated=1` (success) or `?updated=0` (failure) and `?tab={current_tab}`.

### checkbox and toggle fields

Both use a hidden input (`value="0"`) immediately before the checkbox input (`value="1"`). Validator casts via `(bool)` — PHP treats `"0"` as false and `"1"` as true. Stored and retrieved as PHP booleans. Toggle and checkbox are visually different (iOS switch vs standard input) but identical in storage and logic.

### Client-side dependencies (`conditional.js`)

`Assets::enqueue()` builds a `rules` array from all fields that have `depends_on` and passes it to `conditional.js` via `wp_localize_script` as `window.optizConditional.rules`. The JS engine uses a **fixpoint loop** (repeat until no state changes, max 10 iterations) to handle chained dependencies: fields whose source field is itself hidden immediately fail their condition, causing cascading hides regardless of rule order. Fields with `depends_on` get `data-field-id` and `data-depends-on` (JSON) on their `<tr>` wrapper; fields without `depends_on` get neither attribute.

### Adding a new field type

Four places require changes:
1. `Parser::FIELD_TYPES` constant — add the type string.
2. `Validator::apply_sanitizer()` — add a `case` with the sanitizer logic.
3. `Renderer` — add a private `render_{type}_field(array $field, $value, string $option_key): string` method.
4. `assets/css/optiz-admin.css` — add styles if the field needs custom UI (scope under `.optiz-wrap`).

## Coding standards

- WordPress coding style: spaces inside parentheses in control structures (`if ( $x )`, not `if($x)`).
- Tabs for indentation in PHP and CSS; 2-space indentation in JSON (per `.editorconfig`).
- snake_case for all method and variable names.
- All HTML output escaped with `esc_html`, `esc_attr`, `esc_url`, or `esc_textarea`. The `$input_html` string passed to `render_field_wrap` is an exception — it is pre-escaped by the calling `render_*_field` method.
- `Parser::parse()` never throws — it returns `WP_Error`. `Manager::instance()` throws `\RuntimeException` for unregistered keys.
- All CSS selectors prefixed `.optiz-wrap`. WordPress provides `form-table`, `nav-tab-*`, and `.notice-*` styles; only tab-content visibility and the toggle switch are custom.
