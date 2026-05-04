<?php

namespace Nilambar\Optiz;

class Registry {

	private array $schema = [];
	private array $option = [];

	public function set_schema( array $schema ): void {
		$this->schema = $schema;
	}

	public function get_schema(): array {
		return $this->schema;
	}

	public function set_option( array $data ): void {
		$this->option = $data;
	}

	public function get_option(): array {
		return $this->option;
	}
}
