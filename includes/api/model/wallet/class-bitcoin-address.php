<?php
/**
 *
 *
 * TODO: Update the wp_post last modified time when updating metadata.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet;

use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\Admin\Addresses_List_Table;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Bitcoin_Gateway;
use InvalidArgumentException;

/**
 * TODO: Should we rename this to payment address?
 */
class Bitcoin_Address implements Bitcoin_Address_Interface {

	/**
	 * Constructor
	 *
	 * TODO: allow setting `required_confirmations`.
	 *
	 * @param int                    $post_id The WordPress post ID for this address.
	 * @param int                    $wallet_parent_post_id The post ID of the parent wallet.
	 * @param string                 $raw_address The Bitcoin address string.
	 * @param ?int                   $derivation_path_sequence_number The derivation path sequence number.
	 * @param Bitcoin_Address_Status $status The current status of the address.
	 * @param ?Money                 $target_amount The target amount for payment.
	 * @param ?int                   $order_id The WooCommerce order ID associated with this address.
	 * @param array<int,string>|null $tx_ids Transaction IDs as post_id:tx_id.
	 * @param ?Money                 $balance The current balance of the address.
	 *
	 * @throws InvalidArgumentException When the supplied post_id is not a post of this type.
	 */
	public function __construct(
		protected int $post_id,
		protected int $wallet_parent_post_id,
		protected string $raw_address,
		protected ?int $derivation_path_sequence_number = null,
		protected Bitcoin_Address_Status $status = Bitcoin_Address_Status::UNKNOWN,
		protected ?Money $target_amount = null,
		protected ?int $order_id = null,
		protected ?array $tx_ids = null,
		protected ?Money $balance = null,
	) {
	}

	/**
	 * Get the WordPress post ID where this Bitcoin payment address is stored.
	 */
	public function get_post_id(): int {
		return $this->post_id;
	}


	/**
	 * The post ID for the xpub|ypub|zpub wallet this address was derived for.
	 *
	 * @return int
	 */
	public function get_wallet_parent_post_id(): int {
		return $this->wallet_parent_post_id;
	}

	/**
	 * Get this Bitcoin address's derivation path.
	 *
	 * @readonly
	 */
	public function get_derivation_path_sequence_number(): ?int {
		return $this->derivation_path_sequence_number;
	}

	/**
	 * Return the raw Bitcoin address this object represents.
	 *
	 * @used-by API::check_new_addresses_for_transactions() When verifying newly generated addresses have no existing transactions.
	 * @used-by API::get_fresh_address_for_order() When adding the payment address to the order meta.
	 * @used-by Bitcoin_Gateway::process_payment() When adding a link in the order notes to view transactions on a 3rd party website.
	 * @used-by API::update_address_transactions() When checking has an order been paid.
	 */
	public function get_raw_address(): string {
		return $this->raw_address;
	}

	// TODO: `get_mempool_transactions()`.

	/**
	 * Return the balance saved in the post meta, or null if the address status is unknown.
	 *
	 * TODO: Might need a $confirmations parameter and calculate the balance from the transactions.
	 *
	 * @used-by Addresses_List_Table::print_columns()
	 *
	 * @return ?Money Null if unknown.
	 */
	public function get_balance(): ?Money {
		return Bitcoin_Address_Status::UNKNOWN === $this->get_status() ? null : $this->balance;
	}

	/**
	 * TODO: "balance" is not an accurate term for what we need.
	 */
	public function get_amount_received(): ?Money {
		return $this->get_balance();
	}

	/**
	 * Return the current status of the Bitcoin address object/post.
	 */
	public function get_status(): Bitcoin_Address_Status {
		return $this->status;
	}

	/**
	 * Get the order id associated with this address, or null if none has ever been assigned.
	 */
	public function get_order_id(): ?int {
		return 0 === $this->order_id ? null : $this->order_id;
	}

	/**
	 * The received amount needed to consider the order "paid".
	 */
	public function get_target_amount(): ?Money {
		return $this->target_amount;
	}

	/**
	 * @return null|array<int, string> <post_id, tx_id> or null if it has never been checked.
	 */
	public function get_tx_ids(): ?array {
		return $this->tx_ids;
	}
}
