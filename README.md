# Optiz

Lightweight schema-driven settings framework for WordPress. Plugins bundle this library to generate admin settings pages from a PHP array — no UI builder required.

## Requirements

- PHP 8.0+
- WordPress 6.0+

## Installation

```bash
composer require ernilambar/optiz
```

Include the entry point in your plugin bootstrap **before** `plugins_loaded`:

```php
require_once __DIR__ . '/vendor/ernilambar/optiz/init.php';
```

When multiple plugins bundle different versions, `init.php` automatically elects the highest version — only one copy runs.

## Quick start

Register the schema on `init` (or later). Registering earlier — e.g. on `plugins_loaded` — calls `__()` before WordPress is ready to load translations and triggers a `_doing_it_wrong` notice in WP 6.7+.

```php
add_action( 'init', function () {
    \Nilambar\Optiz\Manager::register( 'my_plugin', [
        'option_key' => 'my_plugin_options',
        'pages'      => [
            [
                'id'        => 'general',
                'title'     => __( 'My Plugin', 'textdomain' ),
                'menu_slug' => 'my-plugin-settings',
                'tabs'      => [
                    [
                        'id'     => 'general',
                        'label'  => __( 'General', 'textdomain' ),
                        'fields' => [
                            [
                                'id'      => 'api_key',
                                'type'    => 'text',
                                'label'   => __( 'API Key', 'textdomain' ),
                                'default' => '',
                            ],
                            [
                                'id'      => 'enable_feature',
                                'type'    => 'toggle',
                                'label'   => __( 'Enable Feature', 'textdomain' ),
                                'default' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ] );
} );
```

A registration holds one or more `pages`; every field ID must be unique across all of them. Read a saved value anywhere in the plugin — the lookup works regardless of which page owns the field:

```php
$value = \Nilambar\Optiz\Manager::instance( 'my_plugin' )->get( 'api_key' );
```

Get the admin URL for a specific page:

```php
$url = \Nilambar\Optiz\Manager::instance( 'my_plugin' )->get_page_url( 'general' );
```

## Documentation

See [docs/DOCS.md](docs/DOCS.md) for the full reference: schema, all field types, type-specific options, sanitization, conditional fields, and the Manager API.

## Copyright and License

This project is licensed under the [MIT](http://opensource.org/licenses/MIT).

2026 &copy; [Nilambar Sharma](https://www.nilambar.net).
