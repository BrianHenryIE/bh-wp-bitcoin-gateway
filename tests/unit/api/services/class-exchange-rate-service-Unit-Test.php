<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Services;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Exchange_Rate_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Exchange_Rate;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Currency;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\phpDocumentor\Reflection\DocBlock\StandardTagFactory;
use Codeception\Stub\Expected;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use WP_Mock;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Exchange_Rate_Service
 */
class Exchange_Rate_Service_Unit_Test extends \Codeception\Test\Unit {

	protected function setUp(): void {

		// $this->markTestSkipped('Needs changes to WP_Mock to support named parameters.');

		parent::setUp();
		WP_Mock::setUp();

		WP_Mock::userFunction(
			'wp_json_encode',
			array(
				'return' => function ( $value ) {
					return json_encode( $value );
				},
			)
		);
	}

	protected function tearDown(): void {
		parent::tearDown();
		WP_Mock::tearDown();
	}

	protected function get_sut(
		?Exchange_Rate_API_Interface $exchange_rate_api = null,
		?LoggerInterface $logger = null,
	): Exchange_Rate_Service {
		return new Exchange_Rate_Service(
			exchange_rate_api: $exchange_rate_api ?? $this->makeEmpty( Exchange_Rate_API_Interface::class ),
			logger: $logger ?? new ColorLogger(),
		);
	}

	/**
	 * @covers ::get_exchange_rate
	 */
	public function test_get_exchange_rate_already_stored(): void {

		if ( \WP_Mock::usingPatchwork() ) {
			/**
			 * @see StandardTagFactory::createTag()
			 * @ese AbstractPHPStanFactory::create()
			 */
			$this->markTestSkipped( 'This test fails when Patchwork is enabled – jsonmapper fails.' );
		}

		$exchange_rate_api_mock = $this->makeEmpty(
			Exchange_Rate_API_Interface::class,
			array(
				'get_exchange_rate' => Expected::never()
			)
		);

		$sut = $this->get_sut(
			exchange_rate_api: $exchange_rate_api_mock,
		);

		WP_Mock::userFunction(
			'get_transient',
			array(
				'times'  => 1,
				'args'   => array(
					'transient' => 'bh_wp_bitcoin_gateway_exchange_rate_USD',
				),
				'return' => json_encode(
					new Exchange_Rate(
						rate: Money::of( '65535', 'USD' ),
						api_classname: get_class( $this ),
						date_saved: new DateTimeImmutable(),
					)
				),
			)
		);

		WP_Mock::userFunction(
			'set_transient',
			array(
				'times' => 0,
				'args'  => array(
					'transient'  => 'bh_wp_bitcoin_gateway_exchange_rate_USD',
					'value'      => \WP_Mock\Functions::type( 'string' ),
					'expiration' => 3600,
				),
			)
		);

		$result = $sut->get_exchange_rate( Currency::of( 'USD' ) );

		self::assertEquals( '65535', $result?->abs()->getAmount()->toFloat() );
	}

	/**
	 * @covers ::get_exchange_rate
	 */
	public function test_get_exchange_rate_not_already_stored(): void {

		$exchange_rate_api = $this->makeEmpty(
			Exchange_Rate_API_Interface::class,
			array(
				'get_exchange_rate' => Expected::once(
					function ( Currency $currency ): Money {
						assert( 'USD' === $currency->getCurrencyCode() );
						return Money::of( '54321', Currency::of( 'USD' ) );
					}
				),
			)
		);

		$sut = $this->get_sut(
			exchange_rate_api: $exchange_rate_api,
		);

		WP_Mock::userFunction(
			'get_transient',
			array(
				'times'  => 1,
				'args'   => array(
					'transient' => 'bh_wp_bitcoin_gateway_exchange_rate_USD',
				),
				'return' => false,
			)
		);

		WP_Mock::userFunction(
			'set_transient',
			array(
				'times' => 1,
				'args'  => array(
					'transient'          => 'bh_wp_bitcoin_gateway_exchange_rate_USD',
					'value'              => \WP_Mock\Functions::type( 'string' ),
					'expiration' => 3600,
				),
			)
		);

		$result = $sut->get_exchange_rate( Currency::of( 'USD' ) );

		self::assertEquals( '54321', $result?->abs()->getAmount()->toFloat() );
	}
}
