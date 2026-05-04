# Optiz: Feature Set & Technical Details

Optiz is a lightweight, schema-driven settings framework for WordPress developers. It follows a "no-bloat" philosophy, ensuring that you can build professional admin interfaces without adding unnecessary overhead to your plugins.

---

## 1. Core Architecture & Loading
*   **Version Election System:** An `init.php` gatekeeper that automatically detects and loads the latest version of the library found on the server. This prevents fatal errors when multiple plugins bundle the library and ensures the most modern code takes precedence.
*   **Dynamic Versioning:** Automatic version detection via `composer.json`. This eliminates the need for manual version string updates in PHP files, reducing human error during releases.
*   **Instance Registry:** Full support for multiple plugins on a single site. Each plugin maintains its own independent Optiz instance, allowing for unique configurations without cross-plugin interference.
*   **Single-Key Storage:** All settings for a specific instance are serialized into one single row in the `wp_options` table. This minimizes database bloat and simplifies data portability.

---

## 2. Developer API & UX
*   **Schema-Driven Configuration:** Define complex admin pages using a simple nested PHP array. This removes the need to write boilerplate HTML or interact directly with the verbose WordPress Settings API.
*   **Tabbed Interface Support:** Built-in logic for organizing fields into multiple tabs. This keeps the user interface clean and organized, even for plugins with dozens of settings.
*   **Smart Retrieval Helper:** A built-in `get()` method with internal static caching. It ensures that `get_option` is called only once per request, even if you retrieve multiple settings across different files.
*   **Global Instance Access:** Access settings from any file in your plugin (templates, hooks, or logic files) using the static `Manager::instance('key')` accessor.

---

## 3. Field & UI Logic
*   **Conditional (Dependent) Fields:** Client-side visibility toggling (show/hide) based on the values of other fields. This logic is defined entirely within the PHP schema and executed via a lightweight JS engine.
*   **Recursive Dependencies:** Advanced logic that handles "chained" visibility. For example, Field C can be set to show only when Field B is visible, which itself might depend on a toggle in Field A.
*   **Modular Renderer:** A dispatcher system that maps field types to specific HTML templates. This architecture makes it easy for developers to extend Optiz with custom field types (e.g., color pickers, maps, or code editors).

---

## 4. Security & Performance
*   **Automated Sanitization:** Built-in sanitization mapped to field types (e.g., `text` uses `sanitize_text_field`, `email` uses `sanitize_email`). This runs automatically before any data hits the database.
*   **Custom Sanitization Callbacks:** Support for custom PHP functions or class methods. This allows for specialized validation requirements, such as checking an API key against an external server before saving.
*   **Lazy Asset Loading:** Admin CSS and JavaScript are only enqueued on the specific settings page. Furthermore, scripts for advanced fields (like media uploaders) are only loaded if those fields are present in the schema.
*   **State Persistence:** Logic that ensures users are redirected back to the active tab after saving settings, preventing the frustration of being sent back to the "General" tab every time.

---

## 5. Compatibility & Standards
*   **PSR-4 Compliant:** Follows modern PHP standards for namespacing and directory structures, making it compatible with Composer autoloaders and modern development workflows.
*   **No-Bloat Philosophy:** Designed specifically for developers who want a powerful settings framework without the overhead of massive, commercial frameworks that add unnecessary megabytes to their plugin zip.
*   **CSS Scoping:** All admin styles are prefixed and scoped to the Optiz container to avoid clashing with WordPress core or other third-party plugin themes.
