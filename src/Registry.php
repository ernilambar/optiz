<?php

namespace Nilambar\Optiz;

class Registry {

	private array $schema   = [];
	private array $option   = [];
	private array $defaults = [];

	public function set_schema( array $schema ): void {
		$this->schema   = $schema;
		$this->defaults = [];
		foreach ( $schema['tabs'] as $tab ) {
			foreach ( $tab['fields'] as $field ) {
				$this->defaults[ $field['id'] ] = $field['default'];
			}
		}
	}

	public function get_schema(): array {
		return $this->schema;
	}

	public function get_defaults(): array {
		return $this->defaults;
	}

	public function set_option( array $data ): void {
		$this->option = $data;
	}

	public function get_option(): array {
		return $this->option;
	}
}
