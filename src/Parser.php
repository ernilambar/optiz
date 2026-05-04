<?php

namespace Nilambar\Optiz;

use WP_Error;

class Parser {

	private const FIELD_TYPES = [
		'text',
		'textarea',
		'email',
		'url',
		'number',
		'checkbox',
		'toggle',
		'select',
		'radio',
		'color',
		'password',
		'code',
		'multicheck',
		'editor',
		'buttonset',
		'image',
		'radio_image',
		'heading',
		'message',
		'hidden',
	];

	private const BOOLEAN_TYPES = [ 'checkbox', 'toggle' ];

	private const ARRAY_TYPES = [ 'multicheck' ];

	private const DISPLAY_ONLY_TYPES = [ 'heading', 'message' ];

	private const LABEL_OPTIONAL_TYPES = [ 'hidden' ];

	private const CODE_MODES = [ 'text', 'css', 'js' ];

	private const LAYOUT_TYPES = [ 'radio', 'radio_image', 'multicheck' ];

	private const TEXT_PLACEHOLDER_TYPES = [ 'text', 'email', 'url', 'number', 'password', 'textarea', 'code' ];

	private const ROWS_TYPES = [ 'textarea', 'code' ];

	private const SIDE_TEXT_TYPES = [ 'checkbox', 'toggle' ];

	public function parse( array $raw ) {
		if ( empty( $raw['option_key'] ) || ! is_string( $raw['option_key'] ) ) {
			return new WP_Error( 'missing_option_key', '"option_key" is required and must be a non-empty string.' );
		}

		if ( empty( $raw['page'] ) || ! is_array( $raw['page'] ) ) {
			return new WP_Error( 'missing_page', '"page" is required and must be an array.' );
		}

		if ( empty( $raw['page']['title'] ) ) {
			return new WP_Error( 'missing_page_title', '"page.title" is required.' );
		}

		if ( empty( $raw['page']['menu_slug'] ) ) {
			return new WP_Error( 'missing_page_menu_slug', '"page.menu_slug" is required.' );
		}

		if ( empty( $raw['tabs'] ) || ! is_array( $raw['tabs'] ) ) {
			return new WP_Error( 'missing_tabs', '"tabs" is required and must be a non-empty array.' );
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
			'menu_title'  => $page['menu_title'] ?? $page['title'],
			'menu_slug'   => $page['menu_slug'],
			'capability'  => $page['capability'] ?? 'manage_options',
			'icon_url'    => $page['icon_url'] ?? '',
			'position'    => $page['position'] ?? null,
			'parent_slug' => $page['parent_slug'] ?? '',
		];
	}

	private function normalize_tab( array $tab ) {
		if ( empty( $tab['id'] ) ) {
			return new WP_Error( 'missing_tab_id', 'Each tab must include an "id".' );
		}

		if ( ! array_key_exists( 'label', $tab ) ) {
			return new WP_Error(
				'missing_tab_label',
				sprintf( 'Tab "%s" must include a "label".', $tab['id'] )
			);
		}

		if ( empty( $tab['fields'] ) || ! is_array( $tab['fields'] ) ) {
			return new WP_Error(
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
			return new WP_Error( 'missing_field_id', 'Each field must include an "id".' );
		}

		if ( empty( $field['type'] ) ) {
			return new WP_Error(
				'missing_field_type',
				sprintf( 'Field "%s" must include a "type".', $field['id'] )
			);
		}

		if ( ! in_array( $field['type'], self::FIELD_TYPES, true ) ) {
			return new WP_Error(
				'invalid_field_type',
				sprintf( 'Field "%s" has unsupported type "%s".', $field['id'], $field['type'] )
			);
		}

		if ( ! array_key_exists( 'label', $field ) && ! in_array( $field['type'], self::LABEL_OPTIONAL_TYPES, true ) ) {
			return new WP_Error(
				'missing_field_label',
				sprintf( 'Field "%s" must include a "label".', $field['id'] )
			);
		}

		$is_bool  = in_array( $field['type'], self::BOOLEAN_TYPES, true );
		$is_array = in_array( $field['type'], self::ARRAY_TYPES, true );

		// Merge extra_attrs into attributes so downstream code has one key.
		$attrs = $field['attributes'] ?? [];
		if ( isset( $field['extra_attrs'] ) && is_array( $field['extra_attrs'] ) ) {
			$attrs = array_merge( $attrs, $field['extra_attrs'] );
		}

		$normalized = [
			'id'                => $field['id'],
			'type'              => $field['type'],
			'label'             => $field['label'] ?? '',
			'default'           => $field['default'] ?? ( $is_bool ? false : ( $is_array ? [] : '' ) ),
			'description'       => $field['description'] ?? '',
			'attributes'        => $attrs,
			'choices'           => $field['choices'] ?? [],
			'depends_on'        => $this->normalize_depends_on( $field['depends_on'] ?? [] ),
			'sanitize_callback' => $field['sanitize_callback'] ?? null,
			'class'             => $field['class'] ?? '',
		];

		if ( 'code' === $field['type'] ) {
			$mode               = $field['mode'] ?? 'text';
			$normalized['mode'] = in_array( $mode, self::CODE_MODES, true ) ? $mode : 'text';
		}

		if ( in_array( $field['type'], self::LAYOUT_TYPES, true ) ) {
			$layout               = $field['layout'] ?? 'vertical';
			$normalized['layout'] = in_array( $layout, [ 'vertical', 'horizontal' ], true ) ? $layout : 'vertical';
		}

		if ( in_array( $field['type'], self::TEXT_PLACEHOLDER_TYPES, true ) ) {
			$normalized['placeholder'] = $field['placeholder'] ?? '';
		}

		if ( in_array( $field['type'], self::ROWS_TYPES, true ) ) {
			$rows               = isset( $field['rows'] ) ? (int) $field['rows'] : 5;
			$normalized['rows'] = $rows > 0 ? $rows : 5;
		}

		if ( in_array( $field['type'], self::SIDE_TEXT_TYPES, true ) ) {
			$normalized['side_text'] = $field['side_text'] ?? '';
		}

		if ( 'select' === $field['type'] ) {
			$normalized['allow_null'] = ! empty( $field['allow_null'] );
		}

		return $normalized;
	}

	private function normalize_depends_on( $depends_on ): array {
		if ( empty( $depends_on ) ) {
			return [];
		}

		if ( isset( $depends_on['field'] ) ) {
			return [ $this->normalize_condition( $depends_on ) ];
		}

		return array_values(
			array_map( [ $this, 'normalize_condition' ], $depends_on )
		);
	}

	private function normalize_condition( array $cond ): array {
		$compare = isset( $cond['compare'] ) ? (string) $cond['compare'] : '===';
		return [
			'field'   => $cond['field'] ?? '',
			'value'   => $cond['value'] ?? '',
			'compare' => in_array( $compare, [ '===', '!==' ], true ) ? $compare : '===',
		];
	}
}
