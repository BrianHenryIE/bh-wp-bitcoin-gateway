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

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Queries\Bitcoin_Address_Query;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address_WP_Post_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Bitcoin_Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Factories\Bitcoin_Transaction_Factory;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Bitcoin_Transaction_WP_Post_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Exceptions\BH_WP_Bitcoin_Gateway_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Queries\Bitcoin_Transaction_Query;
use BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes\Post_BH_Bitcoin_Transaction;
use JsonException;
use RuntimeException;
use WP_Post;

/**
 * Class for creating/getting `Bitcoin_Transaction` objects stored in wp_posts table.
 *
 * @see Bitcoin_Transaction_WP_Post_Interface
 * @see Post_BH_Bitcoin_Transaction
 *
 * @phpstan-type WpUpdatePostArray array{ID?: int, post_author?: int, post_date?: string, post_date_gmt?: string, post_content?: string, post_content_filtered?: string, post_title?: string, post_excerpt?: string}
 */
class Bitcoin_Transaction_Repository extends WP_Post_Repository_Abstract {

	/**
	 * Constructor.
	 *
	 * @param Bitcoin_Transaction_Factory $bitcoin_transaction_factory Factory for creating Bitcoin transaction objects.
	 */
	public function __construct(
		protected Bitcoin_Transaction_Factory $bitcoin_transaction_factory,
	) {
	}

	/**
	 * Given the id of the wp_posts row storing the bitcoin address, return the typed Bitcoin_Transaction object.
	 *
	 * @param int $post_id WordPress wp_posts ID.
	 *
	 * @throws BH_WP_Bitcoin_Gateway_Exception When the post_type of the post returned for the given post_id is not a Bitcoin_Transaction.
	 */
	public function get_by_post_id( int $post_id ): Bitcoin_Transaction {
		return $this->bitcoin_transaction_factory->get_by_wp_post_id( $post_id );
	}

	/**
	 * Get a WordPress post by transaction ID.
	 *
	 * @param string $tx_id The transaction ID to search for.
	 *
	 * @return WP_Post|null The WordPress post or null if not found.
	 * @throws RuntimeException When more than one post is unexpectedly found for the same transaction ID.
	 */
	protected function get_post_by_transaction_id( string $tx_id ): ?WP_Post {

		$query = new Bitcoin_Transaction_Query(
			tx_id: $tx_id,
		);

		/** @var WP_Post[] $wp_posts */
		$wp_posts = get_posts( $query->to_query_array() );

		if ( empty( $wp_posts ) ) {
			return null;
		}

		if ( count( $wp_posts ) === 1 ) {
			return array_first( $wp_posts );
		}

		throw new RuntimeException( 'Unexpectedly found more than one post for txid: ' . $tx_id );
	}

	/**
	 * Save a transaction to WordPress posts table or return existing post.
	 *
	 * TODO: How to indicate if this was newly saved or already existed.
	 *
	 * @param Transaction $transaction The blockchain transaction object to save or retrieve from WordPress posts.
	 *
	 * @throws RuntimeException When the transaction already exists in the database with a different post ID.
	 * @throws BH_WP_Bitcoin_Gateway_Exception When WordPress fails to create the new transaction post.
	 */
	protected function save_post(
		Transaction $transaction,
	): WP_Post {
		$transaction_post = $this->get_post_by_transaction_id( $transaction->get_txid() );
		// What if the transaction already exists? Potentially it is from a chain that has been discarded. When else might it be updated?
		// (we will in a moment update a transaction's wp_post's meta to connect post ids for the relevant address).

		if ( ! $transaction_post ) {
			$insert_query = new Bitcoin_Transaction_Query(
				transaction_object: $transaction,
				block_height: $transaction->get_block_height(),
				block_datetime: $transaction->get_block_time(),
			);

			/** @var WpUpdatePostArray $args */
			$args = $insert_query->to_query_array();

			$new_post_id = wp_insert_post( $args, true );

			if ( is_wp_error( $new_post_id ) ) {
				// TODO Log.
				throw new BH_WP_Bitcoin_Gateway_Exception( 'WordPress failed to save new transaction.' );
			}

			return get_post( $new_post_id ); // @phpstan-ignore return.type
		}

		return $transaction_post;
	}

	/**
	 * Wrapper on wp_insert_post(), sets the address as the post_title, post_excerpt and post_name.
	 *
	 * @param Transaction     $transaction A transaction from the blockchain API to save as a WordPress post.
	 * @param Bitcoin_Address $address The Bitcoin address that received funds in this transaction, used to create bidirectional links in post meta.
	 *
	 * @throws BH_WP_Bitcoin_Gateway_Exception When WordPress fails to create the transaction post or the address cannot be linked.
	 * @throws JsonException When JSON encoding/decoding of transaction data or address associations fails.
	 * @throws RuntimeException When multiple posts are found for the same transaction ID during the save operation.
	 */
	public function save_new(
		Transaction $transaction,
		Bitcoin_Address $address,
	): Bitcoin_Transaction {

		$transaction_post = $this->save_post( $transaction );

		$this->associate_transaction_post_id_and_address( array( $transaction_post->ID => $transaction->get_txid() ), $address );

		// Using wp_post->ID here so it refreshes rather than just maps.
		return $this->bitcoin_transaction_factory->get_by_wp_post_id( $transaction_post->ID );
	}

	/**
	 * Associate a transaction with a Bitcoin payment address in their respective post_metas.
	 *
	 * TODO: feels this should move into a service and keep this class dumber.
	 *
	 * @param array<int,string> $transaction_post_id_and_txid Transaction post_id:tx_id pairs.
	 * @param Bitcoin_Address   $address The Bitcoin address to associate with.
	 */
	protected function associate_transaction_post_id_and_address(
		array $transaction_post_id_and_txid,
		Bitcoin_Address $address,
	): void {
		$this->associate_transactions_post_ids_to_address( $transaction_post_id_and_txid, $address );
		$this->associate_bitcoin_address_post_ids_to_transaction( $address, $transaction_post_id_and_txid );
	}

	/**
	 * Set the post meta on an address to link the transaction.
	 *
	 * Conversely, elsewhere, the address post_id will be linked on the transaction.
	 *
	 * @param array<int, string> $transactions_post_ids Key/value: <post_id, transaction_id>.
	 * @param Bitcoin_Address    $address The Bitcoin address to link transactions to.
	 *
	 * @return void TODO: return something meaningful.
	 */
	public function associate_transactions_post_ids_to_address(
		array $transactions_post_ids,
		Bitcoin_Address $address,
	): void {

		$address_post_id = $address->get_post_id();

		/**
		 * @see Bitcoin_Address::get_tx_ids() Should we use this?!
		 * @var array<int,string> $existing_meta_transactions_post_ids
		 */
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
	protected function associate_bitcoin_address_post_ids_to_transaction(
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
