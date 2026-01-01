<?php
/**
 * "Please limit your queries to a maximum of 1 every 10 seconds"
 *
 * @see https://www.blockchain.com/api/blockchain_api
 * @see https://www.blockchain.com/api/q
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Blockchain;

use BrianHenryIE\WP_Bitcoin_Gateway\Art4\Requests\Psr\HttpClient;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Blockchain_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\BlockchainInfo\BlockchainInfoApi;
use BrianHenryIE\WP_Bitcoin_Gateway\BlockchainInfo\Model\Transaction;
use Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

class Blockchain_Info_Api implements Blockchain_API_Interface, LoggerAwareInterface {
	use LoggerAwareTrait;

	protected BlockchainInfoApi $api;

	/**
	 * Constructor
	 *
	 * @param LoggerInterface $logger A PSR logger.
	 */
	public function __construct(
		LoggerInterface $logger,
		?BlockchainInfoApi $api = null,
	) {
		$this->logger = $logger;
		$this->api    = $api ?? $this->get_api();
	}

	protected function get_api(): BlockchainInfoApi {
		// Define Requests options
		$options = array();

		$client = new HttpClient( $options );

		return new BlockchainInfoApi( $client, $client );
	}

	/**
	 * @param string $btc_address
	 *
	 * @return Transaction_Interface[]
	 * @throws Exception
	 */
	public function get_transactions_received( string $btc_address ): array {
		$raw_address = $this->api->getRawAddr( $btc_address );

		// TODO: check this returns the array indexed by the txid.
		return array_map(
			fn( Transaction $blockchain_transaction ) => new Blockchain_Info_Api_Transaction_Adapter( $blockchain_transaction ),
			$raw_address->getTxs()
		);
	}

	/**
	 * @throws Exception
	 */
	public function get_blockchain_height(): int {

		return $this->api->getBlockCount();
	}
}
