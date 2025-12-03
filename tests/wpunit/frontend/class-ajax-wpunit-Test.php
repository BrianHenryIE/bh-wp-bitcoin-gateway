<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Frontend;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\API_WooCommerce_Interface;
use Codeception\Stub\Expected;
use Exception;
use WC_Order;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Frontend\AJAX
 */
class AJAX_WPUnit_Test extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * @covers ::get_order_details
	 * @covser ::__construct
	 */
	public function test_omit_data(): void {

		$logger = new ColorLogger();

		$order    = new WC_Order();
		$order_id = $order->save();

		$data_from_api_class = array(
			'btc_total_formatted'                         => '฿ 0.00041811263954509',
			'btc_exchange_rate_formatted'                 => '<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">&#36;</span>23,917.00</bdi></span>',
			'order_status_before_formatted'               => 'On hold',
			'order_status_formatted'                      => 'On hold',
			'btc_amount_received_formatted'               => '฿ 0',
			'last_checked_time_formatted'                 => 'January 29, 2023, 7:58 pm +00:00',
			'btc_address_derivation_path_sequence_number' => 15,
			'parent_wallet_xpub_html'                     => '<span style="border-bottom: 1px dashed #999; word-wrap: break-word" onclick="this.innerText = this.innerText === \'zpub6n37hVDJHFyDG1hBERbMBVjEd6ws6zVhg9bMs5STo21i9DgDE9Z9KTedtGxikpbkaucTzpj79n6Xg8Zwb9kY8bd9GyPh9WVRkM55uK7w97K\' ? \'zpub6n3 ... 97K\' : \'zpub6n37hVDJHFyDG1hBERbMBVjEd6ws6zVhg9bMs5STo21i9DgDE9Z9KTedtGxikpbkaucTzpj79n6Xg8Zwb9kY8bd9GyPh9WVRkM55uK7w97K\';" title="zpub6n37hVDJHFyDG1hBERbMBVjEd6ws6zVhg9bMs5STo21i9DgDE9Z9KTedtGxikpbkaucTzpj79n6Xg8Zwb9kY8bd9GyPh9WVRkM55uK7w97K"\'>zpub6n3 ... 97K</span>',
			'order'                                       => $order,
			'btc_total'                                   => '0.00041811263954509',
			'btc_exchange_rate'                           => '23917',
			'btc_address'                                 => 'bc1q6w640pe0cg8w0xsvwja37agy0s0ru0pkf9m5tz',
			'transactions'                                => null,
			'btc_amount_received'                         => 0.0,
			'status'                                      => 'Awaiting Payment',
		);

		$api = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'get_formatted_order_details' => Expected::once( $data_from_api_class ),
			)
		);

		$_POST['order_id']       = $order_id;
		$_REQUEST['_ajax_nonce'] = wp_create_nonce( Frontend_Assets::class );

		/**
		 * @see wp_doing_ajax()
		 */
		add_filter(
			'wp_doing_ajax',
			function (): bool {
				throw new Exception();
			}
		);

		$sut = new AJAX( $api, $logger );

		ob_start();

		try {
			$sut->get_order_details();
			// phpcs:disable Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		} catch ( Exception $e ) {
			// We're only using an exception here to avoid `die()` being called.
		}

		$output = ob_get_flush();

		$result = json_decode( $output, true ) ?: array();

		$this->assertArrayNotHasKey( 'btc_address_derivation_path_sequence_number', $result );
		$this->assertArrayNotHasKey( 'parent_wallet_xpub_html', $result );
		$this->assertArrayNotHasKey( 'order', $result );
	}
}
