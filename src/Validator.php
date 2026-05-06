<?php
/**
 * Validator class.
 *
 * @package Optiz
 */

declare(strict_types=1);

namespace Nilambar\Optiz;

/**
 * Sanitizes submitted option values against the normalised schema.
 *
 * @since 1.0.0
 */
class Validator {

	private const DISPLAY_ONLY_TYPES = [ 'heading', 'message' ];

	/**
	 * Sanitizes all fields defined in the schema.
	 *
	 * @since 1.0.0
	 *
	 * @param array $raw    Raw POST data keyed by field ID.
	 * @param array $schema Normalised schema.
	 * @return array Sanitized values keyed by field ID.
	 */
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

	/**
	 * Sanitizes a single field value.
	 *
	 * @since 1.0.0
	 *
	 * @param array $field Normalised field definition.
	 * @param mixed $value Raw value.
	 * @return mixed Sanitized value.
	 */
	private function sanitize_field( array $field, $value ) {
		if ( null !== $field['sanitize_callback'] && is_callable( $field['sanitize_callback'] ) ) {
			return call_user_func( $field['sanitize_callback'], $value );
		}

		return $this->apply_sanitizer( $field, $value );
	}

	/**
	 * Applies the built-in sanitizer for a given field type.
	 *
	 * @since 1.0.0
	 *
	 * @param array $field Normalised field definition.
	 * @param mixed $value Raw value.
	 * @return mixed Sanitized value.
	 */
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
