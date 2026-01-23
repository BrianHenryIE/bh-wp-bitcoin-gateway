<?php
/**
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Scheduler_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Clients\Blockchain_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Helpers\Generate_Address_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\lucatume\DI52\Container as DI52_Container;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use Codeception\Test\Unit;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use WP_Mock\Matcher\AnyInstance;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\WooCommerce_Integration
 */
class WooCommerce_Integration_Unit_Test extends Unit {

	protected function setUp(): void {
		parent::setUp();
		\WP_Mock::setUp();
	}

	protected function tearDown(): void {
		parent::tearDown();
		\WP_Mock::tearDown();
	}
	protected function get_container(): ContainerInterface&DI52_Container {

		$container = new DI52_Container();

		$container->bind(
			API_Interface::class,
			function () {
				return $this->makeEmpty( API_Interface::class );
			}
		);
		$container->bind(
			API_WooCommerce_Interface::class,
			function () {
				return $this->makeEmpty( API_WooCommerce_Interface::class );
			}
		);
		$settings = $this->makeEmpty(
			Settings_Interface::class,
			array(
				'get_plugin_basename' => 'bh-wp-bitcoin-gateway/bh-wp-bitcoin-gateway.php',
			)
		);
		$container->bind( Settings_Interface::class, $settings );
		$container->bind( LoggerInterface::class, ColorLogger::class );

		$container->bind(
			Generate_Address_API_Interface::class,
			function () {
				return $this->makeEmpty( Generate_Address_API_Interface::class );
			}
		);
				$container->bind(
					Blockchain_API_Interface::class,
					function () {
						return $this->makeEmpty( Blockchain_API_Interface::class );
					}
				);
				$container->bind(
					Background_Jobs_Scheduler_Interface::class,
					function () {
						return $this->makeEmpty( Background_Jobs_Scheduler_Interface::class );
					}
				);

		return $container;
	}

	/**
	 * @covers ::define_frontend_hooks
	 */
	public function test_frontend_hooks(): void {

		\WP_Mock::expectActionAdded(
			'wp_enqueue_scripts',
			array( new AnyInstance( Frontend_Assets::class ), 'enqueue_styles' )
		);

		\WP_Mock::expectActionAdded(
			'wp_enqueue_scripts',
			array( new AnyInstance( Frontend_Assets::class ), 'enqueue_scripts' )
		);

		$app = new WooCommerce_Integration( $this->get_container() );
		$app->register_hooks();
	}

	/**
	 * @covers ::define_email_hooks
	 */
	public function test_email_hooks(): void {

		\WP_Mock::expectActionAdded(
			'woocommerce_email_before_order_table',
			array( new AnyInstance( Email::class ), 'print_instructions' ),
			10,
			3
		);

		$app = new WooCommerce_Integration( $this->get_container() );
		$app->register_hooks();
	}

	/**
	 * @covers ::define_payment_gateway_hooks
	 */
	public function test_payment_gateway_hooks(): void {

		\WP_Mock::expectFilterAdded(
			'woocommerce_payment_gateways',
			array( new AnyInstance( Payment_Gateways::class ), 'add_to_woocommerce' )
		);

		\WP_Mock::expectActionAdded(
			'woocommerce_blocks_payment_method_type_registration',
			array( new AnyInstance( Payment_Gateways::class ), 'register_woocommerce_block_checkout_support' )
		);

		$app = new WooCommerce_Integration( $this->get_container() );
		$app->register_hooks();
	}

	/**
	 * @covers ::define_woocommerce_checkout_hooks
	 */
	public function test_define_woocommerce_checkout_hooks(): void {

		\WP_Mock::expectActionAdded(
			'woocommerce_checkout_init',
			array( new AnyInstance( Checkout::class ), 'ensure_one_address_for_payment' ),
		);

		$app = new WooCommerce_Integration( $this->get_container() );
		$app->register_hooks();
	}

	public function test_define_order_hooks(): void {

		$this->markTestSkipped( 'addresses should be checked, rather than orders' );

		\WP_Mock::expectActionAdded(
			'woocommerce_order_status_changed',
			array( new AnyInstance( Order::class ), 'schedule_check_for_transactions' ),
			10,
			3
		);

		$app = new WooCommerce_Integration( $this->get_container() );
		$app->register_hooks();
	}

	/**
	 * @covers ::define_admin_order_ui_hooks
	 */
	public function test_define_admin_order_ui_hooks(): void {

		\WP_Mock::expectActionAdded(
			'add_meta_boxes',
			array( new AnyInstance( Admin_Order_UI::class ), 'register_address_transactions_meta_box' )
		);

		$app = new WooCommerce_Integration( $this->get_container() );
		$app->register_hooks();
	}

	/**
	 * @covers ::define_woocommerce_features_hooks
	 */
	public function test_define_woocommerce_features_hooks(): void {

		\WP_Mock::expectActionAdded(
			'before_woocommerce_init',
			array( new AnyInstance( HPOS::class ), 'declare_compatibility' )
		);

		$app = new WooCommerce_Integration( $this->get_container() );
		$app->register_hooks();
	}
}
