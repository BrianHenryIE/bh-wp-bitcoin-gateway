<?php
/**
 * Strongly typed object for querying Bitcoin_Wallet in wp_posts table.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Queries;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet_Status;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet_WP_Post_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes\Post_BH_Bitcoin_Wallet;

/**
 * @see Post_BH_Bitcoin_Wallet
 */
readonly class Bitcoin_Wallet_Query extends WP_Post_Query_Abstract {

	/**
	 * The Bitcoin_Wallet wp_post post_type.
	 */
	protected function get_post_type(): string {
		return Bitcoin_Wallet_WP_Post_Interface::POST_TYPE;
	}

	/**
	 *
	 * @return array<string,mixed> $map to:from
	 */
	protected function get_wp_post_fields(): array {
		$wp_post_fields = array();

		if ( $this->master_public_key ) {
			$wp_post_fields['post_title'] = $this->master_public_key;
		}
		if ( $this->master_public_key ) {
			$wp_post_fields['post_title'] = $this->master_public_key;
		}
		if ( $this->status ) {
			$wp_post_fields['post_status'] = $this->status;
		}
		if ( $this->master_public_key ) {
			$wp_post_fields['post_name'] = sanitize_title( $this->master_public_key );
		}

		return $wp_post_fields;
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function get_meta_input(): array {
		return array_filter(
			array(
				Bitcoin_Wallet_WP_Post_Interface::GATEWAY_IDS_META_KEY => $this->gateway_refs,
				Bitcoin_Wallet_WP_Post_Interface::LAST_DERIVED_ADDRESS_INDEX_META_KEY => $this->last_derived_address_index,
			)
		);
	}

	/**
	 * Constructor.
	 *
	 * @param ?string                $master_public_key The Wallet's master public key.
	 * @param ?Bitcoin_Wallet_Status $status Current status, e.g. new.
	 * @param ?array<int|string>     $gateway_refs List of gateways the Bitcoin_Wallet is being used by.
	 * @param ?int                   $last_derived_address_index The highest address index that has been derived from this wallet's master public key.
	 */
	public function __construct(
		public ?string $master_public_key = null,
		public ?Bitcoin_Wallet_Status $status = null,
		public ?array $gateway_refs = null,
		public ?int $last_derived_address_index = null,
	) {
		parent::__construct();
	}
}
