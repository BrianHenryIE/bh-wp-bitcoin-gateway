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
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Address_Balance;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\BlockchainInfo\BlockchainInfoApi;
use BrianHenryIE\WP_Bitcoin_Gateway\BlockchainInfo\Model\Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
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

		// Define Requests options
		$options = array();

		$client = new HttpClient( $options );

		$this->api = $api ?? new BlockchainInfoApi( $client, $client );
	}

	/**
	 *
	 * @see Blockchain_API_Interface::get_received_by_address()
	 *
	 * @param string $btc_address
	 * @param bool   $confirmed
	 *
	 * @throws Exception
	 */
	public function get_received_by_address( string $btc_address, bool $confirmed ): Money {
		$value = Money::of( $this->api->getReceivedByAddress( $btc_address, $confirmed ), 'BTC' );
		return $value->dividedBy( pow( 10, 8 ) ); // Convert from Satoshis to BTC.
	}

	public function get_address_balance( string $btc_address, int $number_of_confirmations ): Address_Balance {

		$unconfirmed_balance = Money::of( $this->api->getAddressBalance( $btc_address, 0 ), 'BTC' );
		$confirmed_balance   = Money::of( $this->api->getAddressBalance( $btc_address, $number_of_confirmations ), 'BTC' );

		return new Blockchain_Address_Balance(
			$number_of_confirmations,
			$unconfirmed_balance->dividedBy( pow( 10, 8 ) ), // Convert from Satoshis to BTC.
			$confirmed_balance->dividedBy( pow( 10, 8 ) )
		);
	}

	/**
	 * @param string $btc_address
	 *
	 * @return Transaction_Interface[]
	 * @throws Exception
	 */
	public function get_transactions_received( string $btc_address ): array {
		$raw_address = $this->api->getRawAddr( $btc_address );

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
