# Changelog

## [1.0.0] - 2026-05-04

- Schema-driven settings page registration via `Manager::register()`.
- Version election mechanism: multiple plugins can each bundle the library; the highest version wins.
- 15 field types: `text`, `textarea`, `email`, `url`, `number`, `password`, `checkbox`, `toggle`, `select`, `radio`, `buttonset`, `multicheck`, `color`, `code`, `editor`.
- `layout` option for `radio` and `multicheck` fields (`vertical` | `horizontal`).
- `mode` option for `code` fields (`text` | `css` | `js`).
- Conditional field visibility via `depends_on` with chained dependency resolution.
- Custom sanitizer callbacks via `sanitize_callback` on any field.
- Tab-based settings page layout.
- `Manager::get()` with lazy option loading and per-request cache.
