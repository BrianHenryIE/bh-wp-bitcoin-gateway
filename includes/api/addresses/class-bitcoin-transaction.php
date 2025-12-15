<?php
/**
 *
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_VIn;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_VOut;
use DateTimeInterface;
use InvalidArgumentException;
use WP_Post;

/**
 * @phpstan-type WpUpdatePostArray array{ID?: int, post_author?: int, post_date?: string, post_date_gmt?: string, post_content?: string, post_content_filtered?: string, post_title?: string, post_excerpt?: string}
 */
// TODO: prefer `readonly class` but PHPUnit won't mock readonly.
// readonly class Bitcoin_Transaction implements Transaction_Interface {
class Bitcoin_Transaction implements Transaction_Interface {

	/**
	 * Constructor
	 *
	 * @param WP_Post           $post The wp_post the Bitcoin address detail is stored as.
	 * @param int               $block_height
	 * @param DateTimeInterface $block_time
	 * @param string            $tx_id
	 * @param string            $hash
	 * @param int               $version
	 * @param array             $v_in
	 * @param array             $v_out
	 *
	 * @throws InvalidArgumentException When the supplied post_id is not a post of this type.
	 */
	public function __construct(
		protected WP_Post $post,
		protected int $block_height,
		protected DateTimeInterface $block_time,
		protected string $tx_id,
		protected string $hash,
		protected int $version,
		protected array $v_in,
		protected array $v_out,
	) {
	}

	public function get_post_id(): int {
		return $this->post->ID;
	}

	public function get_txid(): string {
		return $this->tx_id;
	}

	public function get_block_time(): DateTimeInterface {
		return $this->block_time;
	}

	public function get_block_height(): ?int {
		return $this->block_height;
	}

	public function get_hash(): string {
		return $this->hash;
	}

	public function get_version(): int {
		return $this->version;
	}

	/**
	 * @return Transaction_VIn[]
	 */
	public function get_v_in(): array {
		return $this->v_in;
	}

	/**
	 * @return Transaction_VOut[]
	 */
	public function get_v_out(): array {
		return $this->v_out;
	}
}
