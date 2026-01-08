<?php
/**
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Model;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Wallet;

class Update_Address_Transactions_Result {

	/**
	 * Constructor
	 *
	 * @param Bitcoin_Address                   $address
	 * @param array<int, string>                $known_tx_ids_before <post_id, tx_id>
	 * @param array<int, Transaction_Interface> $all_transactions <post_id, Transaction_Interface>
	 */
	public function __construct(
		public Bitcoin_Address $address,
		public ?array $known_tx_ids_before,
		public array $all_transactions,
	) {
	}

	/**
	 * @return array<int, Transaction_Interface>
	 */
	public function get_new_transactions(): array {
		if ( is_null( $this->known_tx_ids_before ) ) {
			return array();
		}
		$new_transactions = array();
		foreach ( $this->all_transactions as $post_id => $transaction ) {
			if ( in_array( $post_id, array_keys( $this->known_tx_ids_before ), true ) ) {
				continue;
			}
			$new_transactions[ $post_id ] = $transaction;
		}
		return $new_transactions;
	}
}
