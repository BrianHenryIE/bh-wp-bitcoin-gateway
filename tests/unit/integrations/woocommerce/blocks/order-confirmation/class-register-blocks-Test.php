<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Blocks\Order_Confirmation;

use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use Codeception\Stub\Expected;

class Register_Blocks_Test extends \Codeception\Test\Unit {

	/**
	 * @return array<array{0:class-string,1:string}>
	 */
	public static function data_provider_blocks(): array {
		return array(
			array(
				Bitcoin_Exchange_Rate_Block::class,
				'assets/js/frontend/woocommerce/blocks/order-confirmation/exchange-rate/',
			),
			array(
				Bitcoin_Order_Payment_Address_Block::class,
				'assets/js/frontend/woocommerce/blocks/order-confirmation/payment-address/',
			),
			array(
				Bitcoin_Order_Payment_Amount_Received_Block::class,
				'assets/js/frontend/woocommerce/blocks/order-confirmation/payment-amount-received/',
			),
			array(
				Bitcoin_Order_Payment_Last_Checked_Block::class,
				'assets/js/frontend/woocommerce/blocks/order-confirmation/payment-last-checked/',
			),
			array(
				Bitcoin_Order_Payment_Status_Block::class,
				'assets/js/frontend/woocommerce/blocks/order-confirmation/payment-status/',
			),
			array(
				Bitcoin_Order_Payment_Total_Block::class,
				'assets/js/frontend/woocommerce/blocks/order-confirmation/payment-total/',
			),
		);
	}

	/**
	 * @dataProvider data_provider_blocks
	 *
	 * @param string $class_name The class to instantiate.
	 * @param string $path Location of the built JS file.
	 */
	public function test_register_blocks( string $class_name, string $path ): void {
		\WP_Mock::userFunction(
			'register_block_type',
			array(
				'times' => 1,
				'args'  => array( '/path/to/plugins/' . $path ),
			)
		);

		$settings = $this->makeEmpty(
			Settings_Interface::class,
			array(
				'get_plugin_dir' => Expected::once( '/path/to/plugins/' ),
			)
		);
		$sut      = new $class_name( $settings );

		// TODO: interface.
		$sut->register_block();

		/** @var string $full_path */
		$full_path = codecept_root_dir( $path );
		$this->assertFileExists( $full_path );
	}
}
