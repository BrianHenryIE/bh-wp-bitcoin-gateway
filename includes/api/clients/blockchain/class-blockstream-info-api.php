<?php
/**
 * @see https://github.com/Blockstream/esplora/blob/master/API.md
 * @see https://dashboard.blockstream.info
 *
 * {
 * "error" : "Too Many Requests",
 * "message" : "Blockstream Explorer API NOTICE: Your request rate exceeds the current limit. Starting July 15 2025, monthly unauthenticated usage will be capped at 500,000 requests/month and 700 requests/hour per IP. To maintain uninterrupted access, get your API key at: https://dashboard.blockstream.info"
 * }
 *
 * @see https://github.com/Blockstream/esplora/issues/519
 * @see https://github.com/Blockstream/esplora/issues/449
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Clients\Blockchain;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Blockchain_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Clients\Blockchain\Adapters\BlockStream_Info_API_Transaction_Adapter;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\BH_WP_Bitcoin_Gateway_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Rate_Limit_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_Interface;
use JsonException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * TODO: Complete the transaction in/out array shapes.
 *
 * @phpstan-type BlockStreamApiTransactionVInArray array{txid:string, vout:int, prevout:array{scriptpubkey:string, scriptpubkey_asm:string, scriptpubkey_type:string, scriptpubkey_address:string, value:int}, scriptsig:string, scriptsig_asm:string, witness:array<string>, is_coinbase:bool, sequence:int, inner_redeemscript_asm:string, inner_witnewssscript_asm:string}
 * @phpstan-type BlockStreamApiTransactionVOutArray array{scriptpubkey:string, scriptpubkey_asm:string, scriptpubkey_type:string, scriptpubkey_address:string, value:int}
 * @phpstan-type BlockStreamApiTransactionArray array{txid:string, version:int, locktime:int, vin:BlockStreamApiTransactionVInArray, vout:BlockStreamApiTransactionVOutArray, size:int, weight:int, fee:int, status:array{confirmed:bool, block_height:int, block_hash:string, block_time:int}}
 * @phpstan-type Stats array{funded_txo_count:int, funded_txo_sum:int, spent_txo_count:int, spent_txo_sum:int, tx_count:int}
 */
class Blockstream_Info_API implements Blockchain_API_Interface, LoggerAwareInterface {
	use LoggerAwareTrait;

	/**
	 * Constructor
	 *
	 * @param LoggerInterface $logger Logger instance for debug logging API calls.
	 */
	public function __construct(
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );
	}

	/**
	 * Get all transactions received for a Bitcoin address.
	 *
	 * @param string $btc_address The Bitcoin address to query.
	 *
	 * @return array<string, Transaction_Interface> Transactions keyed by txid.
	 *
	 * @throws JsonException When JSON decoding of the API response fails.
	 * @throws Rate_Limit_Exception When HTTP 429 is returned, indicating the rate limit has been exceeded.
	 * @throws BH_WP_Bitcoin_Gateway_Exception When the API request fails or returns an unexpected response code.
	 */
	public function get_transactions_received( string $btc_address ): array {

		$address_info_url_bs = "https://blockstream.info/api/address/{$btc_address}/txs";

		$this->logger->debug( 'URL: ' . $address_info_url_bs );

		$request_response = wp_remote_get( $address_info_url_bs );

		if ( is_wp_error( $request_response ) ) {
			throw new BH_WP_Bitcoin_Gateway_Exception( $request_response->get_error_message() );
		}
		if ( 429 === $request_response['response']['code'] ) {
			/** @var array{error:string, message:string} $blockstream_rate_limit_response */
			$blockstream_rate_limit_response = json_decode( $request_response['body'], true, 512, JSON_THROW_ON_ERROR );
			throw new Rate_Limit_Exception(
				reset_time: null,
				message: $blockstream_rate_limit_response['message']
			);
		}

		if ( 200 !== $request_response['response']['code'] ) {
			throw new BH_WP_Bitcoin_Gateway_Exception( 'Unexpected response received.' );
		}

		/**
		 * @var BlockStreamApiTransactionArray[] $blockstream_transactions
		 */
		$blockstream_transactions = json_decode( $request_response['body'], true, 512, JSON_THROW_ON_ERROR );

		$adapter = new BlockStream_Info_API_Transaction_Adapter();

		/**
		 * `block_time` is in unix-time.
		 *
		 * @param array{txid:string, version:int, locktime:int, vin:array, vout:array, size:int, weight:int, fee:int, status:array{confirmed:bool, block_height:int, block_hash:string, block_time:int}} $blockstream_transaction
		 *
		 * @var Transaction_Interface[] $transactions
		 */
		$transactions = array_map(
			fn( array $blockstream_transaction ) => $adapter->adapt( $blockstream_transaction ),
			$blockstream_transactions
		);

		$keyed_transactions = array();
		foreach ( $transactions as $transaction ) {
			$keyed_transactions[ $transaction->get_txid() ] = $transaction;
		}

		return $keyed_transactions;
	}

	/**
	 * Get the current Bitcoin blockchain height.
	 *
	 * @return int The current block height from Blockstream's API.
	 * @throws BH_WP_Bitcoin_Gateway_Exception When the API request fails or returns a non-200 status code.
	 */
	public function get_blockchain_height(): int {
		$blocks_url_bs    = 'https://blockstream.info/api/blocks/tip/height';
		$request_response = wp_remote_get( $blocks_url_bs );
		if ( is_wp_error( $request_response ) || 200 !== $request_response['response']['code'] ) {
			throw new BH_WP_Bitcoin_Gateway_Exception();
		}
		return intval( $request_response['body'] );
	}
}
