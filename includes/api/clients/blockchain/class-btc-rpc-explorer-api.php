<?php
/**
 * `btc-rpc-explorer` is a "Database-free, self-hosted Bitcoin explorer, via RPC to Bitcoin Core."
 *
 * @see https://github.com/janoside/btc-rpc-explorer
 * @see https://github.com/BrianHenryIE/bh-php-btc-rpc-explorer-api-client
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Clients\Blockchain;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Blockchain_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Clients\Blockchain\Adapters\Btc_Rpc_Explorer_Api_Transaction_Adapter;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\BtcRpcExplorer\BtcRpcExplorerApi;
use BrianHenryIE\WP_Bitcoin_Gateway\BtcRpcExplorer\Model\TXSummary;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Adapter to use `BtcRpcExplorerApi` as `Blockchain_API_Interface`.
 *
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
 */
class Btc_Rpc_Explorer_Api implements Blockchain_API_Interface, LoggerAwareInterface {
	use LoggerAwareTrait;

	/**
	 * Constructor
	 *
	 * @param BtcRpcExplorerApi $api The API client instance.
	 * @param LoggerInterface   $logger A PSR logger.
	 */
	public function __construct(
		protected BtcRpcExplorerApi $api,
		LoggerInterface $logger,
	) {
		$this->logger = $logger;
	}

	/**
	 * Get the current height (sequential block number) of the Bitcoin blockchain.
	 */
	public function get_blockchain_height(): int {
		return $this->api->tipHeight();
	}

	/**
	 * Query the API for all transactions relevant to the address.
	 *
	 * If this doesn't work try {@see BtcRpcExplorerApi::extendedPublicKeyTransactions()}.
	 *
	 * @param string $btc_address The payment address as a string.
	 * @return Transaction_Interface[]
	 */
	public function get_transactions_received( string $btc_address ): array {
		$address_summary = $this->api->addressSummary( $btc_address );

		// TODO: Maybe this function could/should accept a `excluding_tx_ids` array for already known/saved transactions.
		// In all likelihood, addresses will only have one or two transactions associated.
		// Q: Does the information in a transaction ever change? E.g. the block was rejected and the chain continued on a different branch.
		$transactions = array_map(
			fn( string $tx_id ) => $this->api->tx( $tx_id ),
			$address_summary->txHistory->txIds
		);

		$adapter = new Btc_Rpc_Explorer_Api_Transaction_Adapter();

		return array_map(
			fn( TXSummary $transaction ) => $adapter->adapt(
				transaction: $transaction,
				block_height: $address_summary->txHistory->blockHeightsByTxId[ $transaction->txId ]
			),
			$transactions
		);
	}
}
