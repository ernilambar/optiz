<?php
/**
 * Tests for Validator.
 *
 * @package Optiz
 */

declare(strict_types=1);

namespace Nilambar\Optiz\Tests\Unit;

use Nilambar\Optiz\Parser;
use Nilambar\Optiz\Validator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Nilambar\Optiz\Validator
 */
class ValidatorTest extends TestCase {

	private Validator $validator;
	private Parser $parser;

	protected function setUp(): void {
		$this->validator = new Validator();
		$this->parser    = new Parser();
	}

	/** Builds a normalised schema from a flat list of field definitions. */
	private function make_schema( array $fields ): array {
		$raw = [
			'option_key' => 'test_options',
			'page'       => [
				'title'     => 'Test',
				'menu_slug' => 'test',
			],
			'tabs'       => [
				[
					'id'     => 'general',
					'label'  => 'General',
					'fields' => $fields,
				],
			],
		];
		$result = $this->parser->parse( $raw );
		$this->assertIsArray( $result, 'make_schema: parser returned WP_Error — check field definitions.' );
		return $result;
	}

	// -------------------------------------------------------------------------
	// Item 1: password field
	// -------------------------------------------------------------------------

	public function test_password_field_applies_sanitize_text_field(): void {
		$schema = $this->make_schema( [
			[ 'id' => 'api_key', 'type' => 'password', 'label' => 'API Key' ],
		] );
		// Control characters and leading/trailing whitespace should be stripped.
		$result = $this->validator->sanitize( [ 'api_key' => "  hello\x00world  " ], $schema );
		$this->assertSame( 'helloworld', $result['api_key'] );
	}

	public function test_code_field_is_stored_raw(): void {
		$schema   = $this->make_schema( [
			[ 'id' => 'snippet', 'type' => 'code', 'label' => 'Code' ],
		] );
		$raw_code = "<?php echo 'hello'; ?>";
		$result   = $this->validator->sanitize( [ 'snippet' => $raw_code ], $schema );
		$this->assertSame( $raw_code, $result['snippet'] );
	}

	// -------------------------------------------------------------------------
	// Item 7: number field (int vs float based on step)
	// -------------------------------------------------------------------------

	public function test_number_field_uses_intval_when_step_is_absent(): void {
		$schema = $this->make_schema( [
			[ 'id' => 'count', 'type' => 'number', 'label' => 'Count' ],
		] );
		$result = $this->validator->sanitize( [ 'count' => '3.7' ], $schema );
		$this->assertSame( 3, $result['count'] );
	}

	public function test_number_field_uses_intval_when_step_is_whole(): void {
		$schema = $this->make_schema( [
			[ 'id' => 'qty', 'type' => 'number', 'label' => 'Qty', 'attributes' => [ 'step' => '1' ] ],
		] );
		$result = $this->validator->sanitize( [ 'qty' => '5.9' ], $schema );
		$this->assertSame( 5, $result['qty'] );
	}

	public function test_number_field_uses_floatval_when_step_is_decimal(): void {
		$schema = $this->make_schema( [
			[ 'id' => 'opacity', 'type' => 'number', 'label' => 'Opacity', 'attributes' => [ 'step' => '0.1' ] ],
		] );
		$result = $this->validator->sanitize( [ 'opacity' => '0.7' ], $schema );
		$this->assertSame( 0.7, $result['opacity'] );
	}

	// -------------------------------------------------------------------------
	// Item 2: strict choice-field comparison
	// -------------------------------------------------------------------------

	public function test_select_accepts_valid_string_choice(): void {
		$schema = $this->make_schema( [
			[
				'id'      => 'color',
				'type'    => 'select',
				'label'   => 'Color',
				'default' => 'red',
				'choices' => [ 'red' => 'Red', 'blue' => 'Blue' ],
			],
		] );
		$result = $this->validator->sanitize( [ 'color' => 'blue' ], $schema );
		$this->assertSame( 'blue', $result['color'] );
	}

	public function test_select_falls_back_to_default_for_invalid_choice(): void {
		$schema = $this->make_schema( [
			[
				'id'      => 'color',
				'type'    => 'select',
				'label'   => 'Color',
				'default' => 'red',
				'choices' => [ 'red' => 'Red', 'blue' => 'Blue' ],
			],
		] );
		$result = $this->validator->sanitize( [ 'color' => 'green' ], $schema );
		$this->assertSame( 'red', $result['color'] );
	}

	public function test_radio_falls_back_to_default_for_invalid_choice(): void {
		$schema = $this->make_schema( [
			[
				'id'      => 'size',
				'type'    => 'radio',
				'label'   => 'Size',
				'default' => 'sm',
				'choices' => [ 'sm' => 'Small', 'lg' => 'Large' ],
			],
		] );
		$result = $this->validator->sanitize( [ 'size' => 'xl' ], $schema );
		$this->assertSame( 'sm', $result['size'] );
	}

	public function test_multicheck_filters_to_valid_choices_strictly(): void {
		$schema = $this->make_schema( [
			[
				'id'      => 'features',
				'type'    => 'multicheck',
				'label'   => 'Features',
				'choices' => [ 'a' => 'A', 'b' => 'B', 'c' => 'C' ],
			],
		] );
		$result = $this->validator->sanitize( [ 'features' => [ 'a', 'z', 'b' ] ], $schema );
		$this->assertSame( [ 'a', 'b' ], $result['features'] );
	}

	public function test_multicheck_returns_empty_array_for_non_array_input(): void {
		$schema = $this->make_schema( [
			[
				'id'      => 'features',
				'type'    => 'multicheck',
				'label'   => 'Features',
				'choices' => [ 'a' => 'A', 'b' => 'B' ],
			],
		] );
		$result = $this->validator->sanitize( [ 'features' => 'not-an-array' ], $schema );
		$this->assertSame( [], $result['features'] );
	}

	// -------------------------------------------------------------------------
	// Item 3: sanitize_callback type guard
	// -------------------------------------------------------------------------

	public function test_valid_sanitize_callback_result_is_used(): void {
		$schema = $this->make_schema( [
			[
				'id'                => 'custom',
				'type'              => 'text',
				'label'             => 'Custom',
				'sanitize_callback' => static fn( mixed $v ) => strtoupper( (string) $v ),
			],
		] );
		$result = $this->validator->sanitize( [ 'custom' => 'hello' ], $schema );
		$this->assertSame( 'HELLO', $result['custom'] );
	}

	public function test_sanitize_callback_returning_wrong_type_falls_back_to_builtin(): void {
		$schema = $this->make_schema( [
			[
				'id'                => 'bad_field',
				'type'              => 'text',
				'label'             => 'Bad',
				// Returning an array for a text field is the wrong type.
				'sanitize_callback' => static fn( mixed $v ) => [ 'wrong' => 'type' ],
			],
		] );
		$result = $this->validator->sanitize( [ 'bad_field' => 'hello world' ], $schema );
		// Built-in text sanitizer returns a string.
		$this->assertIsString( $result['bad_field'] );
	}

	public function test_multicheck_sanitize_callback_returning_array_is_accepted(): void {
		$schema = $this->make_schema( [
			[
				'id'                => 'flags',
				'type'              => 'multicheck',
				'label'             => 'Flags',
				'choices'           => [ 'a' => 'A', 'b' => 'B' ],
				'sanitize_callback' => static fn( mixed $v ) => [ 'a' ],
			],
		] );
		$result = $this->validator->sanitize( [ 'flags' => [ 'a', 'b' ] ], $schema );
		$this->assertSame( [ 'a' ], $result['flags'] );
	}

	// -------------------------------------------------------------------------
	// Other sanitizers
	// -------------------------------------------------------------------------

	public function test_checkbox_returns_bool_true_for_truthy_input(): void {
		$schema = $this->make_schema( [
			[ 'id' => 'enabled', 'type' => 'checkbox', 'label' => 'Enabled' ],
		] );
		$result = $this->validator->sanitize( [ 'enabled' => '1' ], $schema );
		$this->assertTrue( $result['enabled'] );
	}

	public function test_checkbox_returns_bool_false_for_zero_string(): void {
		$schema = $this->make_schema( [
			[ 'id' => 'enabled', 'type' => 'checkbox', 'label' => 'Enabled' ],
		] );
		$result = $this->validator->sanitize( [ 'enabled' => '0' ], $schema );
		$this->assertFalse( $result['enabled'] );
	}

	public function test_color_falls_back_to_default_for_invalid_hex(): void {
		$schema = $this->make_schema( [
			[ 'id' => 'bg', 'type' => 'color', 'label' => 'BG', 'default' => '#ffffff' ],
		] );
		$result = $this->validator->sanitize( [ 'bg' => 'not-a-color' ], $schema );
		$this->assertSame( '#ffffff', $result['bg'] );
	}

	public function test_display_only_fields_are_skipped(): void {
		$schema = $this->make_schema( [
			[ 'id' => 'my_heading', 'type' => 'heading', 'label' => 'Section' ],
			[ 'id' => 'my_text', 'type' => 'text', 'label' => 'Text' ],
		] );
		$result = $this->validator->sanitize( [ 'my_heading' => 'x', 'my_text' => 'hello' ], $schema );
		$this->assertArrayNotHasKey( 'my_heading', $result );
		$this->assertArrayHasKey( 'my_text', $result );
	}
}
