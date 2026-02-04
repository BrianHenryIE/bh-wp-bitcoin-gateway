<?php
/**
 * Tests for Post_BH_Bitcoin_Address class.
 *
 * Verifies that the dependencies array is correctly structured and passed
 * to register_post_type() for consumption by Addresses_List_Table.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Address_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Wallet_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use Codeception\Test\Unit;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes\Post_BH_Bitcoin_Address
 */
class Post_BH_Bitcoin_Address_Unit_Test extends Unit {

	protected function setUp(): void {
		parent::setUp();
		\WP_Mock::setUp();
	}

	#[\Override]
	protected function tearDown(): void {
		parent::tearDown();
		\WP_Mock::tearDown();
	}

	protected function get_sut(
		?API_Interface $api = null,
		?Bitcoin_Address_Repository $bitcoin_address_repository = null,
		?Bitcoin_Wallet_Repository $bitcoin_wallet_repository = null,
	): Post_BH_Bitcoin_Address {
		return new Post_BH_Bitcoin_Address(
			api: $api ?? $this->makeEmpty( API_Interface::class ),
			bitcoin_address_repository: $bitcoin_address_repository ?? $this->make( Bitcoin_Address_Repository::class ),
			bitcoin_wallet_repository: $bitcoin_wallet_repository ?? $this->make( Bitcoin_Wallet_Repository::class ),
		);
	}

	/**
	 * Verify register_post_type is called with dependencies in args.
	 *
	 * @covers ::register_address_post_type
	 */
	public function test_register_address_post_type_passes_dependencies(): void {

		$api                        = $this->makeEmpty( API_Interface::class );
		$bitcoin_address_repository = $this->make( Bitcoin_Address_Repository::class );
		$bitcoin_wallet_repository  = $this->make( Bitcoin_Wallet_Repository::class );

		$sut = $this->get_sut( $api, $bitcoin_address_repository, $bitcoin_wallet_repository );

		$captured_args = null;

		\WP_Mock::userFunction(
			'register_post_type',
		)->withArgs(
			function ( $post_type, $args ) use ( &$captured_args ) {
				$captured_args = $args;
				return 'bh-bitcoin-address' === $post_type;
			}
		)->once()->andReturn( new \stdClass() );

		\WP_Mock::userFunction(
			'register_post_status',
			array(
				'times' => 4, // unknown, unused, assigned, used.
			)
		);

		\WP_Mock::passthruFunction( '_x' );

		\WP_Mock::userFunction(
			'_n_noop',
			array(
				'return' => array(),
			)
		);

		$sut->register_address_post_type();

		$this->assertNotNull( $captured_args, 'register_post_type should have been called' );

		$this->assertArrayHasKey( 'dependencies', $captured_args );
		$this->assertIsArray( $captured_args['dependencies'] );

		$this->assertArrayHasKey( 'api', $captured_args['dependencies'] );
		$this->assertSame( $api, $captured_args['dependencies']['api'] );

		$this->assertArrayHasKey( 'bitcoin_address_repository', $captured_args['dependencies'] );
		$this->assertSame( $bitcoin_address_repository, $captured_args['dependencies']['bitcoin_address_repository'] );

		$this->assertArrayHasKey( 'bitcoin_wallet_repository', $captured_args['dependencies'] );
		$this->assertSame( $bitcoin_wallet_repository, $captured_args['dependencies']['bitcoin_wallet_repository'] );
	}
}
