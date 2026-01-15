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
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Actions_Handler;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Scheduler_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Factories\Bitcoin_Address_Factory;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Address_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Factories\Bitcoin_Transaction_Factory;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Transaction_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Wallet_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\API;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Blockchain_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Exchange_Rate_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Generate_Address_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use Psr\Log\LoggerInterface;
use stdClass;
use WP_Post;

/**
 * @coversNothing
 *
 * Uses WPTestCase because the test uses the Requests library.
 */
class Bitcoin_API_Contract_Test extends \lucatume\WPBrowser\TestCase\WPTestCase {

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
	 * @return array<Blockchain_API_Interface[]>
	 */
	public function dataprovider_get_bitcoin_apis(): array {
		$result = array();

		$logger = new ColorLogger();

		$result[] = array( new Blockchain_Info_Api( $logger ) );
		$result[] = array( new Blockstream_Info_API( $logger ) );

		return $result;
	}

	/**
	 * @dataProvider dataprovider_get_bitcoin_apis
	 *
	 * @param Blockchain_API_Interface $sut An instance of the API we are testing.
	 */
	public function test_get_transactions_received( Blockchain_API_Interface $sut ): void {
		$logger = new ColorLogger();

		$sent_to = '3KKUGZk4yU9QfZZA9y9K5MkwBX7Rozaaum';

		$result = $sut->get_transactions_received( $sent_to );

		$logger->info( get_class( $sut ) );
		$logger->info( json_encode( $result, JSON_THROW_ON_ERROR ) );

		/** @var Transaction_Interface $first_transaction */
		$first_transaction = array_pop( $result );

		self::assertEquals( '882dccf5a828a62ecc42c1251b3086ad4f315ef6864653e01f3e64a1793555bd', $first_transaction->get_txid() );
		self::assertEquals( '686306', $first_transaction->get_block_height() );
		self::assertEquals( 1622852486, $first_transaction->get_block_time()->getTimestamp() );
	}

	/**
	 * @dataProvider dataprovider_get_bitcoin_apis
	 *
	 * @param Blockchain_API_Interface $blockchain_api An instance of the API we are testing.
	 */
	public function test_save_transactions_received( ?Blockchain_API_Interface $blockchain_api ): void {

		$raw_address = '3KKUGZk4yU9QfZZA9y9K5MkwBX7Rozaaum';

		$address = $this->make(
			Bitcoin_Address::class,
			array(
				'raw_address' => $raw_address,
				'get_post_id' => 123,
			)
		);

		$bitcoin_address_factory     = new Bitcoin_Address_Factory();
		$bitcoin_transaction_factory = new Bitcoin_Transaction_Factory();
		$sut                         = $this->get_sut(
			bitcoin_address_repository: new Bitcoin_Address_Repository( $bitcoin_address_factory ),
			bitcoin_transaction_repository: new Bitcoin_Transaction_Repository( $bitcoin_transaction_factory ),
			blockchain_api: $blockchain_api,
		);
		/** @var Transaction_Interface[] $result */
		$result = $sut->update_address_transactions( $address );

		/** @var Transaction_Interface $first */
		$first = array_pop( $result );
		$ff    = $first->get_v_out();

		$this->fail( 'incomplete' );
	}
}
