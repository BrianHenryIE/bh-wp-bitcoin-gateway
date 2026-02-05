<?php
/**
 * Blockchain.com API client for querying Bitcoin addresses and transactions.
 *
 * "Please limit your queries to a maximum of 1 every 10 seconds"
 *
 * @see https://www.blockchain.com/api/blockchain_api
 * @see https://www.blockchain.com/api/q
 * @see https://github.com/brianhenryie/bh-php-blockchain-info
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Clients\Blockchain;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Clients\Blockchain\Adapters\Blockchain_Info_Api_Transaction_Adapter;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Exceptions\BH_WP_Bitcoin_Gateway_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\Art4\Requests\Psr\HttpClient;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Clients\Blockchain_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\BlockchainInfo\BlockchainInfoApi;
use BrianHenryIE\WP_Bitcoin_Gateway\BlockchainInfo\Model\Transaction;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Maps objects from brianhenryie/bh-php-blockchain-info library to internal representations.
 */
class Blockchain_Info_Api implements Blockchain_API_Interface, LoggerAwareInterface {
	use LoggerAwareTrait;

	/**
	 * The Blockchain.com API client instance.
	 */
	protected BlockchainInfoApi $api;

	/**
	 * Constructor
	 *
	 * @param LoggerInterface        $logger A PSR logger for recording API requests and errors.
	 * @param BlockchainInfoApi|null $api Optional pre-configured API client instance, or null to create default client with HTTP adapter.
	 */
	public function __construct(
		LoggerInterface $logger,
		?BlockchainInfoApi $api = null,
	) {
		$this->logger = $logger;
		$this->api    = $api ?? $this->get_api();
	}

	/**
	 * Construct an instance of the Blockchain.com API client.
	 */
	protected function get_api(): BlockchainInfoApi {
		// Define options Requests library will set on cURL request.
		$options = array();

		$client = new HttpClient( $options );

		return new BlockchainInfoApi( $client, $client );
	}

	/**
	 * Get all transactions received by a Bitcoin address.
	 *
	 * @param string $btc_address The Bitcoin address to query for incoming transactions from the blockchain.
	 *
	 * @return Transaction_Interface[] Array of transactions where this address received funds.
	 * @throws BH_WP_Bitcoin_Gateway_Exception When the API request fails or returns rate limit errors.
	 */
	public function get_transactions_received( string $btc_address ): array {

		/**
		 * TODO: In BlockchainInfoApi functions, first check is `$response->getBody()` = 'Rate limited'.
		 */

		$raw_address = $this->api->getRawAddr( $btc_address );

		$adapter = new Blockchain_Info_Api_Transaction_Adapter();

		return array_map(
			$adapter->adapt( ... ),
			$raw_address->getTxs()
		);
	}

	/**
	 * Get the current height of the Bitcoin blockchain.
	 *
	 * @return int The current block height from Blockchain.com's API.
	 * @throws BH_WP_Bitcoin_Gateway_Exception When the API request fails or the blockchain height cannot be retrieved.
	 */
	public function get_blockchain_height(): int {

		return $this->api->getBlockCount();
	}
}
