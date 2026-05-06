<?php
/**
 * Renderer class.
 *
 * @package Optiz
 */

declare(strict_types=1);

namespace Nilambar\Optiz;

/**
 * Generates the HTML output for the admin settings page.
 *
 * @since 1.0.0
 */
class Renderer {

	/**
	 * Outputs the full settings page HTML including tabs, form, and notices.
	 *
	 * @since 1.0.0
	 *
	 * @param Registry $registry Registry instance holding the schema.
	 * @param string   $key      Plugin key.
	 */
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

		$notice = get_transient( 'optiz_notices_' . $key );
		if ( ! empty( $notice ) ) {
			delete_transient( 'optiz_notices_' . $key );
			add_settings_error( 'optiz_' . $key, 'optiz_notice', $notice['message'], $notice['type'] );
		}
		settings_errors( 'optiz_' . $key );

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( 'optiz_save_' . $key ) . '">';

		wp_nonce_field( 'optiz_save_' . $key, 'optiz_nonce' );

		if ( count( $tabs ) > 1 ) {
			$this->render_tabs( $tabs, $active_tab, $page['menu_slug'], $page['parent_slug'] );
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

	/**
	 * Outputs the tab navigation bar.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $tabs        Normalised tabs array.
	 * @param string $active_tab  ID of the currently active tab.
	 * @param string $menu_slug   Page menu slug used to build tab URLs.
	 * @param string $parent_slug Parent menu slug, if the page is a submenu.
	 */
	public function render_tabs( array $tabs, string $active_tab, string $menu_slug, string $parent_slug = '' ): void {
		$base = ! empty( $parent_slug ) ? $parent_slug : 'admin.php';
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $tab ) {
			$url   = add_query_arg(
				[
					'page' => $menu_slug,
					'tab'  => $tab['id'],
				],
				admin_url( $base )
			);
			$class = 'nav-tab' . ( $tab['id'] === $active_tab ? ' nav-tab-active' : '' );
			echo '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $class ) . '">'
				. esc_html( $tab['label'] ) . '</a>';
		}
		echo '</h2>';
	}

	/**
	 * Outputs the form-table rows for all fields in a tab.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $fields       Normalised fields array.
	 * @param array  $saved_values Saved option values keyed by field ID.
	 * @param string $option_key   Option key used for input name attributes.
	 */
	public function render_fields( array $fields, array $saved_values, string $option_key ): void {
		echo '<table class="form-table" role="presentation"><tbody>';

		$hidden_inputs = '';

		foreach ( $fields as $field ) {
			$value  = array_key_exists( $field['id'], $saved_values ) ? $saved_values[ $field['id'] ] : $field['default'];
			$method = 'render_' . $field['type'] . '_field';

			if ( 'hidden' === $field['type'] ) {
				$hidden_inputs .= method_exists( $this, $method ) ? $this->$method( $field, $value, $option_key ) : '';
				continue;
			}

			$input_html = method_exists( $this, $method ) ? $this->$method( $field, $value, $option_key ) : '';
			$this->render_field_wrap( $field, $input_html, $option_key );
		}

		echo '</tbody></table>';

		if ( $hidden_inputs ) {
			echo $hidden_inputs; // phpcs:ignore WordPress.Security.EscapeOutput -- pre-escaped by render_hidden_field
		}
	}

	/**
	 * Outputs the <tr> wrapper around a field, including label and description.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $field      Normalised field definition.
	 * @param string $input_html Pre-escaped input HTML.
	 * @param string $option_key Option key for hook arguments.
	 */
	private function render_field_wrap( array $field, string $input_html, string $option_key ): void {
		$is_display_only = in_array( $field['type'], [ 'heading', 'message' ], true );
		$wrapper_attrs   = '';

		if ( ! empty( $field['conditions'] ) ) {
			$json           = wp_json_encode( $field['conditions'] );
			$wrapper_attrs .= ' data-field-id="' . esc_attr( $field['id'] ) . '"';
			$wrapper_attrs .= ' data-conditions="' . esc_attr( $json ? $json : '[]' ) . '"';
		}

		$row_class = 'optiz-field-wrap optiz-field-type-' . esc_attr( $field['type'] ) . ' optiz-field-id-' . esc_attr( $field['id'] );

		echo '<tr class="' . esc_attr( $row_class ) . '"' . $wrapper_attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $wrapper_attrs is built from esc_attr() calls

		if ( $is_display_only ) {
			echo '<td colspan="2">';
			do_action( 'optiz_before_field', $field, $option_key );
			do_action( 'optiz_before_field_' . $option_key . '_' . $field['id'], $field, $option_key );
			echo $input_html; // phpcs:ignore WordPress.Security.EscapeOutput -- pre-escaped
			do_action( 'optiz_after_field_' . $option_key . '_' . $field['id'], $field, $option_key );
			do_action( 'optiz_after_field', $field, $option_key );
			// heading shows its description; message content already comes from description.
			if ( 'heading' === $field['type'] && ! empty( $field['description'] ) ) {
				echo '<p class="description">' . esc_html( $field['description'] ) . '</p>';
			}
			echo '</td>';
			echo '</tr>';
			return;
		}

		$no_label_for = [ 'radio', 'multicheck', 'buttonset', 'radio_image' ];
		$label_for    = ! in_array( $field['type'], $no_label_for, true )
			? ' for="optiz_' . esc_attr( $field['id'] ) . '"'
			: '';

		echo '<th scope="row"><label' . $label_for . '>' . esc_html( $field['label'] ) . '</label></th>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $label_for is built from esc_attr()
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
	private function render_text_field( array $field, $value, string $option_key ): string {
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
	private function render_email_field( array $field, $value, string $option_key ): string {
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
	private function render_url_field( array $field, $value, string $option_key ): string {
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
	private function render_number_field( array $field, $value, string $option_key ): string {
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
	private function render_password_field( array $field, $value, string $option_key ): string {
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
	private function render_hidden_field( array $field, $value, string $option_key ): string {
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
	private function render_color_field( array $field, $value, string $option_key ): string {
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
	private function render_textarea_field( array $field, $value, string $option_key ): string {
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
	private function render_checkbox_field( array $field, $value, string $option_key ): string {
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
	 * @since 1.0.0
	 *
	 * @param array  $field      Normalised field definition.
	 * @param mixed  $value      Current field value.
	 * @param string $option_key Option key for the input name attribute.
	 * @return string Pre-escaped HTML.
	 */
	private function render_toggle_field( array $field, $value, string $option_key ): string {
		$name    = esc_attr( $option_key ) . '[' . esc_attr( $field['id'] ) . ']';
		$checked = $value ? ' checked' : '';
		$output  = '<input type="hidden" name="' . $name . '" value="0">';
		$output .= '<label class="optiz-toggle">';
		$output .= sprintf(
			'<input type="checkbox" id="optiz_%s" name="%s" value="1" class="optiz-input"%s%s>',
			esc_attr( $field['id'] ),
			$name,
			$checked,
			$this->build_attrs( $field['attributes'] )
		);
		$output .= '<span class="optiz-toggle-slider"></span>';
		$output .= '</label>';
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
	private function render_select_field( array $field, $value, string $option_key ): string {
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
	private function render_radio_field( array $field, $value, string $option_key ): string {
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
	private function render_radio_image_field( array $field, $value, string $option_key ): string {
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
	private function render_image_field( array $field, $value, string $option_key ): string {
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
	 * Renders a display-only heading field.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $field      Normalised field definition.
	 * @param mixed  $value      Unused.
	 * @param string $option_key Unused.
	 * @return string Pre-escaped HTML.
	 */
	private function render_heading_field( array $field, $value, string $option_key ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
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
	private function render_message_field( array $field, $value, string $option_key ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
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
	private function render_code_field( array $field, $value, string $option_key ): string {
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
	private function render_multicheck_field( array $field, $value, string $option_key ): string {
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
	private function render_editor_field( array $field, $value, string $option_key ): string {
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
	private function render_buttonset_field( array $field, $value, string $option_key ): string {
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
