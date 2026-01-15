<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Scheduler_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address_Status;
use BrianHenryIE\WP_Bitcoin_Gateway\API\API;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Results\Addresses_Generation_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Results\Update_Address_Transactions_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Bitcoin_Wallet_Service;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Exchange_Rate_Service;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Payment_Service;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Results\Check_Address_For_Payment_Service_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Results\Get_Wallet_For_Xpub_Service_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use Codeception\Stub\Expected;
use Psr\Log\LoggerInterface;
use WC_Order;
use WC_Payment_Gateway;
use WC_Payment_Gateways;

/**
 * @see \BrianHenryIE\WP_Bitcoin_Gateway\API\API
 * @see API_WooCommerce_Trait
 * @see API_WooCommerce_Interface
 */
class API_WooCommerce_WPUnit_Test extends \lucatume\WPBrowser\TestCase\WPTestCase {

	protected function get_sut(
		?Settings_Interface $settings = null,
		?Exchange_Rate_Service $exchange_rate_service = null,
		?Bitcoin_Wallet_Service $bitcoin_wallet_service = null,
		?Payment_Service $payment_service = null,
		?Background_Jobs_Scheduler_Interface $background_jobs_scheduler = null,
		?LoggerInterface $logger = null,
	): API {
		$sut = new API(
			settings: $settings ?? $this->makeEmpty( Settings_Interface::class ),
			exchange_rate_service: $exchange_rate_service ?? $this->makeEmpty( Exchange_Rate_Service::class ),
			wallet_service: $bitcoin_wallet_service ?? $this->make( Bitcoin_Wallet_Service::class ),
			payment_service: $payment_service ?? $this->make( Payment_Service::class ),
			background_jobs_scheduler: $background_jobs_scheduler ?? $this->makeEmpty( Background_Jobs_Scheduler_Interface::class ),
			logger: $logger ?? new ColorLogger(),
		);
		return $sut;
	}

	/**
	 * @see API_WooCommerce_Trait::get_bitcoin_gateways
	 */
	public function test_get_bitcoin_gateways(): void {

		$sut = $this->get_sut();

		$wc_payment_gateways = WC_Payment_Gateways::instance();
		$bitcoin_1           = new Bitcoin_Gateway( $sut );
		$bitcoin_1->id       = 'bitcoin_1';

		$wc_payment_gateways->payment_gateways['bitcoin_1'] = $bitcoin_1;

		$bitcoin_2     = new Bitcoin_Gateway( $sut );
		$bitcoin_2->id = 'bitcoin_2';

		$wc_payment_gateways->payment_gateways['bitcoin_2'] = $bitcoin_2;

		/** @var array<WC_Payment_Gateway> $result */
		$result = $sut->get_bitcoin_gateways();

		$this->assertCount( 2, $result );

		$all_bitcoin_gateways = array_reduce(
			$result,
			function ( bool $carry, WC_Payment_Gateway $gateway ): bool {
				return $carry && ( $gateway instanceof Bitcoin_Gateway );
			},
			true
		);

		$this->assertTrue( $all_bitcoin_gateways );

		unset( $wc_payment_gateways->payment_gateways['bitcoin_1'] );
		unset( $wc_payment_gateways->payment_gateways['bitcoin_2'] );
	}

	/**
	 * @see API_WooCommerce_Trait::is_bitcoin_gateway
	 */
	public function test_is_bitcoin_gateway(): void {

		$sut = $this->get_sut();

		$wc_payment_gateways = WC_Payment_Gateways::instance();
		$bitcoin_1           = new Bitcoin_Gateway( $sut );
		$bitcoin_1->id       = 'bitcoin_1';
		$wc_payment_gateways->payment_gateways['bitcoin_1'] = $bitcoin_1;

		$result = $sut->is_bitcoin_gateway( 'bitcoin_1' );

		$this->assertTrue( $result );
	}

	/**
	 * @see API_WooCommerce_Trait::is_order_has_bitcoin_gateway
	 */
	public function test_is_order_has_bitcoin_gateway(): void {

		$sut = $this->get_sut();

		$wc_payment_gateways = WC_Payment_Gateways::instance();
		$bitcoin_1           = new Bitcoin_Gateway( $sut );
		$bitcoin_1->id       = 'bitcoin_1';
		$wc_payment_gateways->payment_gateways['bitcoin_1'] = $bitcoin_1;

		$order = new WC_Order();
		$order->set_payment_method( 'bitcoin_1' );
		$order_id = $order->save();

		$result = $sut->is_order_has_bitcoin_gateway( $order_id );

		$this->assertTrue( $result );
	}

	/**
	 * @see API_WooCommerce_Trait::get_fresh_address_for_gateway
	 */
	public function test_get_fresh_addresses_for_gateway(): void {

		$this->markTestSkipped();

		$address = $this->make(
			Bitcoin_Address::class,
			array(
				'get_post_id'     => 123,
				'get_raw_address' => 'success',
				'get_status'      => Expected::once( fn() => Bitcoin_Address_Status::ASSIGNED ),
				'get_tx_ids'      => array(),
			)
		);

		$addresses_result = array(
			$this->make(
				Bitcoin_Address::class,
				array(
					'get_status' => Bitcoin_Address_Status::ASSIGNED,
				)
			),
			$address,
		);

		$wallet = $this->make(
			Bitcoin_Wallet::class,
			array(
				'get_post_id'       => 321,
				'get_address_index' => 111,
				'get_xpub'          => 'xpub12345',
			)
		);

		$bitcoin_wallet_service = $this->makeEmpty(
			Bitcoin_Wallet_Service::class,
			array(
				'get_by_xpub' => Expected::once( $wallet ),
				'save_new'    => Expected::once( $address ),
				'refresh'     => $address,
			)
		);

		$sut = $this->get_sut(
			bitcoin_wallet_service: $bitcoin_wallet_service,
		);

		$bitcoin_gateway                   = new Bitcoin_Gateway( $sut );
		$bitcoin_gateway->settings['xpub'] = 'xpub';

		$address = $sut->get_fresh_address_for_gateway( $bitcoin_gateway );

		self::assertEquals( 'success', $address->get_raw_address() );
	}

	/**
	 * @see API_WooCommerce_Trait::is_fresh_address_available_for_gateway
	 */
	public function test_is_fresh_address_available_for_gateway_true(): void {

		$this->markTestSkipped( 'is_fresh_address_available_for_gateway no does not make API calls' );

		$address = $this->make(
			Bitcoin_Address::class,
			array(
				'get_post_id'     => 123,
				'get_raw_address' => 'success',
				'get_status'      => Expected::once( fn() => Bitcoin_Address_Status::ASSIGNED ),
				'get_tx_ids'      => array(),
			)
		);

		$addresses_result = array(
			$address,
			$this->make(
				Bitcoin_Address::class,
				array(
					'get_post_id'     => 123,
					'get_raw_address' => 'success',
					'get_status'      => Bitcoin_Address_Status::ASSIGNED,
				)
			),
		);

		$wallet = $this->make(
			Bitcoin_Wallet::class,
			array(
				'get_post_id'       => 321,
				'get_address_index' => 111,
				'get_xpub'          => 'xpub12345',
			)
		);

		$bitcoin_wallet_service = $this->makeEmpty(
			Bitcoin_Wallet_Service::class,
			array(
				'get_by_xpub'   => Expected::once( $wallet ),
				'get_addresses' => Expected::once(
					function ( ?Bitcoin_Wallet $wallet = null, ?Bitcoin_Address_Status $status = null ) use ( $addresses_result ): array {
						/** @return Bitcoin_Address[] */
						return $addresses_result;
					}
				),
				'save_new'      => Expected::once( $address ),
			)
		);

		$sut = $this->get_sut(
			bitcoin_wallet_service: $bitcoin_wallet_service,
		);

		$bitcoin_gateway                   = new Bitcoin_Gateway( $sut );
		$bitcoin_gateway->settings['xpub'] = 'xpub';

		$result = $sut->is_fresh_address_available_for_gateway( $bitcoin_gateway );

		self::assertTrue( $result );
	}

	/**
	 * @see API_WooCommerce_Trait::get_fresh_address_for_order
	 * @see API_WooCommerce_Trait::get_fresh_address_for_gateway
	 */
	public function test_get_fresh_address_for_order(): void {

		$address = $this->make(
			Bitcoin_Address::class,
			array(
				'get_post_id'     => 123,
				'get_raw_address' => 'success',
				'get_tx_ids'      => array(),
			)
		);

		$wallet = $this->make(
			Bitcoin_Wallet::class,
			array(
				'get_post_id'       => 321,
				'get_address_index' => 111,
				'get_xpub'          => 'xpub12345',
			)
		);

		$get_wallet_for_xpub_service_result = new Get_Wallet_For_Xpub_Service_Result(
			xpub: 'xpub12345',
			gateway_id: 'bitcoin',
			wallet:$wallet,
			is_new: false,
		);

		$address_generation_result = new Addresses_Generation_Result(
			wallet: $wallet,
			new_addresses: array( $address ),
			address_index: 1,
		);

		$bitcoin_wallet_service = $this->makeEmpty(
			Bitcoin_Wallet_Service::class,
			array(
				'get_wallet_for_xpub'               => Expected::once( $get_wallet_for_xpub_service_result ),
				'get_unused_bitcoin_addresses'      => Expected::once( fn() => array() ),
				'generate_new_addresses_for_wallet' => Expected::once( $address_generation_result ),
				'save_new'                          => Expected::once( $address ),
				'refresh'                           => $address,
			)
		);

		$background_jobs_scheduler_mock = $this->makeEmpty(
			Background_Jobs_Scheduler_Interface::class,
			array(
				'schedule_single_ensure_unused_addresses' => Expected::once(),
			)
		);

		$update_address_transactions_result = new Update_Address_Transactions_Result(
			queried_address: $address,
			known_tx_ids_before: array(),
			all_transactions: array(), // TODO.
		);
		$payment_service_mock               = $this->make(
			Payment_Service::class,
			array(
				'update_address_transactions' => Expected::once( $update_address_transactions_result ),
			)
		);

		$sut = $this->get_sut(
			bitcoin_wallet_service: $bitcoin_wallet_service,
			payment_service: $payment_service_mock,
			background_jobs_scheduler: $background_jobs_scheduler_mock,
		);

		$wc_payment_gateways                              = WC_Payment_Gateways::instance();
		$bitcoin_gateway                                  = new Bitcoin_Gateway( $sut );
		$bitcoin_gateway->id                              = 'bitcoin';
		$bitcoin_gateway->settings['xpub']                = 'xpub12345';
		$wc_payment_gateways->payment_gateways['bitcoin'] = $bitcoin_gateway;

		$order = new WC_Order();
		$order->set_payment_method( 'bitcoin' );
		$order->save();

		$btc_amount = Money::of( '0.00123', 'BTC' );

		$result = $sut->get_fresh_address_for_order( $order, $btc_amount );

		$this->assertEquals( 'success', $result->get_raw_address() );

		$this->markTestIncomplete();
	}

	/**
	 * @see API_WooCommerce_Trait::get_order_details
	 * @see API_WooCommerce_Trait::refresh_order
	 * @see API_WooCommerce_Trait::update_address_transactions
	 */
	public function test_get_order_details_no_transactions(): void {

		$address = $this->make(
			Bitcoin_Address::class,
			array(
				'get_tx_ids'          => Expected::exactly( 1, array() ),
				'get_amount_received' => Expected::exactly( 1, Money::of( 0.01, 'BTC' ) ),
				'get_target_amount'   => Expected::exactly( 1, Money::of( 0.1, 'BTC' ) ),
			)
		);

		$bitcoin_wallet_service = $this->makeEmpty(
			Bitcoin_Wallet_Service::class,
			array(
				'get_saved_address_by_bitcoin_payment_address' => Expected::once( $address ), // param: `xpub1234`.
			)
		);

		$check_address_for_payment_service_result = new Check_Address_For_Payment_Service_Result(
			update_address_transactions_result: new Update_Address_Transactions_Result(
				queried_address: $address,
				known_tx_ids_before: array(),
				all_transactions: array( $this->makeEmpty( Transaction_Interface::class ) ),
			),
			blockchain_height: 12345,
			required_confirmations: 3,
			total_received: Money::of( 0.02, 'BTC' ),
		);

		$payment_service_mock = $this->make(
			Payment_Service::class,
			array(
				'get_saved_transactions'    => Expected::once( array() ),
				'check_address_for_payment' => Expected::once( $check_address_for_payment_service_result ),
			)
		);

		$sut = $this->get_sut(
			bitcoin_wallet_service: $bitcoin_wallet_service,
			payment_service: $payment_service_mock
		);

		$order = new WC_Order();
		$order->add_meta_data( Order::BITCOIN_ADDRESS_META_KEY, 'xpub1234', true );
		$order->add_meta_data(
			Order::ORDER_TOTAL_BITCOIN_AT_TIME_OF_PURCHASE_META_KEY,
			array(
				'amount'   => '0.1',
				'currency' => 'BTC',
			),
			true
		);
		$order->add_meta_data(
			Order::EXCHANGE_RATE_AT_TIME_OF_PURCHASE_META_KEY,
			array(
				'amount'   => '90000',
				'currency' => 'USD',
			),
			true
		);
		$order->add_meta_data( Order::LAST_CHECKED_META_KEY, new \DateTimeImmutable( '2024-01-15T14:30:00+00:00' ), true );
		$order->save();

		$result = $sut->get_order_details( $order, true );

		$this->markTestIncomplete();
	}

	/**
	 * @see API_WooCommerce_Trait::get_order_details
	 * @see API_WooCommerce_Trait::refresh_order
	 * @see API_WooCommerce_Trait::update_address_transactions
	 */
	public function test_get_order_details_no_refresh(): void {

		$address = $this->make(
			Bitcoin_Address::class,
			array(
				'get_tx_ids' => Expected::exactly( 2, array() ),
			)
		);

		$bitcoin_wallet_service = $this->makeEmpty(
			Bitcoin_Wallet_Service::class,
			array(
				'get_saved_address_by_bitcoin_payment_address' => Expected::once( $address ), // param: `xpub1234`.
			)
		);

		$payment_service_mock = $this->make(
			Payment_Service::class,
			array(
				'get_saved_transactions'    => Expected::once( array() ),
				'check_address_for_payment' => Expected::never(),
			)
		);

		$sut = $this->get_sut(
			bitcoin_wallet_service: $bitcoin_wallet_service,
			payment_service: $payment_service_mock
		);

		$order = new WC_Order();
		$order->add_meta_data( Order::BITCOIN_ADDRESS_META_KEY, 'xpub1234', true );
		$order->add_meta_data(
			Order::ORDER_TOTAL_BITCOIN_AT_TIME_OF_PURCHASE_META_KEY,
			array(
				'amount'   => '0.00123',
				'currency' => 'BTC',
			),
			true
		);
		$order->add_meta_data(
			Order::EXCHANGE_RATE_AT_TIME_OF_PURCHASE_META_KEY,
			array(
				'amount'   => '90000',
				'currency' => 'USD',
			),
			true
		);
		$order->add_meta_data( Order::LAST_CHECKED_META_KEY, new \DateTimeImmutable( '2024-01-15T14:30:00+00:00' ), true );
		$order->save();

		$result = $sut->get_order_details( $order, false );

		$this->assertEquals( Money::of( '0.00123', 'BTC' ), $result->get_btc_total_price() );
		$this->assertEquals( Money::of( '90000', 'USD' ), $result->get_btc_exchange_rate() );
		$this->assertSame( $address, $result->get_address() );
		$this->assertEquals( '2024-01-15 14:30:00', $result->get_last_checked_time()->format( 'Y-m-d H:i:s' ) );
	}
}
