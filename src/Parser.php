<?php

namespace Nilambar\Optiz;

class Parser {

	private const FIELD_TYPES = [
		'text', 'textarea', 'email', 'url', 'number',
		'checkbox', 'toggle', 'select', 'radio', 'color',
	];

	private const BOOLEAN_TYPES = [ 'checkbox', 'toggle' ];

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

		if ( empty( $raw['tabs'] ) && empty( $raw['fields'] ) ) {
			return new \WP_Error( 'missing_fields', 'Schema must include "tabs" or "fields".' );
		}

		$schema = [
			'option_key' => sanitize_key( $raw['option_key'] ),
			'page'       => $this->normalize_page( $raw['page'] ),
			'tabs'       => [],
		];

		$raw_tabs = ! empty( $raw['tabs'] )
			? $raw['tabs']
			: [ [ 'id' => 'default', 'label' => '', 'fields' => $raw['fields'] ] ];

		foreach ( $raw_tabs as $tab ) {
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

		$is_bool = in_array( $field['type'], self::BOOLEAN_TYPES, true );

		return [
			'id'                => $field['id'],
			'type'              => $field['type'],
			'label'             => $field['label'],
			'default'           => $field['default']           ?? ( $is_bool ? false : '' ),
			'description'       => $field['description']       ?? '',
			'attributes'        => $field['attributes']        ?? [],
			'choices'           => $field['choices']           ?? [],
			'depends_on'        => $this->normalize_depends_on( $field['depends_on'] ?? [] ),
			'sanitize_callback' => $field['sanitize_callback'] ?? null,
		];
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
