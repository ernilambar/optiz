<?php

namespace Nilambar\Optiz;

class Manager {

	private static array $instances = [];

	private string $key;
	private Registry $registry;
	private ?array $option_cache = null;

	private function __construct( string $key ) {
		$this->key      = $key;
		$this->registry = new Registry();
	}

	public static function register( string $key, array $schema ): self {
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
			throw new \RuntimeException(
				sprintf( 'Optiz instance "%s" is not registered.', esc_html( $key ) )
			);
		}

		return self::$instances[ $key ];
	}

	public function get( string $field_id, $default = null ) {
		$schema = $this->registry->get_schema();

		if ( empty( $schema ) ) {
			return $default;
		}

		if ( null === $this->option_cache ) {
			$saved              = get_option( $schema['option_key'], [] );
			$this->option_cache = is_array( $saved ) ? $saved : [];
		}

		if ( array_key_exists( $field_id, $this->option_cache ) ) {
			return $this->option_cache[ $field_id ];
		}

		foreach ( $schema['tabs'] as $tab ) {
			foreach ( $tab['fields'] as $field ) {
				if ( $field['id'] === $field_id ) {
					return $field['default'];
				}
			}
		}

		return $default;
	}

	public function save( array $data ): bool {
		$schema = $this->registry->get_schema();

		if ( empty( $schema ) ) {
			return false;
		}

		$validator = new Validator();
		$clean     = $validator->sanitize( $data, $schema );

		$result             = (bool) update_option( $schema['option_key'], $clean );
		$this->option_cache = null;

		return $result;
	}

	public function register_page(): void {
		$schema = $this->registry->get_schema();

		if ( empty( $schema ) ) {
			return;
		}

		$page = $schema['page'];

		if ( ! empty( $page['parent_slug'] ) ) {
			add_submenu_page(
				$page['parent_slug'],
				$page['title'],
				$page['title'],
				$page['capability'],
				$page['menu_slug'],
				[ $this, 'render_page' ]
			);
		} else {
			add_menu_page(
				$page['title'],
				$page['title'],
				$page['capability'],
				$page['menu_slug'],
				[ $this, 'render_page' ],
				$page['icon_url'],
				$page['position']
			);
		}
	}

	public function render_page(): void {
		( new Renderer() )->render_page( $this->registry, $this->key );
	}

	public function handle_save(): void {
		$schema     = $this->registry->get_schema();
		$option_key = $schema['option_key'];

		check_admin_referer( 'optiz_save_' . $this->key, 'optiz_nonce' );

		$data   = isset( $_POST[ $option_key ] ) ? wp_unslash( (array) $_POST[ $option_key ] ) : [];
		$result = $this->save( $data );

		wp_safe_redirect(
			add_query_arg(
				[
					'page'    => $schema['page']['menu_slug'],
					'updated' => $result ? '1' : '0',
					'tab'     => isset( $_POST['current_tab'] ) ? sanitize_key( wp_unslash( $_POST['current_tab'] ) ) : '',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
