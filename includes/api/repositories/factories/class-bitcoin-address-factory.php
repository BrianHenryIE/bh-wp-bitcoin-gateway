<?php
/**
 * Mostly takes a WP_Post and returns a Bitcoin_Address
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Factories;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Exceptions\BH_WP_Bitcoin_Gateway_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address_Status;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address_WP_Post_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\JsonMapper\JsonMapperInterface;
use DateMalformedStringException;
use DateTimeImmutable;
use InvalidArgumentException;
use WP_Post;

/**
 * @phpstan-type MoneySerializedArray array{amount:string,currency:string}
 */
class Bitcoin_Address_Factory {

	/**
	 * @param JsonMapperInterface $json_mapper To parse JSON to typed objects.
	 */
	public function __construct(
		protected JsonMapperInterface $json_mapper,
	) {
	}

	/**
	 * @param int $post_id The WordPress post id this wallet is stored under.
	 *
	 * @throws InvalidArgumentException When the supplied post_id is not a post of this type.
	 */
	public function get_by_wp_post_id( int $post_id ): Bitcoin_Address {
		$post = get_post( $post_id );
		if ( ! ( $post instanceof WP_Post ) || Bitcoin_Address_WP_Post_Interface::POST_TYPE !== $post->post_type ) {
			throw new InvalidArgumentException( 'post_id ' . $post_id . ' is not a ' . Bitcoin_Address_WP_Post_Interface::POST_TYPE . ' post object' );
		}

		return $this->get_by_wp_post( $post );
	}

	/**
	 * Takes a WP_Post and gets the values (primitives?) to create a Bitcoin_Address.
	 *
	 * The first call to {@see get_post_meta()} caches all meta for the object, {@see get_metadata_raw()}.
	 *
	 * @param WP_Post $post The backing WP_Post for this Bitcoin_Address.
	 * @throws DateMalformedStringException If somehow {@see WP_Post::$post_modified_gmt} is not in the expected format.
	 */
	public function get_by_wp_post( WP_Post $post ): Bitcoin_Address {

		return new Bitcoin_Address(
			post_id: $post->ID,
			wallet_parent_post_id: $post->post_parent,
			raw_address: $post->post_title,
			derivation_path_sequence_number: $this->get_derivation_path_sequence_number_from_post( $post ),
			created_time: new DateTimeImmutable( $post->post_date_gmt ),
			modified_time: new DateTimeImmutable( $post->post_modified_gmt ),
			status: Bitcoin_Address_Status::from( $post->post_status ),
			target_amount: $this->get_target_amount_from_post( $post ),
			integration_id: $this->get_integration_id_from_post_meta( $post ),
			order_id: $this->get_order_id_from_post( $post ),
			tx_ids: $this->get_tx_ids_from_post( $post ),
			received: $this->get_received_from_post( $post ),
		);
	}

	/**
	 * @param WP_Post $post The backing WP_Post for this Bitcoin_Address.
	 */
	protected function get_derivation_path_sequence_number_from_post( WP_Post $post ): int {
		/** @var array|bool|float|int|resource|string|null|mixed $meta_value */
		$meta_value = get_post_meta( $post->ID, Bitcoin_Address_WP_Post_Interface::DERIVATION_PATH_SEQUENCE_NUMBER_META_KEY, true );
		return is_numeric( $meta_value )
			? intval( $meta_value )
			: ( function () use ( $meta_value ) {
				throw new BH_WP_Bitcoin_Gateway_Exception( 'get_derivation_path_sequence_number_from_post failed for ' . wp_json_encode( $meta_value ) );
			} )();
	}

	/**
	 * @param WP_Post $post The backing WP_Post for this Bitcoin_Address.
	 */
	protected function get_target_amount_from_post( WP_Post $post ): ?Money {
		/** @var mixed|MoneySerializedArray $target_amount_meta_json_string */
		$target_amount_meta_json_string = get_post_meta( $post->ID, Bitcoin_Address_WP_Post_Interface::TARGET_AMOUNT_META_KEY, true );
		if ( ! is_string( $target_amount_meta_json_string ) ) {
			return null;
		}
		try {
			return $this->json_mapper->mapToClassFromString( $target_amount_meta_json_string, Money::class );
		} catch ( \Exception $exception ) {
			return null;
		}
	}

	/**
	 * @param WP_Post $post The backing WP_Post for this Bitcoin_Address.
	 */
	protected function get_integration_id_from_post_meta( WP_Post $post ): ?string {
		/** @var array|bool|float|int|resource|string|null|mixed $integration_id_meta */
		$integration_id_meta = get_post_meta( $post->ID, Bitcoin_Address_WP_Post_Interface::INTEGRATION_ID_META_KEY, true );
		return is_string( $integration_id_meta ) && ! empty( $integration_id_meta ) ? $integration_id_meta : null;
	}

	/**
	 * @param WP_Post $post The backing WP_Post for this Bitcoin_Address.
	 */
	protected function get_order_id_from_post( WP_Post $post ): ?int {
		/** @var array|bool|float|int|resource|string|null|mixed $order_id_meta */
		$order_id_meta = get_post_meta( $post->ID, Bitcoin_Address_WP_Post_Interface::ORDER_ID_META_KEY, true );
		return is_numeric( $order_id_meta ) ? intval( $order_id_meta ) : null;
	}

	/**
	 * @param WP_Post $post The backing WP_Post for this Bitcoin_Address.
	 * @return array<int,string>|null
	 */
	protected function get_tx_ids_from_post( WP_Post $post ): ?array {
		/** @var string|null|mixed $tx_ids_meta */
		$tx_ids_meta = get_post_meta( $post->ID, Bitcoin_Address_WP_Post_Interface::TRANSACTIONS_META_KEY, true );
		if ( ! is_string( $tx_ids_meta ) ) {
			return null;
		}
		/** @var array<int,string>|null|mixed $tx_ids_meta_array */
		$tx_ids_meta_array = json_decode( $tx_ids_meta, true );
		if ( is_array( $tx_ids_meta_array ) ) {
			foreach ( $tx_ids_meta_array as $post_id => $tx_id ) {
				if ( ! is_int( $post_id ) || ! is_string( $tx_id ) ) {
					// TODO: Log error? Add a fail-hard debug flag?
					return null;
				}
			}
			/** @var array<int,string> $tx_ids_meta_array */
			return $tx_ids_meta_array;
		}
		return null;
	}

	/**
	 * @param WP_Post $post The backing WP_Post for this Bitcoin_Address.
	 */
	protected function get_received_from_post( WP_Post $post ): ?Money {
		/** @var mixed|MoneySerializedArray $confirmed_amount_received_meta */
		$confirmed_amount_received_meta = get_post_meta( $post->ID, Bitcoin_Address_WP_Post_Interface::CONFIRMED_AMOUNT_RECEIVED_META_KEY, true );
		if ( ! is_string( $confirmed_amount_received_meta ) ) {
			return null;
		}
		try {
			return $this->json_mapper->mapToClassFromString( $confirmed_amount_received_meta, Money::class );
		} catch ( \Exception $exception ) {
			return null;
		}
	}
}
