<?php

namespace Nilambar\Optiz;

use RuntimeException;

class Manager {

	private static array $instances = [];

	private string $key;
	private Registry $registry;
	private ?array $option_cache = null;
	private ?string $page_hook   = null;

	private function __construct( string $key ) {
		$this->key      = $key;
		$this->registry = new Registry();
	}

	public static function is_registered( string $key ): bool {
		return isset( self::$instances[ $key ] );
	}

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

	public static function instance( string $key ): self {
		if ( ! isset( self::$instances[ $key ] ) ) {
			throw new RuntimeException(
				sprintf( 'Optiz instance "%s" is not registered.', esc_html( $key ) )
			);
		}

		return self::$instances[ $key ];
	}

	public function get( string $field_id, $fallback = null ) {
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

	public function clear_cache(): void {
		$this->option_cache = null;
	}

	public function save( array $data ): bool {
		$schema = $this->registry->get_schema();

		if ( empty( $schema ) ) {
			return false;
		}

		$validator = new Validator();
		$clean     = $validator->sanitize( $data, $schema );

		global $wpdb;
		$wpdb->last_error = '';

		update_option( $schema['option_key'], $clean );
		$this->option_cache = null;

		// update_option returns false both on DB failure and when the value is
		// unchanged. Use $wpdb->last_error to distinguish: a no-op update
		// produces no DB error, a genuine failure does.
		return empty( $wpdb->last_error );
	}

	public function register_page(): void {
		$schema = $this->registry->get_schema();

		if ( empty( $schema ) ) {
			return;
		}

		$page = $schema['page'];

		if ( ! empty( $page['parent_slug'] ) ) {
			$this->page_hook = add_submenu_page(
				$page['parent_slug'],
				$page['title'],
				$page['menu_title'],
				$page['capability'],
				$page['menu_slug'],
				[ $this, 'render_page' ]
			);
		} else {
			$this->page_hook = add_menu_page(
				$page['title'],
				$page['menu_title'],
				$page['capability'],
				$page['menu_slug'],
				[ $this, 'render_page' ],
				$page['icon_url'],
				$page['position']
			);
		}

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function enqueue_assets( string $hook ): void {
		if ( null === $this->page_hook ) {
			return;
		}

		( new Assets() )->enqueue( $hook, $this->page_hook, $this->registry->get_schema() );
	}

	public function render_page(): void {
		( new Renderer() )->render_page( $this->registry, $this->key );
	}

	public function get_page_url(): string {
		$schema = $this->registry->get_schema();

		if ( empty( $schema ) ) {
			return '';
		}

		$page   = $schema['page'];
		$parent = ! empty( $page['parent_slug'] ) ? $page['parent_slug'] : 'admin.php';

		return add_query_arg( [ 'page' => $page['menu_slug'] ], admin_url( $parent ) );
	}

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
