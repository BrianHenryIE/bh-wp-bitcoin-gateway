<?php
/**
 * Mostly takes a WP_Post and returns a Bitcoin_Address
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Factories;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Exceptions\BH_WP_Bitcoin_Gateway_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address_Status;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address_WP_Post_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\JsonMapper\JsonMapperInterface;
use DateMalformedStringException;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Throwable;
use WP_Post;

/**
 * Some fields are optional (e.g. target amount is only set after an address is assigned) and errors with those
 * (i.e. parsing meta values to objects) fail soft with warnings logged. Non-optional field throw exceptions on
 * failures.
 *
 * @phpstan-type MoneySerializedArray array{amount:string,currency:string}
 */
class Bitcoin_Address_Factory implements LoggerAwareInterface {
	use LoggerAwareTrait;

	/**
	 * Constructor.
	 *
	 * @param JsonMapperInterface $json_mapper To parse JSON to typed objects.
	 * @param LoggerInterface     $logger PSR logger for failures parsing metadata to values.
	 */
	public function __construct(
		protected JsonMapperInterface $json_mapper,
		LoggerInterface $logger,
	) {
		$this->setLogger( $logger );
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
		return $this->get_json_mapped_money_from_post(
			post_id: $post->ID,
			meta_key: Bitcoin_Address_WP_Post_Interface::TARGET_AMOUNT_META_KEY
		);
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
		if ( empty( $tx_ids_meta ) ) {
			return null;
		}
		if ( ! is_string( $tx_ids_meta ) ) {
			$this->log_meta_value_warning( $post->ID, Bitcoin_Address_WP_Post_Interface::TRANSACTIONS_META_KEY, 'array of txids', $tx_ids_meta );
			return null;
		}
		/** @var array<int,string>|null|mixed $tx_ids_meta_array */
		$tx_ids_meta_array = json_decode( $tx_ids_meta, true );
		if ( ! is_array( $tx_ids_meta_array ) ) {
			$this->log_meta_value_warning( $post->ID, Bitcoin_Address_WP_Post_Interface::TRANSACTIONS_META_KEY, 'array of txids', $tx_ids_meta );
			return null;
		}
		foreach ( $tx_ids_meta_array as $post_id => $tx_id ) {
			if ( ! is_int( $post_id ) || ! is_string( $tx_id ) ) {
				$this->log_meta_value_warning( $post->ID, Bitcoin_Address_WP_Post_Interface::TRANSACTIONS_META_KEY, 'array of txids', $tx_ids_meta );
				return null;
			}
		}
		/** @var array<int,string> $tx_ids_meta_array */
		return $tx_ids_meta_array;
	}

	/**
	 * @param WP_Post $post The backing WP_Post for this Bitcoin_Address.
	 */
	protected function get_received_from_post( WP_Post $post ): ?Money {
		return $this->get_json_mapped_money_from_post(
			post_id: $post->ID,
			meta_key: Bitcoin_Address_WP_Post_Interface::CONFIRMED_AMOUNT_RECEIVED_META_KEY
		);
	}

	/**
	 * Use JSON Mapper to parse the meta value to a Money object, if it cannot be parsed, record a warning and return null.
	 *
	 * @param int    $post_id The ID of the WP Post that contained the invalid value.
	 * @param string $meta_key The name of the value we were looking for.
	 */
	protected function get_json_mapped_money_from_post( int $post_id, string $meta_key ): ?Money {
		/** @var mixed|MoneySerializedArray $meta_value */
		$meta_value = get_post_meta( $post_id, $meta_key, true );
		if ( empty( $meta_value ) ) {
			// Empty meta is valid for unassigned addresses and those without transactions.
			return null;
		}
		if ( ! is_string( $meta_value ) ) {
			$this->log_meta_value_warning( $post_id, $meta_key, 'Money', $meta_value );
			return null;
		}
		try {
			return $this->json_mapper->mapToClassFromString( $meta_value, Money::class );
		} catch ( Throwable $exception ) {
			$this->log_meta_value_warning( $post_id, $meta_key, 'Money', $meta_value, $exception );
			return null;
		}
	}

	/**
	 * Log a useful message when the meta value could not be parsed to a Money object.
	 *
	 * @see LoggerInterface::warning()
	 * @param int        $post_id ID for the WP_Post holding the data.
	 * @param string     $meta_key The key.
	 * @param string     $type Human-readable type for the message.
	 * @param mixed      $meta_value The saved value, not empty.
	 * @param ?Throwable $exception Potentially thrown exception.
	 */
	protected function log_meta_value_warning(
		int $post_id,
		string $meta_key,
		string $type,
		mixed $meta_value,
		?Throwable $exception = null
	): void {
		$this->logger->warning(
			'Failed to parse payment address meta {meta_key} as {type} for post id {post_id}, value: {meta_value}',
			array(
				'exception'  => $exception,
				'post_id'    => $post_id,
				'meta_key'   => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'type'       => $type,
				'meta_value' => $meta_value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
	}
}
