<?php
/**
 *
 *
 * TODO: Update the wp_post last modified time when updating metadata.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses;

use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\Admin\Addresses_List_Table;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Bitcoin_Gateway;
use RuntimeException;
use InvalidArgumentException;
use WP_Post;

/**
 * TODO: Should we rename this to payment address?
 */
class Bitcoin_Address implements Bitcoin_Address_Interface {

	// **
	// * The wp_post database row, as a WordPress post object, for the custom post type used to store the data.
	// */
	// protected WP_Post $post;
	//
	// **
	// * Current status of the address, used, unused, assigned...
	// */
	// protected Bitcoin_Address_Status $status;
	//
	// **
	// * The wp_post.id for the {@see Bitcoin_Wallet} the address was derived from.
	// */
	// protected int $wallet_parent_post_id;
	//
	// **
	// * The nth address generated from the wallet.
	// *
	// * TODO: Why is this nullable
	// */
	// protected ?int $derivation_path_sequence_number;
	//
	// ** The Bitcoin xpub address shared with the customer for payment. */
	// protected string $raw_address;
	//
	// ** @var array<string,Transaction_Interface> */
	// protected ?array $transactions = null;
	//
	// ** The address will be considered paid when this amount has been received */
	// protected ?Money $target_amount;
	//
	// TODO: Add `protected ?int $required_number_of_confirmations`.
	//
	// **
	// * The saved balance. Really be a calculation on the transactions.
	// */
	// protected ?Money $balance;
	//
	// **
	// * The wp post_id of the associated order this address has been assigned to.
	// */
	// protected ?int $order_id;

	/**
	 * Constructor
	 *
	 * @param array<int,string>|null $tx_ids post_id:tx_id.
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
		// protected ?int $required_confirmations,
		protected ?int $order_id = null,
		protected ?array $tx_ids = null,
		protected ?Money $balance = null,
	) {
	}

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
