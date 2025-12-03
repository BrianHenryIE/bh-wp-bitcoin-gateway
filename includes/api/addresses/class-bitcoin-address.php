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
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\Admin\Addresses_List_Table;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Bitcoin_Gateway;
use RuntimeException;
use InvalidArgumentException;
use WP_Post;

/**
 * Facade on WP_Post and post_meta.
 */
class Bitcoin_Address {

	const POST_TYPE = 'bh-bitcoin-address';

	const TRANSACTION_META_KEY                     = 'address_transactions';
	const DERIVATION_PATH_SEQUENCE_NUMBER_META_KEY = 'derivation_path_sequence_number';
	const TARGET_AMOUNT_META_KEY                   = 'target_amount';
	const BALANCE_META_KEY                         = 'balance';
	const ORDER_ID_META_KEY                        = 'order_id';

	/**
	 * The wp_post database row, as a WordPress post object, for the custom post type used to store the data.
	 *
	 * @var WP_Post
	 */
	protected WP_Post $post;

	protected int $post_id;
	protected Bitcoin_Address_Status $status;
	protected int $wallet_parent_post_id;
	protected ?int $derivation_path_sequence_number;
	protected string $raw_address;

	/** @var array<string,Transaction_Interface> */
	protected ?array $transactions = null;

	/** The address will be considered paid when this amount has been received */
	protected ?Money $target_amount;

	protected ?Money $balance;

	protected ?int $order_id;

	/**
	 * Constructor
	 *
	 * @param int $post_id The wp_post ID the Bitcoin address detail is stored under.
	 *
	 * @throws Exception When the supplied post_id is not a post of this type.
	 */
	public function __construct( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! ( $post instanceof WP_Post ) || self::POST_TYPE !== $post->post_type ) {
			throw new InvalidArgumentException( 'post_id ' . $post_id . ' is not a ' . self::POST_TYPE . ' post object' );
		}

		$this->post                            = $post;
		$this->post_id                         = $post_id;
		$this->wallet_parent_post_id           = $this->post->post_parent;
		$this->status                          = Bitcoin_Address_Status::from( $this->post->post_status );
		$this->derivation_path_sequence_number = ( function () use ( $post_id ) {
			$meta_value = intval( get_post_meta( $post_id, self::DERIVATION_PATH_SEQUENCE_NUMBER_META_KEY, true ) );
			return $meta_value ?: null;
		} )();
		$this->raw_address                     = $this->post->post_excerpt;
		$this->transactions                    = ( function () use ( $post_id ): ?array {
			$transactions_meta = (array) get_post_meta( $post_id, self::TRANSACTION_META_KEY, true ) ?: null;
			return empty( $transactions_meta ) ? null : $transactions_meta;
		} )();
		$this->balance                         = ( function () use ( $post_id ): ?Money {
			$balance_meta = (array) get_post_meta( $post_id, self::BALANCE_META_KEY, true );
			return empty( $balance_meta ) ? null : Money::of( ...$balance_meta );
		} )();
		$this->target_amount                   = ( function () use ( $post_id ): ?Money {
			$target_amount = (array) get_post_meta( $post_id, self::TARGET_AMOUNT_META_KEY, true );
			return empty( $target_amount ) ? null : Money::of( ...$target_amount );
		} )();
		$this->order_id                        = intval( get_post_meta( $post_id, self::ORDER_ID_META_KEY, true ) ) ?: null;
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
		return is_numeric( $this->derivation_path_sequence_number ) ? intval( $this->derivation_path_sequence_number ) : null;
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
		return is_array( $this->transactions ) ? $this->transactions : null;
	}

	// get_mempool_transactions()

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
				self::TRANSACTION_META_KEY => $refreshed_transactions,
			),
		);

		if ( empty( $refreshed_transactions ) ) {
			$update['post_status'] = Bitcoin_Address_Status::UNUSED->value;
		} elseif ( Bitcoin_Address_Status::UNKNOWN->value === $this->get_status() ) {
			$update['post_status'] = Bitcoin_Address_Status::USED->value;
		}

		/** @var int|\WP_Error $result */
		$result = wp_update_post( $update );
		if ( ! is_wp_error( $result ) ) {
			$this->transactions = $refreshed_transactions;
		} else {
			throw new RuntimeException( $result->get_error_message() );
		}
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
		return Bitcoin_Address_Status::UNKNOWN->value === $this->get_status() ? null : $this->balance;
	}

	/**
	 * TODO: "balance" is not an accurate term for what we need.
	 */
	public function get_amount_received(): ?Money {
		return $this->get_balance();
	}

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
	 * Return the current status of the Bitcoin address object. One of:
	 * * unknown: probably brand new and unchecked
	 * * unused: new and no order id assigned
	 * * assigned: assigned to an order, payment incomplete
	 * * used: transactions present and no order id, or and order id assigned and payment complete
	 *
	 * TODO: Check the saved status is valid.
	 *
	 * @return string unknown|unused|assigned|used.
	 */
	public function get_status(): string {
		return $this->status->value;
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

		if ( ! in_array( $status, Bitcoin_Address_Status::cases(), true ) ) {
			throw new InvalidArgumentException( "{$status->value} should be one of unknown|unused|assigned|used" );
		}

		/** @var int|\WP_Error $result */
		$result = wp_update_post(
			array(
				'post_type'   => self::POST_TYPE,
				'ID'          => $this->post->ID,
				'post_status' => $status->value,
			)
		);

		if ( ! is_wp_error( $result ) ) {
			$this->status = $status;
		} else {
			throw new RuntimeException( $result->get_error_message() );
		}
	}

	/**
	 * Get the order id associated with this address, or null if none has ever been assigned.
	 *
	 * @return ?int
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
				self::ORDER_ID_META_KEY => $order_id,
			),
		);

		if ( 'assigned' !== $this->get_status() ) {
			$update['post_status'] = Bitcoin_Address_Status::ASSIGNED->value;
		}

		/** @var int|\WP_Error $result */
		$result = wp_update_post( $update );
		if ( ! is_wp_error( $result ) ) {
			$this->order_id = $order_id;
		} else {
			throw new RuntimeException( $result->get_error_message() );
		}
	}

	public function assign( int $post_id, Money $btc_total ): void {
		$this->set_order_id( $post_id );
		$this->set_target_amount( $btc_total );
	}

	protected function set_target_amount( Money $btc_total ): void {
		$update = array(
			'ID'         => $this->post->ID,
			'meta_input' => array(
				self::TARGET_AMOUNT_META_KEY => $btc_total->jsonSerialize(),
			),
		);

		/** @var int|\WP_Error $result */
		$result = wp_update_post( $update );
		if ( ! is_wp_error( $result ) ) {
			$this->target_amount = $btc_total;
		} else {
			throw new RuntimeException( $result->get_error_message() );
		}
	}

	public function get_target_amount(): ?Money {
		return $this->target_amount;
	}
}
