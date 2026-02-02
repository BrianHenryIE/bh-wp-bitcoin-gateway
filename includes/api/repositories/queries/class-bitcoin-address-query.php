<?php
/**
 * Strongly typed object for querying Bitcoin_Address in wp_posts table.
 *
 * A mapping of domain terms to WP_Post columns + meta fields.
 *
 * TODO: Add a WP CLI output of a post here.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Queries;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address_Status;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address_WP_Post_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes\Post_BH_Bitcoin_Address;

/**
 * @see Post_BH_Bitcoin_Address
 */
readonly class Bitcoin_Address_Query extends WP_Post_Query_Abstract {

	/**
	 * The Bitcoin_Address wp_post post_type.
	 */
	protected function get_post_type(): string {
		return Bitcoin_Address_WP_Post_Interface::POST_TYPE;
	}

	/**
	 *
	 * @return array<string,mixed> $map to:from
	 */
	#[\Override]
	protected function get_wp_post_fields(): array {
		return array(
			'post_name'   => $this->xpub, // slug.
			'post_title'  => $this->xpub, // post_title.
			'post_status' => $this->status,
			'post_parent' => $this->wallet_wp_post_parent_id,
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function get_meta_input(): array {
		return array(
			Bitcoin_Address_WP_Post_Interface::DERIVATION_PATH_SEQUENCE_NUMBER_META_KEY => $this->derivation_path_sequence_index,
			Bitcoin_Address_WP_Post_Interface::TRANSACTIONS_META_KEY => $this->transactions_post_ids,
			Bitcoin_Address_WP_Post_Interface::ORDER_ID_META_KEY => $this->associated_order_id,
			Bitcoin_Address_WP_Post_Interface::INTEGRATION_ID_META_KEY => $this->integration_id,
			Bitcoin_Address_WP_Post_Interface::TARGET_AMOUNT_META_KEY => $this->target_amount,
		);
	}

	/**
	 * Constructor
	 *
	 * Sometimes these fields are used to fetch, but some are only used to update.
	 *
	 * @param ?int                    $wallet_wp_post_parent_id The wp_posts id of the Bitcoin_Wallet this Bitcoin_Address belongs to.
	 * @param ?Bitcoin_Address_Status $status Is the Bitcoin_Address available etc.
	 * @param ?string                 $xpub The public key for the address.
	 * @param ?int                    $derivation_path_sequence_index This Bitcoin Address is the nth one derived from the Bitcoin_Wallet.
	 * @param array<int, string>|null $transactions_post_ids post_id:tx_id.
	 * @param string|class-string     $integration_id The plugin that is using this address.
	 * @param ?int                    $associated_order_id The wp_post ID for the order associated with the address.
	 * @param ?Money                  $target_amount The target amount of bitcoin to receive for the order the address is associated with. Saved in post_meta as `array{amount:string,currency:string}`.
	 */
	public function __construct(
		public ?int $wallet_wp_post_parent_id = null,
		public ?Bitcoin_Address_Status $status = null,
		public ?string $xpub = null,
		public ?int $derivation_path_sequence_index = null,
		public ?array $transactions_post_ids = null,
		public ?string $integration_id = null,
		public ?int $associated_order_id = null,
		public ?Money $target_amount = null,
	) {
		parent::__construct();
	}
}
