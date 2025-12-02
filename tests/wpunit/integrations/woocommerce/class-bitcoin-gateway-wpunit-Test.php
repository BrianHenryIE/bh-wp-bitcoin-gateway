<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Addresses_Generation_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Currency;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use Codeception\Stub\Expected;
use Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Wallet;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet_Generation_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use WC_Order;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Bitcoin_Gateway
 */
class Bitcoin_Gateway_WPUnit_Test extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * @covers ::process_admin_options
	 */
	public function test_generates_new_addresses_when_xpub_changes(): void {

		$GLOBALS['bh_wp_bitcoin_gateway'] = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'generate_new_wallet'               => Expected::once(
					function ( string $xpub_after, ?string $gateway_id = null ) {
						$mock_wallet = \Codeception\Stub::makeEmpty( Bitcoin_Wallet::class );
						return new Wallet_Generation_Result( $mock_wallet, array(), array() );
					}
				),
				'generate_new_addresses_for_wallet' => Expected::once(
					function ( $wallet, int $generate_count ): Addresses_Generation_Result {
						assert( 2 === $generate_count );
						return $this->createMock( Addresses_Generation_Result::class );
					}
				),
			)
		);

		$sut                   = new Bitcoin_Gateway();
		$sut->settings['xpub'] = 'before';

		$xpub_after = 'after';

		$_POST['woocommerce_bitcoin_gateway_xpub'] = $xpub_after;

		$sut->process_admin_options();
	}


	/**
	 * @covers ::process_admin_options
	 */
	public function test_does_not_generate_new_addresses_when_xpub_does_not_change(): void {

		$GLOBALS['bh_wp_bitcoin_gateway'] = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array()
		);

		$sut                   = new Bitcoin_Gateway();
		$sut->settings['xpub'] = 'same';

		$_POST['woocommerce_bitcoin_gateway_xpub'] = 'same';

		assert( false === as_next_scheduled_action( Background_Jobs::GENERATE_NEW_ADDRESSES_HOOK ) );

		$sut->process_admin_options();

		$this->assertFalse( as_next_scheduled_action( Background_Jobs::GENERATE_NEW_ADDRESSES_HOOK ) );
	}

	/**
	 * @covers ::is_available
	 * @see API_Interface::is_fresh_address_available_for_gateway()
	 */
	public function test_checks_for_available_address_for_availability_true(): void {

		$GLOBALS['bh_wp_bitcoin_gateway'] = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'is_fresh_address_available_for_gateway' => Expected::once(
					function ( Bitcoin_Gateway $gateway ) {
						return true;
					}
				),
				'get_exchange_rate'                      => Expected::once(
					function ( Currency $currency ) {
						return Money::of( '0.0', Currency::of( 'USD' ) );
					}
				),
			)
		);

		$sut          = new Bitcoin_Gateway();
		$sut->enabled = 'yes';

		$result = $sut->is_available();

		$this->assertTrue( $result );
	}

	/**
	 * @covers ::is_available
	 */
	public function test_checks_for_available_address_for_availability_false(): void {

		$GLOBALS['bh_wp_bitcoin_gateway'] = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'is_fresh_address_available_for_gateway' => Expected::once(
					function ( Bitcoin_Gateway $gateway ) {
						return false;
					}
				),
			)
		);

		$sut          = new Bitcoin_Gateway();
		$sut->enabled = 'yes';

		$result = $sut->is_available();

		$this->assertFalse( $result );
	}

	/**
	 * @covers ::is_available
	 */
	public function test_checks_for_available_address_for_availability_uses_cache(): void {

		$GLOBALS['bh_wp_bitcoin_gateway'] = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'is_fresh_address_available_for_gateway' => true,
				'get_exchange_rate'                      => Money::of( 1, 'BTC' ),
			)
		);

		$sut = new class() extends Bitcoin_Gateway {
			public function __construct() {
				parent::__construct();
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

		$sut = new Bitcoin_Gateway();

		$result = $sut->is_available();

		$this->assertFalse( $result );
	}

	/**
	 * @covers ::process_payment
	 */
	public function test_process_payment_returns_exception_on_bad_order_id(): void {

		$GLOBALS['bh_wp_bitcoin_gateway'] = $this->makeEmpty( API_WooCommerce_Interface::class );

		$sut = new Bitcoin_Gateway();

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
	public function test_process_payment_returns_exception_on_missing_api_instance(): void {

		$GLOBALS['bh_wp_bitcoin_gateway'] = null;

		$sut = new Bitcoin_Gateway();

		$order    = new WC_Order();
		$order_id = $order->save();

		$exception = null;
		try {
			$sut->process_payment( $order_id );
		} catch ( Exception $e ) {
			$exception = $e;
		}

		$this->assertNotNull( $exception );
		$this->assertEquals( 'API unavailable for new Bitcoin gateway order.', $exception->getMessage() );
	}


	/**
	 * @covers ::process_payment
	 */
	public function test_process_payment_returns_exception_when_no_address_available(): void {

		$GLOBALS['bh_wp_bitcoin_gateway'] = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'get_fresh_address_for_order' => Expected::once(
					function ( WC_Order $order ) {
						throw new Exception( 'This message will not be shown!' );
					}
				),
				'convert_fiat_to_btc'         => Expected::once(
					function ( Money $money ) {
						return $money;
					}
				),
			)
		);

		$sut = new Bitcoin_Gateway();

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
