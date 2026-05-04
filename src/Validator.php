<?php

namespace Nilambar\Optiz;

class Validator {

	private const DISPLAY_ONLY_TYPES = [ 'heading', 'message' ];

	public function sanitize( array $raw, array $schema ): array {
		$clean = [];

		foreach ( $schema['tabs'] as $tab ) {
			foreach ( $tab['fields'] as $field ) {
				if ( in_array( $field['type'], self::DISPLAY_ONLY_TYPES, true ) ) {
					continue;
				}

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
			case 'image':
				return esc_url_raw( (string) $value );

			case 'number':
				return intval( $value );

			case 'checkbox':
			case 'toggle':
				return (bool) $value;

			case 'select':
			case 'radio':
			case 'radio_image':
				$str = (string) $value;
				return array_key_exists( $str, $field['choices'] ) ? $str : $field['default'];

			case 'color':
				$sanitized = sanitize_hex_color( (string) $value );
				return null !== $sanitized ? $sanitized : (string) $field['default'];

			case 'hidden':
				return sanitize_text_field( (string) $value );

			case 'password':
			case 'code':
				return (string) $value;

			case 'multicheck':
				if ( ! is_array( $value ) ) {
					return [];
				}
				$valid = array_keys( $field['choices'] );
				return array_values( array_intersect( array_map( 'sanitize_text_field', $value ), $valid ) );

			case 'editor':
				return wp_kses_post( (string) $value );

			case 'buttonset':
				$str = (string) $value;
				return array_key_exists( $str, $field['choices'] ) ? $str : $field['default'];

			default:
				return sanitize_text_field( (string) $value );
		}
	}
}
