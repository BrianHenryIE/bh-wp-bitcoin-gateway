<?php
/**
 * All tests on classes implementing the Bitcoin_API_Interface should return the same values, so use a dataprovider.
 *
 * This should be in the Contracts test folder.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Clients\Blockchain;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Clients\Blockchain_API_Extended_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Helpers\JsonMapper\JsonMapper_Helper;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Exceptions\Rate_Limit_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Factories\Bitcoin_Transaction_Factory;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Transaction_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Clients\Blockchain_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Payment_Service;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Exception\MoneyMismatchException;
use Exception;
use JsonException;
use lucatume\WPBrowser\TestCase\WPTestCase;
use Psr\Log\LoggerInterface;

/**
 * @coversNothing
 *
 * Uses WPTestCase because the test uses the Requests library.
 */
class Bitcoin_API_Contract_Test extends WPTestCase {

	protected LoggerInterface $logger;

	protected function get_logger(): LoggerInterface {
		if ( ! isset( $this->logger ) ) {
			$this->logger = new ColorLogger();
		}
		return $this->logger;
	}

	protected function get_payment_service(
		Blockchain_API_Extended_Interface $blockchain_api,
		?Bitcoin_Transaction_Repository $bitcoin_transaction_repository = null,
	): Payment_Service {
		return new Payment_Service(
			blockchain_api: $blockchain_api,
			bitcoin_transaction_repository: $bitcoin_transaction_repository
				?? ( fn() => new Bitcoin_Transaction_Repository( new Bitcoin_Transaction_Factory( new JsonMapper_Helper()->build() ) ) )(),
			logger: $this->get_logger(),
		);
	}

	/**
	 * @return array<Blockchain_API_Extended_Interface[]>
	 */
	public function dataprovider_get_bitcoin_apis(): array {
		$result = array();

		$result[] = array( new Blockchain_Info_Api_Extended( $this->get_logger() ) );
		$result[] = array( new Blockstream_Info_API_Extended( $this->get_logger() ) );

		return $result;
	}

	/**
	 * @dataProvider dataprovider_get_bitcoin_apis
	 *
	 * @param Blockchain_API_Interface $sut An instance of the API we are testing.
	 * @throws JsonException
	 * @throws Rate_Limit_Exception
	 */
	public function test_get_transactions_received( Blockchain_API_Interface $sut ): void {
		$logger = new ColorLogger();

		$sent_to = '3KKUGZk4yU9QfZZA9y9K5MkwBX7Rozaaum';

		$result = $sut->get_transactions_received( $sent_to );

		$logger->info( $sut::class );
		$logger->info( json_encode( $result, JSON_THROW_ON_ERROR ) );

		/** @var Transaction_Interface $first_transaction */
		$first_transaction = array_pop( $result );

		self::assertEquals( '882dccf5a828a62ecc42c1251b3086ad4f315ef6864653e01f3e64a1793555bd', $first_transaction->get_txid() );
		self::assertEquals( '686306', $first_transaction->get_block_height() );
		self::assertEquals( 1622852486, $first_transaction->get_block_time()->getTimestamp() );
	}

	/**
	 * Ensure our internal calculations match the
	 *
	 * @dataProvider dataprovider_get_bitcoin_apis
	 *
	 * @param Blockchain_API_Extended_Interface $blockchain_api An instance of the API we are testing.
	 * @throws MoneyMismatchException
	 * @throws Exception
	 */
	public function test_save_transactions_received( Blockchain_API_Extended_Interface $blockchain_api ): void {

		$raw_address = '3KKUGZk4yU9QfZZA9y9K5MkwBX7Rozaaum';

		$address = $this->make(
			Bitcoin_Address::class,
			array(
				'raw_address' => $raw_address,
				'get_post_id' => 123,
				'get_tx_ids'  => array(),
			)
		);

		$payment_service = $this->get_payment_service( $blockchain_api );

		$received = $blockchain_api->get_received_by_address( $raw_address, true );

		$result = $payment_service->check_address_for_payment( $address );

		$this->assertTrue( $result->confirmed_received->isEqualTo( $received ) );
	}
}
