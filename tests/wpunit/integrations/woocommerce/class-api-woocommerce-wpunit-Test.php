<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Scheduler_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address_Status;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Transaction_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\API;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Blockchain_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Exchange_Rate_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Generate_Address_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Address_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Wallet;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Wallet_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use Codeception\Stub\Expected;
use Psr\Log\LoggerInterface;
use WC_Order;
use WC_Payment_Gateway;
use WC_Payment_Gateways;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\API\API
 * @see API_WooCommerce_Interface
 */
class API_WooCommerce_WPUnit_Test extends \lucatume\WPBrowser\TestCase\WPTestCase {

	protected function get_sut(
		?Settings_Interface $settings = null,
		?LoggerInterface $logger = null,
		?Bitcoin_Wallet_Repository $bitcoin_wallet_repository = null,
		?Bitcoin_Address_Repository $bitcoin_address_repository = null,
		?Bitcoin_Transaction_Repository $bitcoin_transaction_repository = null,
		?Blockchain_API_Interface $blockchain_api = null,
		?Generate_Address_API_Interface $generate_address_api = null,
		?Exchange_Rate_API_Interface $exchange_rate_api = null,
		?Background_Jobs_Scheduler_Interface $background_jobs_scheduler = null,
	): API {
		$sut = new API(
			settings: $settings ?? $this->makeEmpty( Settings_Interface::class ),
			logger: $logger ?? new ColorLogger(),
			bitcoin_wallet_repository: $bitcoin_wallet_repository ?? $this->make( Bitcoin_Wallet_Repository::class ),
			bitcoin_address_repository: $bitcoin_address_repository ?? $this->make( Bitcoin_Address_Repository::class ),
			bitcoin_transaction_repository: $bitcoin_transaction_repository ?? $this->make( Bitcoin_Transaction_Repository::class ),
			blockchain_api: $blockchain_api ?? $this->makeEmpty( Blockchain_API_Interface::class ),
			wallet_service: $generate_address_api ?? $this->makeEmpty( Generate_Address_API_Interface::class ),
			exchange_rate_service: $exchange_rate_api ?? $this->makeEmpty( Exchange_Rate_API_Interface::class ),
			background_jobs_scheduler: $background_jobs_scheduler ?? $this->makeEmpty( Background_Jobs_Scheduler_Interface::class ),
		);
		return $sut;
	}

	/**
	 * @covers ::get_bitcoin_gateways
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
	 * @covers ::is_bitcoin_gateway
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
	 * @covers ::is_order_has_bitcoin_gateway
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
	 * @covers ::get_fresh_addresses_for_gateway
	 */
	public function test_get_fresh_addresses_for_gateway(): void {

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

		$bitcoin_wallet_repository = $this->makeEmpty(
			Bitcoin_Wallet_Repository::class,
			array(
				'get_by_xpub' => Expected::once( $wallet ),
			)
		);

		$bitcoin_address_repository = $this->makeEmpty(
			Bitcoin_Address_Repository::class,
			array(
				'save_new' => Expected::once( $address ),
				'refresh'  => $address,
			)
		);

		$sut = $this->get_sut(
			bitcoin_wallet_repository: $bitcoin_wallet_repository,
			bitcoin_address_repository: $bitcoin_address_repository
		);

		$bitcoin_gateway                   = new Bitcoin_Gateway( $sut );
		$bitcoin_gateway->settings['xpub'] = 'xpub';

		$address = $sut->get_fresh_address_for_gateway( $bitcoin_gateway );

		self::assertEquals( 'success', $address->get_raw_address() );
	}

	/**
	 * @covers ::is_fresh_address_available_for_gateway
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

		$bitcoin_wallet_repository = $this->makeEmpty(
			Bitcoin_Wallet_Repository::class,
			array(
				'get_by_xpub' => Expected::once( $wallet ),
			)
		);

		$bitcoin_address_repository = $this->makeEmpty(
			Bitcoin_Address_Repository::class,
			array(
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
			bitcoin_wallet_repository: $bitcoin_wallet_repository,
			bitcoin_address_repository: $bitcoin_address_repository
		);

		$bitcoin_gateway                   = new Bitcoin_Gateway( $sut );
		$bitcoin_gateway->settings['xpub'] = 'xpub';

		$result = $sut->is_fresh_address_available_for_gateway( $bitcoin_gateway );

		self::assertTrue( $result );
	}

	/**
	 * @covers ::get_fresh_address_for_order
	 */
	public function test_get_fresh_address_for_order(): void {

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
			$this->make( Bitcoin_Address::class ),
		);

		$wallet = $this->make(
			Bitcoin_Wallet::class,
			array(
				'get_post_id'       => 321,
				'get_address_index' => 111,
				'get_xpub'          => 'xpub12345',
			)
		);

		$bitcoin_wallet_repository = $this->makeEmpty(
			Bitcoin_Wallet_Repository::class,
			array(
				'get_by_xpub' => Expected::once( $wallet ),
			)
		);

		$bitcoin_address_repository = $this->makeEmpty(
			Bitcoin_Address_Repository::class,
			array(
				'get_unused_bitcoin_addresses' => Expected::once( fn()=> array() ),
				'save_new'                     => Expected::once( $address ),
				'refresh'                      => $address,
			)
		);

		$generate_address_api = $this->makeEmpty(
			Generate_Address_API_Interface::class,
			array(
				'generate_address' => Expected::once( 'xpub123new456' ),
			)
		);

		$sut = $this->get_sut(
			bitcoin_wallet_repository: $bitcoin_wallet_repository,
			bitcoin_address_repository: $bitcoin_address_repository,
			generate_address_api: $generate_address_api,
		);

		$wc_payment_gateways                              = WC_Payment_Gateways::instance();
		$bitcoin_gateway                                  = new Bitcoin_Gateway( $sut );
		$bitcoin_gateway->id                              = 'bitcoin';
		$bitcoin_gateway->settings['xpub']                = 'bitcoinxpub';
		$wc_payment_gateways->payment_gateways['bitcoin'] = $bitcoin_gateway;

		$order = new WC_Order();
		$order->set_payment_method( 'bitcoin' );
		$order->save();

		$btc_amount = Money::of( '0.00123', 'BTC' );

		$result = $sut->get_fresh_address_for_order( $order, $btc_amount );

		$this->assertEquals( 'success', $result->get_raw_address() );
	}

	/**
	 * @covers ::get_order_details
	 * @covers ::refresh_order
	 * @covers ::update_address_transactions
	 */
	public function test_get_order_details_no_transactions(): void {

		$address = $this->make(
			Bitcoin_Address::class,
			array(
				'get_raw_address' => Expected::exactly( 1, 'xpub' ),
				'get_status'      => Expected::atLeastOnce( Bitcoin_Address_Status::ASSIGNED ),
				'get_tx_ids'      => Expected::once( array() ),
				'get_balance'     => Expected::once( null ),
			)
		);

		$bitcoin_wallet_repository = $this->makeEmpty( Bitcoin_Wallet_Repository::class );

		$bitcoin_address_repository = $this->makeEmpty(
			Bitcoin_Address_Repository::class,
			array(
				'get_post_id_for_address' => Expected::once( 456 ),
				'get_by_post_id'          => Expected::once(
					function ( int $post_id ) use ( $address ): Bitcoin_Address {
						assert( 456 === $post_id );
						return $address;
					}
				),
			)
		);

		$bitcoin_transaction_repository = $this->makeEmpty(
			Bitcoin_Transaction_Repository::class,
			array(
				'get_transactions_for_address' => Expected::once(
					function ( Bitcoin_Address $address ): array {
						// This test is for "no_transactions".
						return array();
					}
				),
			)
		);

		$blockchain_api = $this->makeEmpty(
			Blockchain_API_Interface::class,
			array(
				'get_transactions' => array(),
			)
		);

		$sut = $this->get_sut(
			bitcoin_wallet_repository: $bitcoin_wallet_repository,
			bitcoin_address_repository: $bitcoin_address_repository,
			bitcoin_transaction_repository: $bitcoin_transaction_repository,
			blockchain_api: $blockchain_api,
		);

		$order = new WC_Order();
		$order->add_meta_data( Order::BITCOIN_ADDRESS_META_KEY, 'xpub', true );
		$order->save();

		$result = $sut->get_order_details( $order, true );

		$this->markTestIncomplete( 'What is this testing?' );

		self::assertEmpty( $result->get_address()->get_blockchain_transactions() );
	}
}
