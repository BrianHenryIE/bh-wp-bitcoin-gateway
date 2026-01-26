<?php
/**
 * "vector out"
 *
 * NB: This does not account for multisig transactions.
 *
 * @see https://developer.bitcoin.org/reference/transactions.html
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments;

use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;

/**
 * @used-by Transaction_Interface::get_v_out()
 */
readonly class Transaction_VOut {

	/**
	 * Constructor.
	 *
	 * @see https://learnmeabitcoin.com/technical/transaction/output/scriptpubkey/
	 *
	 * @param Money   $value The value of the transaction output.
	 * @param ?string $scriptpubkey_address Locking code.
	 */
	public function __construct(
		public Money $value,
		// public int $n, // index of vouts for transaction?
		// TODO: properly parse the scriptpubkey to an object with all its properties.
		// TODO: when could this be null BtcRpcExplorer says: "null for non-standard scripts".
		public ?string $scriptpubkey_address,
	) {
	}
}
