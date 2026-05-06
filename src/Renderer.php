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
 * Page-level concerns (tabs, notices, form wrapper, field rows) live here.
 * Per-field HTML is delegated to FieldRenderer.
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

		$hidden_inputs  = '';
		$field_renderer = new FieldRenderer();
		$visibility     = Conditions::evaluate( $fields, $saved_values );

		foreach ( $fields as $field ) {
			$value = array_key_exists( $field['id'], $saved_values ) ? $saved_values[ $field['id'] ] : $field['default'];

			if ( 'hidden' === $field['type'] ) {
				$hidden_inputs .= $field_renderer->render( $field, $value, $option_key );
				continue;
			}

			$input_html = $field_renderer->render( $field, $value, $option_key );
			$is_visible = $visibility[ $field['id'] ] ?? true;
			$this->render_field_wrap( $field, $input_html, $option_key, $is_visible );
		}

		echo '</tbody></table>';

		if ( $hidden_inputs ) {
			echo $hidden_inputs; // phpcs:ignore WordPress.Security.EscapeOutput -- pre-escaped by FieldRenderer
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
	 * @param bool   $is_visible Whether the field should be visible on initial render.
	 */
	private function render_field_wrap( array $field, string $input_html, string $option_key, bool $is_visible = true ): void {
		$is_display_only = in_array( $field['type'], [ 'heading', 'message' ], true );
		$wrapper_attrs   = '';

		if ( ! empty( $field['conditions'] ) ) {
			$json           = wp_json_encode( $field['conditions'] );
			$wrapper_attrs .= ' data-field-id="' . esc_attr( $field['id'] ) . '"';
			$wrapper_attrs .= ' data-conditions="' . esc_attr( $json ? $json : '[]' ) . '"';
		}

		if ( ! $is_visible ) {
			$wrapper_attrs .= ' style="display:none;"';
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
		echo $input_html; // phpcs:ignore WordPress.Security.EscapeOutput -- pre-escaped by FieldRenderer
		do_action( 'optiz_after_field_' . $option_key . '_' . $field['id'], $field, $option_key );
		do_action( 'optiz_after_field', $field, $option_key );
		if ( ! empty( $field['description'] ) ) {
			echo '<p class="description">' . esc_html( $field['description'] ) . '</p>';
		}
		echo '</td>';
		echo '</tr>';
	}
}
