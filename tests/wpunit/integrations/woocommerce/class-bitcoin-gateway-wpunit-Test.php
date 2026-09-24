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
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Exceptions\BH_WP_Bitcoin_Gateway_Exception;
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
		?ColorLogger $logger = null,
	): Bitcoin_Gateway {
		return new Bitcoin_Gateway(
			api: $api ?? $this->makeEmpty( API_Interface::class ),
			api_woocommerce: $api_woocommerce ?? $this->makeEmpty( API_WooCommerce_Interface::class ),
			plugin_settings: $plugin_settings ?? $this->makeEmpty( Settings_Interface::class ),
			logger: $logger ?? new ColorLogger(),
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
					fn( Currency $currency ) => Money::of( '0.0', Currency::of( 'USD' ) )
				),
			)
		);
		$api_woocommerce = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'is_unused_address_available_for_gateway' => Expected::once(
					fn( Bitcoin_Gateway $gateway ) => true
				),
			)
		);

		$sut          = $this->get_sut( api: $api, api_woocommerce: $api_woocommerce );
		$sut->enabled = 'yes';

		$result = $sut->is_available();

		$this->assertTrue( $result );
	}

	/**
	 * `is_available()` runs on every cart and checkout page load; a repository or API failure must hide the
	 * gateway and log, never crash the page.
	 *
	 * @covers ::is_available
	 */
	public function test_is_available_returns_false_and_logs_when_check_throws(): void {

		$api_woocommerce = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'is_unused_address_available_for_gateway' => Expected::once(
					function () {
						throw new \RuntimeException( 'Database gone away' );
					}
				),
			)
		);
		$logger          = new ColorLogger();

		$sut          = $this->get_sut( api_woocommerce: $api_woocommerce, logger: $logger );
		$sut->enabled = 'yes';

		$this->assertFalse( $sut->is_available() );
		$this->assertTrue( $logger->hasErrorThatContains( 'Database gone away' ) );
	}

	/**
	 * Saving the gateway settings must not fatal when the wallet cannot be created; the settings are already
	 * saved by the parent, so show the problem as a settings error instead.
	 *
	 * @covers ::process_admin_options
	 */
	public function test_process_admin_options_does_not_throw_when_wallet_save_fails(): void {

		$api    = $this->makeEmpty(
			API_Interface::class,
			array(
				'get_or_save_wallet_for_master_public_key' => Expected::once(
					function () {
						throw new \RuntimeException( 'Duplicate wallet posts found' );
					}
				),
			)
		);
		$logger = new ColorLogger();

		$sut                   = $this->get_sut( api: $api, logger: $logger );
		$sut->settings['xpub'] = 'xpub-value';

		$_POST[ 'woocommerce_' . $sut->id . '_xpub' ] = 'xpub-value';

		$sut->process_admin_options();

		$this->assertTrue( $logger->hasErrorThatContains( 'Duplicate wallet posts found' ) );
		$this->assertNotEmpty( $sut->get_errors() );
	}

	/**
	 * @covers ::process_payment
	 * @covers ::prepare_bitcoin_payment
	 */
	public function test_process_payment_shows_friendly_message_when_exchange_rate_unavailable(): void {

		$api    = $this->makeEmpty(
			API_Interface::class,
			array(
				'convert_fiat_to_btc' => Expected::once(
					function () {
						throw new BH_WP_Bitcoin_Gateway_Exception( 'No exchange rate available' );
					}
				),
			)
		);
		$logger = new ColorLogger();

		$sut = $this->get_sut( api: $api, logger: $logger );

		$order    = new WC_Order();
		$order_id = $order->save();

		$exception = null;
		try {
			$sut->process_payment( $order_id );
		} catch ( Exception $e ) {
			$exception = $e;
		}

		$this->assertInstanceOf( BH_WP_Bitcoin_Gateway_Exception::class, $exception );
		$this->assertStringContainsString( 'Unable to determine the Bitcoin exchange rate', $exception->getMessage() );
		$this->assertTrue( $logger->hasErrorThatContains( 'No exchange rate available' ) );
	}

	/**
	 * An `Error` (not `Exception`) escapes WooCommerce's own catch and would be a fatal at the checkout.
	 *
	 * @covers ::process_payment
	 */
	public function test_process_payment_converts_errors_to_friendly_exception(): void {

		$api             = $this->makeEmpty(
			API_Interface::class,
			array(
				'convert_fiat_to_btc' => Expected::once(
					fn( Money $money ) => $money
				),
			)
		);
		$api_woocommerce = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'assign_unused_address_to_order' => Expected::once(
					function () {
						throw new \TypeError( 'Argument #1 must be of type Bitcoin_Gateway, null given' );
					}
				),
			)
		);
		$logger          = new ColorLogger();

		$sut = $this->get_sut( api: $api, api_woocommerce: $api_woocommerce, logger: $logger );

		$order    = new WC_Order();
		$order_id = $order->save();

		$exception = null;
		try {
			$sut->process_payment( $order_id );
		} catch ( \Throwable $e ) {
			$exception = $e;
		}

		$this->assertInstanceOf( BH_WP_Bitcoin_Gateway_Exception::class, $exception );
		$this->assertTrue( $logger->hasErrorThatContains( 'null given' ) );
	}

	/**
	 * @covers ::is_available
	 */
	public function test_checks_for_available_address_for_availability_false(): void {

		$api_woocommerce = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'is_fresh_address_available_for_gateway' => Expected::once(
					fn( Bitcoin_Gateway $gateway ) => false
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

		$logger = new ColorLogger();
		$sut    = $this->get_sut( logger: $logger );

		$exception = null;
		try {
			$sut->process_payment( 123 );
		} catch ( Exception $e ) {
			$exception = $e;
		}

		$this->assertInstanceOf( BH_WP_Bitcoin_Gateway_Exception::class, $exception );
		$this->assertStringContainsString( 'There was a problem preparing your Bitcoin payment', $exception->getMessage() );
		// WooCommerce's underlying "Invalid order." is kept for the log and as the previous exception.
		$this->assertTrue( $logger->hasErrorThatContains( 'Invalid order.' ) );
		$this->assertEquals( 'Invalid order.', $exception->getPrevious()?->getMessage() );
	}

	/**
	 * @covers ::process_payment
	 */
	public function test_process_payment_returns_exception_when_no_address_available(): void {

		$api             = $this->makeEmpty(
			API_Interface::class,
			array(
				'convert_fiat_to_btc' => Expected::once(
					fn( Money $money ) => $money
				),
			)
		);
		$api_woocommerce = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'assign_unused_address_to_order' => Expected::once(
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

	/**
	 * Test that the method description includes a link to view scheduled actions.
	 * The get_view_scheduled_actions_link() method is protected, so we test it indirectly
	 * through get_method_description() which calls it.
	 *
	 * @covers ::get_view_scheduled_actions_link
	 * @covers ::get_method_description
	 */
	public function test_get_view_scheduled_actions_link(): void {
		$api = $this->makeEmpty(
			API_Interface::class,
			array(
				'get_exchange_rate' => Expected::once(
					fn( Currency $currency ) => Money::of( '100000', $currency )
				),
			)
		);

		$sut    = $this->get_sut( api: $api );
		$result = $sut->get_method_description();

		// Verify the link to Action Scheduler is present.
		$this->assertStringContainsString( '<a href=', $result );
		$this->assertStringContainsString( 'View Scheduled Actions', $result );

		// Verify it links to the tools.php admin page.
		$this->assertStringContainsString( 'tools.php', $result );

		// Verify the link contains the correct query parameters.
		$this->assertStringContainsString( 'page=action-scheduler', $result );
		$this->assertStringContainsString( 'status=pending', $result );
		$this->assertStringContainsString( 'orderby=schedule', $result );
		$this->assertStringContainsString( 'order=desc', $result );
		$this->assertStringContainsString( 's=bh_wp_bitcoin_gateway', $result );
	}
}
