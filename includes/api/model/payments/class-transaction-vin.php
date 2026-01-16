<?php
/**
 * Vector-in/Value-in
 *
 * TODO: This has not been fully implemented yet in a way that is working with all three APIs.
 *
 * @see https://developer.bitcoin.org/reference/transactions.html
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments;

use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;

/**
 * Represents a transaction input (vector-in) in a Bitcoin transaction.
 *
 * A transaction input references a previous transaction output that is being spent.
 */
readonly class Transaction_VIn {
	/**
	 * Constructor.
	 *
	 * @param int    $sequence The sequence number for this input, used for transaction replacement and locktime.
	 * @param string $scriptsig The scriptSig (signature script) that satisfies the conditions of the previous output's scriptPubKey.
	 * @param string $address The Bitcoin address that owned the previous output being spent.
	 * @param string $prevout_scriptpubkey The scriptPubKey from the previous transaction output being referenced.
	 * @param Money  $value The amount of Bitcoin from the previous output being spent in this input.
	 * @param int    $prev_out_n The index of the output in the previous transaction that this input is spending.
	 */
	public function __construct(
		public int $sequence,
		public string $scriptsig,
		// TODO: for transactions we are concerned about, this will usually be our payment address. Document when it might not be, e.g. miner.
		public string $address,
		public string $prevout_scriptpubkey,
		public Money $value,
		public int $prev_out_n,
		// TODO: witness.
	) {
	}
}
