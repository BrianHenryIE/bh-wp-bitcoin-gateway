<?php
/**
 * Mostly takes a WP_Post and returns a Bitcoin_Transaction
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\BH_WP_Bitcoin_Gateway_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\BtcRpcExplorer\JsonMapper\AssociativeArrayMiddleware;
use BrianHenryIE\WP_Bitcoin_Gateway\JsonMapper\Exception\BuilderException;
use BrianHenryIE\WP_Bitcoin_Gateway\JsonMapper\Exception\ClassFactoryException;
use BrianHenryIE\WP_Bitcoin_Gateway\JsonMapper\Handler\FactoryRegistry;
use BrianHenryIE\WP_Bitcoin_Gateway\JsonMapper\Handler\PropertyMapper;
use BrianHenryIE\WP_Bitcoin_Gateway\JsonMapper\JsonMapperBuilder;
use InvalidArgumentException;
use JsonException;
use stdClass;
use WP_Post;

/**
 * @phpstan-type MoneySerializedArray array{amount:string,currency:string}
 */
class Bitcoin_Transaction_Factory {

	/**
	 * @param int $post_id The WordPress post id this wallet is stored under.
	 *
	 * @throws InvalidArgumentException When the supplied post_id is not a post of this type.
	 * @throws BuilderException
	 * @throws ClassFactoryException
	 */
	public function get_by_wp_post_id( int $post_id ): Bitcoin_Transaction {
		$post = get_post( $post_id );
		if ( ! ( $post instanceof WP_Post ) || Bitcoin_Transaction_WP_Post_Interface::POST_TYPE !== $post->post_type ) {
			throw new InvalidArgumentException( 'post_id ' . $post_id . ' is not a ' . Bitcoin_Transaction_WP_Post_Interface::POST_TYPE . ' post object' );
		}

		return $this->get_by_wp_post( $post );
	}

	/**
	 * Takes a WP_Post and gets the values (primitives?) to create a Bitcoin_Transaction.
	 *
	 * TODO: Transactions.
	 *
	 * @param WP_Post $post The backing WP_Post for this Bitcoin_Transaction.
	 *
	 * @throws BuilderException
	 * @throws ClassFactoryException
	 */
	public function get_by_wp_post( WP_Post $post ): Bitcoin_Transaction {

		$factory_registry = new FactoryRegistry();

		$factory_registry->addFactory(
			\DateTimeInterface::class,
			/** @param stdClass{date?:string, timezone:string} $json_object */
			function ( stdClass $json_object ) {
				if (
					! property_exists( $json_object, 'date' )
					|| ! property_exists( $json_object, 'timezone' )
				) {
					throw new BH_WP_Bitcoin_Gateway_Exception( 'Invalid json encoded DateTime object.' );
				}
				/** @var string $date */
				$date = $json_object->date;
				/** @var string $timezone */
				$timezone = $json_object->timezone;
				return new \DateTimeImmutable( $date )
				->setTimezone(
					new \DateTimeZone( $timezone )
				);
			}
		);

		$factory_registry->addFactory(
			Money::class,
			function ( stdClass $json_object ) {
				/**
				 * @see Money::jsonSerialize()
				 */
				if (
					! property_exists( $json_object, 'amount' )
					|| ! property_exists( $json_object, 'currency' )
				) {
					throw new BH_WP_Bitcoin_Gateway_Exception( 'Invalid json encoded money object.' );
				}
				/** @var string $amount */
				$amount = $json_object->amount;
				/** @var string $currency */
				$currency = $json_object->currency;
				return Money::of( $amount, $currency );
			}
		);

		$property_mapper = new PropertyMapper( $factory_registry );
		$mapper          = JsonMapperBuilder::new()
			->withPropertyMapper( $property_mapper )
			->withAttributesMiddleware()
			->withDocBlockAnnotationsMiddleware()
			->withTypedPropertiesMiddleware()
			->withNamespaceResolverMiddleware()
			->withObjectConstructorMiddleware( $factory_registry )
			->build();
		$mapper->push( new AssociativeArrayMiddleware() );

		/** @var Transaction $transaction */
		$transaction = $mapper->mapToClassFromString( $post->post_content, Transaction::class );

		// If this is an empty array, something is up. We never save a transaction unless it is relevant to an address of ours.
		/** @var array<int,string> $addresses post_id:bitcoin_address */
		$addresses = array_filter(
			(array) get_post_meta( $post->ID, Bitcoin_Transaction_WP_Post_Interface::BITCOIN_ADDRESSES_POST_IDS_META_KEY, true )
		);

		return new Bitcoin_Transaction(
			post_id: $post->ID,
			transaction: $transaction,
			bitcoin_addresses: $addresses,
		);
	}
}
