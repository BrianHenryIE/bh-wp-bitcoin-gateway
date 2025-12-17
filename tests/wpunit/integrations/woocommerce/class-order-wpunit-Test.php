<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Actions_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Scheduler_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Wallet_Repository;
use Codeception\Stub\Expected;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Order
 */
class Order_WPUnit_Test extends \lucatume\WPBrowser\TestCase\WPTestCase {

	protected function get_sut(
		?API_WooCommerce_Interface $api = null,
		?Bitcoin_Wallet_Repository $bitcoin_wallet_repository = null,
		?Bitcoin_Address_Repository $bitcoin_address_repository = null,
		?Background_Jobs_Scheduler_Interface $background_jobs_scheduler = null,
		?LoggerInterface $logger = null,
	): Order {
		return new Order(
			api: $api ?? $this->makeEmpty( API_WooCommerce_Interface::class ),
			bitcoin_wallet_repository: $bitcoin_wallet_repository ?? $this->makeEmpty( Bitcoin_Wallet_Repository::class ),
			bitcoin_address_repository: $bitcoin_address_repository ?? $this->makeEmpty( Bitcoin_Address_Repository::class ),
			background_jobs_scheduler: $background_jobs_scheduler ?? $this->makeEmpty( Background_Jobs_Scheduler_Interface::class ),
			logger: $logger ?? new ColorLogger(),
		);
	}

	/**
	 * @covers ::schedule_check_for_transactions
	 */
	public function test_schedule_check_for_transactions(): void {

		$background_jobs_scheduler_mock = $this->makeEmpty(
			Background_Jobs_Scheduler_Interface::class,
			array(
				'schedule_check_assigned_bitcoin_address_for_transactions' => Expected::once(),
			)
		);
		$api                            = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'is_order_has_bitcoin_gateway' => Expected::once( true ),
			)
		);

		$sut = $this->get_sut(
			api: $api,
			background_jobs_scheduler: $background_jobs_scheduler_mock,
		);

		$order    = new \WC_Order();
		$order_id = $order->save();

		$sut->schedule_check_for_transactions( $order_id, 'pending', 'on-hold' );
	}

	/**
	 * @covers ::schedule_check_for_transactions
	 */
	public function test_schedule_check_for_transactions_not_when_setting_to_other_status(): void {

		$api = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'is_order_has_bitcoin_gateway' => Expected::never(),
			)
		);

		$sut = $this->get_sut(
			api: $api,
		);

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

		$api = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'is_order_has_bitcoin_gateway' => Expected::once( false ),
			)
		);

		$sut = $this->get_sut(
			api: $api,
		);

		$order    = new \WC_Order();
		$order_id = $order->save();

		assert( false === as_has_scheduled_action( Background_Jobs_Actions_Interface::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK ) );

		$sut->schedule_check_for_transactions( $order_id, 'pending', 'on-hold' );

		$this->assertFalse( as_has_scheduled_action( Background_Jobs_Actions_Interface::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK ) );
	}
}
