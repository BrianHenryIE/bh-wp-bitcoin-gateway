<?php
/**
 * Strongly typed object for querying Bitcoin_Wallet in wp_posts table.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses;

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
		return array(
			'post_title'   => $this->master_public_key,
			'post_status'  => $this->status,
			'post_excerpt' => $this->master_public_key,
			'post_name'    => sanitize_title( $this->master_public_key ),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function get_meta_input(): array {
		return array(
			Bitcoin_Wallet_WP_Post_Interface::GATEWAY_IDS_META_KEY => $this->gateways,
		);
	}

	/**
	 * Constructor.
	 *
	 * @param ?string $master_public_key The Wallet's master public key.
	 * @param ?string $status Current status, e.g. new. TODO: enum.
	 * @param ?array $gateways List of gateways the Bitcoin_Wallet is being used by
	 */
	public function __construct(
		public ?string $master_public_key = null,
		public ?string $status = null,
		public ?array $gateways = null,
	) {
		parent::__construct();
	}
}
