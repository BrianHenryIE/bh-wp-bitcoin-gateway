<?php
/**
 * Given a Transaction, return strings to print in HTML.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Transaction_Interface;

/**
 * Just a utility class. Not strictly WooCommerce related but not used elsewhere.
 */
class Transaction_Formatter {

	/**
	 * URL to the transaction details on blockchain.com.
	 *
	 * @param Transaction_Interface $transaction The transaction to get URL for.
	 * @return string The URL to view the transaction.
	 */
	public static function get_url( Transaction_Interface $transaction ): string {
		return sprintf(
			'https://blockchain.com/explorer/transactions/btc/%s',
			$transaction->get_txid()
		);
	}

	/**
	 * Given a long string, return a short version with ellipses in the middle.
	 *
	 * @param Transaction_Interface $transaction The transaction to format.
	 * @return string The shortened transaction ID with ellipses.
	 */
	public static function get_ellipses( Transaction_Interface $transaction ): string {
		return substr( $transaction->get_txid(), 0, 3 ) . '...' . substr( $transaction->get_txid(), - 3 );
	}

	/**
	 * Generate an order note for newly seen transactions.
	 *
	 * @param Transaction_Interface[] $new_order_transactions New transactions to include in the note.
	 * @return string The HTML formatted order note.
	 */
	public static function get_order_note( array $new_order_transactions ): string {

		$note                   = '';
		$plural                 = count( $new_order_transactions ) === 1 ? '' : 's';
		$note                  .= "New transaction{$plural} seen: ";
		$new_transactions_notes = array();
		foreach ( $new_order_transactions as $new_transaction ) {
			$new_transactions_notes[] = self::get_note_part( $new_transaction );
		}
		$note .= implode( ',', $new_transactions_notes ) . ".\n\n";

		return $note;
	}

	/**
	 * Get HTML formatted single transaction data for use in a WooCommerce order note.
	 *
	 * @param Transaction_Interface $transaction The transaction to format.
	 * @return string The formatted note part with link and block height.
	 */
	protected static function get_note_part( Transaction_Interface $transaction ): string {
		return sprintf(
			'<a href="%s" target="_blank">%s</a>, @%s',
			esc_url( self::get_url( $transaction ) ),
			self::get_ellipses( $transaction ),
			$transaction->get_block_height() ?? 'mempool'
		);
	}
}
