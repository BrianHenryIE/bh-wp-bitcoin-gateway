<?php
/**
 * Get a JsonMapper instance that can decode Money and DateTimeInterface.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Helpers\JsonMapper;

use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\BtcRpcExplorer\JsonMapper\AssociativeArrayMiddleware;
use BrianHenryIE\WP_Bitcoin_Gateway\JsonMapper\Exception\BuilderException;
use BrianHenryIE\WP_Bitcoin_Gateway\JsonMapper\Exception\ClassFactoryException;
use BrianHenryIE\WP_Bitcoin_Gateway\JsonMapper\Handler\FactoryRegistry;
use BrianHenryIE\WP_Bitcoin_Gateway\JsonMapper\Handler\PropertyMapper;
use BrianHenryIE\WP_Bitcoin_Gateway\JsonMapper\JsonMapperBuilder;
use BrianHenryIE\WP_Bitcoin_Gateway\JsonMapper\JsonMapperInterface;
use DateTimeInterface;

/**
 * @see JsonMapperBuilder::build()
 */
class JsonMapper_Helper {

	/**
	 * Get a JSONMapper instance configured with DateTimeInterface and Money helpers.
	 *
	 * @throws ClassFactoryException Something must be wrong with one of our factory implementations. Definitely should not be a run-time error to just register them.
	 * @throws BuilderException Would suggest something went wrong inside JsonMapper itself.
	 */
	public function build(): JsonMapperInterface {

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

		return $mapper;
	}
}
