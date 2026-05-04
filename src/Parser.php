<?php

namespace Nilambar\Optiz;

class Parser {

	private const FIELD_TYPES = [
		'text', 'textarea', 'email', 'url', 'number',
		'checkbox', 'toggle', 'select', 'radio', 'color',
		'password', 'code', 'multicheck', 'editor', 'buttonset',
	];

	private const BOOLEAN_TYPES = [ 'checkbox', 'toggle' ];

	private const ARRAY_TYPES = [ 'multicheck' ];

	private const CODE_MODES = [ 'text', 'css', 'js' ];

	public function parse( array $raw ) {
		if ( empty( $raw['option_key'] ) || ! is_string( $raw['option_key'] ) ) {
			return new \WP_Error( 'missing_option_key', '"option_key" is required and must be a non-empty string.' );
		}

		if ( empty( $raw['page'] ) || ! is_array( $raw['page'] ) ) {
			return new \WP_Error( 'missing_page', '"page" is required and must be an array.' );
		}

		if ( empty( $raw['page']['title'] ) ) {
			return new \WP_Error( 'missing_page_title', '"page.title" is required.' );
		}

		if ( empty( $raw['page']['menu_slug'] ) ) {
			return new \WP_Error( 'missing_page_menu_slug', '"page.menu_slug" is required.' );
		}

		if ( empty( $raw['tabs'] ) || ! is_array( $raw['tabs'] ) ) {
			return new \WP_Error( 'missing_tabs', '"tabs" is required and must be a non-empty array.' );
		}

		$schema = [
			'option_key' => sanitize_key( $raw['option_key'] ),
			'page'       => $this->normalize_page( $raw['page'] ),
			'tabs'       => [],
		];

		foreach ( $raw['tabs'] as $tab ) {
			$result = $this->normalize_tab( $tab );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$schema['tabs'][] = $result;
		}

		return $schema;
	}

	private function normalize_page( array $page ): array {
		return [
			'title'       => $page['title'],
			'menu_slug'   => $page['menu_slug'],
			'capability'  => $page['capability']  ?? 'manage_options',
			'icon_url'    => $page['icon_url']     ?? '',
			'position'    => $page['position']     ?? null,
			'parent_slug' => $page['parent_slug']  ?? '',
		];
	}

	private function normalize_tab( array $tab ) {
		if ( empty( $tab['id'] ) ) {
			return new \WP_Error( 'missing_tab_id', 'Each tab must include an "id".' );
		}

		if ( ! array_key_exists( 'label', $tab ) ) {
			return new \WP_Error(
				'missing_tab_label',
				sprintf( 'Tab "%s" must include a "label".', $tab['id'] )
			);
		}

		if ( empty( $tab['fields'] ) || ! is_array( $tab['fields'] ) ) {
			return new \WP_Error(
				'missing_tab_fields',
				sprintf( 'Tab "%s" must include a "fields" array.', $tab['id'] )
			);
		}

		$fields = [];
		foreach ( $tab['fields'] as $field ) {
			$result = $this->normalize_field( $field );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$fields[] = $result;
		}

		return [
			'id'     => $tab['id'],
			'label'  => $tab['label'],
			'fields' => $fields,
		];
	}

	private function normalize_field( array $field ) {
		if ( empty( $field['id'] ) ) {
			return new \WP_Error( 'missing_field_id', 'Each field must include an "id".' );
		}

		if ( empty( $field['type'] ) ) {
			return new \WP_Error(
				'missing_field_type',
				sprintf( 'Field "%s" must include a "type".', $field['id'] )
			);
		}

		if ( ! in_array( $field['type'], self::FIELD_TYPES, true ) ) {
			return new \WP_Error(
				'invalid_field_type',
				sprintf( 'Field "%s" has unsupported type "%s".', $field['id'], $field['type'] )
			);
		}

		if ( ! array_key_exists( 'label', $field ) ) {
			return new \WP_Error(
				'missing_field_label',
				sprintf( 'Field "%s" must include a "label".', $field['id'] )
			);
		}

		$is_bool  = in_array( $field['type'], self::BOOLEAN_TYPES, true );
		$is_array = in_array( $field['type'], self::ARRAY_TYPES, true );

		$normalized = [
			'id'                => $field['id'],
			'type'              => $field['type'],
			'label'             => $field['label'],
			'default'           => $field['default']           ?? ( $is_bool ? false : ( $is_array ? [] : '' ) ),
			'description'       => $field['description']       ?? '',
			'attributes'        => $field['attributes']        ?? [],
			'choices'           => $field['choices']           ?? [],
			'depends_on'        => $this->normalize_depends_on( $field['depends_on'] ?? [] ),
			'sanitize_callback' => $field['sanitize_callback'] ?? null,
		];

		if ( 'code' === $field['type'] ) {
			$mode                = $field['mode'] ?? 'text';
			$normalized['mode'] = in_array( $mode, self::CODE_MODES, true ) ? $mode : 'text';
		}

		return $normalized;
	}

	private function normalize_depends_on( $depends_on ): array {
		if ( empty( $depends_on ) ) {
			return [];
		}

		if ( isset( $depends_on['field'] ) ) {
			return [ $depends_on ];
		}

		return array_values( $depends_on );
	}
}
