<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Queries;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet_Status;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet_WP_Post_Interface;
use Codeception\Test\Unit;
use WP_Mock;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Queries\Bitcoin_Wallet_Query
 */
class Bitcoin_Wallet_Query_Unit_Test extends Unit {

	protected function setUp(): void {
		WP_Mock::setUp();

		// Mock WordPress functions.
		WP_Mock::userFunction(
			'sanitize_title',
			array(
				'return' => fn( string $title ) => strtolower(
					str_replace( array( ' ', '_', '/' ), '-', $title )
				),
			)
		);
		WP_Mock::userFunction(
			'wp_json_encode',
			array(
				'return' => fn( $value ) => json_encode( $value ),
			)
		);
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
	}

	/**
	 * Test constructor with no parameters.
	 *
	 * @covers ::__construct
	 * @covers ::get_post_type
	 */
	public function test_constructor_with_no_parameters(): void {
		$sut = new Bitcoin_Wallet_Query();

		$this->assertNull( $sut->master_public_key );
		$this->assertNull( $sut->status );
		$this->assertNull( $sut->gateway_refs );
		$this->assertNull( $sut->last_derived_address_index );
		$this->assertEquals( Bitcoin_Wallet_WP_Post_Interface::POST_TYPE, $sut->post_type );
	}

	/**
	 * Test constructor with master_public_key parameter.
	 *
	 * @covers ::__construct
	 * @covers ::to_query_array
	 * @covers ::get_wp_post_fields
	 * @covers ::get_meta_input
	 */
	public function test_constructor_with_master_public_key(): void {
		$xpub = 'xpub6CUGRUonZSQ4TWtTMmzXdrXDtypWKiKrhko4egpiMZbpiaQL2jkwSB1icqYh2cfDfVxdx4df189oLKnC5fSwqPfgyP3hooxujYzAu3fDVmz';
		$sut  = new Bitcoin_Wallet_Query( master_public_key: $xpub );

		$this->assertEquals( $xpub, $sut->master_public_key );

		$query_array = $sut->to_query_array();

		$this->assertArrayHasKey( 'post_title', $query_array );
		$this->assertArrayHasKey( 'post_name', $query_array );
		$this->assertEquals( $xpub, $query_array['post_title'] );
		$this->assertEquals( sanitize_title( $xpub ), $query_array['post_name'] );
		$this->assertArrayNotHasKey( 'master_public_key', $query_array );
	}

	/**
	 * Test constructor with status parameter.
	 *
	 * @covers ::__construct
	 * @covers ::to_query_array
	 * @covers ::get_wp_post_fields
	 * @covers ::get_meta_input
	 */
	public function test_constructor_with_status(): void {
		$sut = new Bitcoin_Wallet_Query( status: Bitcoin_Wallet_Status::ACTIVE );

		$this->assertEquals( Bitcoin_Wallet_Status::ACTIVE, $sut->status );

		$query_array = $sut->to_query_array();

		$this->assertArrayHasKey( 'post_status', $query_array );
		$this->assertEquals( Bitcoin_Wallet_Status::ACTIVE->value, $query_array['post_status'] );
		$this->assertArrayNotHasKey( 'status', $query_array );
	}

	/**
	 * Test constructor with gateway_refs parameter.
	 *
	 * @covers ::__construct
	 * @covers ::to_query_array
	 * @covers ::get_wp_post_fields
	 * @covers ::get_meta_input
	 */
	public function test_constructor_with_gateway_refs(): void {
		$gateway_refs = array(
			array(
				'integration' => get_class( $this ),
				'gateway_id'  => 'gateway_1',
			),
			array(
				'integration' => get_class( $this ),
				'gateway_id'  => 'gateway_2',
			),
		);
		$sut          = new Bitcoin_Wallet_Query( gateway_refs: $gateway_refs );

		$this->assertEquals( $gateway_refs, $sut->gateway_refs );

		$query_array = $sut->to_query_array();

		$this->assertArrayHasKey( 'meta_input', $query_array );
		$this->assertArrayHasKey( Bitcoin_Wallet_WP_Post_Interface::GATEWAYS_DETAILS_META_KEY, $query_array['meta_input'] );
		$this->assertEquals( json_encode( $gateway_refs ), $query_array['meta_input'][ Bitcoin_Wallet_WP_Post_Interface::GATEWAYS_DETAILS_META_KEY ] );
		$this->assertArrayNotHasKey( 'gateway_refs', $query_array );
	}

	/**
	 * Test constructor with last_derived_address_index parameter.
	 *
	 * @covers ::__construct
	 * @covers ::to_query_array
	 * @covers ::get_wp_post_fields
	 * @covers ::get_meta_input
	 */
	public function test_constructor_with_last_derived_address_index(): void {
		$sut = new Bitcoin_Wallet_Query( last_derived_address_index: 42 );

		$this->assertEquals( 42, $sut->last_derived_address_index );

		$query_array = $sut->to_query_array();

		$this->assertArrayHasKey( 'meta_input', $query_array );
		$this->assertArrayHasKey( Bitcoin_Wallet_WP_Post_Interface::LAST_DERIVED_ADDRESS_INDEX_META_KEY, $query_array['meta_input'] );
		$this->assertEquals( 42, $query_array['meta_input'][ Bitcoin_Wallet_WP_Post_Interface::LAST_DERIVED_ADDRESS_INDEX_META_KEY ] );
		$this->assertArrayNotHasKey( 'last_derived_address_index', $query_array );
	}

	/**
	 * Test constructor with all parameters.
	 *
	 * @covers ::__construct
	 * @covers ::to_query_array
	 * @covers ::get_wp_post_fields
	 * @covers ::get_meta_input
	 */
	public function test_constructor_with_all_parameters(): void {
		$xpub         = 'xpub6BosfCnifzxcFwrSzQiqu2DBVTshkCXacvNsWGYJVVhhawA7d4R5WSWGFNbi8Aw6ZRc1brxMyWMzG3DSSSSoekkudhUd9yLb6qx39T9nMdj';
		$status       = Bitcoin_Wallet_Status::ACTIVE;
		$gateway_refs = array(
			array(
				'integration' => get_class( $this ),
				'gateway_id'  => 'gateway_1',
			),
			array(
				'integration' => get_class( $this ),
				'gateway_id'  => 'gateway_2',
			),
		);
		$index        = 100;

		$sut = new Bitcoin_Wallet_Query(
			master_public_key: $xpub,
			status: $status,
			gateway_refs: $gateway_refs,
			last_derived_address_index: $index
		);

		$this->assertEquals( $xpub, $sut->master_public_key );
		$this->assertEquals( $status, $sut->status );
		$this->assertEquals( $gateway_refs, $sut->gateway_refs );
		$this->assertEquals( $index, $sut->last_derived_address_index );

		$query_array = $sut->to_query_array();

		// Check post fields.
		$this->assertArrayHasKey( 'post_type', $query_array );
		$this->assertArrayHasKey( 'post_title', $query_array );
		$this->assertArrayHasKey( 'post_name', $query_array );
		$this->assertArrayHasKey( 'post_status', $query_array );

		$this->assertEquals( Bitcoin_Wallet_WP_Post_Interface::POST_TYPE, $query_array['post_type'] );
		$this->assertEquals( $xpub, $query_array['post_title'] );
		$this->assertEquals( sanitize_title( $xpub ), $query_array['post_name'] );
		$this->assertEquals( $status->value, $query_array['post_status'] );

		// Check meta_input.
		$this->assertArrayHasKey( 'meta_input', $query_array );
		$this->assertArrayHasKey( Bitcoin_Wallet_WP_Post_Interface::GATEWAYS_DETAILS_META_KEY, $query_array['meta_input'] );
		$this->assertArrayHasKey( Bitcoin_Wallet_WP_Post_Interface::LAST_DERIVED_ADDRESS_INDEX_META_KEY, $query_array['meta_input'] );
		$this->assertEquals( json_encode( $gateway_refs ), $query_array['meta_input'][ Bitcoin_Wallet_WP_Post_Interface::GATEWAYS_DETAILS_META_KEY ] );
		$this->assertEquals( $index, $query_array['meta_input'][ Bitcoin_Wallet_WP_Post_Interface::LAST_DERIVED_ADDRESS_INDEX_META_KEY ] );

		// Ensure original property names are not in the query array.
		$this->assertArrayNotHasKey( 'master_public_key', $query_array );
		$this->assertArrayNotHasKey( 'status', $query_array );
		$this->assertArrayNotHasKey( 'gateway_refs', $query_array );
		$this->assertArrayNotHasKey( 'last_derived_address_index', $query_array );
	}

	/**
	 * Test constructor with master_public_key and status.
	 *
	 * @covers ::__construct
	 * @covers ::to_query_array
	 * @covers ::get_wp_post_fields
	 * @covers ::get_meta_input
	 */
	public function test_constructor_with_master_public_key_and_status(): void {
		$xpub = 'xpub6ASuArnXKPbfEwhqN6e3mwBcDTgzisQN1wXN9BJcM47sSikHjJf3UFHKkNAWbWMiGj7Wf5uMash7SyYq527Hqck2AxYysAA7xmALppuCkwQ';
		$sut  = new Bitcoin_Wallet_Query(
			master_public_key: $xpub,
			status: Bitcoin_Wallet_Status::INACTIVE
		);

		$query_array = $sut->to_query_array();

		$this->assertEquals( $xpub, $query_array['post_title'] );
		$this->assertEquals( Bitcoin_Wallet_Status::INACTIVE->value, $query_array['post_status'] );
		$this->assertArrayNotHasKey( 'meta_input', $query_array );
	}

	/**
	 * Test that status enum is converted to its string value.
	 *
	 * @covers ::to_query_array
	 * @covers ::get_wp_post_fields
	 */
	public function test_status_enum_conversion(): void {
		$statuses = array(
			Bitcoin_Wallet_Status::ACTIVE,
			Bitcoin_Wallet_Status::INACTIVE,
			Bitcoin_Wallet_Status::DRAFT,
			Bitcoin_Wallet_Status::PUBLISH,
		);

		foreach ( $statuses as $status ) {
			$sut         = new Bitcoin_Wallet_Query( status: $status );
			$query_array = $sut->to_query_array();

			$this->assertEquals( $status->value, $query_array['post_status'] );
		}
	}

	/**
	 * Test that post_type is always set correctly.
	 *
	 * @covers ::to_query_array
	 * @covers ::get_post_type
	 */
	public function test_post_type_always_set(): void {
		$sut         = new Bitcoin_Wallet_Query();
		$query_array = $sut->to_query_array();

		$this->assertArrayHasKey( 'post_type', $query_array );
		$this->assertEquals( Bitcoin_Wallet_WP_Post_Interface::POST_TYPE, $query_array['post_type'] );
	}

	/**
	 * Test that empty meta_input is not included in query array.
	 *
	 * @covers ::to_query_array
	 * @covers ::get_meta_input
	 */
	public function test_empty_meta_input_not_included(): void {
		$sut         = new Bitcoin_Wallet_Query( master_public_key: 'xpub_test' );
		$query_array = $sut->to_query_array();

		$this->assertArrayNotHasKey( 'meta_input', $query_array );
	}

	/**
	 * Test that to_query_array can be called multiple times (idempotent).
	 *
	 * @covers ::to_query_array
	 */
	public function test_to_query_array_idempotent(): void {
		$sut = new Bitcoin_Wallet_Query(
			master_public_key: 'xpub_idempotent',
			status: Bitcoin_Wallet_Status::ACTIVE,
			gateway_refs: array(
				array(
					'integration' => get_class( $this ),
					'gateway_id'  => 'single_gateway',
				),
			),
			last_derived_address_index: 50
		);

		$query_array_1 = $sut->to_query_array();
		$query_array_2 = $sut->to_query_array();

		$this->assertEquals( $query_array_1, $query_array_2 );
	}

	/**
	 * Test that readonly properties cannot be modified after construction.
	 *
	 * @covers ::__construct
	 */
	public function test_readonly_properties(): void {
		$sut = new Bitcoin_Wallet_Query( last_derived_address_index: 10 );

		$this->expectException( \Error::class );
		$this->expectExceptionMessage( 'Cannot modify readonly property' );

		// Attempt to modify readonly property should throw Error.
		/* @phpstan-ignore property.readOnlyAssignOutOfClass */
		$sut->last_derived_address_index = 20;
	}

	/**
	 * Test sanitize_title is applied to master_public_key for post_name.
	 *
	 * @covers ::to_query_array
	 * @covers ::get_wp_post_fields
	 */
	public function test_master_public_key_sanitization(): void {
		$xpub        = 'xpub6D4BDPcP2GT577Vvch3R8wDkScZWzQzMMUm3PWbmWvVJrZwQY4VUNgqFJPMM3No2dFDFGTsxxpG5uJh7n7epu4trkrX7x7DogT5Uv6fcLW5';
		$sut         = new Bitcoin_Wallet_Query( master_public_key: $xpub );
		$query_array = $sut->to_query_array();

		$this->assertEquals( $xpub, $query_array['post_title'] );
		$this->assertEquals( sanitize_title( $xpub ), $query_array['post_name'] );
		$this->assertIsString( $query_array['post_name'] );
	}

	/**
	 * Test gateway_refs with single gateway.
	 *
	 * @covers ::to_query_array
	 * @covers ::get_meta_input
	 */
	public function test_gateway_refs_single_gateway(): void {
		$gateway_refs = array(
			array(
				'integration' => get_class( $this ),
				'gateway_id'  => 'single_gateway',
			),
		);
		$sut          = new Bitcoin_Wallet_Query( gateway_refs: $gateway_refs );
		$query_array  = $sut->to_query_array();

		$this->assertArrayHasKey( 'meta_input', $query_array );
		$this->assertArrayHasKey( Bitcoin_Wallet_WP_Post_Interface::GATEWAYS_DETAILS_META_KEY, $query_array['meta_input'] );
		$this->assertEquals( json_encode( $gateway_refs ), $query_array['meta_input'][ Bitcoin_Wallet_WP_Post_Interface::GATEWAYS_DETAILS_META_KEY ] );
	}

	/**
	 * Test gateway_refs with multiple gateways.
	 *
	 * @covers ::to_query_array
	 * @covers ::get_meta_input
	 */
	public function test_gateway_refs_multiple_gateways(): void {
		$gateway_refs = array(
			array(
				'integration' => get_class( $this ),
				'gateway_id'  => 'gateway_1',
			),
			array(
				'integration' => get_class( $this ),
				'gateway_id'  => 'gateway_2',
			),
			array(
				'integration' => get_class( $this ),
				'gateway_id'  => 'gateway_3',
			),
		);

		$sut         = new Bitcoin_Wallet_Query( gateway_refs: $gateway_refs );
		$query_array = $sut->to_query_array();

		$this->assertArrayHasKey( 'meta_input', $query_array );
		$this->assertArrayHasKey( Bitcoin_Wallet_WP_Post_Interface::GATEWAYS_DETAILS_META_KEY, $query_array['meta_input'] );
	}

	/**
	 * Test last_derived_address_index with zero value.
	 * Note: Zero is filtered out by array_filter in the abstract class, so meta_input won't be set.
	 *
	 * @covers ::to_query_array
	 * @covers ::get_meta_input
	 */
	public function test_last_derived_address_index_zero(): void {
		$sut         = new Bitcoin_Wallet_Query( master_public_key: 'abc123' );
		$query_array = $sut->to_query_array();

		// Zero is a falsy value and gets filtered out by array_filter.
		$this->assertArrayNotHasKey( 'meta_input', $query_array );
	}

	/**
	 * Test only meta_input fields are set when no post fields provided.
	 *
	 * @covers ::to_query_array
	 * @covers ::get_meta_input
	 */
	public function test_only_meta_input_fields(): void {
		$sut = new Bitcoin_Wallet_Query(
			last_derived_address_index: 5
		);

		$query_array = $sut->to_query_array();

		$this->assertArrayHasKey( 'post_type', $query_array );
		$this->assertArrayHasKey( 'meta_input', $query_array );
		$this->assertArrayNotHasKey( 'post_title', $query_array );
		$this->assertArrayNotHasKey( 'post_name', $query_array );
		$this->assertArrayNotHasKey( 'post_status', $query_array );
	}
}
