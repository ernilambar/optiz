<?php
/**
 * Assets class.
 *
 * @package Optiz
 */

declare(strict_types=1);

namespace Nilambar\Optiz;

/**
 * Enqueues admin-page CSS, JS, and their localised data.
 *
 * @since 1.0.0
 */
class Assets {

	/**
	 * Enqueues styles and scripts for the current settings page.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $hook       Current admin page hook.
	 * @param array<string,string> $page_hooks Registered page hooks keyed by page ID.
	 * @param array                $schema     Normalised schema.
	 */
	public function enqueue( string $hook, array $page_hooks, array $schema ): void {
		$page_id = array_search( $hook, $page_hooks, true );

		if ( false === $page_id ) {
			return;
		}

		$page = null;
		foreach ( $schema['pages'] as $candidate ) {
			if ( $candidate['id'] === $page_id ) {
				$page = $candidate;
				break;
			}
		}

		if ( null === $page ) {
			return;
		}

		$rules          = [];
		$has_code_field = false;
		$has_media      = false;

		foreach ( $page['tabs'] as $tab ) {
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
					case 'image':
					case 'file':
						$has_media = true;
						break;
				}
			}
		}

		$deps = [];

		if ( $has_media ) {
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

		$optiz_data = [ 'conditional' => [ 'rules' => $rules ] ];

		if ( $has_code_field ) {
			$editor_settings = wp_enqueue_code_editor( [ 'type' => 'text/plain' ] );

			if ( false !== $editor_settings ) {
				$optiz_data['codeEditor'] = [
					'settings' => $editor_settings,
					'mimeMap'  => [
						'text' => 'text/plain',
						'css'  => 'text/css',
						'js'   => 'text/javascript',
					],
				];
			}
		}

		wp_add_inline_script( 'optiz', 'window.optiz = ' . wp_json_encode( $optiz_data ) . ';', 'before' );
	}
}
