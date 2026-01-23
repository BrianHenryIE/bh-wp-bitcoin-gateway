<?php
/**
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 * @author  BrianHenryIE <BrianHenryIE@gmail.com>
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\API_Background_Jobs_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Actions_Handler;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Actions_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Scheduler;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Scheduler_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Admin\Plugins_Page;
use BrianHenryIE\WP_Bitcoin_Gateway\Admin\Register_List_Tables;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Helpers\Generate_Address_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\Woo_Cancel_Abandoned_Order\Woo_Cancel_Abandoned_Order;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\API_WooCommerce_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\lucatume\DI52\Container;
use BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes\I18n;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use WP_Mock\Matcher\AnyInstance;

/**
 * Class BH_WP_Bitcoin_Gateway_Unit_Test
 *
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\BH_WP_Bitcoin_Gateway
 */
class BH_WP_Bitcoin_Gateway_Unit_Test extends \Codeception\Test\Unit {

	protected function setUp(): void {
		parent::setUp();
		\WP_Mock::setUp();
	}

	protected function tearDown(): void {
		parent::tearDown();
		\WP_Mock::tearDown();
	}
	protected function get_container(): ContainerInterface {

		$container = new Container();

		$container->bind(
			API_Interface::class,
			function () {
				return $this->makeEmpty( API_Interface::class );
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
			API_Background_Jobs_Interface::class,
			function () {
				return $this->makeEmpty( API_Background_Jobs_Interface::class );
			}
		);

		$container->bind( Background_Jobs_Scheduler_Interface::class, Background_Jobs_Scheduler::class );
		$container->bind( Background_Jobs_Actions_Interface::class, Background_Jobs_Actions_Handler::class );

		$container->bind(
			Generate_Address_API_Interface::class,
			function () {
				return $this->makeEmpty( Generate_Address_API_Interface::class );
			}
		);

		$container->bind(
			API_WooCommerce_Interface::class,
			function () {
				return $this->makeEmpty( API_WooCommerce_Interface::class );
			}
		);

		return $container;
	}

	/**
	 * @covers ::set_locale
	 */
	public function test_set_locale_hooked(): void {

		\WP_Mock::expectActionAdded(
			'init',
			array( new AnyInstance( I18n::class ), 'load_plugin_textdomain' )
		);

		$app = new BH_WP_Bitcoin_Gateway( $this->get_container() );
		$app->register_hooks();
	}

	public function test_admin_hooks(): void {
		$this->markTestSkipped( 'Not using Admin class right now' );

		\WP_Mock::expectActionAdded(
			'admin_enqueue_scripts',
			array( new AnyInstance( Admin::class ), 'enqueue_styles' )
		);

		\WP_Mock::expectActionAdded(
			'admin_enqueue_scripts',
			array( new AnyInstance( Admin::class ), 'enqueue_scripts' )
		);

		$app = new BH_WP_Bitcoin_Gateway( $this->get_container() );
		$app->register_hooks();
	}

	/**
	 * @covers ::define_plugins_page_hooks
	 */
	public function test_plugins_page_hooks(): void {

		\WP_Mock::expectFilterAdded(
			'plugin_action_links_bh-wp-bitcoin-gateway/bh-wp-bitcoin-gateway.php',
			array( new AnyInstance( Plugins_Page::class ), 'add_settings_action_link' )
		);

		\WP_Mock::expectFilterAdded(
			'plugin_action_links_bh-wp-bitcoin-gateway/bh-wp-bitcoin-gateway.php',
			array( new AnyInstance( Plugins_Page::class ), 'add_orders_action_link' )
		);

		\WP_Mock::expectFilterAdded(
			'plugin_row_meta',
			array( new AnyInstance( Plugins_Page::class ), 'split_author_link_into_two_links' ),
			10,
			2
		);

		$app = new BH_WP_Bitcoin_Gateway( $this->get_container() );
		$app->register_hooks();
	}

	/**
	 * @covers ::define_action_scheduler_hooks
	 */
	public function test_define_action_scheduler_hooks(): void {

		\WP_Mock::expectActionAdded(
			'action_scheduler_run_recurring_actions_schedule_hook',
			array( new AnyInstance( Background_Jobs_Actions_Handler::class ), 'add_action_scheduler_repeating_actions' )
		);

		\WP_Mock::expectActionAdded(
			Background_Jobs_Actions_Interface::RECURRING_ENSURE_UNUSED_ADDRESSES_HOOK,
			array( new AnyInstance( Background_Jobs_Actions_Handler::class ), 'ensure_unused_addresses' )
		);

		\WP_Mock::expectActionAdded(
			Background_Jobs_Actions_Interface::SINGLE_ENSURE_UNUSED_ADDRESSES_HOOK,
			array( new AnyInstance( Background_Jobs_Actions_Handler::class ), 'single_ensure_unused_addresses' )
		);

		\WP_Mock::expectActionAdded(
			Background_Jobs_Actions_Interface::GENERATE_NEW_ADDRESSES_HOOK,
			array( new AnyInstance( Background_Jobs_Actions_Handler::class ), 'generate_new_addresses' )
		);

		\WP_Mock::expectActionAdded(
			Background_Jobs_Actions_Interface::CHECK_NEW_ADDRESSES_TRANSACTIONS_HOOK,
			array( new AnyInstance( Background_Jobs_Actions_Handler::class ), 'check_new_addresses_for_transactions' )
		);

		\WP_Mock::expectActionAdded(
			Background_Jobs_Actions_Interface::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK,
			array( new AnyInstance( Background_Jobs_Actions_Handler::class ), 'check_assigned_addresses_for_transactions' )
		);

		\WP_Mock::expectActionAdded(
			Background_Jobs_Actions_Interface::UPDATE_EXCHANGE_RATE_HOOK,
			array( \WP_Mock\Functions::type( Background_Jobs_Actions_Handler::class ), 'update_exchange_rate' )
		);

		$app = new BH_WP_Bitcoin_Gateway( $this->get_container() );
		$app->register_hooks();
	}

	/**
	 * @covers ::define_wp_list_page_ui_hooks
	 */
	public function test_define_wp_list_page_ui_hooks(): void {

		\WP_Mock::expectFilterAdded(
			'wp_list_table_class_name',
			array( new AnyInstance( Register_List_Tables::class ), 'register_bitcoin_address_table' ),
			10,
			2
		);

		\WP_Mock::expectFilterAdded(
			'wp_list_table_class_name',
			array( new AnyInstance( Register_List_Tables::class ), 'register_bitcoin_wallet_table' ),
			10,
			2
		);

		$app = new BH_WP_Bitcoin_Gateway( $this->get_container() );
		$app->register_hooks();
	}

	/**
	 * @covers ::define_integration_woo_cancel_abandoned_order_hooks
	 */
	public function test_define_integration_woo_cancel_abandoned_order_hooks(): void {

		\WP_Mock::expectFilterAdded(
			'woo_cao_gateways',
			array( new AnyInstance( Woo_Cancel_Abandoned_Order::class ), 'enable_cao_for_bitcoin' )
		);

		\WP_Mock::expectFilterAdded(
			'woo_cao_before_cancel_order',
			array( new AnyInstance( Woo_Cancel_Abandoned_Order::class ), 'abort_canceling_partially_paid_order' ),
			10,
			3
		);

		$app = new BH_WP_Bitcoin_Gateway( $this->get_container() );
		$app->register_hooks();
	}
}
