<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Currency;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use Codeception\Stub\Expected;
use Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Actions_Handler;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Results\Wallet_Generation_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use lucatume\WPBrowser\TestCase\WPTestCase;
use WC_Order;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Bitcoin_Gateway
 */
class Bitcoin_Gateway_WPUnit_Test extends WPTestCase {

	protected function get_sut(
		?API_Interface $api = null,
		?API_WooCommerce_Interface $api_woocommerce = null,
		?Settings_Interface $plugin_settings = null,
	): Bitcoin_Gateway {
		return new Bitcoin_Gateway(
			api: $api ?? $this->makeEmpty( API_Interface::class ),
			api_woocommerce: $api_woocommerce ?? $this->makeEmpty( API_WooCommerce_Interface::class ),
			plugin_settings: $plugin_settings ?? $this->makeEmpty( Settings_Interface::class ),
			logger: new ColorLogger(),
		);
	}

	/**
	 * @covers ::process_admin_options
	 */
	public function test_generates_new_addresses_when_xpub_changes(): void {

		$this->markTestSkipped( 'TODO: inject background jobs scheduler.' );

		$api_woocommerce = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'generate_new_wallet' => Expected::once(
					function ( string $xpub_after, ?string $gateway_id = null ) {
						$mock_wallet = \Codeception\Stub::makeEmpty( Bitcoin_Wallet::class );
						return new Wallet_Generation_Result( $mock_wallet, array(), array() );
					}
				),
			)
		);

		$sut                   = $this->get_sut( api_woocommerce: $api_woocommerce );
		$sut->settings['xpub'] = 'before';

		$xpub_after = 'after';

		$_POST['woocommerce_bitcoin_gateway_xpub'] = $xpub_after;

		$sut->process_admin_options();
	}

	/**
	 * @covers ::process_admin_options
	 */
	public function test_does_not_generate_new_addresses_when_xpub_does_not_change(): void {

		$sut                   = $this->get_sut();
		$sut->settings['xpub'] = 'same';

		$_POST['woocommerce_bitcoin_gateway_xpub'] = 'same';

		assert( false === as_next_scheduled_action( Background_Jobs_Actions_Handler::GENERATE_NEW_ADDRESSES_HOOK ) );

		$sut->process_admin_options();

		$this->assertFalse( as_next_scheduled_action( Background_Jobs_Actions_Handler::GENERATE_NEW_ADDRESSES_HOOK ) );
	}

	/**
	 * @covers ::is_available
	 * @see API_WooCommerce_Interface::is_unused_address_available_for_gateway()
	 * @see API_Interface::is_unused_address_available_for_wallet()
	 */
	public function test_checks_for_available_address_for_availability_true(): void {

		$api             = $this->makeEmpty(
			API_Interface::class,
			array(
				'get_exchange_rate' => Expected::once(
					function ( Currency $currency ) {
						return Money::of( '0.0', Currency::of( 'USD' ) );
					}
				),
			)
		);
		$api_woocommerce = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'is_unused_address_available_for_gateway' => Expected::once(
					function ( Bitcoin_Gateway $gateway ) {
						return true;
					}
				),
			)
		);

		$sut          = $this->get_sut( api: $api, api_woocommerce: $api_woocommerce );
		$sut->enabled = 'yes';

		$result = $sut->is_available();

		$this->assertTrue( $result );
	}

	/**
	 * @covers ::is_available
	 */
	public function test_checks_for_available_address_for_availability_false(): void {

		$api_woocommerce = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'is_fresh_address_available_for_gateway' => Expected::once(
					function ( Bitcoin_Gateway $gateway ) {
						return false;
					}
				),
			)
		);

		$sut          = $this->get_sut( api_woocommerce: $api_woocommerce );
		$sut->enabled = 'yes';

		$result = $sut->is_available();

		$this->assertFalse( $result );
	}

	/**
	 * @covers ::is_available
	 */
	public function test_checks_for_available_address_for_availability_uses_cache(): void {

		$this->markTestSkipped( 'Skipping while we figure out how things work if a customer arrives at the checkout, when there are no addresses, one is created, is the instance of the gateway the same?' );

		$api_woocommerce = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'is_fresh_address_available_for_gateway' => true,
				'get_exchange_rate'                      => Money::of( 1, 'BTC' ),
			)
		);

		$sut = new class($api_woocommerce) extends Bitcoin_Gateway {
			public function __construct( API_WooCommerce_Interface $api_woocommerce ) {
				parent::__construct( $api_woocommerce );
				$this->is_available_cache = false;
			}
		};

		$result = $sut->is_available();

		$this->assertFalse( $result );
	}

	/**
	 * @covers ::is_available
	 */
	public function test_checks_for_available_address_for_availability_false_when_no_api_class(): void {

		$GLOBALS['bh_wp_bitcoin_gateway'] = null;

		$sut = $this->get_sut();

		$result = $sut->is_available();

		$this->assertFalse( $result );
	}

	/**
	 * @covers ::process_payment
	 */
	public function test_process_payment_returns_exception_on_bad_order_id(): void {

		$sut = $this->get_sut();

		$exception = null;
		try {
			$sut->process_payment( 123 );
		} catch ( Exception $e ) {
			$exception = $e;
		}

		$this->assertNotNull( $exception );
		$this->assertEquals( 'Error creating order.', $exception->getMessage() );
	}

	/**
	 * @covers ::process_payment
	 */
	public function test_process_payment_returns_exception_when_no_address_available(): void {

		$api             = $this->makeEmpty(
			API_Interface::class,
			array(
				'convert_fiat_to_btc' => Expected::once(
					function ( Money $money ) {
						return $money;
					}
				),
			)
		);
		$api_woocommerce = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'get_fresh_address_for_order' => Expected::once(
					function ( WC_Order $order ) {
						throw new Exception( 'This message will not be shown!' );
					}
				),
			)
		);

		$sut = $this->get_sut( api: $api, api_woocommerce: $api_woocommerce );

		$order    = new WC_Order();
		$order_id = $order->save();

		$exception = null;
		try {
			$sut->process_payment( $order_id );
		} catch ( Exception $e ) {
			$exception = $e;
		}

		$this->assertNotNull( $exception );
		$this->assertEquals( 'Unable to find Bitcoin address to send to. Please choose another payment method.', $exception->getMessage() );
	}
}
