<?php
/**
 * Strongly typed object for querying Bitcoin_Transaction in wp_posts table.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Transactions;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Transaction_WP_Post_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\WP_Post_Query_Abstract;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
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
		return array(
			'post_name'    => $this->tx_id,
			'post_excerpt' => $this->tx_id,
			'post_title'   => $this->tx_id,
			'post_parent'  => $this->address_wp_post_parent_id,
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function get_meta_input(): array {
		return array_filter(
			array(
				Bitcoin_Transaction_WP_Post_Interface::BLOCK_HEIGHT_META_KEY   => $this->block_height,
				Bitcoin_Transaction_WP_Post_Interface::BLOCK_DATETIME_META_KEY => $this->block_datetime,
				Bitcoin_Transaction_WP_Post_Interface::VALUE_META_KEY          => $this->value,
				Bitcoin_Transaction_WP_Post_Interface::BITCOIN_ADDRESSES_POST_IDS_META_KEY => $this->updated_transaction_meta_bitcoin_address_post_ids,
			)
		);
	}

	public function __construct(
		public ?string $tx_id = null,
		public ?int $address_wp_post_parent_id = null,
		public ?int $block_height = null,
		public ?DateTimeInterface $block_datetime = null,  // TODO: don't use timestamps, use something legible.
		public ?Money $value = null,
		public ?array $updated_transaction_meta_bitcoin_address_post_ids = null,
	) {
		parent::__construct();
	}
}
