<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\ColorLogger\ColorLogger;
use Codeception\Stub\Expected;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Results\Update_Address_Transactions_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Results\Check_Address_For_Payment_Service_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use ReflectionClass;
use WC_Order;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Order
 */
class Order_WPUnit_Test extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * @covers ::__construct
	 * @covers ::get_wc_order
	 */
	public function test_get_wc_order_returns_null_when_integration_id_does_not_match(): void {

		$api_woocommerce_mock = $this->makeEmpty( API_WooCommerce_Interface::class );
		$logger               = new ColorLogger();
		$sut                  = new Order( $api_woocommerce_mock, $logger );

		$result = $this->invokeMethod(
			$sut,
			'get_wc_order',
			array(
				'SomeOtherIntegration',
				123,
			)
		);

		$this->assertNull( $result );
	}

	/**
	 * @covers ::get_wc_order
	 */
	public function test_get_wc_order_returns_null_when_order_does_not_exist(): void {

		$api_woocommerce_mock = $this->makeEmpty( API_WooCommerce_Interface::class );
		$logger               = new ColorLogger();
		$sut                  = new Order( $api_woocommerce_mock, $logger );

		$result = $this->invokeMethod(
			$sut,
			'get_wc_order',
			array(
				WooCommerce_Integration::class,
				99999,
			)
		);

		$this->assertNull( $result );
	}

	/**
	 * @covers ::get_wc_order
	 */
	public function test_get_wc_order_returns_order_when_valid(): void {

		$order = wc_create_order();
		$this->assertInstanceOf( WC_Order::class, $order );

		$api_woocommerce_mock = $this->makeEmpty( API_WooCommerce_Interface::class );
		$logger               = new ColorLogger();
		$sut                  = new Order( $api_woocommerce_mock, $logger );

		$result = $this->invokeMethod(
			$sut,
			'get_wc_order',
			array(
				WooCommerce_Integration::class,
				$order->get_id(),
			)
		);

		$this->assertInstanceOf( WC_Order::class, $result );
		$this->assertEquals( $order->get_id(), $result->get_id() );
	}

	/**
	 * @covers ::new_transactions_seen
	 */
	public function test_new_transactions_seen_returns_early_when_integration_id_does_not_match(): void {

		$api_woocommerce_mock = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'add_order_note_for_transactions' => Expected::never(),
			)
		);
		$logger               = new ColorLogger();
		$sut                  = new Order( $api_woocommerce_mock, $logger );

		$bitcoin_address_mock                     = $this->makeEmpty( Bitcoin_Address::class );
		$check_address_for_payment_service_result = new Check_Address_For_Payment_Service_Result(
			update_address_transactions_result: new Update_Address_Transactions_Result(
				queried_address: $bitcoin_address_mock,
				known_tx_ids_before: array(),
				all_transactions: array(),
			),
			blockchain_height: 123456,
			required_confirmations: 6,
			confirmed_received: Money::of( 0, 'BTC' )
		);

		$sut->new_transactions_seen(
			'SomeOtherIntegration',
			123,
			$bitcoin_address_mock,
			$check_address_for_payment_service_result
		);
	}

	/**
	 * @covers ::new_transactions_seen
	 */
	public function test_new_transactions_seen_returns_early_when_order_does_not_exist(): void {

		$api_woocommerce_mock = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'add_order_note_for_transactions' => Expected::never(),
			)
		);
		$logger               = new ColorLogger();
		$sut                  = new Order( $api_woocommerce_mock, $logger );

		$bitcoin_address_mock                     = $this->makeEmpty( Bitcoin_Address::class );
		$check_address_for_payment_service_result = new Check_Address_For_Payment_Service_Result(
			update_address_transactions_result: new Update_Address_Transactions_Result(
				queried_address: $bitcoin_address_mock,
				known_tx_ids_before: array(),
				all_transactions: array(),
			),
			blockchain_height: 123456,
			required_confirmations: 6,
			confirmed_received: Money::of( 0, 'BTC' )
		);

		$sut->new_transactions_seen(
			WooCommerce_Integration::class,
			99999,
			$bitcoin_address_mock,
			$check_address_for_payment_service_result
		);
	}

	/**
	 * @covers ::new_transactions_seen
	 */
	public function test_new_transactions_seen_adds_order_note_for_new_transactions(): void {

		$order = wc_create_order();
		$this->assertInstanceOf( WC_Order::class, $order );

		$new_transactions = array();

		$api_woocommerce_mock = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'add_order_note_for_transactions' => Expected::once(
					function ( $wc_order, $transactions ) use ( $order, $new_transactions ) {
						$this->assertEquals( $order->get_id(), $wc_order->get_id() );
						$this->assertEquals( $new_transactions, $transactions );
					}
				),
			)
		);
		$logger               = new ColorLogger();
		$sut                  = new Order( $api_woocommerce_mock, $logger );

		$bitcoin_address_mock = $this->makeEmpty( Bitcoin_Address::class );

		$update_address_transactions_result = new Update_Address_Transactions_Result(
			queried_address:     $bitcoin_address_mock,
			known_tx_ids_before: array(),
			all_transactions:    array()
		);

		$check_address_for_payment_service_result = new Check_Address_For_Payment_Service_Result(
			update_address_transactions_result: $update_address_transactions_result,
			blockchain_height:                   800000,
			required_confirmations:              3,
			confirmed_received:                  Money::of( '0', 'BTC' )
		);

		$sut->new_transactions_seen(
			WooCommerce_Integration::class,
			$order->get_id(),
			$bitcoin_address_mock,
			$check_address_for_payment_service_result
		);
	}

	/**
	 * @covers ::payment_received
	 */
	public function test_payment_received_returns_early_when_integration_id_does_not_match(): void {

		$api_woocommerce_mock = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'mark_order_paid' => Expected::never(),
			)
		);
		$logger               = new ColorLogger();
		$sut                  = new Order( $api_woocommerce_mock, $logger );

		$bitcoin_address_mock                     = $this->makeEmpty( Bitcoin_Address::class );
		$check_address_for_payment_service_result = new Check_Address_For_Payment_Service_Result(
			update_address_transactions_result: new Update_Address_Transactions_Result(
				queried_address: $bitcoin_address_mock,
				known_tx_ids_before: array(),
				all_transactions: array(),
			),
			blockchain_height: 123456,
			required_confirmations: 6,
			confirmed_received: Money::of( 0, 'BTC' )
		);

		$sut->payment_received(
			'SomeOtherIntegration',
			123,
			$bitcoin_address_mock,
			$check_address_for_payment_service_result
		);
	}

	/**
	 * @covers ::payment_received
	 */
	public function test_payment_received_returns_early_when_order_does_not_exist(): void {

		$api_woocommerce_mock = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'mark_order_paid' => Expected::never(),
			)
		);
		$logger               = new ColorLogger();
		$sut                  = new Order( $api_woocommerce_mock, $logger );

		$bitcoin_address_mock                     = $this->makeEmpty( Bitcoin_Address::class );
		$check_address_for_payment_service_result = new Check_Address_For_Payment_Service_Result(
			update_address_transactions_result: new Update_Address_Transactions_Result(
				queried_address: $bitcoin_address_mock,
				known_tx_ids_before: array( 123 => 'abc' ),
				all_transactions: array(),
			),
			blockchain_height: 123456,
			required_confirmations: 6,
			confirmed_received: Money::of( 0, 'BTC' )
		);

		$sut->payment_received(
			WooCommerce_Integration::class,
			99999,
			$bitcoin_address_mock,
			$check_address_for_payment_service_result
		);
	}

	/**
	 * @covers ::payment_received
	 */
	public function test_payment_received_marks_order_paid_when_valid(): void {

		$order = wc_create_order();
		$this->assertInstanceOf( WC_Order::class, $order );

		$bitcoin_address_mock = $this->makeEmpty( Bitcoin_Address::class );

		$update_address_transactions_result = new Update_Address_Transactions_Result(
			queried_address:     $bitcoin_address_mock,
			known_tx_ids_before: array(),
			all_transactions:    array()
		);

		$check_address_for_payment_service_result = new Check_Address_For_Payment_Service_Result(
			update_address_transactions_result: $update_address_transactions_result,
			blockchain_height:                   800000,
			required_confirmations:              3,
			confirmed_received:                  Money::of( '0.001', 'BTC' )
		);

		$api_woocommerce_mock = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'mark_order_paid' => Expected::once(
					function ( WC_Order $wc_order, Check_Address_For_Payment_Service_Result $result ) use ( $order, $check_address_for_payment_service_result ) {
						$this->assertEquals( $order->get_id(), $wc_order->get_id() );
						$this->assertEquals( $check_address_for_payment_service_result->blockchain_height, $result->blockchain_height );
						$this->assertEquals( $check_address_for_payment_service_result->required_confirmations, $result->required_confirmations );
						$this->assertTrue( $check_address_for_payment_service_result->confirmed_received->isEqualTo( $result->confirmed_received ) );
					}
				),
			)
		);
		$logger               = new ColorLogger();
		$sut                  = new Order( $api_woocommerce_mock, $logger );

		$sut->payment_received(
			WooCommerce_Integration::class,
			$order->get_id(),
			$bitcoin_address_mock,
			$check_address_for_payment_service_result
		);
	}

	/**
	 * Helper method to invoke protected/private methods.
	 *
	 * @param object               $object_instance The object instance.
	 * @param string               $method_name The method name to invoke.
	 * @param array<string, mixed> $parameters The method parameters.
	 *
	 * @return mixed
	 * @throws \ReflectionException If the method does not exist.
	 */
	protected function invokeMethod( object $object_instance, string $method_name, array $parameters = array() ) {
		$reflection = new ReflectionClass( $object_instance::class );
		$method     = $reflection->getMethod( $method_name );

		return $method->invokeArgs( $object_instance, $parameters );
	}
}
