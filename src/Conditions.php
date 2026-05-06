<?php
/**
 * Conditions class.
 *
 * @package Optiz
 */

declare(strict_types=1);

namespace Nilambar\Optiz;

/**
 * Evaluates field visibility from conditional rules.
 *
 * Mirrors the client-side fixpoint engine in resources/js/conditional.js so the
 * server can emit the correct initial visibility and avoid flicker before JS
 * takes over.
 *
 * @since 1.0.0
 */
class Conditions {

	/**
	 * Computes the initial visibility map for a tab's fields.
	 *
	 * @since 1.0.0
	 *
	 * @param array $fields       Normalised fields array.
	 * @param array $saved_values Saved option values keyed by field ID.
	 * @return array<string,bool> Map of field ID → visible flag.
	 */
	public static function evaluate( array $fields, array $saved_values ): array {
		$values  = [];
		$visible = [];
		$rules   = [];

		foreach ( $fields as $field ) {
			$id             = $field['id'];
			$values[ $id ]  = array_key_exists( $id, $saved_values ) ? $saved_values[ $id ] : $field['default'];
			$visible[ $id ] = true;
			if ( ! empty( $field['conditions'] ) ) {
				$rules[ $id ] = $field['conditions'];
			}
		}

		$changed    = true;
		$iterations = 0;

		while ( $changed && $iterations < 10 ) {
			$changed = false;
			++$iterations;

			foreach ( $rules as $field_id => $conditions ) {
				$should_show = true;

				foreach ( $conditions as $condition ) {
					$src_id = $condition['field'] ?? '';

					// Source field hidden → cascade hide.
					if ( ! ( $visible[ $src_id ] ?? true ) ) {
						$should_show = false;
						break;
					}

					if ( ! self::condition_met( $condition, $values[ $src_id ] ?? null ) ) {
						$should_show = false;
						break;
					}
				}

				if ( $should_show !== $visible[ $field_id ] ) {
					$visible[ $field_id ] = $should_show;
					$changed              = true;
				}
			}
		}

		return $visible;
	}

	/**
	 * Tests one condition against an actual value.
	 *
	 * @since 1.0.0
	 *
	 * @param array $condition Condition definition with 'value' and optional 'compare'.
	 * @param mixed $value     Current value of the source field.
	 */
	private static function condition_met( array $condition, $value ): bool {
		$expected = $condition['value'] ?? null;
		$compare  = $condition['compare'] ?? '===';

		if ( is_bool( $expected ) ) {
			$match = (bool) $value === $expected;
			return '!==' === $compare ? ! $match : $match;
		}

		$str_value    = (string) $value;
		$str_expected = (string) $expected;
		return '!==' === $compare ? $str_value !== $str_expected : $str_value === $str_expected;
	}
}
