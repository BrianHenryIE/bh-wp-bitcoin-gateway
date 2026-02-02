<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Admin;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Admin\Register_List_Tables
 */
class Register_List_Tables_Unit_Test extends \Codeception\Test\Unit {

	protected function setUp(): void {
		parent::setUp();
		\WP_Mock::setUp();
	}

	#[\Override]
	protected function tearDown(): void {
		parent::tearDown();
		\WP_Mock::tearDown();
	}

	/**
	 * @covers ::register_bitcoin_wallet_table
	 */
	public function test_register_bitcoin_wallet_table(): void {
		$sut = new Register_List_Tables();

		$default_class = \WP_Posts_List_Table::class;

		\WP_Mock::userFunction(
			'post_type_exists',
			array(
				'times'  => 1,
				'args'   => array( 'edit-bh-bitcoin-wallet' ),
				'return' => false,
			)
		);

		\WP_Mock::userFunction(
			'post_type_exists',
			array(
				'times'  => 1,
				'args'   => array( 'bh-bitcoin-wallet' ),
				'return' => true,
			)
		);

		\WP_Mock::passthruFunction( 'sanitize_key' );

		\WP_Mock::userFunction(
			'taxonomy_exists',
			array(
				'times'  => 1,
				'return' => false,
			)
		);

		$screen = \WP_Screen::get( 'edit-bh-bitcoin-wallet' );

		$args = array(
			'screen' => $screen,
		);

		$result = $sut->register_bitcoin_wallet_table( $default_class, $args );

		$this->assertEquals( Wallets_List_Table::class, $result );
	}

	/**
	 * @covers ::register_bitcoin_wallet_table
	 */
	public function test_register_bitcoin_wallet_table_not_applicable(): void {
		$sut = new Register_List_Tables();

		$default_class = \WP_Posts_List_Table::class;

		$screen = \WP_Screen::get();

		$args = array(
			'screen' => $screen,
		);

		$result = $sut->register_bitcoin_wallet_table( $default_class, $args );

		$this->assertEquals( $default_class, $result );
	}


	/**
	 * @covers ::register_bitcoin_address_table
	 */
	public function test_register_bitcoin_address_table(): void {
		$sut = new Register_List_Tables();

		$default_class = \WP_Posts_List_Table::class;

		\WP_Mock::userFunction(
			'post_type_exists',
			array(
				'times'  => 1,
				'args'   => array( 'edit-bh-bitcoin-address' ),
				'return' => false,
			)
		);

		\WP_Mock::userFunction(
			'post_type_exists',
			array(
				'times'  => 1,
				'args'   => array( 'bh-bitcoin-address' ),
				'return' => true,
			)
		);

		\WP_Mock::passthruFunction( 'sanitize_key' );

		\WP_Mock::userFunction(
			'taxonomy_exists',
			array(
				'times'  => 1,
				'return' => false,
			)
		);

		$screen = \WP_Screen::get( 'edit-bh-bitcoin-address' );

		$args = array(
			'screen' => $screen,
		);

		$result = $sut->register_bitcoin_address_table( $default_class, $args );

		$this->assertEquals( Addresses_List_Table::class, $result );
	}

	/**
	 * @covers ::register_bitcoin_address_table
	 */
	public function test_register_bitcoin_address_table_not_applicable(): void {
		$sut = new Register_List_Tables();

		$default_class = \WP_Posts_List_Table::class;

		\WP_Mock::userFunction(
			'get_current_screen',
			array(
				'times'  => 1,
				'return' => false,
			)
		);

		$screen = \WP_Screen::get();

		$args = array(
			'screen' => $screen,
		);

		$result = $sut->register_bitcoin_address_table( $default_class, $args );

		$this->assertEquals( $default_class, $result );
	}
}
