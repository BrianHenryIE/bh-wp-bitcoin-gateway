<?php
/**
 * Save new Bitcoin transactions in WordPress, and fetch them via xpub or post id.
 *
 * I had considered using taxonomies for the many-to-many relationship between Bitcoin_Addresses and Transactions
 * but there's no real querying going on so post_meta on each end is probably adequate. This repository class will
 * know about Bitcoin_Address postmeta that the Bitcoin address repository doesn't even know about!
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Transactions\Bitcoin_Transaction_Query;
use Exception;
use JsonException;
use RuntimeException;
use WP_Post;

/**
 * Class for creating/getting `Bitcoin_Transaction` objects stored in wp_posts table.
 *
 * @phpstan-type WpUpdatePostArray array{ID?: int, post_author?: int, post_date?: string, post_date_gmt?: string, post_content?: string, post_content_filtered?: string, post_title?: string, post_excerpt?: string}
 */
class Bitcoin_Transaction_Repository extends WP_Post_Repository_Abstract {

	public function __construct(
		protected Bitcoin_Transaction_Factory $bitcoin_transaction_factory,
	) {
	}

	/**
	 * Given the id of the wp_posts row storing the bitcoin address, return the typed Bitcoin_Transaction object.
	 *
	 * @param int $post_id WordPress wp_posts ID.
	 *
	 * @throws Exception When the post_type of the post returned for the given post_id is not a Bitcoin_Transaction.
	 */
	public function get_by_post_id( int $post_id ): Bitcoin_Transaction {
		return $this->bitcoin_transaction_factory->get_by_wp_post_id( $post_id );
	}

	/**
	 * @param string $tx_id
	 *
	 * @return ?WP_Post
	 * @throws RuntimeException
	 */
	protected function get_post_by_transaction_id( string $tx_id ): ?WP_Post {

		$query = new Bitcoin_Transaction_Query(
			tx_id: $tx_id,
		);

		$wp_posts = get_posts( $query->to_query_array() );

		if ( empty( $wp_posts ) ) {
			return null;
		}

		if ( count( $wp_posts ) === 1 ) {
			$post = array_pop( $wp_posts );
			return $post;
		}

		throw new RuntimeException( 'Unexpectedly found more than one post for txid: ' . $tx_id );
	}

	/**
	 * Return the previously saved transactions for this address.
	 *
	 * The Bitcoin_Address's wp_post has a meta key that holds an array of post ids for saved transactions.
	 *
	 * @see Addresses_List_Table::column_transactions_count() When displaying all addresses.
	 * @used-by API::get_saved_transactions() When displaying all addresses.
	 *
	 * @return ?Bitcoin_Transaction[] Where null suggests there was nothing saved before, and an empty array suggests it has been checked but no transactions had been seen.
	 * @throws Exception
	 */
	public function get_transactions_for_address(
		Bitcoin_Address $address,
	): ?array {
		$transaction_post_ids = $this->get_transactions_wp_post_ids_for_address( $address );

		if ( is_null( $transaction_post_ids ) ) {
			return null;
		}

		return array_map(
			fn( $address_post_id ) => $this->get_by_post_id( $address_post_id ),
			$transaction_post_ids
		);
	}

	/**
	 * @param Bitcoin_Address $address
	 *
	 * @return ?int[]
	 */
	protected function get_transactions_wp_post_ids_for_address(
		Bitcoin_Address $address,
	): ?array {
		$saved_post_meta = get_post_meta( $address->get_post_id(), Bitcoin_Address_WP_Post_Interface::TRANSACTIONS_META_KEY, true );

		if ( empty( $saved_post_meta ) ) {
			return null;
		}

		/** @var array<int, string> $saved_meta_array <post_id : transaction id>. */
		$saved_meta_array = json_decode( json: $saved_post_meta, associative: true, flags: JSON_THROW_ON_ERROR );

		return array_keys( $saved_meta_array );
	}

	/**
	 * TODO: How to indicate if this was newly saved or already existed.
	 *
	 * @throws RuntimeException
	 */
	protected function save_post(
		Transaction_Interface $transaction,
	): WP_Post {

		$transaction_post = $this->get_post_by_transaction_id( $transaction->get_txid() );

		if ( ! $transaction_post ) {
			$insert_query = new Bitcoin_Transaction_Query(
				tx_id: $transaction->get_txid(),
				block_height: $transaction->get_block_height(),
				block_datetime: $transaction->get_block_time(),
			);

			/** @var WpUpdatePostArray $args */
			$args = $insert_query->to_query_array();

			$new_post_id = wp_insert_post( $args, true );

			if ( is_wp_error( $new_post_id ) ) {
				// TODO Log.
				throw new Exception( 'WordPress failed to save new transaction.' );
			}

			return get_post( $new_post_id );
		}

		return $transaction_post;
	}

	/**
	 * Wrapper on wp_insert_post(), sets the address as the post_title, post_excerpt and post_name.
	 *
	 * @param Transaction_Interface $transaction
	 *
	 * @throws Exception When WordPress fails to create the wp_post.
	 * @throws JsonException
	 */
	public function save_new(
		Transaction_Interface $transaction,
		Bitcoin_Address $address,
	): Bitcoin_Transaction {

		$transaction_post = $this->save_post( $transaction );

		$this->associate_transaction_post_id_and_address( $transaction_post->ID, $address );

		return $this->get_by_post_id( $transaction_post->ID );
	}

	/**
	 * @param int             $transaction_post_id
	 * @param Bitcoin_Address $address
	 *
	 * @return void
	 */
	protected function associate_transaction_post_id_and_address(
		int $transaction_post_id,
		Bitcoin_Address $address,
	): void {
		$this->associate_transactions_post_ids_to_address( array( $transaction_post_id ), $address );
	}

	/**
	 * Set the post meta on an address to link the transaction.
	 *
	 * Conversely, elsewhere, the address post_id will be linked on the transaction.
	 *
	 * @param array<int, string> $transactions_post_ids Key/value: <post_id, transaction_id>.
	 * @param Bitcoin_Address    $address
	 *
	 * @return void TODO: return something meaningful.
	 */
	public function associate_transactions_post_ids_to_address(
		array $transactions_post_ids,
		Bitcoin_Address $address,
	): void {

		$address_post_id = $address->get_post_id();

		/** @var array<int,string> $existing_meta_transactions_post_ids */
		$existing_meta_transactions_post_ids = get_post_meta( $address_post_id, Bitcoin_Address_WP_Post_Interface::TRANSACTIONS_META_KEY, true );

		if ( empty( $existing_meta_transactions_post_ids ) ) {
			$existing_meta_transactions_post_ids = array();
		}

		$updated_transactions_post_ids = $existing_meta_transactions_post_ids;
		$new_transactions_post_ids     = array();

		foreach ( $transactions_post_ids as $post_id => $transaction_id ) {
			if ( ! isset( $existing_meta_transactions_post_ids[ $post_id ] ) ) {
				$updated_transactions_post_ids[ $post_id ] = $transaction_id;
				$new_transactions_post_ids[ $post_id ]     = $transaction_id;
			}
		}

		if ( empty( $updated_transactions_post_ids ) ) {
			return;
		}

		$this->update(
			model: $address,
			query:new Bitcoin_Address_Query(
				updated_transactions_post_ids: $updated_transactions_post_ids,
			)
		);
	}

	/**
	 * Update transaction posts to reference a bitcoin address they are relevant to.
	 *
	 * @param Bitcoin_Address    $bitcoin_address The Bitcoin Address these transactions should be linked to.
	 * @param array<int, string> $transactions_post_ids Key/value: <wp_post_id, transaction_id>.
	 *
	 * @return void TODO: return something meaningful.
	 */
	protected function associate_address_post_id_to_transactions_posts(
		Bitcoin_Address $bitcoin_address,
		array $transactions_post_ids,
	): void {

		$bitcoin_address_post_id     = $bitcoin_address->get_post_id();
		$bitcoin_address_raw_address = $bitcoin_address->get_raw_address();
		unset( $bitcoin_address );

		foreach ( $transactions_post_ids as $transaction_post_id => $transaction_id ) {

			/** @var array<int,string> $existing_transaction_meta_bitcoin_address_post_ids */
			$existing_transaction_meta_bitcoin_address_post_ids = get_post_meta(
				post_id: $transaction_post_id,
				key: Bitcoin_Transaction_WP_Post_Interface::BITCOIN_ADDRESSES_POST_IDS_META_KEY,
				single: true
			);

			if ( empty( $existing_transaction_meta_bitcoin_address_post_ids ) ) {
				$existing_transaction_meta_bitcoin_address_post_ids = array();
			}

			$updated_transaction_meta_bitcoin_address_post_ids = $existing_transaction_meta_bitcoin_address_post_ids;
			$new_transaction_meta_bitcoin_address_post_ids     = array();

			if ( ! isset( $existing_transaction_meta_bitcoin_address_post_ids[ $bitcoin_address_post_id ] ) ) {

				$updated_transaction_meta_bitcoin_address_post_ids[ $bitcoin_address_post_id ] = $bitcoin_address_raw_address;
				$new_transaction_meta_bitcoin_address_post_ids[ $bitcoin_address_post_id ]     = $bitcoin_address_raw_address;

				$transaction = $this->bitcoin_transaction_factory->get_by_wp_post_id( $transaction_post_id );

				// TODO: How best to handle errors in the loop & return all the results.
				$this->update(
					model: $transaction,
					query:  new Bitcoin_Transaction_Query(
						updated_transaction_meta_bitcoin_address_post_ids: $updated_transaction_meta_bitcoin_address_post_ids
					)
				);

			}
		}
	}
}
