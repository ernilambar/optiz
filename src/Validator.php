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

	private const BOOLEAN_TYPES = [ 'checkbox', 'toggle' ];

	private const ARRAY_TYPES = [ 'multicheck' ];

	/**
	 * Sanitizes all fields defined in the schema.
	 *
	 * @since 1.0.0
	 *
	 * @param array $raw      Raw POST data keyed by field ID.
	 * @param array $schema   Normalised schema.
	 * @param array $existing Previously saved values keyed by field ID. Used to
	 *                        preserve readonly fields against form tampering.
	 * @return array Sanitized values keyed by field ID.
	 */
	public function sanitize( array $raw, array $schema, array $existing = [] ): array {
		$clean = [];

		foreach ( $schema['tabs'] as $tab ) {
			foreach ( $tab['fields'] as $field ) {
				if ( in_array( $field['type'], self::DISPLAY_ONLY_TYPES, true ) ) {
					continue;
				}

				$id = $field['id'];

				if ( ! empty( $field['readonly'] ) ) {
					$clean[ $id ] = array_key_exists( $id, $existing ) ? $existing[ $id ] : $field['default'];
					continue;
				}

				$value        = $raw[ $id ] ?? null;
				$clean[ $id ] = $this->sanitize_field( $field, $value );
			}
		}

		return $clean;
	}

	/**
	 * Sanitizes a single field value.
	 *
	 * Calls the developer-supplied sanitize_callback first; if the callback
	 * returns an unexpected type the built-in sanitizer is used as a fallback
	 * and _doing_it_wrong() is triggered so the developer is alerted.
	 *
	 * @since 1.0.0
	 *
	 * @param array $field Normalised field definition.
	 * @param mixed $value Raw value.
	 * @return mixed Sanitized value.
	 */
	private function sanitize_field( array $field, mixed $value ): mixed {
		if ( null !== $field['sanitize_callback'] && is_callable( $field['sanitize_callback'] ) ) {
			$result = call_user_func( $field['sanitize_callback'], $value );

			if ( $this->is_valid_callback_result( $field['type'], $result ) ) {
				return $result;
			}

			_doing_it_wrong(
				'Optiz sanitize_callback',
				sprintf(
					'The sanitize_callback for field "%s" returned an unexpected type. Falling back to the built-in sanitizer.',
					esc_html( $field['id'] )
				),
				'1.0.0'
			);
		}

		return $this->apply_sanitizer( $field, $value );
	}

	/**
	 * Returns whether a sanitize_callback return value is the correct type for the field.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type   Field type.
	 * @param mixed  $result Value returned by the callback.
	 * @return bool True when the type matches expectations.
	 */
	private function is_valid_callback_result( string $type, mixed $result ): bool {
		if ( in_array( $type, self::ARRAY_TYPES, true ) ) {
			return is_array( $result );
		}

		if ( in_array( $type, self::BOOLEAN_TYPES, true ) ) {
			return is_bool( $result );
		}

		return is_scalar( $result ) || null === $result;
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
	private function apply_sanitizer( array $field, mixed $value ): mixed {
		switch ( $field['type'] ) {
			case 'text':
				return sanitize_text_field( (string) $value );

			case 'textarea':
				return sanitize_textarea_field( (string) $value );

			case 'email':
				return sanitize_email( (string) $value );

			case 'url':
			case 'image':
			case 'file':
				return esc_url_raw( (string) $value );

			case 'number':
				$step       = (string) ( $field['attributes']['step'] ?? '1' );
				$step_float = (float) $step;
				$is_float   = (float) (int) $step_float !== $step_float;
				$numeric    = $is_float ? floatval( $value ) : intval( $value );

				$attrs   = $field['attributes'];
				$has_min = array_key_exists( 'min', $attrs ) && '' !== $attrs['min'];
				$has_max = array_key_exists( 'max', $attrs ) && '' !== $attrs['max'];

				if ( $has_min && $numeric < (float) $attrs['min'] ) {
					return $is_float ? floatval( $field['default'] ) : intval( $field['default'] );
				}
				if ( $has_max && $numeric > (float) $attrs['max'] ) {
					return $is_float ? floatval( $field['default'] ) : intval( $field['default'] );
				}

				return $numeric;

			case 'checkbox':
			case 'toggle':
				return (bool) $value;

			case 'select':
			case 'radio':
			case 'radio_image':
			case 'buttonset':
				$str = (string) $value;
				return $this->is_valid_choice( $str, $field['choices'] ) ? $str : (string) $field['default'];

			case 'color':
				$sanitized = sanitize_hex_color( (string) $value );
				return null !== $sanitized ? $sanitized : (string) $field['default'];

			case 'hidden':
				return sanitize_text_field( (string) $value );

			case 'password':
				return sanitize_text_field( (string) $value );

			case 'code':
				return (string) $value;

			case 'multicheck':
				if ( ! is_array( $value ) ) {
					return [];
				}
				$choices   = $field['choices'];
				$sanitized = array_map( 'sanitize_text_field', $value );
				return array_values(
					array_filter( $sanitized, static fn( string $v ) => array_key_exists( $v, $choices ) )
				);

			case 'editor':
				return wp_kses_post( (string) $value );

			default:
				return sanitize_text_field( (string) $value );
		}
	}

	/**
	 * Returns whether a string value matches one of the valid choice keys.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value   Submitted value.
	 * @param array  $choices Normalised choices array.
	 * @return bool True when the value is a valid choice key.
	 */
	private function is_valid_choice( string $value, array $choices ): bool {
		return array_key_exists( $value, $choices );
	}
}
