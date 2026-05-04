<?php

namespace Nilambar\Optiz;

class Renderer {

	public function render_page( Registry $registry, string $key ): void {
		$schema     = $registry->get_schema();
		$tabs       = $schema['tabs'];
		$option_key = $schema['option_key'];
		$page       = $schema['page'];

		$tab_ids    = array_column( $tabs, 'id' );
		$active_tab = isset( $_GET['tab'] ) && in_array( sanitize_key( $_GET['tab'] ), $tab_ids, true )
			? sanitize_key( $_GET['tab'] )
			: $tabs[0]['id'];

		$saved = get_option( $option_key, [] );
		if ( ! is_array( $saved ) ) {
			$saved = [];
		}

		echo '<div class="wrap optiz-wrap">';
		echo '<h1>' . esc_html( $page['title'] ) . '</h1>';

		if ( isset( $_GET['updated'] ) ) {
			if ( '1' === $_GET['updated'] ) {
				echo '<div class="notice notice-success is-dismissible"><p>'
					. esc_html__( 'Settings saved.', 'optiz' )
					. '</p></div>';
			} else {
				echo '<div class="notice notice-error is-dismissible"><p>'
					. esc_html__( 'Settings could not be saved.', 'optiz' )
					. '</p></div>';
			}
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( 'optiz_save_' . $key ) . '">';

		wp_nonce_field( 'optiz_save_' . $key, 'optiz_nonce' );

		if ( count( $tabs ) > 1 ) {
			$this->render_tabs( $tabs, $active_tab, $page['menu_slug'] );
		}

		foreach ( $tabs as $tab ) {
			$class = 'optiz-tab-content' . ( $tab['id'] === $active_tab ? ' is-active' : '' );
			echo '<div id="optiz-tab-' . esc_attr( $tab['id'] ) . '" class="' . esc_attr( $class ) . '">';
			$this->render_fields( $tab['fields'], $saved, $option_key );
			echo '</div>';
		}

		echo '<input type="hidden" name="current_tab" value="' . esc_attr( $active_tab ) . '">';
		submit_button();
		echo '</form>';
		echo '</div>';
	}

	public function render_tabs( array $tabs, string $active_tab, string $menu_slug ): void {
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $tab ) {
			$url   = add_query_arg( [ 'page' => $menu_slug, 'tab' => $tab['id'] ], admin_url( 'admin.php' ) );
			$class = 'nav-tab' . ( $tab['id'] === $active_tab ? ' nav-tab-active' : '' );
			echo '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $class ) . '">'
				. esc_html( $tab['label'] ) . '</a>';
		}
		echo '</h2>';
	}

	public function render_fields( array $fields, array $saved_values, string $option_key ): void {
		echo '<table class="form-table" role="presentation"><tbody>';

		foreach ( $fields as $field ) {
			$value      = array_key_exists( $field['id'], $saved_values ) ? $saved_values[ $field['id'] ] : $field['default'];
			$method     = 'render_' . $field['type'] . '_field';
			$input_html = method_exists( $this, $method ) ? $this->$method( $field, $value, $option_key ) : '';
			$this->render_field_wrap( $field, $input_html, $option_key );
		}

		echo '</tbody></table>';
	}

	private function render_field_wrap( array $field, string $input_html, string $option_key ): void {
		$wrapper_attrs = '';

		if ( ! empty( $field['depends_on'] ) ) {
			$json           = wp_json_encode( $field['depends_on'] );
			$wrapper_attrs .= ' data-field-id="' . esc_attr( $field['id'] ) . '"';
			$wrapper_attrs .= ' data-depends-on="' . esc_attr( $json ?: '[]' ) . '"';
		}

		$no_label_for = [ 'radio', 'multicheck', 'buttonset' ];
		$label_for    = ! in_array( $field['type'], $no_label_for, true )
			? ' for="optiz_' . esc_attr( $field['id'] ) . '"'
			: '';

		$row_class = 'optiz-field-wrap optiz-field-type-' . esc_attr( $field['type'] ) . ' optiz-field-id-' . esc_attr( $field['id'] );

		echo '<tr class="' . esc_attr( $row_class ) . '"' . $wrapper_attrs . '>';
		echo '<th scope="row"><label' . $label_for . '>' . esc_html( $field['label'] ) . '</label></th>';
		echo '<td>';
		do_action( 'optiz_before_field', $field, $option_key );
		do_action( 'optiz_before_field_' . $option_key . '_' . $field['id'], $field, $option_key );
		echo $input_html; // phpcs:ignore WordPress.Security.EscapeOutput -- pre-escaped by render_*_field methods
		do_action( 'optiz_after_field_' . $option_key . '_' . $field['id'], $field, $option_key );
		do_action( 'optiz_after_field', $field, $option_key );
		if ( ! empty( $field['description'] ) ) {
			echo '<p class="description">' . esc_html( $field['description'] ) . '</p>';
		}
		echo '</td>';
		echo '</tr>';
	}

	private function render_text_field( array $field, $value, string $option_key ): string {
		return sprintf(
			'<input type="text" id="optiz_%s" name="%s[%s]" value="%s" class="optiz-input regular-text"%s>',
			esc_attr( $field['id'] ),
			esc_attr( $option_key ),
			esc_attr( $field['id'] ),
			esc_attr( (string) $value ),
			$this->build_attrs( $field['attributes'] )
		);
	}

	private function render_email_field( array $field, $value, string $option_key ): string {
		return sprintf(
			'<input type="email" id="optiz_%s" name="%s[%s]" value="%s" class="optiz-input regular-text"%s>',
			esc_attr( $field['id'] ),
			esc_attr( $option_key ),
			esc_attr( $field['id'] ),
			esc_attr( (string) $value ),
			$this->build_attrs( $field['attributes'] )
		);
	}

	private function render_url_field( array $field, $value, string $option_key ): string {
		return sprintf(
			'<input type="url" id="optiz_%s" name="%s[%s]" value="%s" class="optiz-input regular-text"%s>',
			esc_attr( $field['id'] ),
			esc_attr( $option_key ),
			esc_attr( $field['id'] ),
			esc_attr( (string) $value ),
			$this->build_attrs( $field['attributes'] )
		);
	}

	private function render_number_field( array $field, $value, string $option_key ): string {
		return sprintf(
			'<input type="number" id="optiz_%s" name="%s[%s]" value="%s" class="optiz-input"%s>',
			esc_attr( $field['id'] ),
			esc_attr( $option_key ),
			esc_attr( $field['id'] ),
			esc_attr( (string) $value ),
			$this->build_attrs( $field['attributes'] )
		);
	}

	private function render_color_field( array $field, $value, string $option_key ): string {
		return sprintf(
			'<input type="color" id="optiz_%s" name="%s[%s]" value="%s" class="optiz-input"%s>',
			esc_attr( $field['id'] ),
			esc_attr( $option_key ),
			esc_attr( $field['id'] ),
			esc_attr( (string) $value ),
			$this->build_attrs( $field['attributes'] )
		);
	}

	private function render_textarea_field( array $field, $value, string $option_key ): string {
		return sprintf(
			'<textarea id="optiz_%s" name="%s[%s]" class="optiz-input"%s>%s</textarea>',
			esc_attr( $field['id'] ),
			esc_attr( $option_key ),
			esc_attr( $field['id'] ),
			$this->build_attrs( $field['attributes'] ),
			esc_textarea( (string) $value )
		);
	}

	private function render_checkbox_field( array $field, $value, string $option_key ): string {
		$name    = esc_attr( $option_key ) . '[' . esc_attr( $field['id'] ) . ']';
		$checked = $value ? ' checked' : '';
		return '<input type="hidden" name="' . $name . '" value="0">'
			. sprintf(
				'<input type="checkbox" id="optiz_%s" name="%s" value="1" class="optiz-input"%s%s>',
				esc_attr( $field['id'] ),
				$name,
				$checked,
				$this->build_attrs( $field['attributes'] )
			);
	}

	private function render_toggle_field( array $field, $value, string $option_key ): string {
		$name    = esc_attr( $option_key ) . '[' . esc_attr( $field['id'] ) . ']';
		$checked = $value ? ' checked' : '';
		return '<input type="hidden" name="' . $name . '" value="0">'
			. '<label class="optiz-toggle">'
			. sprintf(
				'<input type="checkbox" id="optiz_%s" name="%s" value="1" class="optiz-input"%s%s>',
				esc_attr( $field['id'] ),
				$name,
				$checked,
				$this->build_attrs( $field['attributes'] )
			)
			. '<span class="optiz-toggle-slider"></span>'
			. '</label>';
	}

	private function render_select_field( array $field, $value, string $option_key ): string {
		$options = '';
		foreach ( $field['choices'] as $choice_value => $choice_label ) {
			$options .= sprintf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $choice_value ),
				selected( $value, $choice_value, false ),
				esc_html( $choice_label )
			);
		}

		return sprintf(
			'<select id="optiz_%s" name="%s[%s]" class="optiz-input"%s>%s</select>',
			esc_attr( $field['id'] ),
			esc_attr( $option_key ),
			esc_attr( $field['id'] ),
			$this->build_attrs( $field['attributes'] ),
			$options
		);
	}

	private function render_radio_field( array $field, $value, string $option_key ): string {
		$name   = esc_attr( $option_key ) . '[' . esc_attr( $field['id'] ) . ']';
		$inputs = '';

		foreach ( $field['choices'] as $choice_value => $choice_label ) {
			$id      = 'optiz_' . $field['id'] . '_' . $choice_value;
			$inputs .= sprintf(
				'<label><input type="radio" id="%s" name="%s" value="%s"%s> %s</label><br>',
				esc_attr( $id ),
				$name,
				esc_attr( $choice_value ),
				checked( $value, $choice_value, false ),
				esc_html( $choice_label )
			);
		}

		return '<fieldset class="optiz-radio-group">' . $inputs . '</fieldset>';
	}

	private function render_password_field( array $field, $value, string $option_key ): string {
		return sprintf(
			'<input type="password" id="optiz_%s" name="%s[%s]" value="%s" class="optiz-input regular-text"%s>',
			esc_attr( $field['id'] ),
			esc_attr( $option_key ),
			esc_attr( $field['id'] ),
			esc_attr( (string) $value ),
			$this->build_attrs( $field['attributes'] )
		);
	}

	private function render_code_field( array $field, $value, string $option_key ): string {
		return sprintf(
			'<textarea id="optiz_%s" name="%s[%s]" class="optiz-input optiz-code-editor" data-code-type="%s"%s>%s</textarea>',
			esc_attr( $field['id'] ),
			esc_attr( $option_key ),
			esc_attr( $field['id'] ),
			esc_attr( $field['mode'] ),
			$this->build_attrs( $field['attributes'] ),
			esc_textarea( (string) $value )
		);
	}

	private function render_multicheck_field( array $field, $value, string $option_key ): string {
		$value  = is_array( $value ) ? $value : [];
		$name   = esc_attr( $option_key ) . '[' . esc_attr( $field['id'] ) . '][]';
		$output = '<div class="optiz-multicheck-group">';

		foreach ( $field['choices'] as $choice_value => $choice_label ) {
			$id      = 'optiz_' . $field['id'] . '_' . $choice_value;
			$checked = in_array( (string) $choice_value, array_map( 'strval', $value ), true ) ? ' checked' : '';
			$output .= sprintf(
				'<label class="optiz-multicheck-item"><input type="checkbox" id="%s" name="%s" value="%s"%s class="optiz-input"> %s</label>',
				esc_attr( $id ),
				$name,
				esc_attr( $choice_value ),
				$checked,
				esc_html( $choice_label )
			);
		}

		$output .= '</div>';
		return $output;
	}

	private function render_editor_field( array $field, $value, string $option_key ): string {
		$settings = [
			'textarea_name' => $option_key . '[' . $field['id'] . ']',
			'editor_class'  => 'optiz-input',
			'teeny'         => true,
			'media_buttons' => false,
		];

		ob_start();
		wp_editor( wp_kses_post( (string) $value ), 'optiz_' . $field['id'], $settings );
		return ob_get_clean();
	}

	private function render_buttonset_field( array $field, $value, string $option_key ): string {
		$name   = esc_attr( $option_key ) . '[' . esc_attr( $field['id'] ) . ']';
		$output = '<div class="optiz-buttonset">';

		foreach ( $field['choices'] as $choice_value => $choice_label ) {
			$id       = 'optiz_' . $field['id'] . '_' . $choice_value;
			$checked  = checked( $value, $choice_value, false );
			$is_active = ( (string) $value === (string) $choice_value ) ? ' is-active' : '';
			$output  .= sprintf(
				'<label class="optiz-buttonset-item%s"><input type="radio" id="%s" name="%s" value="%s"%s> %s</label>',
				$is_active,
				esc_attr( $id ),
				$name,
				esc_attr( $choice_value ),
				$checked,
				esc_html( $choice_label )
			);
		}

		$output .= '</div>';
		return $output;
	}

	private function build_attrs( array $attrs ): string {
		$output = '';
		foreach ( $attrs as $key => $val ) {
			$output .= ' ' . esc_attr( $key ) . '="' . esc_attr( (string) $val ) . '"';
		}
		return $output;
	}
}
