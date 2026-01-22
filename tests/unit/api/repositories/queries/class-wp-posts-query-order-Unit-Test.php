<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Queries;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Queries\WP_Posts_Query_Order
 */
class WP_Posts_Query_Order_Unit_Test extends \Codeception\Test\Unit {

	/**
	 * Test constructor with no parameters returns empty query array.
	 *
	 * @covers ::__construct
	 * @covers ::to_query_array
	 */
	public function test_constructor_with_no_parameters(): void {
		$sut = new WP_Posts_Query_Order();

		$this->assertNull( $sut->count );
		$this->assertNull( $sut->order_by );
		$this->assertNull( $sut->order_direction );

		$query_array = $sut->to_query_array();

		$this->assertEmpty( $query_array );
	}

	/**
	 * Test constructor with count parameter maps to numberposts.
	 *
	 * @covers ::__construct
	 * @covers ::to_query_array
	 */
	public function test_constructor_with_count(): void {
		$sut = new WP_Posts_Query_Order( count: 10 );

		$this->assertEquals( 10, $sut->count );

		$query_array = $sut->to_query_array();

		$this->assertArrayHasKey( 'numberposts', $query_array );
		$this->assertEquals( 10, $query_array['numberposts'] );
		$this->assertArrayNotHasKey( 'count', $query_array );
	}

	/**
	 * Test constructor with order_by parameter maps to orderby.
	 *
	 * @covers ::__construct
	 * @covers ::to_query_array
	 */
	public function test_constructor_with_order_by(): void {
		$sut = new WP_Posts_Query_Order( order_by: 'post_modified' );

		$this->assertEquals( 'post_modified', $sut->order_by );

		$query_array = $sut->to_query_array();

		$this->assertArrayHasKey( 'orderby', $query_array );
		$this->assertEquals( 'post_modified', $query_array['orderby'] );
		$this->assertArrayNotHasKey( 'order_by', $query_array );
	}

	/**
	 * Test constructor with order_direction parameter maps to order.
	 *
	 * @covers ::__construct
	 * @covers ::to_query_array
	 */
	public function test_constructor_with_order_direction(): void {
		$sut = new WP_Posts_Query_Order( order_direction: 'ASC' );

		$this->assertEquals( 'ASC', $sut->order_direction );

		$query_array = $sut->to_query_array();

		$this->assertArrayHasKey( 'order', $query_array );
		$this->assertEquals( 'ASC', $query_array['order'] );
		$this->assertArrayNotHasKey( 'order_direction', $query_array );
	}

	/**
	 * Test constructor with all parameters.
	 *
	 * @covers ::__construct
	 * @covers ::to_query_array
	 */
	public function test_constructor_with_all_parameters(): void {
		$sut = new WP_Posts_Query_Order(
			count: 50,
			order_by: 'post_date',
			order_direction: 'DESC'
		);

		$this->assertEquals( 50, $sut->count );
		$this->assertEquals( 'post_date', $sut->order_by );
		$this->assertEquals( 'DESC', $sut->order_direction );

		$query_array = $sut->to_query_array();

		$this->assertArrayHasKey( 'numberposts', $query_array );
		$this->assertArrayHasKey( 'orderby', $query_array );
		$this->assertArrayHasKey( 'order', $query_array );

		$this->assertEquals( 50, $query_array['numberposts'] );
		$this->assertEquals( 'post_date', $query_array['orderby'] );
		$this->assertEquals( 'DESC', $query_array['order'] );

		$this->assertArrayNotHasKey( 'count', $query_array );
		$this->assertArrayNotHasKey( 'order_by', $query_array );
		$this->assertArrayNotHasKey( 'order_direction', $query_array );
	}

	/**
	 * Test count and order_by combination.
	 *
	 * @covers ::__construct
	 * @covers ::to_query_array
	 */
	public function test_constructor_with_count_and_order_by(): void {
		$sut = new WP_Posts_Query_Order(
			count: 25,
			order_by: 'title'
		);

		$query_array = $sut->to_query_array();

		$this->assertCount( 2, $query_array );
		$this->assertEquals( 25, $query_array['numberposts'] );
		$this->assertEquals( 'title', $query_array['orderby'] );
		$this->assertArrayNotHasKey( 'order', $query_array );
	}

	/**
	 * Test count and order_direction combination.
	 *
	 * @covers ::__construct
	 * @covers ::to_query_array
	 */
	public function test_constructor_with_count_and_order_direction(): void {
		$sut = new WP_Posts_Query_Order(
			count: 100,
			order_direction: 'ASC'
		);

		$query_array = $sut->to_query_array();

		$this->assertCount( 2, $query_array );
		$this->assertEquals( 100, $query_array['numberposts'] );
		$this->assertEquals( 'ASC', $query_array['order'] );
		$this->assertArrayNotHasKey( 'orderby', $query_array );
	}

	/**
	 * Test order_by and order_direction combination.
	 *
	 * @covers ::__construct
	 * @covers ::to_query_array
	 */
	public function test_constructor_with_order_by_and_order_direction(): void {
		$sut = new WP_Posts_Query_Order(
			order_by: 'post_modified',
			order_direction: 'DESC'
		);

		$query_array = $sut->to_query_array();

		$this->assertCount( 2, $query_array );
		$this->assertEquals( 'post_modified', $query_array['orderby'] );
		$this->assertEquals( 'DESC', $query_array['order'] );
		$this->assertArrayNotHasKey( 'numberposts', $query_array );
	}

	/**
	 * Test with maximum count value.
	 *
	 * @covers ::__construct
	 * @covers ::to_query_array
	 */
	public function test_constructor_with_max_count(): void {
		$sut = new WP_Posts_Query_Order( count: 200 );

		$query_array = $sut->to_query_array();

		$this->assertEquals( 200, $query_array['numberposts'] );
	}

	/**
	 * Test with count of 1 (minimum useful value).
	 *
	 * @covers ::__construct
	 * @covers ::to_query_array
	 */
	public function test_constructor_with_count_one(): void {
		$sut = new WP_Posts_Query_Order( count: 1 );

		$query_array = $sut->to_query_array();

		$this->assertEquals( 1, $query_array['numberposts'] );
	}

	/**
	 * Test that readonly properties cannot be modified after construction.
	 *
	 * @covers ::__construct
	 */
	public function test_readonly_properties(): void {
		$sut = new WP_Posts_Query_Order( count: 10 );

		$this->expectException( \Error::class );
		$this->expectExceptionMessage( 'Cannot modify readonly property' );

		// Attempt to modify readonly property should throw Error.
		$sut->count = 20;
	}

	/**
	 * Test various order_by values that WordPress supports.
	 *
	 * @covers ::__construct
	 * @covers ::to_query_array
	 */
	public function test_various_order_by_values(): void {
		$order_by_values = array( 'ID', 'post_date', 'post_title', 'post_name', 'post_modified', 'rand' );

		foreach ( $order_by_values as $order_by_value ) {
			$sut = new WP_Posts_Query_Order( order_by: $order_by_value );

			$query_array = $sut->to_query_array();

			$this->assertEquals( $order_by_value, $query_array['orderby'] );
		}
	}

	/**
	 * Test ASC and DESC order directions.
	 *
	 * @covers ::__construct
	 * @covers ::to_query_array
	 */
	public function test_asc_and_desc_order_directions(): void {
		$sut_asc         = new WP_Posts_Query_Order( order_direction: 'ASC' );
		$query_array_asc = $sut_asc->to_query_array();
		$this->assertEquals( 'ASC', $query_array_asc['order'] );

		$sut_desc         = new WP_Posts_Query_Order( order_direction: 'DESC' );
		$query_array_desc = $sut_desc->to_query_array();
		$this->assertEquals( 'DESC', $query_array_desc['order'] );
	}

	/**
	 * Test that to_query_array can be called multiple times.
	 *
	 * @covers ::to_query_array
	 */
	public function test_to_query_array_idempotent(): void {
		$sut = new WP_Posts_Query_Order(
			count: 30,
			order_by: 'post_date',
			order_direction: 'ASC'
		);

		$query_array_1 = $sut->to_query_array();
		$query_array_2 = $sut->to_query_array();

		$this->assertEquals( $query_array_1, $query_array_2 );
	}
}
