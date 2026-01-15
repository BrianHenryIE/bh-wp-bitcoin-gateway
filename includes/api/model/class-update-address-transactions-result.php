<?php
/**
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Model;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address;

/**
 * @used-by API::update_address_transactions()
 */
class Update_Address_Transactions_Result {

	/**
	 * Constructor
	 *
	 * @param Bitcoin_Address                   $queried_address The checked (unmodified) address details/object.
	 * @param array<int, string>                $known_tx_ids_before <post_id, tx_id>.
	 * @param array<int, Transaction_Interface> $all_transactions <post_id, Transaction_Interface>.
	 */
	public function __construct(
		public Bitcoin_Address $queried_address,
		public ?array $known_tx_ids_before,
		public array $all_transactions,
	) {
	}

	/**
	 * Filters all known transactions to those that new since this update was run.
	 *
	 * @return array<int, Transaction_Interface>
	 */
	public function get_new_transactions(): array {
		// If there were none before, all transactions are new transactions (potentially empty array).
		if ( empty( $this->known_tx_ids_before ) ) {
			return $this->all_transactions;
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

	/**
	 * Are there any new transactions?
	 */
	public function is_updated(): bool {
		return ! empty( $this->get_new_transactions() );
	}

	/**
	 * After checking for transactions, an address is unused if no transactions were found.
	 */
	public function is_unused(): bool {
		return empty( $this->all_transactions );
	}
}
