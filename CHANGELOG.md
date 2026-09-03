# Changelog

## 1.0.1 - 2026-09-04
- Changed: conditional field visibility rebuilt on the `showmo` library
- Changed: hidden fields use the `hidden` attribute instead of inline `display:none`

## 1.0.0 - 2026-05-06
- Added: schema-driven settings page registration via `Manager::register()`
- Added: version election, so multiple plugins can each bundle the library and the highest version wins
- Added: 15 field types with tab-based page layout
- Added: conditional field visibility via `conditions`, with chained dependency resolution
- Added: `layout` option for `radio` and `multicheck` fields, `mode` option for `code` fields
- Added: custom sanitizers via `sanitize_callback` on any field
- Added: `Manager::get()` with lazy option loading and per-request cache
