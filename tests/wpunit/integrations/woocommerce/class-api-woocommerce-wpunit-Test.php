<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Scheduler_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Results\Ensure_Unused_Addresses_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Results\Update_Address_Transactions_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Bitcoin_Wallet_Service;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Payment_Service;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Results\Check_Address_For_Payment_Service_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Results\Get_Wallet_For_Xpub_Service_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use Codeception\Stub\Expected;
use lucatume\WPBrowser\TestCase\WPTestCase;
use Psr\Log\LoggerInterface;
use WC_Order;
use WC_Payment_Gateway;
use WC_Payment_Gateways;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\API_WooCommerce
 */
class API_WooCommerce_WPUnit_Test extends WPTestCase {

	protected function get_sut(
		?API_Interface $api = null,
		?Bitcoin_Wallet_Service $wallet_service = null,
		?Payment_Service $payment_service = null,
		?Background_Jobs_Scheduler_Interface $background_jobs_scheduler = null,
		?LoggerInterface $logger = null,
	): API_WooCommerce {
		return new API_WooCommerce(
			api: $api ?? $this->makeEmpty( API_Interface::class ),
			wallet_service: $wallet_service ?? $this->makeEmpty( Bitcoin_Wallet_Service::class ),
			payment_service: $payment_service ?? $this->makeEmpty( Payment_Service::class ),
			background_jobs_scheduler: $background_jobs_scheduler ?? $this->makeEmpty( Background_Jobs_Scheduler_Interface::class ),
			logger: $logger ?? new ColorLogger(),
		);
	}

	protected function get_bitcoin_gateway(
		?API_Interface $api = null,
		?API_WooCommerce_Interface $api_woocommerce = null,
		?Settings_Interface $settings = null,
		?LoggerInterface $logger = null,
	): Bitcoin_Gateway {
		return new Bitcoin_Gateway(
			api: $api ?? $this->makeEmpty( API_Interface::class ),
			api_woocommerce: $api_woocommerce ?? $this->makeEmpty( API_WooCommerce_Interface::class ),
			plugin_settings: $settings ?? $this->makeEmpty( Settings_Interface::class ),
			logger: $logger ?? new ColorLogger(),
		);
	}

	/**
	 * @see API_WooCommerce_Trait::get_bitcoin_gateways
	 */
	public function test_get_bitcoin_gateways(): void {

		$sut = $this->get_sut();

		$wc_payment_gateways = WC_Payment_Gateways::instance();
		$bitcoin_1           = $this->get_bitcoin_gateway( api_woocommerce: $sut );
		$bitcoin_1->id       = 'bitcoin_1';

		$wc_payment_gateways->payment_gateways['bitcoin_1'] = $bitcoin_1;

		$bitcoin_2     = $this->get_bitcoin_gateway( api_woocommerce: $sut );
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
		$bitcoin_1           = $this->get_bitcoin_gateway( api_woocommerce: $sut );
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
		$bitcoin_1           = $this->get_bitcoin_gateway( api_woocommerce: $sut );
		$bitcoin_1->id       = 'bitcoin_1';
		$wc_payment_gateways->payment_gateways['bitcoin_1'] = $bitcoin_1;

		$order = new WC_Order();
		$order->set_payment_method( 'bitcoin_1' );
		$order_id = $order->save();

		$result = $sut->is_order_has_bitcoin_gateway( $order_id );

		$this->assertTrue( $result );
	}

	/**
	 * @covers ::get_fresh_address_for_gateway
	 */
	public function test_get_fresh_addresses_for_gateway_no_master_public_key(): void {
		$gateway = $this->make( Bitcoin_Gateway::class );

		assert( empty( $gateway->get_xpub() ) );

		$sut = $this->get_sut();

		$result = $sut->get_fresh_address_for_gateway( $gateway );

		$this->assertNull( $result );
	}

	/**
	 * @covers ::get_fresh_address_for_gateway
	 */
	public function test_get_fresh_addresses_for_gateway(): void {

		$address = $this->make(
			Bitcoin_Address::class,
			array(
				'get_post_id' => 123,
				'get_tx_ids'  => array(),
			)
		);

		$addresses_result = array(
			$this->make(
				Bitcoin_Address::class,
				array(
					'get_raw_address' => 'success',
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

		$get_wallet_for_xpub_service_result = new Get_Wallet_For_Xpub_Service_Result(
			xpub: 'xpub12345',
			gateway_details: null,
			wallet: $wallet,
			is_new: false,
		);

		$bitcoin_wallet_service = $this->makeEmpty(
			Bitcoin_Wallet_Service::class,
			array(
				'get_or_save_wallet_for_xpub' => Expected::once( $get_wallet_for_xpub_service_result ),
				'save_new'                    => Expected::once( $address ),
				'refresh'                     => $address,
			)
		);

		$ensure_unused_addresses_for_wallet_synchronously_result = new Ensure_Unused_Addresses_Result(
			wallet: $wallet,
			assumed_existing_unused_addresses: array(),
			actual_existing_unused_addresses: array(),
			unexpectedly_used_addresses_by_wallet: array(),
			new_unused_addresses: $addresses_result,
		);

		$api_mock = $this->makeEmpty(
			API_Interface::class,
			array(
				'ensure_unused_addresses_for_wallet_synchronously' => Expected::once( $ensure_unused_addresses_for_wallet_synchronously_result ),
			)
		);

		$sut = $this->get_sut(
			api: $api_mock,
			wallet_service: $bitcoin_wallet_service,
		);

		$gateway_mock = $this->make(
			Bitcoin_Gateway::class,
			array(
				'get_xpub' => Expected::atLeastOnce( 'xpub12345' ),
			)
		);

		$bitcoin_gateway                   = $gateway_mock;
		$bitcoin_gateway->settings['xpub'] = 'xpub';

		$address = $sut->get_fresh_address_for_gateway( $bitcoin_gateway );

		self::assertEquals( 'success', $address?->get_raw_address() );
	}

	/**
	 * @covers ::is_unused_address_available_for_gateway()
	 */
	public function test_is_unused_address_available_for_gateway_true(): void {

		$wallet = $this->make(
			Bitcoin_Wallet::class,
			array()
		);

		$get_wallet_for_xpub_service_result = new Get_Wallet_For_Xpub_Service_Result(
			xpub: 'xpub12345',
			gateway_details: null,
			wallet: $wallet,
			is_new: false,
		);

		$wallet_service_mock = $this->makeEmpty(
			Bitcoin_Wallet_Service::class,
			array(
				'get_or_save_wallet_for_xpub' => Expected::once( $get_wallet_for_xpub_service_result ),
			)
		);

		$api_mock = $this->makeEmpty(
			API_Interface::class,
			array(
				'is_unused_address_available_for_wallet' => Expected::once( true ),
			)
		);

		$sut = $this->get_sut(
			api: $api_mock,
			wallet_service: $wallet_service_mock,
		);

		$bitcoin_gateway                   = $this->get_bitcoin_gateway( api_woocommerce: $sut );
		$bitcoin_gateway->settings['xpub'] = 'xpub';

		$result = $sut->is_unused_address_available_for_gateway( $bitcoin_gateway );

		$this->assertTrue( $result );
	}

	/**
	 * @covers ::assign_unused_address_to_order
	 * @covers ::get_fresh_address_for_gateway
	 */
	public function test_get_fresh_address_for_order(): void {

		$address = $this->make(
			Bitcoin_Address::class,
			array(
				'get_post_id'     => 123,
				'get_tx_ids'      => array(),
				'get_raw_address' => 'before',
			)
		);

		$refreshed_address = $this->make(
			Bitcoin_Address::class,
			array(
				'get_raw_address' => 'success',
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

		$gateway = array(
			'integration' => WooCommerce_Integration::class,
			'gateway_id'  => 'gateway_id',
		);

		$get_wallet_for_xpub_service_result = new Get_Wallet_For_Xpub_Service_Result(
			xpub: 'xpub12345',
			gateway_details: $gateway,
			wallet: $wallet,
			is_new: false,
		);

		$bitcoin_wallet_service_mock = $this->makeEmpty(
			Bitcoin_Wallet_Service::class,
			array(
				'get_or_save_wallet_for_xpub'             => Expected::once( $get_wallet_for_xpub_service_result ),
				'assign_order_to_bitcoin_payment_address' => Expected::once( $refreshed_address ),
			)
		);

		$ensure_unused_addresses_for_wallet = new Ensure_Unused_Addresses_Result(
			wallet: $wallet,
			assumed_existing_unused_addresses: array(),
			actual_existing_unused_addresses: array(),
			unexpectedly_used_addresses_by_wallet: array(),
			new_unused_addresses: array( $address ),
		);
		$api_mock                           = $this->makeEmpty(
			API_Interface::class,
			array(
				'ensure_unused_addresses_for_wallet_synchronously' => Expected::once( $ensure_unused_addresses_for_wallet ),
			)
		);

		$sut = $this->get_sut(
			api: $api_mock,
			wallet_service: $bitcoin_wallet_service_mock,
		);

		$wc_payment_gateways                              = WC_Payment_Gateways::instance();
		$bitcoin_gateway                                  = $this->get_bitcoin_gateway( api_woocommerce: $sut );
		$bitcoin_gateway->id                              = 'bitcoin';
		$bitcoin_gateway->settings['xpub']                = 'xpub12345';
		$wc_payment_gateways->payment_gateways['bitcoin'] = $bitcoin_gateway;

		$order = new WC_Order();
		$order->set_payment_method( 'bitcoin' );
		$order->save();

		$btc_amount = Money::of( '0.00123', 'BTC' );

		$result = $sut->assign_unused_address_to_order( $order, $btc_amount );

		$this->assertEquals( 'success', $result->get_raw_address() );
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
			wallet_service: $bitcoin_wallet_service,
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
			wallet_service: $bitcoin_wallet_service,
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
		$this->assertEquals( '2024-01-15 14:30:00', $result->get_last_checked_time()?->format( 'Y-m-d H:i:s' ) );
	}
}
