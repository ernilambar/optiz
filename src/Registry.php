<?php
/**
 * Registry class.
 *
 * @package Optiz
 */

declare(strict_types=1);

namespace Nilambar\Optiz;

/**
 * Holds the normalised schema and field defaults for a single plugin instance.
 *
 * @since 1.0.0
 */
class Registry {

	/**
	 * Normalised schema.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	private array $schema = [];

	/**
	 * Saved option values.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	private array $option = [];

	/**
	 * Field defaults keyed by field ID.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	private array $defaults = [];

	/**
	 * Stores the normalised schema and extracts field defaults.
	 *
	 * @since 1.0.0
	 *
	 * @param array $schema Normalised schema.
	 */
	public function set_schema( array $schema ): void {
		$this->schema   = $schema;
		$this->defaults = [];
		foreach ( $schema['pages'] as $page ) {
			foreach ( $page['tabs'] as $tab ) {
				foreach ( $tab['fields'] as $field ) {
					$this->defaults[ $field['id'] ] = $field['default'];
				}
			}
		}
	}

	/**
	 * Returns the normalised schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array Normalised schema.
	 */
	public function get_schema(): array {
		return $this->schema;
	}

	/**
	 * Returns a single normalised page by ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $page_id Page ID.
	 * @return array|null Normalised page configuration, or null if no page has this ID.
	 */
	public function get_page( string $page_id ): ?array {
		foreach ( $this->schema['pages'] ?? [] as $page ) {
			if ( $page['id'] === $page_id ) {
				return $page;
			}
		}
		return null;
	}

	/**
	 * Returns field defaults keyed by field ID.
	 *
	 * @since 1.0.0
	 *
	 * @return array Defaults map.
	 */
	public function get_defaults(): array {
		return $this->defaults;
	}

	/**
	 * Stores option values.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Option values keyed by field ID.
	 */
	public function set_option( array $data ): void {
		$this->option = $data;
	}

	/**
	 * Returns the stored option values.
	 *
	 * @since 1.0.0
	 *
	 * @return array Option values keyed by field ID.
	 */
	public function get_option(): array {
		return $this->option;
	}
}
