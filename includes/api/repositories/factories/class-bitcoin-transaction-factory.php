<?php
/**
 * Mostly takes a WP_Post and returns a Bitcoin_Transaction
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Factories;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Bitcoin_Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Bitcoin_Transaction_WP_Post_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Helpers\JsonMapper\JsonMapper_DateTimeInterface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Helpers\JsonMapper\JsonMapper_Money;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\BtcRpcExplorer\JsonMapper\AssociativeArrayMiddleware;
use BrianHenryIE\WP_Bitcoin_Gateway\JsonMapper\Exception\BuilderException;
use BrianHenryIE\WP_Bitcoin_Gateway\JsonMapper\Exception\ClassFactoryException;
use BrianHenryIE\WP_Bitcoin_Gateway\JsonMapper\Handler\FactoryRegistry;
use BrianHenryIE\WP_Bitcoin_Gateway\JsonMapper\Handler\PropertyMapper;
use BrianHenryIE\WP_Bitcoin_Gateway\JsonMapper\JsonMapperBuilder;
use DateTimeInterface;
use InvalidArgumentException;
use WP_Post;

/**
 * @phpstan-type MoneySerializedArray array{amount:string,currency:string}
 */
class Bitcoin_Transaction_Factory {

	/**
	 * Get a Bitcoin transaction by WordPress post ID.
	 *
	 * @param int $post_id The WordPress post id this wallet is stored under.
	 *
	 * @throws InvalidArgumentException When the supplied post_id is not a post of this type.
	 * @throws BuilderException When the JSON mapper fails to build the transaction object from the post content.
	 * @throws ClassFactoryException When the JSON mapper cannot create required class instances during deserialization.
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
	 * @throws BuilderException When the JSON mapper fails to deserialize the transaction data from post_content.
	 * @throws ClassFactoryException When the JSON mapper cannot instantiate required classes like DateTimeInterface or Money during object creation.
	 */
	public function get_by_wp_post( WP_Post $post ): Bitcoin_Transaction {

		$factory_registry = new FactoryRegistry();

		$factory_registry->addFactory(
			DateTimeInterface::class,
			new JsonMapper_DateTimeInterface()
		);

		$factory_registry->addFactory(
			Money::class,
			new JsonMapper_Money()
		);

		// TODO: after testing, see what -> are unnecessary.
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
