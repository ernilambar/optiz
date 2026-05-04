# Optiz — Implementation Plan

Optiz is a lightweight, schema-driven WordPress settings framework. This plan organizes the build into sequential phases, each independently testable before moving to the next.

---

## Project Structure

```
optiz/
├── src/
│   ├── Manager.php       # Singleton/instance registry + get() helper
│   ├── Registry.php      # Data store for parsed schema & raw options
│   ├── Parser.php        # Schema validation & default merging
│   ├── Renderer.php      # Tab/field HTML dispatcher
│   ├── Validator.php     # Sanitization + custom callbacks
│   └── Assets.php        # Conditional CSS/JS enqueue
├── assets/
│   ├── css/
│   │   └── optiz-admin.css
│   └── js/
│       └── conditional.js  # Client-side show/hide dependency engine
├── init.php              # Version election gatekeeper
└── composer.json         # PSR-4 autoload + version source of truth
```

---

## Phase 1 — Scaffold & Bootstrapping

Goal: get a loadable, autoloaded package with no functional code yet.

### 1.1 `composer.json`
- Define package name, description, license, minimum PHP version: **7.4**.
- Set `version` field (this is the single source of truth).
- Configure PSR-4 autoload: `Nilambar\\Optiz\\` → `src/`.

### 1.2 `init.php` — Version Election Gatekeeper
- Define a global constant `OPTIZ_LOADED_VERSION`.
- On each include, compare the bundled version against any already-loaded version.
- Only proceed (require the autoloader / define classes) if this version is newer.
- Pattern: use `version_compare()` to elect the winner; late-static binding keeps the namespace clean.
- End result: the highest-version copy wins, no matter how many plugins bundle the library.

### 1.3 Directory scaffolding
- Create all `src/` stubs (empty classes with namespace + class declaration only).
- Create `assets/css/` and `assets/js/` directories with placeholder files.

**Deliverable:** `composer install` works, PSR-4 autoloads all classes, `init.php` can be `require`-d safely multiple times.

---

## Phase 2 — Core Data Layer

Goal: parse a developer-supplied schema array into a validated, default-enriched structure stored in `Registry`.

### 2.1 `Registry.php`
- Holds two pieces of state per instance: the normalized schema and the raw saved option value.
- Provides `set_schema(array $schema)`, `get_schema()`, `set_option(array $data)`, `get_option()`.
- No WordPress dependency in this class; plain PHP.

### 2.2 `Parser.php`
- `parse(array $rawSchema): array|WP_Error` — validates required top-level keys (`option_key`, `tabs` or `fields`); returns a `WP_Error` (never throws) on missing or invalid keys.
- Walks every field definition and merges developer-supplied defaults so downstream code never has to null-check optional keys.
- Normalizes optional per-field keys: `description` (help text rendered below the input) and `attributes` (arbitrary HTML attributes such as `placeholder`, `min`, `max`, passed through to the rendered element).
- Normalizes `tabs` into a flat list of sections, each containing a fields array.
- Normalizes `depends_on` rules into a canonical shape for the JS engine to consume. A field may declare multiple conditions (all must be satisfied — AND logic).

**Schema shape (reference):**
```php
[
  'option_key' => 'my_plugin_options',
  'page'       => [ 'title' => '...', 'menu_slug' => '...' ],
  'tabs'       => [
    [
      'id'     => 'general',
      'label'  => 'General',
      'fields' => [
        [
          'id'          => 'api_key',
          'type'        => 'text',
          'label'       => '...',
          'default'     => '',
          'description' => 'Your API key from the dashboard.',
          'attributes'  => [ 'placeholder' => 'sk-...' ],
        ],
        [
          'id'      => 'mode',
          'type'    => 'select',
          'label'   => '...',
          'choices' => [ 'simple' => 'Simple', 'advanced' => 'Advanced' ],
          'default' => 'simple',
        ],
        [
          'id'         => 'detail',
          'type'       => 'textarea',
          'label'      => '...',
          'depends_on' => [
            [ 'field' => 'mode',    'value' => 'advanced' ],
            [ 'field' => 'enabled', 'value' => true ],
          ],
        ],
      ],
    ],
  ],
]
```

**Deliverable:** Unit-testable `Parser` that rejects bad schemas and enriches valid ones.

---

## Phase 3 — Manager & Instance Registry

Goal: provide the public API that plugin developers actually call.

### 3.1 `Manager.php`
- Static `$instances` map: `string $key → Manager`.
- `Manager::register(string $key, array $schema): Manager` — creates/returns an instance, passes schema through `Parser`, stores in `Registry`.
- `Manager::instance(string $key): Manager` — returns existing instance; throws if key not registered.
- `get(string $field_id, mixed $default = null): mixed` — lazy-loads option from `get_option()` on first call (static cache). Return priority: saved DB value → schema-defined `default` → `$default` argument.
- `save(array $data): bool` — runs data through `Validator`, then calls `update_option()`.
- Hooks into `admin_menu` to register the settings page (delegates rendering to `Renderer`).
- Hooks into `admin_post_{action}` or `admin_init` to handle form submission (delegates to `Validator` + `save()`).

**Deliverable:** A plugin can call `Manager::register('my_plugin', $schema)` in its bootstrap and then `Manager::instance('my_plugin')->get('api_key')` anywhere. Field identifiers throughout are snake_case strings (e.g., `'api_key'`, `'send_email'`).

---

## Phase 4 — Validator & Sanitization

Goal: clean every value before it touches the database.

### 4.1 `Validator.php`
- `sanitize(array $rawPost, array $schema): array` — iterates fields, applies the correct sanitizer per `type`.
- Built-in type → sanitizer map:

  | type       | WordPress function / result                  |
  |------------|----------------------------------------------|
  | text       | `sanitize_text_field`                        |
  | textarea   | `sanitize_textarea_field`                    |
  | email      | `sanitize_email`                             |
  | url        | `esc_url_raw`                                |
  | number     | `intval`                                     |
  | checkbox   | cast to `true` / `false` (PHP bool)          |
  | toggle     | cast to `true` / `false` (PHP bool)          |
  | select     | whitelist against `choices`                  |
  | radio      | whitelist against `choices`                  |
  | color      | validate hex color string (`#rgb` / `#rrggbb`) |

- If a field defines `sanitize_callback`, invoke it instead of the default — accepts any PHP callable.
- Unknown/unlisted types fall back to `sanitize_text_field`.
- `checkbox` and `toggle` fields are stored as PHP booleans (`true`/`false`), not `"0"`/`"1"` strings. They are visually distinct (toggle renders as an iOS-style switch; checkbox renders as a traditional input) but behave identically.
- `color` field accepts only hex values (`#rgb` or `#rrggbb`); rendered as a native `<input type="color">`. Invalid values are rejected and the field's schema `default` is used instead.
- `number` field uses `intval` (integer only for now).
- Returns a flat associative array `[ field_id => sanitized_value ]` ready for `update_option()`.

**Deliverable:** `Validator::sanitize()` is independently testable with no WordPress dependency (mock the WP functions if needed).

---

## Phase 5 — Renderer & Admin UI

Goal: generate the settings page HTML from the normalized schema.

### 5.1 `Renderer.php`
- `render_page(Registry $registry): void` — outputs the full settings page wrapper, nonce, form action, active-tab logic, tab navigation, and WordPress admin notices.
- `render_tabs(array $tabs, string $active_tab): void` — outputs tab nav links. **Tab navigation is hidden entirely when the schema contains only one tab.**
- `render_fields(array $fields, array $saved_values): void` — loops fields and dispatches to the appropriate `render_*_field` method.
- Per-type render methods (private):
  - `render_text_field`, `render_textarea_field`, `render_select_field`, `render_checkbox_field`, `render_radio_field`, `render_number_field`, `render_email_field`, `render_url_field`, `render_color_field`, `render_toggle_field`.
- Every field supports optional `description` (rendered as `<p class="description">` below the input) and `attributes` (key-value pairs applied as HTML attributes on the input element, e.g. `placeholder`, `min`, `max`).
- Each field wrapper includes `data-field-id` and `data-depends-on` attributes when a `depends_on` rule is present. Multiple conditions are serialized as a JSON array for the JS engine.
- Active-tab persistence: read `$_GET['tab']`; default to first tab; output a hidden `current_tab` input so after-save redirect can restore it.
- After a successful save, display a standard WordPress `.notice.notice-success` admin notice. On failure, display `.notice.notice-error`.
- All output is properly escaped with `esc_html`, `esc_attr`, `esc_url`.

### 5.2 CSS scoping
- All selectors prefixed `.optiz-wrap` to avoid collisions.
- Minimal, functional styles: tab nav, field rows, description text.

**Deliverable:** A working settings page renders from any valid schema without touching HTML.

---

## Phase 6 — Assets (Conditional Enqueue)

Goal: load CSS/JS only when needed, never on unrelated admin pages.

### 6.1 `Assets.php`
- `enqueue(string $pageSlug, array $schema): void` — hooked to `admin_enqueue_scripts`.
- Compares `$hook` (passed by WP) against the registered page slug; bails early if not a match.
- Always enqueues `optiz-admin.css` and `conditional.js` on the settings page.
- Passes the dependency config to `conditional.js` via `wp_localize_script` so no inline PHP is embedded in HTML.

### 6.2 `conditional.js`
- Reads the `optizConditional` localized object.
- On DOM ready and on `change` events: evaluates each rule, toggles `.optiz-field-wrap` visibility.
- Multiple conditions per field are evaluated with AND logic — all must be true for the field to be visible.
- Handles chained/recursive dependencies: re-evaluate all rules whenever any field changes.

**Deliverable:** No unnecessary scripts load; dependency toggling works client-side.

---

## Phase 7 — Integration & Polish

### 7.1 Wiring everything together
- `Manager` passes the right objects to `Renderer`, `Validator`, and `Assets` — no class should reach outside its own responsibility.
- Ensure `init.php` election logic is exercised with two dummy version numbers.

### 7.2 Single-key storage verification
- Confirm all field values collapse into one `wp_options` row under `option_key`.
- Add `get()` static cache test: `get_option` called exactly once per request regardless of how many fields are retrieved.

### 7.3 Edge cases
- Schema with no tabs (flat field list): Parser normalizes to a single implicit tab.
- `depends_on` on a field that itself has `depends_on` (recursive chain): JS engine handles correctly.
- Empty or missing saved option: `get()` falls back to schema `default` values.
- Multisite: use `get_option` / `update_option` (not `get_site_option`) unless the developer opts in.

### 7.4 CSS final pass
- Verify no style leaks outside `.optiz-wrap`.
- Test in WP admin with Twenty Twenty-Five theme.

---

## Implementation Order Summary

| Phase | Files touched                              | Depends on  |
|-------|--------------------------------------------|-------------|
| 1     | `composer.json`, `init.php`, stubs         | —           |
| 2     | `Registry.php`, `Parser.php`               | 1           |
| 3     | `Manager.php`                              | 2           |
| 4     | `Validator.php`                            | 2           |
| 5     | `Renderer.php`, `optiz-admin.css`          | 2, 3        |
| 6     | `Assets.php`, `conditional.js`             | 3, 5        |
| 7     | All                                        | 1–6         |

---

## Agreed Decisions

| # | Topic | Decision |
|---|-------|----------|
| 1 | Minimum PHP | 7.4 |
| 2 | Parser errors | Return `WP_Error`; never throw |
| 3 | `toggle` vs `checkbox` | Same boolean value; differ in rendering only (toggle = iOS switch) |
| 4 | `number` field | `intval()` only (no float support for now) |
| 5 | `color` field | Hex only (`#rgb` / `#rrggbb`); native `<input type="color">` |
| 6 | `depends_on` multi-condition | Supported; AND logic (all conditions must be true) |
| 7 | `get()` fallback order | Saved DB value → schema `default` → `$default` argument |
| 8 | After-save notices | Standard WP admin notice classes (`.notice-success` / `.notice-error`) |
| 9 | Reset to defaults | Not supported |
| 10 | Field extras | `description` (help text) and `attributes` (e.g. `placeholder`) are supported |

---

## Coding Standards

- PHP: tabs for indentation (per `.editorconfig`), PSR-4 namespacing under `Nilambar\Optiz\`.
- No external PHP dependencies beyond WordPress core.
- All public-facing strings run through `esc_html__` / `esc_attr__` for i18n readiness.
- No inline styles or scripts; everything goes through the enqueue system.
- JavaScript: vanilla JS only, no jQuery dependency (WP ships jQuery but we avoid coupling to it).
