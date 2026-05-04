# Optiz

Lightweight schema-driven settings framework for WordPress. Plugins bundle this library to generate admin settings pages from a PHP array — no UI builder required.

## Requirements

- PHP 7.4+
- WordPress (no other dependencies)

## Installation

Plugins bundle this library directly:

```bash
composer require ernilambar/optiz
```

Include the entry point in your plugin bootstrap **before** `plugins_loaded`:

```php
require_once __DIR__ . '/vendor/ernilambar/optiz/init.php';
```

When multiple plugins bundle different versions, `init.php` automatically elects the highest version — only one copy runs.

## Usage

### Register a settings page

```php
add_action( 'plugins_loaded', function () {
    $schema = [
        'option_key' => 'my_plugin_options',
        'page'       => [
            'title'     => 'My Plugin',
            'menu_slug' => 'my-plugin-settings',
        ],
        'tabs' => [
            [
                'id'     => 'general',
                'label'  => 'General',
                'fields' => [
                    [
                        'id'      => 'api_key',
                        'type'    => 'text',
                        'label'   => 'API Key',
                        'default' => '',
                    ],
                    [
                        'id'      => 'enable_feature',
                        'type'    => 'toggle',
                        'label'   => 'Enable Feature',
                        'default' => false,
                    ],
                ],
            ],
        ],
    ];

    \Nilambar\Optiz\Manager::register( 'my_plugin', $schema );
} );
```

### Read saved values

```php
$value = \Nilambar\Optiz\Manager::instance( 'my_plugin' )->get( 'api_key' );
```

`get()` returns the saved DB value, falling back to the field's `default`.

## Field types

| Type         | Description                                        |
|--------------|----------------------------------------------------|
| `text`       | Single-line text input                             |
| `textarea`   | Multi-line text input                              |
| `email`      | Email input                                        |
| `url`        | URL input                                          |
| `number`     | Numeric input                                      |
| `password`   | Password input (value not sanitized by default)    |
| `checkbox`   | Standard checkbox (boolean)                        |
| `toggle`     | iOS-style toggle switch (boolean)                  |
| `select`     | Dropdown (requires `choices`)                      |
| `radio`      | Radio buttons (requires `choices`)                 |
| `buttonset`  | Button-group selection (requires `choices`)        |
| `multicheck` | Multiple checkboxes (requires `choices`)           |
| `color`      | Color picker                                       |
| `code`       | Code editor (CSS, JS, or plain text)               |
| `editor`     | WordPress rich-text editor                         |

## Field options

Type-specific options are flat top-level keys on the field array — the same level as `id`, `type`, and `label`.

### `layout` — `radio`, `multicheck`

Controls whether items are stacked or inline. Accepted values: `vertical` (default), `horizontal`.

```php
[ 'id' => 'alignment', 'type' => 'radio', 'label' => 'Alignment', 'layout' => 'horizontal', 'choices' => [ 'left' => 'Left', 'center' => 'Center', 'right' => 'Right' ] ],
[ 'id' => 'features',  'type' => 'multicheck', 'label' => 'Features', 'layout' => 'horizontal', 'choices' => [ 'a' => 'Feature A', 'b' => 'Feature B' ] ],
```

### `mode` — `code`

Sets the syntax highlighting mode. Accepted values: `text` (default), `css`, `js`.

```php
[ 'id' => 'custom_css', 'type' => 'code', 'label' => 'Custom CSS', 'mode' => 'css' ],
```

## Conditional fields

Show or hide a field based on another field's value:

```php
[
    'id'         => 'api_endpoint',
    'type'       => 'text',
    'label'      => 'API Endpoint',
    'depends_on' => [ 'field' => 'enable_feature', 'value' => '1' ],
],
```

Multiple conditions can be passed as an array of condition arrays. Chained dependencies are resolved automatically.

## License

GPL-2.0-or-later
