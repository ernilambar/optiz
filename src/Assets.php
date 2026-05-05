<?php

namespace Nilambar\Optiz;

class Assets {

	public function enqueue( string $hook, string $page_hook, array $schema ): void {
		if ( $hook !== $page_hook ) {
			return;
		}

		$rules               = [];
		$has_code_field      = false;
		$has_color_field     = false;
		$has_image_field     = false;

		foreach ( $schema['tabs'] as $tab ) {
			foreach ( $tab['fields'] as $field ) {
				if ( ! empty( $field['conditions'] ) ) {
					$rules[] = [
						'fieldId'    => $field['id'],
						'conditions' => $field['conditions'],
					];
				}

				switch ( $field['type'] ) {
					case 'code':
						$has_code_field = true;
						break;
					case 'color':
						$has_color_field = true;
						break;
					case 'image':
						$has_image_field = true;
						break;
				}
			}
		}

		$deps = [];

		if ( $has_color_field ) {
			wp_enqueue_style( 'wp-color-picker' );
			$deps[] = 'wp-color-picker';
		}

		if ( $has_image_field ) {
			wp_enqueue_media();
		}

		wp_enqueue_style(
			'optiz',
			OPTIZ_URL . 'assets/optiz.css',
			[],
			OPTIZ_LOADED_VERSION
		);

		wp_enqueue_script(
			'optiz',
			OPTIZ_URL . 'assets/optiz.js',
			$deps,
			OPTIZ_LOADED_VERSION,
			true
		);

		wp_localize_script( 'optiz', 'optizConditional', [ 'rules' => $rules ] );

		if ( $has_code_field ) {
			$editor_settings = wp_enqueue_code_editor( [ 'type' => 'text/plain' ] );

			if ( false !== $editor_settings ) {
				$mime_map = [
					'text' => 'text/plain',
					'css'  => 'text/css',
					'js'   => 'text/javascript',
				];
				wp_localize_script( 'optiz', 'optizCodeEditor', [
					'settings' => $editor_settings,
					'mimeMap'  => $mime_map,
				] );
			}
		}
	}
}
