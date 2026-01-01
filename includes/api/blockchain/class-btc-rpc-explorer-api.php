<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Blockchain;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Blockchain_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\BtcRpcExplorer\BtcRpcExplorerApi;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

class Btc_Rpc_Explorer_Api implements Blockchain_API_Interface, LoggerAwareInterface {
	use LoggerAwareTrait;

	/**
	 * Constructor
	 *
	 * @param LoggerInterface $logger A PSR logger.
	 */
	public function __construct(
		LoggerInterface $logger,
		protected BtcRpcExplorerApi $api,
	) {
		$this->logger = $logger;
	}

	public function get_blockchain_height(): int {
		return $this->api->tipHeight();
	}

	/**
	 *
	 * If this doesn't work try {@see BtcRpcExplorerApi::extendedPublicKeyTransactions()}.
	 *
	 * @param string $btc_address
	 * @return Transaction_Interface[]
	 */
	public function get_transactions_received( string $btc_address ): array {
		$address_summary = $this->api->addressSummary( $btc_address );

		$transactions = array_map(
			fn( string $txId ) => $this->api->tx( $txId ),
			$address_summary->txHistory->txIds
		);

		return array_map(
			fn( $transaction ) => new Btc_Rpc_Explorer_Api_Transaction_Adapter( $transaction, $address_summary->txHistory->blockHeightsByTxId[ $transaction->txId ] ),
			$transactions
		);
	}
}
