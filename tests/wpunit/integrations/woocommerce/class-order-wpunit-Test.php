<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Actions_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Scheduler_Interface;
use Codeception\Stub\Expected;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Order
 */
class Order_WPUnit_Test extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * @covers ::schedule_check_for_transactions
	 */
	public function test_schedule_check_for_transactions(): void {

		$logger               = new ColorLogger();
		$background_jobs_mock = $this->makeEmpty(
			Background_Jobs_Scheduler_Interface::class,
			array(
				'schedule_check_assigned_bitcoin_address_for_transactions' => Expected::once(),
			)
		);
		$api                  = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'is_order_has_bitcoin_gateway' => Expected::once( true ),
			)
		);

		$sut = new Order( $api, $background_jobs_mock, $logger );

		$order    = new \WC_Order();
		$order_id = $order->save();

		$sut->schedule_check_for_transactions( $order_id, 'pending', 'on-hold' );
	}

	/**
	 * @covers ::schedule_check_for_transactions
	 */
	public function test_schedule_check_for_transactions_not_when_setting_to_other_status(): void {

		$logger               = new ColorLogger();
		$background_jobs_mock = $this->makeEmpty( Background_Jobs_Scheduler_Interface::class );
		$api                  = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'is_order_has_bitcoin_gateway' => Expected::never(),
			)
		);

		$sut = new Order( $api, $background_jobs_mock, $logger );

		$order    = new \WC_Order();
		$order_id = $order->save();

		assert( false === as_has_scheduled_action( Background_Jobs_Actions_Interface::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK ) );

		$sut->schedule_check_for_transactions( $order_id, 'pending', 'processing' );

		$this->assertFalse( as_has_scheduled_action( Background_Jobs_Actions_Interface::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK ) );
	}

	/**
	 * @covers ::schedule_check_for_transactions
	 */
	public function test_schedule_check_for_transactions_not_when_not_bitcoin_gateway(): void {

		$logger               = new ColorLogger();
		$background_jobs_mock = $this->makeEmpty( Background_Jobs_Scheduler_Interface::class );
		$api                  = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'is_order_has_bitcoin_gateway' => Expected::once( false ),
			)
		);

		$sut = new Order( $api, $background_jobs_mock, $logger );

		$order    = new \WC_Order();
		$order_id = $order->save();

		assert( false === as_has_scheduled_action( Background_Jobs_Actions_Interface::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK ) );

		$sut->schedule_check_for_transactions( $order_id, 'pending', 'on-hold' );

		$this->assertFalse( as_has_scheduled_action( Background_Jobs_Actions_Interface::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK ) );
	}
}
