<?php
/**
 * FieldRenderer class.
 *
 * @package Optiz
 */

declare(strict_types=1);

namespace Nilambar\Optiz;

/**
 * Renders the HTML for individual field types.
 *
 * Renderer delegates all per-field output to this class, keeping page-level
 * concerns (tabs, notices, form wrapper) separate from field-level HTML.
 *
 * @since 1.0.0
 */
class FieldRenderer {

	/**
	 * Dispatches to the appropriate render method for the given field type.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $field      Normalised field definition.
	 * @param mixed  $value      Current field value.
	 * @param string $option_key Option key for input name attributes.
	 * @return string Pre-escaped HTML.
	 */
	public function render( array $field, mixed $value, string $option_key ): string {
		$method = 'render_' . $field['type'] . '_field';
		if ( method_exists( $this, $method ) ) {
			return $this->$method( $field, $value, $option_key );
		}
		return '';
	}

	/**
	 * Renders a text input field.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $field      Normalised field definition.
	 * @param mixed  $value      Current field value.
	 * @param string $option_key Option key for the input name attribute.
	 * @return string Pre-escaped HTML.
	 */
	private function render_text_field( array $field, mixed $value, string $option_key ): string {
		$class = 'optiz-input regular-text';
		if ( ! empty( $field['class'] ) ) {
			$class .= ' ' . $field['class'];
		}
		return sprintf(
			'<input type="text" id="optiz_%s" name="%s[%s]" value="%s" class="%s"%s>',
			esc_attr( $field['id'] ),
			esc_attr( $option_key ),
			esc_attr( $field['id'] ),
			esc_attr( (string) $value ),
			esc_attr( $class ),
			$this->build_input_extra( $field )
		);
	}

	/**
	 * Renders an email input field.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $field      Normalised field definition.
	 * @param mixed  $value      Current field value.
	 * @param string $option_key Option key for the input name attribute.
	 * @return string Pre-escaped HTML.
	 */
	private function render_email_field( array $field, mixed $value, string $option_key ): string {
		$class = 'optiz-input regular-text';
		if ( ! empty( $field['class'] ) ) {
			$class .= ' ' . $field['class'];
		}
		return sprintf(
			'<input type="email" id="optiz_%s" name="%s[%s]" value="%s" class="%s"%s>',
			esc_attr( $field['id'] ),
			esc_attr( $option_key ),
			esc_attr( $field['id'] ),
			esc_attr( (string) $value ),
			esc_attr( $class ),
			$this->build_input_extra( $field )
		);
	}

	/**
	 * Renders a URL input field.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $field      Normalised field definition.
	 * @param mixed  $value      Current field value.
	 * @param string $option_key Option key for the input name attribute.
	 * @return string Pre-escaped HTML.
	 */
	private function render_url_field( array $field, mixed $value, string $option_key ): string {
		$class = 'optiz-input regular-text';
		if ( ! empty( $field['class'] ) ) {
			$class .= ' ' . $field['class'];
		}
		return sprintf(
			'<input type="url" id="optiz_%s" name="%s[%s]" value="%s" class="%s"%s>',
			esc_attr( $field['id'] ),
			esc_attr( $option_key ),
			esc_attr( $field['id'] ),
			esc_attr( (string) $value ),
			esc_attr( $class ),
			$this->build_input_extra( $field )
		);
	}

	/**
	 * Renders a number input field.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $field      Normalised field definition.
	 * @param mixed  $value      Current field value.
	 * @param string $option_key Option key for the input name attribute.
	 * @return string Pre-escaped HTML.
	 */
	private function render_number_field( array $field, mixed $value, string $option_key ): string {
		$class = 'optiz-input small-text';
		if ( ! empty( $field['class'] ) ) {
			$class .= ' ' . $field['class'];
		}
		return sprintf(
			'<input type="number" id="optiz_%s" name="%s[%s]" value="%s" class="%s"%s>',
			esc_attr( $field['id'] ),
			esc_attr( $option_key ),
			esc_attr( $field['id'] ),
			esc_attr( (string) $value ),
			esc_attr( $class ),
			$this->build_input_extra( $field )
		);
	}

	/**
	 * Renders a password input field.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $field      Normalised field definition.
	 * @param mixed  $value      Current field value.
	 * @param string $option_key Option key for the input name attribute.
	 * @return string Pre-escaped HTML.
	 */
	private function render_password_field( array $field, mixed $value, string $option_key ): string {
		$class = 'optiz-input regular-text';
		if ( ! empty( $field['class'] ) ) {
			$class .= ' ' . $field['class'];
		}
		return sprintf(
			'<input type="password" id="optiz_%s" name="%s[%s]" value="%s" class="%s"%s>',
			esc_attr( $field['id'] ),
			esc_attr( $option_key ),
			esc_attr( $field['id'] ),
			esc_attr( (string) $value ),
			esc_attr( $class ),
			$this->build_input_extra( $field )
		);
	}

	/**
	 * Renders a hidden input field.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $field      Normalised field definition.
	 * @param mixed  $value      Current field value.
	 * @param string $option_key Option key for the input name attribute.
	 * @return string Pre-escaped HTML.
	 */
	private function render_hidden_field( array $field, mixed $value, string $option_key ): string {
		return sprintf(
			'<input type="hidden" name="%s[%s]" value="%s">',
			esc_attr( $option_key ),
			esc_attr( $field['id'] ),
			esc_attr( (string) $value )
		);
	}

	/**
	 * Renders a color picker input field.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $field      Normalised field definition.
	 * @param mixed  $value      Current field value.
	 * @param string $option_key Option key for the input name attribute.
	 * @return string Pre-escaped HTML.
	 */
	private function render_color_field( array $field, mixed $value, string $option_key ): string {
		return sprintf(
			'<input type="text" id="optiz_%s" name="%s[%s]" value="%s" class="optiz-input optiz-color-picker"%s>',
			esc_attr( $field['id'] ),
			esc_attr( $option_key ),
			esc_attr( $field['id'] ),
			esc_attr( (string) $value ),
			$this->build_attrs( $field['attributes'] )
		);
	}

	/**
	 * Renders a textarea field.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $field      Normalised field definition.
	 * @param mixed  $value      Current field value.
	 * @param string $option_key Option key for the input name attribute.
	 * @return string Pre-escaped HTML.
	 */
	private function render_textarea_field( array $field, mixed $value, string $option_key ): string {
		$class = 'optiz-input large-text';
		if ( ! empty( $field['class'] ) ) {
			$class .= ' ' . $field['class'];
		}
		$rows = isset( $field['rows'] ) ? (int) $field['rows'] : 5;
		return sprintf(
			'<textarea id="optiz_%s" name="%s[%s]" rows="%d" class="%s"%s>%s</textarea>',
			esc_attr( $field['id'] ),
			esc_attr( $option_key ),
			esc_attr( $field['id'] ),
			$rows,
			esc_attr( $class ),
			$this->build_input_extra( $field ),
			esc_textarea( (string) $value )
		);
	}

	/**
	 * Renders a checkbox field with a hidden fallback input.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $field      Normalised field definition.
	 * @param mixed  $value      Current field value.
	 * @param string $option_key Option key for the input name attribute.
	 * @return string Pre-escaped HTML.
	 */
	private function render_checkbox_field( array $field, mixed $value, string $option_key ): string {
		$name    = esc_attr( $option_key ) . '[' . esc_attr( $field['id'] ) . ']';
		$checked = $value ? ' checked' : '';
		$output  = '<input type="hidden" name="' . $name . '" value="0">';
		$output .= sprintf(
			'<input type="checkbox" id="optiz_%s" name="%s" value="1" class="optiz-input"%s%s>',
			esc_attr( $field['id'] ),
			$name,
			$checked,
			$this->build_attrs( $field['attributes'] )
		);
		if ( ! empty( $field['side_text'] ) ) {
			$output .= ' <span class="optiz-side-text">' . esc_html( $field['side_text'] ) . '</span>';
		}
		return $output;
	}

	/**
	 * Renders an iOS-style toggle switch field with a hidden fallback input.
	 *
	 * The checkbox uses role="switch" and aria-checked for screen-reader
	 * accessibility. The label's for attribute is set explicitly so the
	 * association is programmatically unambiguous.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $field      Normalised field definition.
	 * @param mixed  $value      Current field value.
	 * @param string $option_key Option key for the input name attribute.
	 * @return string Pre-escaped HTML.
	 */
	private function render_toggle_field( array $field, mixed $value, string $option_key ): string {
		$name         = esc_attr( $option_key ) . '[' . esc_attr( $field['id'] ) . ']';
		$checked      = $value ? ' checked' : '';
		$aria_checked = $value ? 'true' : 'false';
		$output       = '<input type="hidden" name="' . $name . '" value="0">';
		$output      .= '<label class="optiz-toggle" for="optiz_' . esc_attr( $field['id'] ) . '">';
		$output      .= sprintf(
			'<input type="checkbox" id="optiz_%s" name="%s" value="1" role="switch" aria-checked="%s" class="optiz-input"%s%s>',
			esc_attr( $field['id'] ),
			$name,
			esc_attr( $aria_checked ),
			$checked,
			$this->build_attrs( $field['attributes'] )
		);
		$output      .= '<span class="optiz-toggle-slider"></span>';
		$output      .= '</label>';
		if ( ! empty( $field['side_text'] ) ) {
			$output .= ' <span class="optiz-side-text">' . esc_html( $field['side_text'] ) . '</span>';
		}
		return $output;
	}

	/**
	 * Renders a select dropdown field.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $field      Normalised field definition.
	 * @param mixed  $value      Current field value.
	 * @param string $option_key Option key for the input name attribute.
	 * @return string Pre-escaped HTML.
	 */
	private function render_select_field( array $field, mixed $value, string $option_key ): string {
		$options = '';
		if ( ! empty( $field['allow_null'] ) ) {
			$options .= '<option value="">&mdash; ' . esc_html__( 'Select' ) . ' &mdash;</option>';
		}
		foreach ( $field['choices'] as $choice_value => $choice_label ) {
			$options .= sprintf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $choice_value ),
				selected( $value, $choice_value, false ),
				esc_html( $choice_label )
			);
		}

		$class = 'optiz-input';
		if ( ! empty( $field['class'] ) ) {
			$class .= ' ' . $field['class'];
		}

		return sprintf(
			'<select id="optiz_%s" name="%s[%s]" class="%s"%s>%s</select>',
			esc_attr( $field['id'] ),
			esc_attr( $option_key ),
			esc_attr( $field['id'] ),
			esc_attr( $class ),
			$this->build_attrs( $field['attributes'] ),
			$options
		);
	}

	/**
	 * Renders a radio button group field.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $field      Normalised field definition.
	 * @param mixed  $value      Current field value.
	 * @param string $option_key Option key for the input name attribute.
	 * @return string Pre-escaped HTML.
	 */
	private function render_radio_field( array $field, mixed $value, string $option_key ): string {
		$name   = esc_attr( $option_key ) . '[' . esc_attr( $field['id'] ) . ']';
		$class  = 'optiz-radio-group' . ( 'horizontal' === $field['layout'] ? ' is-horizontal' : '' );
		$inputs = '';

		foreach ( $field['choices'] as $choice_value => $choice_label ) {
			$id      = 'optiz_' . $field['id'] . '_' . $choice_value;
			$inputs .= sprintf(
				'<label><input type="radio" id="%s" name="%s" value="%s"%s> %s</label>',
				esc_attr( $id ),
				$name,
				esc_attr( $choice_value ),
				checked( $value, $choice_value, false ),
				esc_html( $choice_label )
			);
		}

		return '<fieldset class="' . esc_attr( $class ) . '">' . $inputs . '</fieldset>';
	}

	/**
	 * Renders a radio image picker field.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $field      Normalised field definition.
	 * @param mixed  $value      Current field value.
	 * @param string $option_key Option key for the input name attribute.
	 * @return string Pre-escaped HTML.
	 */
	private function render_radio_image_field( array $field, mixed $value, string $option_key ): string {
		$name   = esc_attr( $option_key ) . '[' . esc_attr( $field['id'] ) . ']';
		$class  = 'optiz-radio-image-group' . ( 'horizontal' === $field['layout'] ? ' is-horizontal' : '' );
		$output = '<ul class="' . esc_attr( $class ) . '">';

		foreach ( $field['choices'] as $choice_value => $image_url ) {
			$id      = 'optiz_' . $field['id'] . '_' . $choice_value;
			$output .= '<li>';
			$output .= sprintf(
				'<label><input type="radio" id="%s" name="%s" value="%s"%s><img src="%s" alt="%s"></label>',
				esc_attr( $id ),
				$name,
				esc_attr( $choice_value ),
				checked( $value, $choice_value, false ),
				esc_url( (string) $image_url ),
				esc_attr( (string) $choice_value )
			);
			$output .= '</li>';
		}

		$output .= '</ul>';
		return $output;
	}

	/**
	 * Renders an image upload field with preview.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $field      Normalised field definition.
	 * @param mixed  $value      Current field value (image URL).
	 * @param string $option_key Option key for the input name attribute.
	 * @return string Pre-escaped HTML.
	 */
	private function render_image_field( array $field, mixed $value, string $option_key ): string {
		$has_value = ! empty( $value );
		$output    = '<div class="optiz-image-field">';
		$output   .= sprintf(
			'<input type="text" id="optiz_%s" name="%s[%s]" value="%s" class="optiz-input regular-text optiz-image-url">',
			esc_attr( $field['id'] ),
			esc_attr( $option_key ),
			esc_attr( $field['id'] ),
			esc_attr( (string) $value )
		);
		$output   .= ' <button type="button" class="button optiz-upload-image">' . esc_html__( 'Upload Image' ) . '</button>';
		$output   .= ' <button type="button" class="button optiz-remove-image"' . ( $has_value ? '' : ' style="display:none"' ) . '>' . esc_html__( 'Remove' ) . '</button>';
		$output   .= '<div class="optiz-image-preview"' . ( $has_value ? '' : ' style="display:none"' ) . '>';
		$output   .= '<img src="' . ( $has_value ? esc_url( (string) $value ) : '' ) . '" alt="">';
		$output   .= '</div>';
		$output   .= '</div>';
		return $output;
	}

	/**
	 * Renders a file upload field (any media library file, no preview).
	 *
	 * @since 1.0.0
	 *
	 * @param array  $field      Normalised field definition.
	 * @param mixed  $value      Current field value (file URL).
	 * @param string $option_key Option key for the input name attribute.
	 * @return string Pre-escaped HTML.
	 */
	private function render_file_field( array $field, mixed $value, string $option_key ): string {
		$has_value = ! empty( $value );
		$output    = '<div class="optiz-file-field">';
		$output   .= sprintf(
			'<input type="text" id="optiz_%s" name="%s[%s]" value="%s" class="optiz-input regular-text optiz-file-url">',
			esc_attr( $field['id'] ),
			esc_attr( $option_key ),
			esc_attr( $field['id'] ),
			esc_attr( (string) $value )
		);
		$output   .= ' <button type="button" class="button optiz-upload-file">' . esc_html__( 'Upload File' ) . '</button>';
		$output   .= ' <button type="button" class="button optiz-remove-file"' . ( $has_value ? '' : ' style="display:none"' ) . '>' . esc_html__( 'Remove' ) . '</button>';
		$output   .= '</div>';
		return $output;
	}

	/**
	 * Renders a display-only heading field.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $field      Normalised field definition.
	 * @param mixed  $value      Unused.
	 * @param string $option_key Unused.
	 * @return string Pre-escaped HTML.
	 */
	private function render_heading_field( array $field, mixed $value, string $option_key ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return '<h2 class="optiz-heading">' . esc_html( $field['label'] ) . '</h2>';
	}

	/**
	 * Renders a display-only message field (content comes from the description).
	 *
	 * @since 1.0.0
	 *
	 * @param array  $field      Normalised field definition.
	 * @param mixed  $value      Unused.
	 * @param string $option_key Unused.
	 * @return string Pre-escaped HTML.
	 */
	private function render_message_field( array $field, mixed $value, string $option_key ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return '<div class="optiz-message">' . wp_kses_post( $field['description'] ) . '</div>';
	}

	/**
	 * Renders a CodeMirror-enhanced code editor field.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $field      Normalised field definition.
	 * @param mixed  $value      Current field value.
	 * @param string $option_key Option key for the input name attribute.
	 * @return string Pre-escaped HTML.
	 */
	private function render_code_field( array $field, mixed $value, string $option_key ): string {
		$class = 'optiz-input optiz-code-editor';
		if ( ! empty( $field['class'] ) ) {
			$class .= ' ' . $field['class'];
		}
		$rows = isset( $field['rows'] ) ? (int) $field['rows'] : 5;
		return sprintf(
			'<textarea id="optiz_%s" name="%s[%s]" class="%s" data-code-type="%s" rows="%d"%s>%s</textarea>',
			esc_attr( $field['id'] ),
			esc_attr( $option_key ),
			esc_attr( $field['id'] ),
			esc_attr( $class ),
			esc_attr( $field['mode'] ),
			$rows,
			$this->build_input_extra( $field ),
			esc_textarea( (string) $value )
		);
	}

	/**
	 * Renders a multi-checkbox group field.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $field      Normalised field definition.
	 * @param mixed  $value      Current field value (array of selected values).
	 * @param string $option_key Option key for the input name attribute.
	 * @return string Pre-escaped HTML.
	 */
	private function render_multicheck_field( array $field, mixed $value, string $option_key ): string {
		$value  = is_array( $value ) ? $value : [];
		$name   = esc_attr( $option_key ) . '[' . esc_attr( $field['id'] ) . '][]';
		$class  = 'optiz-multicheck-group' . ( 'horizontal' === $field['layout'] ? ' is-horizontal' : '' );
		$output = '<div class="' . esc_attr( $class ) . '">';

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

	/**
	 * Renders a TinyMCE / wp_editor field.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $field      Normalised field definition.
	 * @param mixed  $value      Current field value.
	 * @param string $option_key Option key for the textarea_name setting.
	 * @return string Pre-escaped HTML.
	 */
	private function render_editor_field( array $field, mixed $value, string $option_key ): string {
		$settings = [
			'textarea_name' => $option_key . '[' . $field['id'] . ']',
			'editor_class'  => 'optiz-input',
			'teeny'         => true,
			'media_buttons' => false,
		];

		ob_start();
		wp_editor( wp_kses_post( (string) $value ), 'optiz_' . $field['id'], $settings );
		return (string) ob_get_clean();
	}

	/**
	 * Renders a buttonset (styled radio group) field.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $field      Normalised field definition.
	 * @param mixed  $value      Current field value.
	 * @param string $option_key Option key for the input name attribute.
	 * @return string Pre-escaped HTML.
	 */
	private function render_buttonset_field( array $field, mixed $value, string $option_key ): string {
		$name   = esc_attr( $option_key ) . '[' . esc_attr( $field['id'] ) . ']';
		$output = '<div class="optiz-buttonset">';

		foreach ( $field['choices'] as $choice_value => $choice_label ) {
			$id        = 'optiz_' . $field['id'] . '_' . $choice_value;
			$checked   = checked( $value, $choice_value, false );
			$is_active = ( (string) $value === (string) $choice_value ) ? ' is-active' : '';
			$output   .= sprintf(
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

	/**
	 * Builds extra HTML attributes: placeholder (if set on the field) + field attributes array.
	 *
	 * @since 1.0.0
	 *
	 * @param array $field Normalised field definition.
	 * @return string Pre-escaped attribute string (leading space included).
	 */
	private function build_input_extra( array $field ): string {
		$output = '';
		if ( isset( $field['placeholder'] ) && '' !== $field['placeholder'] ) {
			$output .= ' placeholder="' . esc_attr( $field['placeholder'] ) . '"';
		}
		$output .= $this->build_attrs( $field['attributes'] );
		return $output;
	}

	/**
	 * Converts a key-value attributes array to a pre-escaped HTML attribute string.
	 *
	 * @since 1.0.0
	 *
	 * @param array $attrs Associative array of attribute name => value.
	 * @return string Pre-escaped attribute string (leading space included).
	 */
	private function build_attrs( array $attrs ): string {
		$output = '';
		foreach ( $attrs as $key => $val ) {
			$output .= ' ' . esc_attr( $key ) . '="' . esc_attr( (string) $val ) . '"';
		}
		return $output;
	}
}
