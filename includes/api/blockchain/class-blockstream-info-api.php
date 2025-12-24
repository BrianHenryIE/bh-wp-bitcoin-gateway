<?php
/**
 * @see https://github.com/Blockstream/esplora/blob/master/API.md
 *
 * {
 * "error" : "Too Many Requests",
 * "message" : "Blockstream Explorer API NOTICE: Your request rate exceeds the current limit. Starting July 15 2025, monthly unauthenticated usage will be capped at 500,000 requests/month and 700 requests/hour per IP. To maintain uninterrupted access, get your API key at: https://dashboard.blockstream.info"
 * }
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Blockchain;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Blockchain_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Address_Balance;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use Exception;
use JsonException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * TODO: Complete the transaction in/out array shapes.
 *
 * @phpstan-type BlockStreamApiTransactionVInArray array{}
 * @phpstan-type BlockStreamApiTransactionVOutArray array{}
 * @phpstan-type BlockStreamApiTransactionArray array{txid:string, version:int, locktime:int, vin:BlockStreamApiTransactionVInArray, vout:BlockStreamApiTransactionVOutArray, size:int, weight:int, fee:int, status:array{confirmed:bool, block_height:int, block_hash:string, block_time:int}}
 * @phpstan-type Stats array{funded_txo_count:int, funded_txo_sum:int, spent_txo_count:int, spent_txo_sum:int, tx_count:int}
 */
class Blockstream_Info_API implements Blockchain_API_Interface, LoggerAwareInterface {
	use LoggerAwareTrait;

	/**
	 * Constructor
	 */
	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 *
	 * @return array{address:string, chain_stats:Stats, mempool_stats:Stats}
	 */
	protected function get_address_data( string $btc_address ): array {
		$address_info_url = 'https://blockstream.info/api/address/' . $btc_address;

		$this->logger->debug( 'URL: ' . $address_info_url );

		$request_response = wp_remote_get( $address_info_url );

		if ( is_wp_error( $request_response ) || 200 !== $request_response['response']['code'] ) {
			throw new Exception();
		}

		$address_info = json_decode( $request_response['body'], true );

		return $address_info;
	}

	/**
	 * @see Blockchain_API_Interface::get_address_balance()
	 *
	 * @param string $btc_address
	 * @param int    $number_of_confirmations
	 *
	 * @return Address_Balance
	 * @throws Exception
	 */
	public function get_address_balance( string $btc_address, int $number_of_confirmations ): Address_Balance {

		if ( 1 !== $number_of_confirmations ) {
			error_log( __CLASS__ . ' ' . __FUNCTION__ . ' using 1 for number of confirmations.' );

			// Maybe `number_of_confirmations` should be block_height and the client can decide is that enough.
		}

		$address_info = $this->get_address_data( $btc_address );

		$confirmed_balance = Money::of( $address_info['chain_stats']['funded_txo_sum'], 'BTC' )
									->minus(
										Money::of( $address_info['chain_stats']['spent_txo_sum'], 'BTC' )
									)->dividedBy( 100_000_000 );
		$this->logger->debug( 'Confirmed balance: ' . number_format( $confirmed_balance->getAmount()->toFloat(), 8 ), array( 'address_info' => $address_info ) );

		$unconfirmed_balance = Money::of( $address_info['mempool_stats']['funded_txo_sum'], 'BTC' )->minus( Money::of( $address_info['mempool_stats']['spent_txo_sum'], 'BTC' ) )->dividedBy( 100_000_000 );
		$this->logger->debug( 'Unconfirmed balance: ' . number_format( $unconfirmed_balance->getAmount()->toFloat(), 8 ), array( 'address_info' => $address_info ) );

		return new Blockchain_Address_Balance(
			$number_of_confirmations,
			$unconfirmed_balance,
			$confirmed_balance,
		);
	}

	/**
	 * The total amount in BTC received at this address.
	 *
	 * @param string $btc_address The Bitcoin address.
	 *
	 * @throws Exception
	 */
	public function get_received_by_address( string $btc_address, bool $confirmed ): Money {

		$address_info = $this->get_address_data( $btc_address );

		if ( $confirmed ) {
			$calc = Money::of( $address_info['chain_stats']['funded_txo_sum'], 'BTC' )
						->dividedBy( 100_000_000 );
		} else {
			$calc = Money::of( $address_info['chain_stats']['funded_txo_sum'], 'BTC' )
					->plus( Money::of( $address_info['mempool_stats']['funded_txo_sum'], 'BTC' ) )
					->dividedBy( 100_000_000 );
		}
		return $calc;
	}

	/**
	 * @param string $btc_address
	 *
	 * @return array<string, Transaction_Interface> Transactions keyed by txid.
	 *
	 * @throws JsonException
	 * @throws Rate_Limit_Exception
	 */
	public function get_transactions_received( string $btc_address ): array {

		$address_info_url_bs = "https://blockstream.info/api/address/{$btc_address}/txs";

		$this->logger->debug( 'URL: ' . $address_info_url_bs );

		$request_response = wp_remote_get( $address_info_url_bs );

		if ( is_wp_error( $request_response ) ) {
			throw new Exception( $request_response->get_error_message() );
		}
		if ( 429 === $request_response['response']['code'] ) {
			throw new Rate_Limit_Exception(
				reset_time: null,
				message: json_decode( $request_response['body'], true, 512, JSON_THROW_ON_ERROR )['message']
			);
		}

		if ( 200 !== $request_response['response']['code'] ) {
			throw new Exception( 'Unexpected response received.' );
		}

		/**
		 * @var BlockStreamApiTransactionArray[] $blockstream_transactions
		 */
		$blockstream_transactions = json_decode( $request_response['body'], true, 512, JSON_THROW_ON_ERROR );

		/**
		 * `block_time` is in unix-time.
		 *
		 * @param array{txid:string, version:int, locktime:int, vin:array, vout:array, size:int, weight:int, fee:int, status:array{confirmed:bool, block_height:int, block_hash:string, block_time:int}} $blockstream_transaction
		 *
		 * @var Transaction_Interface[] $transactions
		 */
		$transactions = array_map(
			fn( array $blockstream_transaction ) => new BlockStream_Info_API_Transaction_Adapter( $blockstream_transaction ),
			$blockstream_transactions
		);

		$keyed_transactions = array();
		foreach ( $transactions as $transaction ) {
			$keyed_transactions[ $transaction->get_txid() ] = $transaction;
		}

		return $keyed_transactions;
	}

	/**
	 * @return int
	 * @throws Exception
	 */
	public function get_blockchain_height(): int {
		$blocks_url_bs    = 'https://blockstream.info/api/blocks/tip/height';
		$request_response = wp_remote_get( $blocks_url_bs );
		if ( is_wp_error( $request_response ) || 200 !== $request_response['response']['code'] ) {
			throw new Exception();
		}
		return intval( $request_response['body'] );
	}
}
