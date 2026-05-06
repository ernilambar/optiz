# Optiz documentation

Detailed reference for schema, fields, sanitization, and the runtime API. For a quick start, see the [README](../README.md).

## Table of contents

- [Schema](#schema)
- [Page](#page)
- [Tabs](#tabs)
- [Fields](#fields)
  - [Common keys](#common-keys)
  - [Type-specific keys](#type-specific-keys)
  - [Field types](#field-types)
- [Sanitization](#sanitization)
- [Conditional fields](#conditional-fields)
- [Manager API](#manager-api)
- [Form submission](#form-submission)

## Schema

A schema is a plain PHP array passed to `Manager::register()`. Register from a callback on `init` (or later) so translation functions in the schema run after WordPress is ready to load textdomains — registering on `plugins_loaded` will trigger a `_doing_it_wrong` notice in WP 6.7+.

After parsing, the schema is normalised — defaults are filled in, conditions are unwrapped, and missing optional keys are populated. Top-level keys:

| Key          | Type    | Required | Description                                           |
|--------------|---------|----------|-------------------------------------------------------|
| `option_key` | string  | yes      | The `wp_options` row name. Sanitized with `sanitize_key()`. |
| `page`       | array   | yes      | Admin page configuration. See [Page](#page).          |
| `tabs`       | array   | yes      | One or more tab definitions. See [Tabs](#tabs).       |

Validation errors return a `WP_Error`; `Manager::register()` calls `_doing_it_wrong()` and skips hook registration.

## Page

| Key           | Type    | Required | Default            | Description                                                   |
|---------------|---------|----------|--------------------|---------------------------------------------------------------|
| `title`       | string  | yes      | —                  | Page `<title>`.                                               |
| `menu_title`  | string  | no       | `title`            | Sidebar label.                                                |
| `menu_slug`   | string  | yes      | —                  | URL slug (`?page=<slug>`).                                    |
| `capability`  | string  | no       | `manage_options`   | Required user capability.                                     |
| `icon_url`    | string  | no       | `''`               | Dashicons URL or class. Used only for top-level menus.        |
| `position`    | int     | no       | `null`             | Top-level menu position.                                      |
| `parent_slug` | string  | no       | `''`               | If set, registers as a submenu under that parent.             |

## Tabs

Each tab is an array with `id`, `label`, and `fields`. When only one tab is defined, the tab navigation is hidden but the tab is still rendered.

```php
'tabs' => [
    [
        'id'     => 'general',
        'label'  => __( 'General', 'textdomain' ),
        'fields' => [ /* ... */ ],
    ],
],
```

## Fields

### Common keys

These keys are accepted on every field. After parsing, they are always present (with empty defaults) so renderers and validators do not need to null-check.

| Key                 | Type      | Default                      | Description                                                                       |
|---------------------|-----------|------------------------------|-----------------------------------------------------------------------------------|
| `id`                | string    | required                     | Unique field ID within the option.                                                |
| `type`              | string    | required                     | One of the [field types](#field-types).                                           |
| `label`             | string    | required (except `hidden`)   | Label shown in the row header.                                                    |
| `default`           | mixed     | `''` / `false` / `[]`        | Fallback when no DB value exists. Defaults to `false` for booleans and `[]` for arrays. |
| `description`       | string    | `''`                         | Help text under the field. For `message` fields, this is the rendered content.    |
| `attributes`        | array     | `[]`                         | HTML attributes added to the input element (e.g. `min`, `step`, `data-*`).        |
| `class`             | string    | `''`                         | Extra CSS class on the input.                                                     |
| `choices`           | array     | `[]`                         | Required for choice-based types. Keys are stored values; values are labels.       |
| `conditions`        | array     | `[]`                         | See [Conditional fields](#conditional-fields).                                    |
| `sanitize_callback` | callable  | `null`                       | Custom sanitizer. See [Sanitization](#sanitization).                              |

`attributes` is reserved for raw HTML element attributes. Use the type-specific keys below for behavior options.

### Type-specific keys

Type-specific options sit at the top level of the field array — never nested inside `attributes`.

| Key           | Applies to                       | Values                          | Default     | Description                                              |
|---------------|----------------------------------|---------------------------------|-------------|----------------------------------------------------------|
| `placeholder` | `text`, `email`, `url`, `number`, `password`, `textarea`, `code` | string | `''`        | HTML placeholder.                                        |
| `rows`        | `textarea`, `code`               | int (>0)                        | `5`         | Visible rows. Falls back to `5` if non-positive.         |
| `side_text`   | `checkbox`, `toggle`             | string                          | `''`        | Inline text shown next to the input.                     |
| `layout`      | `radio`, `radio_image`, `multicheck` | `vertical` \| `horizontal`  | `vertical`  | Stacked vs inline arrangement.                           |
| `mode`        | `code`                           | `text` \| `css` \| `js`         | `text`      | CodeMirror syntax mode.                                  |
| `allow_null`  | `select`                         | bool                            | `false`     | Adds an empty `— Select —` option.                       |

### Field types

| Type          | Storage  | Notes                                                                             |
|---------------|----------|-----------------------------------------------------------------------------------|
| `text`        | string   | Single-line input.                                                                |
| `textarea`    | string   | Multi-line. Supports `rows`, `placeholder`.                                       |
| `email`       | string   | Sanitized via `sanitize_email()`.                                                 |
| `url`         | string   | Sanitized via `esc_url_raw()`.                                                    |
| `number`      | int/float| Cast to int when `step` is integer; otherwise float.                              |
| `password`    | string   | Same sanitizer as `text`. Value is rendered into the HTML — keep secrets out.     |
| `hidden`      | string   | No label required. Sanitized as text.                                             |
| `checkbox`    | bool     | Standard checkbox. Hidden `value="0"` companion ensures unchecked posts as false. |
| `toggle`      | bool     | iOS-style switch. Storage identical to `checkbox`.                                |
| `select`      | string   | Requires `choices`. Optional `allow_null`.                                        |
| `radio`       | string   | Requires `choices`. Supports `layout`.                                            |
| `radio_image` | string   | Requires `choices` mapping value → image URL. Supports `layout`.                  |
| `buttonset`   | string   | Requires `choices`. Renders as a styled button group.                             |
| `multicheck`  | array    | Requires `choices`. Stores an array of selected keys. Supports `layout`.          |
| `color`       | string   | WP color picker. Sanitized via `sanitize_hex_color()`.                            |
| `image`       | string   | Image URL with WP media frame. Sanitized via `esc_url_raw()`.                     |
| `code`        | string   | CodeMirror editor. Stored verbatim — no sanitization.                             |
| `editor`      | string   | TinyMCE (`wp_editor`). Sanitized via `wp_kses_post()`.                            |
| `heading`     | —        | Display-only. Renders `label` as an `<h2>`. Skipped during save.                  |
| `message`     | —        | Display-only. Renders `description` (allows `wp_kses_post` HTML). Skipped during save. |

## Sanitization

Every field is sanitized on save before being passed to `update_option()`. The pipeline is:

1. If `sanitize_callback` is set and callable, it is called with the raw value.
2. The return type is checked against the field type:
   - `multicheck` → must return an array.
   - `checkbox`, `toggle` → must return a bool.
   - All others → must return a scalar or `null`.
3. If the return type is wrong, `_doing_it_wrong()` fires and the built-in sanitizer runs as a fallback.
4. If `sanitize_callback` is not set, the built-in sanitizer runs directly.

Display-only types (`heading`, `message`) are skipped entirely during sanitization — they are never persisted.

### Built-in sanitizers

| Type                                 | Sanitizer                                                                        |
|--------------------------------------|----------------------------------------------------------------------------------|
| `text`, `textarea`, `password`, `hidden` | `sanitize_text_field()` / `sanitize_textarea_field()`                        |
| `email`                              | `sanitize_email()`                                                               |
| `url`, `image`                       | `esc_url_raw()`                                                                  |
| `number`                             | `intval()` when `attributes.step` is integer; otherwise `floatval()`             |
| `checkbox`, `toggle`                 | Cast to `bool`                                                                   |
| `select`, `radio`, `radio_image`, `buttonset` | Must match a `choices` key; otherwise falls back to `default`           |
| `color`                              | `sanitize_hex_color()`; falls back to `default` when invalid                     |
| `multicheck`                         | Filtered array of valid choice keys                                              |
| `code`                               | Stored verbatim (no sanitization)                                                |
| `editor`                             | `wp_kses_post()`                                                                 |

### Custom sanitization

```php
[
    'id'                => 'slug',
    'type'              => 'text',
    'label'             => __( 'Slug', 'textdomain' ),
    'sanitize_callback' => 'sanitize_title',
],
```

The callback receives the raw POST value and must return a value matching the field type's expected shape (see step 2 above).

## Conditional fields

A field can be conditionally shown or hidden based on another field's value. Pass either a single condition or an array of conditions; the parser normalises both shapes into an array of conditions.

```php
// Single condition (shorthand)
'conditions' => [ 'field' => 'enable_feature', 'value' => '1' ],

// Multiple conditions (AND)
'conditions' => [
    [ 'field' => 'enable_feature', 'value' => '1' ],
    [ 'field' => 'mode', 'value' => 'advanced', 'compare' => '!==' ],
],
```

Condition keys:

| Key       | Default | Values             | Description                                |
|-----------|---------|--------------------|--------------------------------------------|
| `field`   | —       | string             | ID of the source field.                    |
| `value`   | —       | scalar             | Value to compare against.                  |
| `compare` | `===`   | `===` \| `!==`     | Comparison operator.                       |

Evaluation runs client-side as a fixpoint loop: when a source field is itself hidden, dependent fields are also hidden. Chained dependencies resolve regardless of declaration order.

## Manager API

```php
use Nilambar\Optiz\Manager;
```

### Registration

```php
Manager::register( string $key, array $schema ): Manager
```

Registers a schema and hooks `admin_menu` and `admin_post_optiz_save_{key}`. Re-registering the same key triggers `_doing_it_wrong()` and returns the existing instance. A schema with validation errors logs the error and returns a Manager that does not register hooks.

### Instance lookup

```php
Manager::instance( string $key ): Manager
```

Throws `\RuntimeException` if no instance is registered for the key. Use `Manager::is_registered( $key )` to check first if needed.

### Reading values

```php
$value = Manager::instance( 'my_plugin' )->get( 'field_id', $fallback = null );
```

Resolution order: saved DB value → field `default` → `$fallback`. Values are cached per-request after the first read; call `clear_cache()` to force a re-read.

### Saving values

```php
Manager::instance( 'my_plugin' )->save( array $data ): bool
```

Sanitizes `$data` against the schema and writes via `update_option()`. Returns `false` only on a real DB error — `update_option()` also returns false when the value is unchanged, so the Manager checks `$wpdb->last_error` to distinguish.

### Page URL

```php
$url = Manager::instance( 'my_plugin' )->get_page_url();
```

Returns the full admin URL for the registered page.

## Form submission

The rendered form posts to `admin-post.php` with `action=optiz_save_{key}`. `handle_save()`:

1. Verifies the nonce (`optiz_save_{key}` / `optiz_nonce`).
2. Extracts `$_POST[$option_key]` as an associative array.
3. Calls `Validator::sanitize()` then `update_option()`.
4. Stores a transient notice (`optiz_notices_{key}`, 30s TTL).
5. Redirects back to the page, preserving the active tab via `?tab={current_tab}`.

Field inputs are named `{option_key}[{field_id}]`. `multicheck` uses `{option_key}[{field_id}][]`. `checkbox` and `toggle` emit a hidden `value="0"` companion immediately before the visible input so unchecked boxes still submit a value.
