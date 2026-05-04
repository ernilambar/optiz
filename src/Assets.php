<?php

namespace Nilambar\Optiz;

class Assets {

	public function enqueue( string $hook, string $page_hook, array $schema ): void {
		if ( $hook !== $page_hook ) {
			return;
		}

		wp_enqueue_style(
			'optiz-admin',
			OPTIZ_URL . 'assets/css/optiz-admin.css',
			[],
			OPTIZ_LOADED_VERSION
		);

		wp_enqueue_script(
			'optiz-conditional',
			OPTIZ_URL . 'assets/js/conditional.js',
			[],
			OPTIZ_LOADED_VERSION,
			true
		);

		$rules = [];
		foreach ( $schema['tabs'] as $tab ) {
			foreach ( $tab['fields'] as $field ) {
				if ( ! empty( $field['depends_on'] ) ) {
					$rules[] = [
						'fieldId'    => $field['id'],
						'conditions' => $field['depends_on'],
					];
				}
			}
		}

		wp_localize_script( 'optiz-conditional', 'optizConditional', [ 'rules' => $rules ] );
	}
}
