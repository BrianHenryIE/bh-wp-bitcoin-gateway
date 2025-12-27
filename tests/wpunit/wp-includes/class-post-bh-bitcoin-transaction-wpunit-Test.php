<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes;

use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use WP_Post_Type;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes\Post_BH_Bitcoin_Transaction
 */
class Post_BH_Bitcoin_Transaction_WPUnit_Test extends \lucatume\WPBrowser\TestCase\WPTestCase {
	protected function setUp(): void {
		parent::setUp();

		/** @var array<string, WP_Post_Type> $wp_post_types */
		global $wp_post_types;
		unset( $wp_post_types['bh-bitcoin-tx'] );
	}

	/**
	 * @covers ::register_transaction_post_type
	 * @covers ::__construct
	 */
	public function test_transaction_post_type_registered(): void {

		$api = $this->makeEmpty( API_Interface::class );

		$sut = new Post_BH_Bitcoin_Transaction( $api );

		assert( ! post_type_exists( 'bh-bitcoin-tx' ) );

		$sut->register_transaction_post_type();

		$this->assertTrue( post_type_exists( 'bh-bitcoin-tx' ) );
	}
}
