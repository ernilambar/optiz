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

		$rules          = [];
		$has_code_field = false;

		foreach ( $schema['tabs'] as $tab ) {
			foreach ( $tab['fields'] as $field ) {
				if ( ! empty( $field['depends_on'] ) ) {
					$rules[] = [
						'fieldId'    => $field['id'],
						'conditions' => $field['depends_on'],
					];
				}
				if ( 'code' === $field['type'] ) {
					$has_code_field = true;
				}
			}
		}

		wp_localize_script( 'optiz-conditional', 'optizConditional', [ 'rules' => $rules ] );

		if ( $has_code_field ) {
			$editor_settings = wp_enqueue_code_editor( [ 'type' => 'text/plain' ] );

			if ( false !== $editor_settings ) {
				$mime_map = [ 'text' => 'text/plain', 'css' => 'text/css', 'js' => 'text/javascript' ];
				wp_add_inline_script(
					'code-editor',
					'document.addEventListener("DOMContentLoaded",function(){' .
					'var s=' . wp_json_encode( $editor_settings ) . ';' .
					'var m=' . wp_json_encode( $mime_map ) . ';' .
					'document.querySelectorAll(".optiz-code-editor").forEach(function(el){' .
					'var c=Object.assign({},s,{codemirror:Object.assign({},s.codemirror,{mode:m[el.dataset.codeType]||"text/plain"})});' .
					'wp.codeEditor.initialize(el,c);' .
					'});});'
				);
			}
		}
	}
}
