<?php
/**
 * Manager class.
 *
 * @package Optiz
 */

declare(strict_types=1);

namespace Nilambar\Optiz;

use RuntimeException;

/**
 * Orchestrates schema registration, option retrieval, and admin-page lifecycle.
 *
 * @since 1.0.0
 */
class Manager {

	/**
	 * Registered Manager instances keyed by plugin key.
	 *
	 * @since 1.0.0
	 *
	 * @var self[]
	 */
	private static array $instances = [];

	/**
	 * Plugin key for this instance.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private string $key;

	/**
	 * Schema and defaults registry.
	 *
	 * @since 1.0.0
	 *
	 * @var Registry
	 */
	private Registry $registry;

	/**
	 * Cached option values from the database, or null when not yet loaded.
	 *
	 * @since 1.0.0
	 *
	 * @var array|null
	 */
	private ?array $option_cache = null;

	/**
	 * WP admin page hook suffix returned by add_menu_page / add_submenu_page.
	 *
	 * @since 1.0.0
	 *
	 * @var string|null
	 */
	private ?string $page_hook = null;

	/**
	 * Constructor — use register() to create instances.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Plugin key.
	 */
	private function __construct( string $key ) {
		$this->key      = $key;
		$this->registry = new Registry();
	}

	/**
	 * Returns whether a Manager instance has been registered for the given key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Plugin key.
	 * @return bool True if registered.
	 */
	public static function is_registered( string $key ): bool {
		return isset( self::$instances[ $key ] );
	}

	/**
	 * Creates and registers a Manager instance for the given key and schema.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key    Plugin key.
	 * @param array  $schema Raw developer-supplied schema.
	 * @return self The registered Manager instance.
	 */
	public static function register( string $key, array $schema ): self {
		if ( isset( self::$instances[ $key ] ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf( 'Optiz instance "%s" is already registered.', esc_html( $key ) ),
				'1.0.0'
			);
			return self::$instances[ $key ];
		}

		$parser = new Parser();
		$parsed = $parser->parse( $schema );

		$instance = new self( $key );

		if ( is_wp_error( $parsed ) ) {
			_doing_it_wrong( __METHOD__, esc_html( $parsed->get_error_message() ), '1.0.0' );
		} else {
			$instance->registry->set_schema( $parsed );
			add_action( 'admin_menu', [ $instance, 'register_page' ] );
			add_action( 'admin_post_optiz_save_' . $key, [ $instance, 'handle_save' ] );
		}

		self::$instances[ $key ] = $instance;

		return $instance;
	}

	/**
	 * Returns the registered Manager instance for the given key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Plugin key.
	 * @return self The registered Manager instance.
	 * @throws RuntimeException If no instance is registered for the key.
	 */
	public static function instance( string $key ): self {
		if ( ! isset( self::$instances[ $key ] ) ) {
			throw new RuntimeException(
				sprintf( 'Optiz instance "%s" is not registered.', esc_html( $key ) )
			);
		}

		return self::$instances[ $key ];
	}

	/**
	 * Clears all registered instances. Only available when OPTIZ_TESTS is defined.
	 *
	 * @since 1.0.0
	 */
	public static function reset(): void {
		if ( ! defined( 'OPTIZ_TESTS' ) ) {
			return;
		}
		self::$instances = [];
	}

	/**
	 * Returns the saved value for a field, falling back to its schema default then to $fallback.
	 *
	 * @since 1.0.0
	 *
	 * @param string $field_id Field ID.
	 * @param mixed  $fallback Value to return when no saved value or default exists.
	 * @return mixed Field value.
	 */
	public function get( string $field_id, mixed $fallback = null ): mixed {
		$schema = $this->registry->get_schema();

		if ( empty( $schema ) ) {
			return $fallback;
		}

		if ( null === $this->option_cache ) {
			$saved              = get_option( $schema['option_key'], [] );
			$this->option_cache = is_array( $saved ) ? $saved : [];
		}

		if ( array_key_exists( $field_id, $this->option_cache ) ) {
			return $this->option_cache[ $field_id ];
		}

		$defaults = $this->registry->get_defaults();
		return array_key_exists( $field_id, $defaults ) ? $defaults[ $field_id ] : $fallback;
	}

	/**
	 * Invalidates the in-memory option cache, forcing a fresh DB read on next get().
	 *
	 * @since 1.0.0
	 */
	public function clear_cache(): void {
		$this->option_cache = null;
	}

	/**
	 * Sanitizes and persists option data to the database.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Raw field values keyed by field ID.
	 * @return bool True on success, false on DB error.
	 */
	public function save( array $data ): bool {
		$schema = $this->registry->get_schema();

		if ( empty( $schema ) ) {
			return false;
		}

		$existing = get_option( $schema['option_key'], [] );
		if ( ! is_array( $existing ) ) {
			$existing = [];
		}

		$validator = new Validator();
		$clean     = $validator->sanitize( $data, $schema, $existing );

		global $wpdb;
		$wpdb->last_error = '';

		update_option( $schema['option_key'], $clean );
		$this->option_cache = null;

		// update_option returns false both on DB failure and when the value is
		// unchanged. Use $wpdb->last_error to distinguish: a no-op update
		// produces no DB error, a genuine failure does.
		return empty( $wpdb->last_error );
	}

	/**
	 * Registers the admin settings page via add_menu_page or add_submenu_page.
	 *
	 * @since 1.0.0
	 */
	public function register_page(): void {
		$schema = $this->registry->get_schema();

		if ( empty( $schema ) ) {
			return;
		}

		$page = $schema['page'];

		if ( ! empty( $page['parent_slug'] ) ) {
			$hook            = add_submenu_page(
				$page['parent_slug'],
				$page['title'],
				$page['menu_title'],
				$page['capability'],
				$page['menu_slug'],
				[ $this, 'render_page' ]
			);
			$this->page_hook = $hook ? $hook : null;
		} else {
			$hook            = add_menu_page(
				$page['title'],
				$page['menu_title'],
				$page['capability'],
				$page['menu_slug'],
				[ $this, 'render_page' ],
				$page['icon_url'],
				$page['position']
			);
			$this->page_hook = $hook ? $hook : null;
		}

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Enqueues assets for the settings page; called on admin_enqueue_scripts.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( null === $this->page_hook ) {
			return;
		}

		( new Assets() )->enqueue( $hook, $this->page_hook, $this->registry->get_schema() );
	}

	/**
	 * Outputs the HTML for the admin settings page.
	 *
	 * @since 1.0.0
	 */
	public function render_page(): void {
		( new Renderer() )->render_page( $this->registry, $this->key );
	}

	/**
	 * Returns the full admin URL for the settings page.
	 *
	 * @since 1.0.0
	 *
	 * @return string Admin URL, or empty string if no schema is loaded.
	 */
	public function get_page_url(): string {
		$schema = $this->registry->get_schema();

		if ( empty( $schema ) ) {
			return '';
		}

		$page   = $schema['page'];
		$parent = 'admin.php';
		if ( ! empty( $page['parent_slug'] ) && '.php' === substr( $page['parent_slug'], -4 ) ) {
			$parent = $page['parent_slug'];
		}

		return add_query_arg( [ 'page' => $page['menu_slug'] ], admin_url( $parent ) );
	}

	/**
	 * Handles the form POST: verifies nonce, sanitizes, saves, and redirects.
	 *
	 * @since 1.0.0
	 */
	public function handle_save(): void {
		$schema     = $this->registry->get_schema();
		$option_key = $schema['option_key'];

		check_admin_referer( 'optiz_save_' . $this->key, 'optiz_nonce' );

		$data   = isset( $_POST[ $option_key ] ) ? wp_unslash( (array) $_POST[ $option_key ] ) : [];
		$result = $this->save( $data );

		$notice = $result
			? [
				'type'    => 'success',
				'message' => __( 'Settings saved.' ),
			]
			: [
				'type'    => 'error',
				'message' => __( 'Settings save failed.' ),
			];

		set_transient( 'optiz_notices_' . $this->key, $notice, 30 );

		$args = [];
		if ( count( $schema['tabs'] ) > 1 && isset( $_POST['current_tab'] ) ) {
			$args['tab'] = sanitize_key( wp_unslash( $_POST['current_tab'] ) );
		}

		wp_safe_redirect( add_query_arg( $args, $this->get_page_url() ) );
		exit;
	}
}
