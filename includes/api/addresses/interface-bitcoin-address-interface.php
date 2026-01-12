<?php
/**
 * The functions expected by the core plugin when using a Bitcoin_Address.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses;

use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\Admin\Addresses_List_Table;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Bitcoin_Gateway;

interface Bitcoin_Address_Interface {

	/**
	 * Get the WordPress post ID for this saved Bitcoin payment address.
	 *
	 * @return int The post ID.
	 */
	public function get_post_id(): int;

	/**
	 * The post ID for the xpub|ypub|zpub wallet this address was derived for.
	 *
	 * @return int
	 */
	public function get_wallet_parent_post_id(): int;

	/**
	 * Get this Bitcoin address's derivation path.
	 *
	 * @readonly
	 */
	public function get_derivation_path_sequence_number(): ?int;

	/**
	 * Return the raw Bitcoin address this object represents.
	 *
	 * @used-by API::check_new_addresses_for_transactions() When verifying newly generated addresses have no existing transactions.
	 * @used-by API::get_fresh_address_for_order() When adding the payment address to the order meta.
	 * @used-by Bitcoin_Gateway::process_payment() When adding a link in the order notes to view transactions on a 3rd party website.
	 * @used-by API::update_address_transactions() When checking has an order been paid.
	 */
	public function get_raw_address(): string;

	/**
	 * Return the balance saved in the post meta, or null if the address status is unknown.
	 *
	 * TODO: Might need a $confirmations parameter and calculate the balance from the transactions.
	 *
	 * @used-by Addresses_List_Table::print_columns()
	 *
	 * @return ?Money Null if unknown.
	 */
	public function get_balance(): ?Money;

	/**
	 * TODO: "balance" is not an accurate term for what we need.
	 */
	public function get_amount_received(): ?Money;

	/**
	 * Return the current status of the Bitcoin address object/post.
	 */
	public function get_status(): Bitcoin_Address_Status;

	/**
	 * Get the order id associated with this address, or null if none has ever been assigned.
	 */
	public function get_order_id(): ?int;


	/**
	 * The received amount needed to consider the order "paid".
	 */
	public function get_target_amount(): ?Money;

	/**
	 * @return ?array<int, string> <post_id, tx_id>
	 */
	public function get_tx_ids(): ?array;
}
