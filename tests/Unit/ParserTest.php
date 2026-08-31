<?php
/**
 * Tests for Parser.
 *
 * @package Optiz
 */

declare(strict_types=1);

namespace Nilambar\Optiz\Tests\Unit;

use Nilambar\Optiz\Parser;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * @covers \Nilambar\Optiz\Parser
 */
class ParserTest extends TestCase {

	private Parser $parser;
	private array $valid_schema;

	protected function setUp(): void {
		$this->parser       = new Parser();
		$this->valid_schema = [
			'option_key' => 'my_plugin_options',
			'pages'      => [
				[
					'id'        => 'general',
					'title'     => 'My Plugin Settings',
					'menu_slug' => 'my-plugin-settings',
					'tabs'      => [
						[
							'id'     => 'general',
							'label'  => 'General',
							'fields' => [
								[
									'id'    => 'my_text',
									'type'  => 'text',
									'label' => 'My Text',
								],
							],
						],
					],
				],
			],
		];
	}

	public function test_valid_schema_parses_successfully(): void {
		$result = $this->parser->parse( $this->valid_schema );
		$this->assertIsArray( $result );
		$this->assertSame( 'my_plugin_options', $result['option_key'] );
	}

	public function test_missing_option_key_returns_wp_error(): void {
		$schema = $this->valid_schema;
		unset( $schema['option_key'] );
		$result = $this->parser->parse( $schema );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'missing_option_key', $result->get_error_code() );
	}

	public function test_missing_pages_returns_wp_error(): void {
		$schema = $this->valid_schema;
		unset( $schema['pages'] );
		$result = $this->parser->parse( $schema );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'missing_pages', $result->get_error_code() );
	}

	public function test_missing_page_id_returns_wp_error(): void {
		$schema = $this->valid_schema;
		unset( $schema['pages'][0]['id'] );
		$result = $this->parser->parse( $schema );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'missing_page_id', $result->get_error_code() );
	}

	public function test_missing_page_title_returns_wp_error(): void {
		$schema = $this->valid_schema;
		unset( $schema['pages'][0]['title'] );
		$result = $this->parser->parse( $schema );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'missing_page_title', $result->get_error_code() );
	}

	public function test_missing_page_menu_slug_returns_wp_error(): void {
		$schema = $this->valid_schema;
		unset( $schema['pages'][0]['menu_slug'] );
		$result = $this->parser->parse( $schema );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'missing_page_menu_slug', $result->get_error_code() );
	}

	public function test_missing_tabs_returns_wp_error(): void {
		$schema = $this->valid_schema;
		unset( $schema['pages'][0]['tabs'] );
		$result = $this->parser->parse( $schema );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'missing_tabs', $result->get_error_code() );
	}

	public function test_invalid_field_type_returns_wp_error(): void {
		$schema                                            = $this->valid_schema;
		$schema['pages'][0]['tabs'][0]['fields'][0]['type'] = 'nonexistent_type';
		$result                                             = $this->parser->parse( $schema );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_field_type', $result->get_error_code() );
	}

	public function test_choice_integer_keys_are_cast_to_string(): void {
		$schema                              = $this->valid_schema;
		$schema['pages'][0]['tabs'][0]['fields'][0] = [
			'id'      => 'my_select',
			'type'    => 'select',
			'label'   => 'My Select',
			'choices' => [ 1 => 'One', 2 => 'Two' ],
		];
		$result  = $this->parser->parse( $schema );
		$choices = $result['pages'][0]['tabs'][0]['fields'][0]['choices'];
		$this->assertArrayHasKey( '1', $choices );
		$this->assertArrayHasKey( '2', $choices );
		$this->assertSame( 'One', $choices['1'] );
	}

	public function test_flat_condition_is_wrapped_in_array(): void {
		$schema                                       = $this->valid_schema;
		$schema['pages'][0]['tabs'][0]['fields'][]    = [
			'id'         => 'dependent_field',
			'type'       => 'text',
			'label'      => 'Dependent',
			'conditions' => [ 'field' => 'my_text', 'value' => 'foo' ],
		];
		$result     = $this->parser->parse( $schema );
		$conditions = $result['pages'][0]['tabs'][0]['fields'][1]['conditions'];
		$this->assertIsArray( $conditions );
		$this->assertCount( 1, $conditions );
		$this->assertSame( 'my_text', $conditions[0]['field'] );
		$this->assertSame( 'foo', $conditions[0]['value'] );
		$this->assertSame( '===', $conditions[0]['compare'] );
	}

	public function test_page_defaults_are_applied(): void {
		$result = $this->parser->parse( $this->valid_schema );
		$page   = $result['pages'][0];
		$this->assertSame( 'manage_options', $page['capability'] );
		$this->assertSame( '', $page['icon_url'] );
		$this->assertSame( 0, $page['position'] );
		$this->assertSame( '', $page['parent_slug'] );
	}

	public function test_page_id_is_sanitized(): void {
		$schema                    = $this->valid_schema;
		$schema['pages'][0]['id'] = 'General Page!';
		$result                    = $this->parser->parse( $schema );
		$this->assertSame( 'generalpage', $result['pages'][0]['id'] );
	}

	public function test_page_position_defaults_to_array_index(): void {
		$schema                   = $this->valid_schema;
		$schema['pages'][]        = [
			'id'        => 'second',
			'title'     => 'Second Page',
			'menu_slug' => 'second-page',
			'tabs'      => [
				[
					'id'     => 'tab',
					'label'  => 'Tab',
					'fields' => [
						[ 'id' => 'other_field', 'type' => 'text', 'label' => 'Other' ],
					],
				],
			],
		];
		$result = $this->parser->parse( $schema );
		$this->assertSame( 0, $result['pages'][0]['position'] );
		$this->assertSame( 1, $result['pages'][1]['position'] );
	}

	public function test_explicit_page_position_overrides_array_index(): void {
		$schema                             = $this->valid_schema;
		$schema['pages'][0]['position']     = 50;
		$result                             = $this->parser->parse( $schema );
		$this->assertSame( 50, $result['pages'][0]['position'] );
	}

	public function test_duplicate_page_id_returns_wp_error(): void {
		$schema             = $this->valid_schema;
		$schema['pages'][]  = [
			'id'        => 'general',
			'title'     => 'Duplicate Page',
			'menu_slug' => 'duplicate-page',
			'tabs'      => [
				[
					'id'     => 'tab',
					'label'  => 'Tab',
					'fields' => [
						[ 'id' => 'other_field', 'type' => 'text', 'label' => 'Other' ],
					],
				],
			],
		];
		$result = $this->parser->parse( $schema );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'duplicate_page_id', $result->get_error_code() );
	}

	public function test_duplicate_menu_slug_returns_wp_error(): void {
		$schema            = $this->valid_schema;
		$schema['pages'][] = [
			'id'        => 'second',
			'title'     => 'Second Page',
			'menu_slug' => 'my-plugin-settings',
			'tabs'      => [
				[
					'id'     => 'tab',
					'label'  => 'Tab',
					'fields' => [
						[ 'id' => 'other_field', 'type' => 'text', 'label' => 'Other' ],
					],
				],
			],
		];
		$result = $this->parser->parse( $schema );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'duplicate_menu_slug', $result->get_error_code() );
	}

	public function test_duplicate_field_id_across_pages_returns_wp_error_listing_page_ids(): void {
		$schema            = $this->valid_schema;
		$schema['pages'][] = [
			'id'        => 'second',
			'title'     => 'Second Page',
			'menu_slug' => 'second-page',
			'tabs'      => [
				[
					'id'     => 'tab',
					'label'  => 'Tab',
					'fields' => [
						[ 'id' => 'my_text', 'type' => 'text', 'label' => 'Duplicate' ],
					],
				],
			],
		];
		$result = $this->parser->parse( $schema );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'duplicate_field_id', $result->get_error_code() );
		$this->assertStringContainsString( 'my_text', $result->get_error_message() );
		$this->assertStringContainsString( 'general', $result->get_error_message() );
		$this->assertStringContainsString( 'second', $result->get_error_message() );
	}

	public function test_field_page_map_is_built_across_pages(): void {
		$schema            = $this->valid_schema;
		$schema['pages'][] = [
			'id'        => 'second',
			'title'     => 'Second Page',
			'menu_slug' => 'second-page',
			'tabs'      => [
				[
					'id'     => 'tab',
					'label'  => 'Tab',
					'fields' => [
						[ 'id' => 'other_field', 'type' => 'text', 'label' => 'Other' ],
					],
				],
			],
		];
		$result = $this->parser->parse( $schema );
		$this->assertSame( 'general', $result['field_page_map']['my_text'] );
		$this->assertSame( 'second', $result['field_page_map']['other_field'] );
	}

	public function test_autoload_defaults_to_null(): void {
		$result = $this->parser->parse( $this->valid_schema );
		$this->assertNull( $result['autoload'] );
	}

	public function test_autoload_is_passed_through_as_bool(): void {
		$schema              = $this->valid_schema;
		$schema['autoload'] = false;
		$result              = $this->parser->parse( $schema );
		$this->assertFalse( $result['autoload'] );
	}

	public function test_field_defaults_are_set(): void {
		$result = $this->parser->parse( $this->valid_schema );
		$field  = $result['pages'][0]['tabs'][0]['fields'][0];
		$this->assertSame( '', $field['default'] );
		$this->assertSame( '', $field['description'] );
		$this->assertSame( [], $field['attributes'] );
		$this->assertSame( [], $field['choices'] );
		$this->assertSame( [], $field['conditions'] );
		$this->assertNull( $field['sanitize_callback'] );
	}

	public function test_boolean_field_default_is_false(): void {
		$schema                                      = $this->valid_schema;
		$schema['pages'][0]['tabs'][0]['fields'][0] = [
			'id'    => 'my_toggle',
			'type'  => 'toggle',
			'label' => 'My Toggle',
		];
		$result = $this->parser->parse( $schema );
		$this->assertFalse( $result['pages'][0]['tabs'][0]['fields'][0]['default'] );
	}

	public function test_multicheck_field_default_is_empty_array(): void {
		$schema                                      = $this->valid_schema;
		$schema['pages'][0]['tabs'][0]['fields'][0] = [
			'id'      => 'my_multicheck',
			'type'    => 'multicheck',
			'label'   => 'My Multicheck',
			'choices' => [ 'a' => 'A', 'b' => 'B' ],
		];
		$result = $this->parser->parse( $schema );
		$this->assertSame( [], $result['pages'][0]['tabs'][0]['fields'][0]['default'] );
	}

	public function test_code_field_mode_defaults_to_text(): void {
		$schema                                      = $this->valid_schema;
		$schema['pages'][0]['tabs'][0]['fields'][0] = [
			'id'    => 'my_code',
			'type'  => 'code',
			'label' => 'My Code',
		];
		$result = $this->parser->parse( $schema );
		$this->assertSame( 'text', $result['pages'][0]['tabs'][0]['fields'][0]['mode'] );
	}

	public function test_radio_field_layout_defaults_to_vertical(): void {
		$schema                                      = $this->valid_schema;
		$schema['pages'][0]['tabs'][0]['fields'][0] = [
			'id'      => 'my_radio',
			'type'    => 'radio',
			'label'   => 'My Radio',
			'choices' => [ 'a' => 'A' ],
		];
		$result = $this->parser->parse( $schema );
		$this->assertSame( 'vertical', $result['pages'][0]['tabs'][0]['fields'][0]['layout'] );
	}
}
