<?php
/**
 * Save new Bitcoin wallets in WordPress, and fetch them via xpub or post id.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet_WP_Post_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Factories\Bitcoin_Wallet_Factory;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Queries\Bitcoin_Wallet_Query;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet_Status;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Exceptions\BH_WP_Bitcoin_Gateway_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Exception\UnknownCurrencyException;
use DateTimeImmutable;
use InvalidArgumentException;
use WP_Post;
use wpdb;

/**
 * @see Bitcoin_Wallet_WP_Post_Interface
 */
class Bitcoin_Wallet_Repository extends WP_Post_Repository_Abstract {

	/**
	 * Constructor.
	 *
	 * @param Bitcoin_Wallet_Factory $bitcoin_wallet_factory Factory for creating Bitcoin wallet objects.
	 */
	public function __construct(
		protected Bitcoin_Wallet_Factory $bitcoin_wallet_factory,
	) {
	}

	/**
	 * NB: post_name is 200 characters long. zpub is 111 characters.
	 *
	 * @param string $xpub The master public key of the wallet.
	 * @throws BH_WP_Bitcoin_Gateway_Exception If more than one saved wallet was found for the master public key.
	 * @throws UnknownCurrencyException If BTC is not correctly added to brick/money.
	 */
	public function get_by_xpub( string $xpub ): ?Bitcoin_Wallet {

		$post_id = $this->get_post_id_for_master_public_key( $xpub );

		if ( ! $post_id ) {
			return null;
		}

		return $this->bitcoin_wallet_factory->get_by_wp_post_id( $post_id );
	}

	/**
	 * Search wp_posts.post_name for the wallet master public key.
	 *
	 * @see wordpress/wp-admin/includes/schema.php:184
	 *
	 * @param string $master_public_key The Wallet address we may have saved.
	 *
	 * @throws BH_WP_Bitcoin_Gateway_Exception If there is more than one db entry for the same wallet (v.unlikely).
	 */
	protected function get_post_id_for_master_public_key( string $master_public_key ): ?int {

		$cached = wp_cache_get( $master_public_key, Bitcoin_Wallet_WP_Post_Interface::POST_TYPE );
		if ( is_numeric( $cached ) ) {
			return intval( $cached );
		}

		/** @var wpdb $wpdb */
		global $wpdb;

		/**
		 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		 *
		 * @var array<int|numeric-string> $post_ids
		 */
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT ID FROM %i WHERE post_name=%s AND post_type=%s',
				$wpdb->posts,
				sanitize_title( $master_public_key ),
				Bitcoin_Wallet_WP_Post_Interface::POST_TYPE
			)
		);

		switch ( count( $post_ids ) ) {
			case 0:
				return null;
			case 1:
				$post_id = intval( $post_ids[ array_key_first( $post_ids ) ] );
				wp_cache_set( $master_public_key, $post_id, Bitcoin_Wallet_WP_Post_Interface::POST_TYPE );
				return $post_id;
			default:
				throw new BH_WP_Bitcoin_Gateway_Exception( count( $post_ids ) . ' Bitcoin_Wallets found, only one expected, for ' . $master_public_key );
		}
	}

	/**
	 * Given the id of the wp_posts row storing the bitcoin wallet, return the typed Bitcoin_Wallet object.
	 *
	 * @param int $post_id WordPress wp_posts ID.
	 *
	 * @return Bitcoin_Wallet
	 * @throws InvalidArgumentException When the post_type of the post returned for the given post_id is not a Bitcoin_Wallet.
	 */
	public function get_by_wp_post_id( int $post_id ): Bitcoin_Wallet {
		return $this->bitcoin_wallet_factory->get_by_wp_post_id( $post_id );
	}

	/**
	 *
	 * @param Bitcoin_Wallet_Status $status Filter by Bitcoin_Wallet_Status – 'active'|'inactive'.
	 *
	 * @return Bitcoin_Wallet[]
	 */
	public function get_all( Bitcoin_Wallet_Status $status ): array {
		$args = new Bitcoin_Wallet_Query(
			status: $status,
		);

		$query_array = $args->to_query_array();
		$query       = array_filter(
			$query_array,
			fn( string $key ): bool => in_array( $key, array( 'post_type', 'post_status' ), true ),
			ARRAY_FILTER_USE_KEY,
		);

		/** @var WP_Post[] $posts */
		$posts = get_posts( $query );

		return array_map(
			$this->bitcoin_wallet_factory->get_by_wp_post( ... ),
			$posts
		);
	}

	/**
	 * Create a new Bitcoin_Wallet WordPress post for the provided address and optionally specify the associated gateway.
	 *
	 * @param string                                              $master_public_key The xpub/ypub/zpub of the wallet.
	 * @param ?array{integration:class-string, gateway_id:string} $gateway The WC_Payment_Gateway the wallet is being used with.
	 *
	 * @return Bitcoin_Wallet The wp_posts saved wallet.
	 * @throws BH_WP_Bitcoin_Gateway_Exception When `wp_insert_post()` fails.
	 */
	public function save_new( string $master_public_key, ?array $gateway = null ): Bitcoin_Wallet {

		$existing = $this->get_by_xpub( $master_public_key );
		if ( $existing ) {
			return $existing;
		}

		$args = new Bitcoin_Wallet_Query(
			master_public_key: $master_public_key,
			status: ! is_null( $gateway ) ? Bitcoin_Wallet_Status::ACTIVE : Bitcoin_Wallet_Status::INACTIVE,
			gateway_refs: $gateway ? array( $gateway ) : null,
		);

		$query_args_array = $args->to_query_array();
		$post_id          = wp_insert_post( $query_args_array, true );

		if ( is_wp_error( $post_id ) ) {
			throw new BH_WP_Bitcoin_Gateway_Exception( 'Failed to save new wallet as wp_post' );
		}

		return $this->get_by_wp_post_id( $post_id );
	}

	/**
	 * Save the index of the highest generated address.
	 *
	 * @param Bitcoin_Wallet $wallet The Bitcoin Wallet to indicate its newest derived address index.
	 * @param int            $index Nth address generated index.
	 */
	public function set_highest_address_index( Bitcoin_Wallet $wallet, int $index ): void {

		$this->update(
			model: $wallet,
			query: new Bitcoin_Wallet_Query(
				last_derived_address_index: $index,
			)
		);
	}

	/**
	 * Fetch the wallet from its wp_post again.
	 *
	 * @param Bitcoin_Wallet $wallet To refresh.
	 */
	public function refresh( Bitcoin_Wallet $wallet ): Bitcoin_Wallet {
		return $this->bitcoin_wallet_factory->get_by_wp_post_id( $wallet->get_post_id() );
	}

	/**
	 * Add meta to the wallet to record where it is being used.
	 *
	 * @param Bitcoin_Wallet                                     $wallet The wallet to update.
	 * @param array{integration:class-string, gateway_id:string} $new_gateway_details The integration,gateway_id to associate with the wallet.
	 * @return void
	 * @see Bitcoin_Wallet_WP_Post_Interface::GATEWAYS_DETAILS_META_KEY
	 */
	public function append_gateway_details( Bitcoin_Wallet $wallet, array $new_gateway_details ): void {
		$new_gateway_details['date_added'] = new DateTimeImmutable();

		// TODO: does this need to be done for every one every time?
		$new_gateway_details['integration'] = wp_slash( $new_gateway_details['integration'] );

		$associated_gateway_details   = $wallet->get_associated_gateways_details();
		$associated_gateway_details[] = $new_gateway_details;

		$this->update(
			$wallet,
			new Bitcoin_Wallet_Query(
				gateway_refs: $associated_gateway_details,
			)
		);
	}
}
