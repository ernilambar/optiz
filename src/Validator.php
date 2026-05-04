<?php

namespace Nilambar\Optiz;

class Validator {

	public function sanitize( array $raw, array $schema ): array {
		$clean = [];

		foreach ( $schema['tabs'] as $tab ) {
			foreach ( $tab['fields'] as $field ) {
				$id           = $field['id'];
				$value        = $raw[ $id ] ?? null;
				$clean[ $id ] = $this->sanitize_field( $field, $value );
			}
		}

		return $clean;
	}

	private function sanitize_field( array $field, $value ) {
		if ( null !== $field['sanitize_callback'] && is_callable( $field['sanitize_callback'] ) ) {
			return call_user_func( $field['sanitize_callback'], $value );
		}

		return $this->apply_sanitizer( $field, $value );
	}

	private function apply_sanitizer( array $field, $value ) {
		switch ( $field['type'] ) {
			case 'text':
				return sanitize_text_field( (string) $value );

			case 'textarea':
				return sanitize_textarea_field( (string) $value );

			case 'email':
				return sanitize_email( (string) $value );

			case 'url':
				return esc_url_raw( (string) $value );

			case 'number':
				return intval( $value );

			case 'checkbox':
			case 'toggle':
				return (bool) $value;

			case 'select':
			case 'radio':
				$str = (string) $value;
				return array_key_exists( $str, $field['choices'] ) ? $str : $field['default'];

			case 'color':
				$str = (string) $value;
				return preg_match( '/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/', $str ) ? $str : $field['default'];

			default:
				return sanitize_text_field( (string) $value );
		}
	}
}
