<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use Codeception\Stub\Expected;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Menu
 */
class Menu_Unit_Test extends \Codeception\Test\Unit {

	protected function setUp(): void {
		parent::setUp();
		\WP_Mock::setUp();
	}

	protected function tearDown(): void {
		parent::tearDown();
		\WP_Mock::tearDown();
	}

	/**
	 * @covers ::add_woocommerce_payments_submenu
	 * @covers ::__construct
	 */
	public function test_single_gateway(): void {

		$api_mock = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'get_bitcoin_gateways' => Expected::once(
					array(
						$this->make( Bitcoin_Gateway::class ),
					)
				),
			)
		);

		$sut = new Menu( $api_mock );

		\WP_Mock::userFunction(
			'add_submenu_page',
			array(
				'times' => 1,
			)
		);

		$sut->add_woocommerce_payments_submenu();
	}

	/**
	 * @covers ::add_woocommerce_payments_submenu
	 * @covers ::__construct
	 */
	public function test_multiple_gateways(): void {

		$api_mock = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'get_bitcoin_gateways' => Expected::once(
					array(
						$this->make( Bitcoin_Gateway::class ),
						$this->make( Bitcoin_Gateway::class ),
						$this->make( Bitcoin_Gateway::class ),
					)
				),
			)
		);

		$sut = new Menu( $api_mock );

		\WP_Mock::userFunction(
			'add_submenu_page',
			array(
				'times' => 3,
			)
		);

		$sut->add_woocommerce_payments_submenu();
	}
}
