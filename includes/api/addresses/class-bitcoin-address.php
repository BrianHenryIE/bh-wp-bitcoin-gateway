<?php
/**
 *
 *
 * TODO: Update the wp_post last modified time when updating metadata.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Exception\MoneyMismatchException;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Exception\UnknownCurrencyException;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\Admin\Addresses_List_Table;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Bitcoin_Gateway;
use RuntimeException;
use InvalidArgumentException;
use WP_Post;

/**
 * @phpstan-type WpUpdatePostArray array{ID?: int, post_author?: int, post_date?: string, post_date_gmt?: string, post_content?: string, post_content_filtered?: string, post_title?: string, post_excerpt?: string}
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

	protected Bitcoin_Address_Repository $bitcoin_address_repository;

	/**
	 * Constructor
	 *
	 * @param WP_Post                 $post The wp_post the Bitcoin address detail is stored as.
	 * @param Transaction_Interface[] $transactions TODO: or should this be the ids of those posts?
	 *
	 * @throws InvalidArgumentException When the supplied post_id is not a post of this type.
	 */
	public function __construct(
		protected WP_Post $post,
		protected int $wallet_parent_post_id,
		protected Bitcoin_Address_Status $status,
		protected ?int $derivation_path_sequence_number,
		protected string $raw_address,
		protected ?Money $target_amount,
		protected ?Money $balance,
		protected ?int $order_id,
		protected ?array $transactions = null,
	) {
	}

	public function set_bitcoin_address_repository( Bitcoin_Address_Repository $bitcoin_address_repository ): void {
		$this->bitcoin_address_repository = $bitcoin_address_repository;
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

	/**
	 * Return the previously saved transactions for this address.
	 *
	 * @used-by API::update_address_transactions() When checking previously fetched transactions before a new query.
	 * @used-by API::get_order_details() When displaying the order/address details in the admin/frontend UI.
	 * @used-by Addresses_List_Table::print_columns() When displaying all addresses.
	 *
	 * @return array<string,Transaction_Interface>|null
	 */
	public function get_blockchain_transactions(): ?array {
		return $this->transactions;
	}

	// TODO: `get_mempool_transactions()`.

	/**
	 * Save the transactions recently fetched from the API.
	 *
	 * @used-by API::update_address_transactions()
	 *
	 * @param array<string,Transaction_Interface> $refreshed_transactions Array of the transaction details keyed by each transaction id.
	 */
	public function set_transactions( array $refreshed_transactions ): void {

		$update = array(
			'ID'         => $this->post->ID,
			'meta_input' => array(
				Bitcoin_Address_WP_Post_Interface::TRANSACTION_META_KEY => $refreshed_transactions,
			),
		);

		if ( empty( $refreshed_transactions ) ) {
			$update['post_status'] = Bitcoin_Address_Status::UNUSED->value;
		} elseif ( Bitcoin_Address_Status::UNKNOWN === $this->get_status() ) {
			$update['post_status'] = Bitcoin_Address_Status::USED->value;
		}

		$this->wp_update_post( $update );
		$this->transactions = $refreshed_transactions;
	}

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
	 * From the received transactions, sum those who have enough confirmations.
	 *
	 * @param int $blockchain_height The current blockchain height. (TODO: explain why).
	 * @param int $required_confirmations A confirmation is a subsequent block mined after the transaction.
	 *
	 * @throws MoneyMismatchException If the calculations were somehow using two different currencies.
	 * @throws UnknownCurrencyException If `BTC` has not correctly been added to Money's currency list.
	 */
	public function get_confirmed_balance( int $blockchain_height, int $required_confirmations ): ?Money {
		return array_reduce(
			$this->transactions ?? array(),
			function ( Money $carry, Transaction_Interface $transaction ) use ( $blockchain_height, $required_confirmations ) {
				if ( $blockchain_height - ( $transaction->get_block_height() ?? $blockchain_height ) > $required_confirmations ) {
					return $carry->plus( $transaction->get_value( $this->get_raw_address() ) );
				}
				return $carry;
			},
			Money::of( 0, 'BTC' )
		);
	}

	/**
	 * Return the current status of the Bitcoin address object/post.
	 */
	public function get_status(): Bitcoin_Address_Status {
		return $this->status;
	}

	/**
	 * Set the current status of the address.
	 *
	 * Valid statuses: unknown|unused|assigned|used.
	 *
	 * TODO: Throw an exception if an invalid status is set. Maybe in the `wp_insert_post_data` filter.
	 * TODO: Maybe throw an exception if the update fails.
	 *
	 * @param Bitcoin_Address_Status $status Status to assign.
	 */
	public function set_status( Bitcoin_Address_Status $status ): void {

		$update = array(
			'post_type'   => Bitcoin_Address_WP_Post_Interface::POST_TYPE,
			'ID'          => $this->post->ID,
			'post_status' => $status->value,
		);

		$this->wp_update_post( $update );
		$this->status = $status;
	}

	/**
	 * Get the order id associated with this address, or null if none has ever been assigned.
	 */
	public function get_order_id(): ?int {
		return 0 === $this->order_id ? null : $this->order_id;
	}

	/**
	 * Add order_id metadata to the bitcoin address and update the status to assigned.
	 *
	 * @param int $order_id The WooCommerce order id the address is being used for.
	 */
	public function set_order_id( int $order_id ): void {

		$update = array(
			'ID'         => $this->post->ID,
			'meta_input' => array(
				Bitcoin_Address_WP_Post_Interface::ORDER_ID_META_KEY => $order_id,
			),
		);

		if ( Bitcoin_Address_Status::ASSIGNED !== $this->get_status() ) {
			$update['post_status'] = Bitcoin_Address_Status::ASSIGNED->value;
		}

		$this->wp_update_post( $update );
		$this->order_id = $order_id;
	}

	/**
	 * Associate the Bitcoin Address with an order's post_id, set the expected amount to be paid, change the status
	 * to "assigned".
	 *
	 * @see Bitcoin_Address_Status::ASSIGNED
	 *
	 * @param int   $post_id The post_id (e.g. WooCommerce order id) that transactions to this address represent payment for.
	 * @param Money $btc_total The target amount to be paid, after which the order should be updated.
	 */
	public function assign( int $post_id, Money $btc_total ): void {
		$this->set_order_id( $post_id );
		$this->set_target_amount( $btc_total );
		$this->set_status_assigned();
	}

	/**
	 * Sets the Bitcoin Address's status to `assigned` if not already so.
	 *
	 * @see Bitcoin_Address_Status::ASSIGNED
	 */
	protected function set_status_assigned(): void {

		if ( Bitcoin_Address_Status::ASSIGNED === $this->get_status() ) {
			return;
		}

		$this->set_status( Bitcoin_Address_Status::ASSIGNED );
	}

	/**
	 * After saving, reload the post.
	 *
	 * `wp_update_post()` just returns the post_id, so we fetch the post here separately.
	 *
	 * This should be pulled from cache.
	 *
	 * @throws RuntimeException In the unlikely event the exist post cannot be refreshed.
	 */
	protected function refresh_wp_post(): void {
		$this->post = get_post( $this->post->ID ) ?? ( function () {
			throw new RuntimeException( 'get_post( ' . $this->post->ID . ' ) failed to refresh post.' );
		} )();
	}

	/**
	 * The target amount of bitcoin to receive for the order the address is associated with.
	 *
	 * Saved in post_meta as `array{amount:string,currency:string}`.
	 *
	 * @param Money $btc_total The amount of BitCoin received until this address's status should change to ~"complete"/"paid".
	 *
	 * @see self::TARGET_AMOUNT_META_KEY
	 * @see Money::jsonSerialize()
	 */
	protected function set_target_amount( Money $btc_total ): void {
		$update = array(
			'ID'         => $this->post->ID,
			'meta_input' => array(
				Bitcoin_Address_WP_Post_Interface::TARGET_AMOUNT_META_KEY => $btc_total->jsonSerialize(),
			),
		);

		$this->wp_update_post( $update );
		$this->target_amount = $btc_total;
	}

	/**
	 * Run `wp_update_post()`, after setting the post_id, throw exception on failure, refresh on success.
	 *
	 * @param WpUpdatePostArray $update The array for `wp_update_post()`.
	 *
	 * @throws RuntimeException When failing to save to the WordPress db.
	 */
	protected function wp_update_post( array $update ): void {
		$update['ID'] = $this->post->ID;
		/** @var int|\WP_Error $result */
		$result = wp_update_post( $update );
		if ( is_wp_error( $result ) ) {
			throw new RuntimeException( $result->get_error_message() );
		}
		$this->refresh_wp_post();
	}

	/**
	 * The received amount needed to consider the order "paid".
	 */
	public function get_target_amount(): ?Money {
		return $this->target_amount;
	}
}
