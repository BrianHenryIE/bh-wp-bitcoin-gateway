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

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Bitcoin_Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Factories\Bitcoin_Transaction_Factory;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Bitcoin_Transaction_WP_Post_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Exceptions\BH_WP_Bitcoin_Gateway_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Queries\Bitcoin_Transaction_Query;
use BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes\Post_BH_Bitcoin_Transaction;
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

		/**
		 * The txid is stored as the post slug. `WP_Query` matches the slug via `name` (not `post_name`), and
		 * `get_posts()` defaults to published posts only, so both are set explicitly here. `numberposts` is 2 so a
		 * duplicate is still detected below.
		 *
		 * @var WP_Post[] $wp_posts
		 */
		$wp_posts = get_posts(
			array(
				'post_type'   => Bitcoin_Transaction_WP_Post_Interface::POST_TYPE,
				'name'        => $tx_id,
				'post_status' => 'any',
				'numberposts' => 2,
			)
		);

		if ( empty( $wp_posts ) ) {
			return null;
		}

		if ( count( $wp_posts ) === 1 ) {
			return $wp_posts[0];
		}

		throw new RuntimeException( sprintf( 'Unexpectedly found more than one post for txid: %s', esc_html( $tx_id ) ) );
	}

	/**
	 * Save a transaction to the WordPress posts table, or update the existing post for that txid.
	 *
	 * A transaction is fetched every time its address is checked, so the same txid arrives repeatedly, and a
	 * transaction first seen in the mempool arrives again once it is mined. The post is keyed by txid (slug) and
	 * refreshed in place, so there is only ever one post per transaction and its block height is current.
	 *
	 * TODO: How to indicate if this was newly saved or already existed.
	 *
	 * @param Transaction       $transaction The blockchain transaction object to save or retrieve from WordPress posts.
	 * @param array<int,string> $bitcoin_addresses_indexed_by_post_ids Bitcoin addresses as post_id:bitcoin_address pairs.
	 *
	 * @throws RuntimeException When the transaction already exists in the database with a different post ID.
	 * @throws BH_WP_Bitcoin_Gateway_Exception When WordPress fails to create the new transaction post.
	 */
	protected function save_post(
		Transaction $transaction,
		array $bitcoin_addresses_indexed_by_post_ids,
	): WP_Post {
		$transaction_post = $this->get_post_by_transaction_id( $transaction->get_txid() );

		if ( ! $transaction_post ) {
			$insert_query = new Bitcoin_Transaction_Query(
				transaction_object: $transaction,
				tx_id: $transaction->get_txid(),
				block_height: $transaction->get_block_height(),
				block_datetime: $transaction->get_block_time(),
				updated_transaction_meta_bitcoin_address_post_ids: $bitcoin_addresses_indexed_by_post_ids,
				post_status: 'publish',
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

		// Refresh the saved transaction (e.g. now mined, or more confirmations) and add any newly relevant address.
		$this->update(
			model: $this->bitcoin_transaction_factory->get_by_wp_post( $transaction_post ),
			query: new Bitcoin_Transaction_Query(
				transaction_object: $transaction,
				tx_id: $transaction->get_txid(),
				block_height: $transaction->get_block_height(),
				block_datetime: $transaction->get_block_time(),
				updated_transaction_meta_bitcoin_address_post_ids: $this->get_bitcoin_addresses_meta( $transaction_post->ID ) + $bitcoin_addresses_indexed_by_post_ids,
				post_status: 'publish',
			)
		);

		return get_post( $transaction_post->ID ); // @phpstan-ignore return.type
	}

	/**
	 * Read the address post ids saved on a transaction post as `post_id:address` pairs.
	 *
	 * The query object JSON-encodes arrays into meta, so older/newer rows may hold either a JSON string or an array.
	 *
	 * @param int $transaction_post_id The transaction wp_post id.
	 *
	 * @return array<int,string>
	 */
	protected function get_bitcoin_addresses_meta( int $transaction_post_id ): array {
		/** @var mixed $meta */
		$meta = get_post_meta( $transaction_post_id, Bitcoin_Transaction_WP_Post_Interface::BITCOIN_ADDRESSES_POST_IDS_META_KEY, true );

		if ( is_string( $meta ) ) {
			$meta = json_decode( $meta, true );
		}

		if ( ! is_array( $meta ) ) {
			return array();
		}

		/** @var array<int,string> $addresses */
		$addresses = array();
		foreach ( $meta as $post_id => $address ) {
			if ( is_numeric( $post_id ) && is_string( $address ) ) {
				$addresses[ (int) $post_id ] = $address;
			}
		}
		return $addresses;
	}

	/**
	 * Wrapper on wp_insert_post(), sets the address as the post_title and post_name.
	 *
	 * @param Transaction     $transaction A transaction from the blockchain API to save as a WordPress post.
	 * @param Bitcoin_Address $address The Bitcoin address that received funds in this transaction, used to create bidirectional links in post meta.
	 *
	 * @throws BH_WP_Bitcoin_Gateway_Exception When WordPress fails to create the transaction post or the address cannot be linked.
	 * @throws RuntimeException When multiple posts are found for the same transaction ID during the save operation.
	 */
	public function save_new(
		Transaction $transaction,
		Bitcoin_Address $address,
	): Bitcoin_Transaction {

		$transaction_post = $this->save_post(
			transaction: $transaction,
			bitcoin_addresses_indexed_by_post_ids: array(
				$address->get_post_id() => $address->get_raw_address(),
			)
		);

		// Using wp_post->ID here so it refreshes rather than just maps.
		return $this->bitcoin_transaction_factory->get_by_wp_post_id( $transaction_post->ID );
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
