<?php
/**
 * Strongly typed object for querying Bitcoin_Transaction in wp_posts table.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Queries;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Bitcoin_Transaction_WP_Post_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes\Post_BH_Bitcoin_Transaction;
use DateTimeInterface;

/**
 * @see Post_BH_Bitcoin_Transaction
 */
readonly class Bitcoin_Transaction_Query extends WP_Post_Query_Abstract {

	/**
	 * The Bitcoin_Transaction wp_post post_type.
	 */
	protected function get_post_type(): string {
		return Bitcoin_Transaction_WP_Post_Interface::POST_TYPE;
	}

	/**
	 *
	 * @return array<string,mixed> $map to:from
	 */
	protected function get_wp_post_fields(): array {
		$fields = array();

		if ( $this->tx_id ) {
			$fields['post_name'] = $this->tx_id; // slug, indexed.
		}
		if ( $this->tx_id ) {
			$fields['post_title'] = $this->tx_id;
		}
		if ( $this->transaction_object ) {
			$fields['post_content'] = wp_json_encode( $this->transaction_object );
		}

		return $fields;
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function get_meta_input(): array {
		return array_filter(
			array(
				Bitcoin_Transaction_WP_Post_Interface::BLOCK_HEIGHT_META_KEY   => $this->block_height,
				Bitcoin_Transaction_WP_Post_Interface::BLOCK_DATETIME_META_KEY => $this->block_datetime,
				Bitcoin_Transaction_WP_Post_Interface::BITCOIN_ADDRESSES_POST_IDS_META_KEY => $this->updated_transaction_meta_bitcoin_address_post_ids,
			)
		);
	}

	/**
	 * Constructor for Bitcoin transaction query.
	 *
	 * @param ?Transaction       $transaction_object The complete transaction object from the blockchain API to be serialized and stored in post_content.
	 * @param ?string            $tx_id The transaction ID to search by, stored in both post_name (slug) and post_title for efficient lookups.
	 * @param ?int               $block_height The blockchain height to query transactions by, stored in post meta for confirmation tracking.
	 * @param ?DateTimeInterface $block_datetime The timestamp when the transaction block was mined, stored as serialized DateTimeInterface in post meta.
	 * @param ?array<int,string> $updated_transaction_meta_bitcoin_address_post_ids Mapping of address post IDs to transaction IDs for tracking which addresses received funds.
	 */
	public function __construct(
		public ?Transaction $transaction_object = null,
		public ?string $tx_id = null,
		public ?int $block_height = null,
		public ?DateTimeInterface $block_datetime = null,  // TODO: don't use serialized DateTimeInterface in meta, use something legible.
		public ?array $updated_transaction_meta_bitcoin_address_post_ids = null,
	) {
		parent::__construct();
	}
}
