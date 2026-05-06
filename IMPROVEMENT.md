# Improvement Plan for `ernilambar/optiz`

This document lists the agreed changes to make the library more secure and future-proof.
Each item is numbered so implementation can be tracked and discussed individually.

---

## Security

### 1. Sanitize password fields before storage

**Decision:** Apply `sanitize_text_field()` to password fields only.

`Validator::apply_sanitizer()` currently casts password values with a plain `(string)` and stores them raw. Apply `sanitize_text_field()` to strip control characters and extra whitespace while preserving the key's useful characters. The `code` field continues to store raw strings (caller responsibility).

---

### 2. Strict-type comparison for choice-field validation

**Decision:** Enforce strict comparison; choice keys must always be strings.

Select, radio, radio_image, and buttonset validators must use `in_array($value, array_keys($field['choices']), true)` (strict). Choice keys are always treated as strings — if a plugin needs integer keys it must cast them to strings before passing the schema. `Parser` should enforce this by casting choice keys to `(string)` during normalisation and emitting a `WP_Error` (or `_doing_it_wrong()`) when it encounters a non-string key.

---

### 3. Guard against `sanitize_callback` returning a non-scalar

**Decision:** Implement as suggested.

After calling a developer-supplied `sanitize_callback`, verify the return type matches what the field type expects (e.g., scalar for text fields, array for multicheck). If the type is wrong, fall back to the built-in sanitizer and emit a `_doing_it_wrong()` notice so the developer is alerted without breaking the save silently.

---

## Compatibility & Future-Proofing

### 4. Replace `wp_localize_script()` with `wp_add_inline_script()`

**Decision:** Implement as suggested.

`wp_localize_script()` was deprecated in WordPress 6.3. Replace all calls in `Assets::enqueue()` with `wp_add_inline_script()`:

```php
wp_add_inline_script(
    'optiz',
    'var optizConditional = ' . wp_json_encode( $rules_data ) . ';',
    'before'
);
```

Apply the same pattern for `optizCodeEditor` data. This removes reliance on a deprecated API and avoids the forced global-variable assignment pattern.

---

### 5. Consolidate JS globals into a single `window.optiz` namespace

**Decision:** Implement as suggested.

Replace the two separate globals (`window.optizConditional`, `window.optizCodeEditor`) with a single top-level namespace to reduce collision risk with other plugins:

```js
window.optiz = window.optiz || {};
window.optiz.conditional = { rules: [...] };
window.optiz.codeEditor  = { settings: {...}, mimeMap: {...} };
```

Update the PHP side (in `Assets`) to output the new key names, and update all JS modules to read from `window.optiz.*`.

---

### 6. Raise minimum PHP version to 8.0

**Decision:** Implement as suggested.

WordPress 6.3+ dropped PHP 7.4 from its supported matrix; PHP 7.4 reached end-of-life in November 2022. Bumping `composer.json` to `>=8.0` unlocks union types, named arguments, match expressions, constructor property promotion, `str_contains()` / `str_starts_with()`, and the nullsafe operator `?->`. Update the PHPCS config and CI matrix to reflect the new minimum.

---

### 7. Float/integer support for number fields driven by step size

**Decision:** Use step size to determine whether to apply `intval()` or `floatval()`.

In `Validator::apply_sanitizer()`, read `$field['attributes']['step']` before sanitizing a `number` field:

- If `step` is absent or is a whole number (e.g., `1`, `"1"`), apply `intval()` — existing behaviour preserved.
- If `step` is a decimal value (e.g., `0.1`, `0.01`), apply `floatval()` instead.

This allows plugins to opt into decimal support by setting a step attribute without requiring a new field type.

---

### 8. Eliminate implicitly nullable parameters (PHP 8.4 deprecation)

**Decision:** Avoid implicitly nullable parameters across the codebase.

PHP 8.4 deprecated the `Type $param = null` shorthand. All occurrences must be changed to the explicit `?Type $param = null` form. Audit every method signature in `src/` for this pattern and fix proactively. Add a CI job (PHP 8.4) to catch regressions.

---

## Architecture & Testability

### 9. Introduce a formal PHPUnit test suite

**Decision:** Implement as suggested.

Add `phpunit/phpunit` and `brain/monkey` (or `wp-phpunit/wp-mock`) as dev dependencies. Write unit tests covering at minimum:

- `Parser::parse()` with valid and invalid schemas
- `Validator::sanitize()` for each field type (including the new step-based number logic)
- Conditions normalisation (flat → nested wrapping)
- Choice-field strict comparison behaviour
- Version-election logic in `init.php`

Wire the test suite into CI so it runs on PHP 8.0, 8.1, 8.2, 8.3, and 8.4.

---

### 10. Remove static state from `Manager` to improve testability

**Decision:** Implement as suggested.

`Manager::$instances` is a static array that cannot be reset between test cases. Add a `Manager::reset()` method (guarded by a constant or environment flag so it is unavailable in production) that clears `$instances`. Alternatively, refactor `Manager` to accept an injected `Registry` so tests can pass mock registries without relying on static state.

---

### 11. Replace `global $optiz_candidates` with a static class property

**Decision:** Implement as suggested.

The `global $optiz_candidates` variable in `init.php` is fragile — any code in the global scope can overwrite it. Move the candidate map to a namespaced static property:

```php
final class Bootstrap {
    private static array $candidates = [];
    public static function register( string $version, string $dir ): void { ... }
    public static function elect(): void { ... }
}
```

Because only one copy's autoloader ever loads, static properties on a class work identically across all registered copies within the same PHP process.

---

### 12. Declare jQuery as an explicit script dependency

**Decision:** Implement as suggested.

When color fields are present, `Assets::enqueue()` adds `wp-color-picker` as a dependency of `optiz.js`. However, `jquery` is not explicitly listed as a direct dependency. WordPress resolves transitive dependencies today, but relying on that implicit chain is fragile. Explicitly include `jquery` in the dependency array whenever `wp-color-picker` is added.

---

### 13. Extract field rendering into a dedicated `FieldRenderer` class

**Decision:** Add a separate class for rendering fields.

`Renderer.php` is ~762 lines, mixing page-layout logic with 20+ individual field renderers. Move all `render_*_field()` methods into a new `FieldRenderer` class. `Renderer` delegates field output to `FieldRenderer`, keeping page-level concerns (tabs, notices, form wrapper, nonce) separate from per-field HTML generation. This makes adding new field types a smaller, more contained change.

---

## Accessibility

### 14. Add ARIA attributes to toggle fields

**Decision:** Implement as suggested.

Toggle fields render a hidden checkbox styled as an iOS switch but currently lack semantic accessibility markers:

- Add `role="switch"` to the checkbox `<input>`.
- Ensure the `<label for="...">` `for` attribute matches the `<input id="...">` so the label is programmatically associated.
- Mirror the `checked` state in `aria-checked` (can be kept in sync via the existing JS or a CSS-driven attribute).

This brings the toggle control to WCAG 2.1 Level AA compliance for form controls.

---

## Developer Experience

### 15. Guard global constants with `defined()` checks

**Decision:** Add `if ( ! defined( ... ) )` guards; do not rename the constants.

`OPTIZ_LOADED_VERSION`, `OPTIZ_DIR`, and `OPTIZ_URL` are currently defined unconditionally. If a host plugin or another copy of the library has already defined any of these constants, the `define()` call silently wins or loses depending on load order. Wrap each `define()` with `if ( ! defined( 'CONSTANT_NAME' ) )` to prevent fatal errors from redefinition, and document that these constants must not be pre-defined by host plugins.
