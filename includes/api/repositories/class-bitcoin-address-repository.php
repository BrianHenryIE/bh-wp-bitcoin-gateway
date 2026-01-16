<?php
/**
 * Save new Bitcoin addresses in WordPress, and fetch them via xpub or post id.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Factories\Bitcoin_Address_Factory;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Queries\Bitcoin_Address_Query;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address_Status;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address_WP_Post_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Exceptions\BH_WP_Bitcoin_Gateway_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use WP_Post;

/**
 * Interface for creating/getting Bitcoin_Address objects stored in wp_posts table.
 *
 * @phpstan-type WpUpdatePostArray array{ID?: int, post_author?: int, post_date?: string, post_date_gmt?: string, post_content?: string, post_content_filtered?: string, post_title?: string, post_excerpt?: string}
 */
class Bitcoin_Address_Repository extends WP_Post_Repository_Abstract {

	/**
	 * Constructor.
	 *
	 * @param Bitcoin_Address_Factory $bitcoin_address_factory Factory for creating Bitcoin payment address objects.
	 */
	public function __construct(
		protected Bitcoin_Address_Factory $bitcoin_address_factory,
	) {
	}

	/**
	 * Given a bitcoin public key, get the WordPress post_id it is saved under.
	 *
	 * TODO: If a wallet post is deleted, but its addresses are not, and it is re-saved with
	 * a new post_id, the orphaned addresses will behave unpredictably.
	 *
	 * @param string $address Xpub|ypub|zpub.
	 *
	 * @return int|null The post id if it exists, null if it is not found.
	 */
	public function get_post_id_for_address( string $address ): ?int {

		$cached = wp_cache_get( $address, Bitcoin_Address_WP_Post_Interface::POST_TYPE );
		if ( is_numeric( $cached ) ) {
			return intval( $cached );
		}

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// @phpstan-ignore-next-line
		$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_name=%s", sanitize_title( $address ) ) );

		if ( is_numeric( $post_id ) ) {
			$post_id = intval( $post_id );
			wp_cache_add( $address, $post_id, Bitcoin_Address_WP_Post_Interface::POST_TYPE );
			return $post_id;
		}

		return null;

		// TODO: why does this not work?!

		$posts = get_posts(
			array(
				'post_type'   => Bitcoin_Address_WP_Post_Interface::POST_TYPE,
				'post_status' => 'any',
				'post_title'  => $address, // post_name is slug which is indexed.
			)
		);

		if ( empty( $posts ) ) {
			return null;
		}

		if ( count( $posts ) > 1 ) {
			throw new BH_WP_Bitcoin_Gateway_Exception( 'more than one wp_post found for bitcoin address ' . $address );
		}

		return $posts[0]->ID;
	}

	/**
	 * Given the id of the wp_posts row storing the bitcoin address, return the typed Bitcoin_Address object.
	 *
	 * @param int $post_id WordPress wp_posts ID.
	 *
	 * @return Bitcoin_Address
	 * @throws BH_WP_Bitcoin_Gateway_Exception When the post_type of the post returned for the given post_id is not a Bitcoin_Address.
	 */
	public function get_by_post_id( int $post_id ): Bitcoin_Address {
		return $this->bitcoin_address_factory->get_by_wp_post_id( $post_id );
	}

	/**
	 * Refresh a saved Bitcoin payment address object from the database.
	 *
	 * NB: This does not perform any API calls.
	 *
	 * @param Bitcoin_Address $address The address to refresh.
	 * @return Bitcoin_Address The refreshed Bitcoin address.
	 */
	public function refresh( Bitcoin_Address $address ): Bitcoin_Address {
		return $this->bitcoin_address_factory->get_by_wp_post_id( $address->get_post_id() );
	}

	/**
	 * @return Bitcoin_Address[]
	 */
	public function get_assigned_bitcoin_addresses(): array {
		return $this->get_addresses_query(
			new Bitcoin_Address_Query(
				status: Bitcoin_Address_Status::ASSIGNED,
				numberposts: 200,
			)
		);
	}

	/**
	 * Gets previously saved addresses which have at least once been checked and see to be unused.
	 *
	 * It may be the case that they have been used in the meantime.
	 *
	 * @param ?Bitcoin_Wallet $wallet Optional wallet to filter addresses by.
	 * @return Bitcoin_Address[]
	 */
	public function get_unused_bitcoin_addresses( ?Bitcoin_Wallet $wallet = null ): array {
		return $this->get_addresses_query(
			new Bitcoin_Address_Query(
				wallet_wp_post_parent_id: $wallet?->get_post_id(),
				status: Bitcoin_Address_Status::UNUSED,
				numberposts: 200,
				orderby: 'post_modified',
				order_direction: 'ASC',
			)
		);
	}

	/**
	 * Check do we have at least 1 assigned address, i.e. an address waiting for transactions.
	 *
	 * Across all wallets.
	 */
	public function has_assigned_bitcoin_addresses(): bool {
		return ! empty(
			$this->get_addresses_query(
				new Bitcoin_Address_Query(
					status: Bitcoin_Address_Status::ASSIGNED,
					numberposts: 1,
				)
			)
		);
	}

	/**
	 * @return Bitcoin_Address[]
	 */
	public function get_unknown_bitcoin_addresses(): array {
		// 'orderby'        => 'ID',
		// 'order'          => 'ASC',
		// TODO: Should this query use ID.asc as a way to order?
		// TODO: updated_at is probably correct.

		return $this->get_addresses_query(
			new Bitcoin_Address_Query(
				status: Bitcoin_Address_Status::UNKNOWN,
			)
		);
	}

	/**
	 * Get all saved Bitcoin payment address.
	 *
	 * @param ?Bitcoin_Wallet         $wallet Optional wallet to filter by.
	 * @param ?Bitcoin_Address_Status $status Optional status to filter by.
	 *
	 * @return Bitcoin_Address[]
	 */
	public function get_addresses(
		?Bitcoin_Wallet $wallet = null,
		?Bitcoin_Address_Status $status = null
	): array {

		return $this->get_addresses_query(
			new Bitcoin_Address_Query(
				wallet_wp_post_parent_id: $wallet?->get_post_id(),
				status: $status, // TODO: Should this null-coalesce to 'all'?
			)
		);
	}

	/**
	 * Get addresses matching a query.
	 *
	 * @param Bitcoin_Address_Query $filter The query filter to apply.
	 * @return Bitcoin_Address[]
	 */
	protected function get_addresses_query( Bitcoin_Address_Query $filter ): array {
		/** @var WP_Post[] $posts */
		$posts = get_posts( $filter->to_query_array() );

		return array_map(
			fn( WP_Post $wp_post ) => $this->bitcoin_address_factory->get_by_wp_post( $wp_post ),
			$posts
		);
	}

	/**
	 * Wrapper on wp_insert_post(), sets the address as the post_title, post_excerpt and post_name.
	 *
	 * @param Bitcoin_Wallet $wallet The wallet this address belongs to.
	 * @param int            $derivation_path_sequence_index The derivation path index for this address.
	 * @param string         $address The Bitcoin payment address string.
	 * @return Bitcoin_Address The saved Bitcoin address.
	 * @throws BH_WP_Bitcoin_Gateway_Exception When WordPress fails to create the wp_post.
	 */
	public function save_new(
		Bitcoin_Wallet $wallet,
		int $derivation_path_sequence_index,
		string $address,
	): Bitcoin_Address {

		// Unlikely, but was an issue for Wallets.
		$existing_post_id = $this->get_post_id_for_address( $address );
		if ( $existing_post_id ) {
			throw new BH_WP_Bitcoin_Gateway_Exception( 'Attempted to save a payment address that already exists: ' . $address );
		}

		$query = new Bitcoin_Address_Query(
			wallet_wp_post_parent_id: $wallet->get_post_id(),
			status: Bitcoin_Address_Status::UNKNOWN,
			xpub: $address,
			derivation_path_sequence_index: $derivation_path_sequence_index,
		);

		// TODO: Validate address, throw exception.

		/** @var WpUpdatePostArray $args */
		$args = $query->to_query_array();

		$post_id = wp_insert_post( $args, true );

		if ( is_wp_error( $post_id ) ) {
			// TODO Log.
			throw new BH_WP_Bitcoin_Gateway_Exception( 'WordPress failed to create a post for the wallet.' );
		}

		return $this->bitcoin_address_factory->get_by_wp_post_id( $post_id );
	}

	/**
	 * Set the current status of the address.
	 *
	 * Valid statuses: unknown|unused|assigned|used + ~WordPress built-in.
	 *
	 * TODO: Throw an exception if an invalid status is set.
	 *
	 * @param Bitcoin_Address        $address The address to update.
	 * @param Bitcoin_Address_Status $status Status to assign.
	 */
	public function set_status(
		Bitcoin_Address $address,
		Bitcoin_Address_Status $status
	): void {

		$this->update(
			model: $address,
			query: new Bitcoin_Address_Query(
				status: $status,
			)
		);
	}

	/**
	 * Associate the Bitcoin Address with an order's post_id, set the expected amount to be paid, change the status
	 * to "assigned".
	 *
	 * @see Bitcoin_Address_Status::ASSIGNED
	 *
	 * @param Bitcoin_Address $address The Bitcoin payment address to link.
	 * @param int             $order_id The post_id (e.g. WooCommerce order id) that transactions to this address represent payment for.
	 * @param Money           $btc_total The target amount to be paid, after which the order should be updated.
	 */
	public function assign_to_order(
		Bitcoin_Address $address,
		int $order_id,
		Money $btc_total,
	): void {
		$this->update(
			model: $address,
			query: new Bitcoin_Address_Query(
				status: Bitcoin_Address_Status::ASSIGNED,
				associated_order_id: $order_id,
				target_amount: $btc_total,
			)
		);
	}

	/**
	 * An address wp_post's parent post_id for its wallet may need to be set if the original wallet wp_post was
	 * deleted but the address's wp_post remained orphaned.
	 *
	 * @param Bitcoin_Address $address Address object to update.
	 * @param int             $wallet_post_id The new/correct wallet post_id to set.
	 */
	public function set_wallet_id( Bitcoin_Address $address, int $wallet_post_id ): void {
		$this->update(
			model: $address,
			query: new Bitcoin_Address_Query(
				wallet_wp_post_parent_id: $wallet_post_id,
			)
		);
	}
}
