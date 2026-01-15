<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Services;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Wallet;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Generate_Address_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Address_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Wallet_Repository;
use Codeception\Stub\Expected;
use WP_Mock;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Bitcoin_Wallet_Service
 */
class Bitcoin_Wallet_Service_Unit_Test extends \Codeception\Test\Unit {

	protected function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
	}

	protected function tearDown(): void {
		parent::tearDown();
		WP_Mock::tearDown();
	}

	protected function get_sut(
		?Generate_Address_API_Interface $generate_address_api = null,
		?Bitcoin_Wallet_Repository $bitcoin_wallet_repository = null,
		?Bitcoin_Address_Repository $bitcoin_address_repository = null,
	): Bitcoin_Wallet_Service {
		return new Bitcoin_Wallet_Service(
			generate_address_api: $generate_address_api ?? $this->makeEmpty( Generate_Address_API_Interface::class ),
			bitcoin_wallet_repository: $bitcoin_wallet_repository ?? $this->makeEmpty( Bitcoin_Wallet_Repository::class ),
			bitcoin_address_repository: $bitcoin_address_repository ?? $this->makeEmpty( Bitcoin_Address_Repository::class ),
		);
	}

	/**
	 * Test generating new addresses for a wallet starting from index null (first time).
	 *
	 * @covers ::generate_new_addresses_for_wallet
	 */
	public function test_generate_new_addresses_for_wallet_from_null_index(): void {
		$xpub = 'xpub6CUGRUonZSQ4TWtTMmzXdrXDtypWKiKrhko4egpiMZbpiaQL2jkwSB1icqYh2cfDfVxdx4df189oLKnC5fSwqPfgyP3hooxujYzAu3fDVmz';

		$wallet = $this->make(
			Bitcoin_Wallet::class,
			array(
				'get_xpub'          => Expected::atLeastOnce( $xpub ),
				'get_address_index' => Expected::once( null ),
			)
		);

		$generate_address_api = $this->makeEmpty(
			Generate_Address_API_Interface::class,
			array(
				'generate_address' => Expected::exactly(
					2,
					function ( string $xpub_param, int $index ) use ( $xpub ): string {
						$this->assertEquals( $xpub, $xpub_param );
						return 'bc1qaddress' . $index;
					}
				),
			)
		);

		$bitcoin_address_1 = $this->make(
			Bitcoin_Address::class,
			array(
				'get_raw_address' => 'bc1qaddress0',
			)
		);

		$bitcoin_address_2 = $this->make(
			Bitcoin_Address::class,
			array(
				'get_raw_address' => 'bc1qaddress1',
			)
		);

		$bitcoin_address_repository = $this->makeEmpty(
			Bitcoin_Address_Repository::class,
			array(
				'get_post_id_for_address' => Expected::exactly(
					2,
					function ( string $address ): ?int {
						// Return null to indicate address doesn't exist yet.
						return null;
					}
				),
				'save_new'                => Expected::exactly(
					2,
					function ( Bitcoin_Wallet $wallet, int $index, string $address ) use ( $bitcoin_address_1, $bitcoin_address_2 ): Bitcoin_Address {
						if ( 0 === $index ) {
							$this->assertEquals( 'bc1qaddress0', $address );
							return $bitcoin_address_1;
						} else {
							$this->assertEquals( 'bc1qaddress1', $address );
							return $bitcoin_address_2;
						}
					}
				),
			)
		);

		$bitcoin_wallet_repository = $this->makeEmpty(
			Bitcoin_Wallet_Repository::class,
			array(
				'set_highest_address_index' => Expected::once(
					function ( Bitcoin_Wallet $wallet, int $index ): void {
						$this->assertEquals( 1, $index );
					}
				),
			)
		);

		$sut = $this->get_sut(
			generate_address_api: $generate_address_api,
			bitcoin_wallet_repository: $bitcoin_wallet_repository,
			bitcoin_address_repository: $bitcoin_address_repository
		);

		$result = $sut->generate_new_addresses_for_wallet( $wallet );

		$this->assertCount( 2, $result->new_addresses );
		$this->assertEquals( 1, $result->address_index );
		$this->assertSame( $wallet, $result->wallet );
	}

	/**
	 * Test generating new addresses starting from an existing index.
	 *
	 * @covers ::generate_new_addresses_for_wallet
	 */
	public function test_generate_new_addresses_for_wallet_from_existing_index(): void {
		$xpub = 'xpub6CUGRUonZSQ4TWtTMmzXdrXDtypWKiKrhko4egpiMZbpiaQL2jkwSB1icqYh2cfDfVxdx4df189oLKnC5fSwqPfgyP3hooxujYzAu3fDVmz';

		$wallet = $this->make(
			Bitcoin_Wallet::class,
			array(
				'get_xpub'          => Expected::atLeastOnce( $xpub ),
				'get_address_index' => Expected::once( 5 ),
			)
		);

		$generate_address_api = $this->makeEmpty(
			Generate_Address_API_Interface::class,
			array(
				'generate_address' => Expected::exactly(
					2,
					function ( string $xpub_param, int $index ) use ( $xpub ): string {
						$this->assertEquals( $xpub, $xpub_param );
						$this->assertContains( $index, array( 6, 7 ) );
						return 'bc1qaddress' . $index;
					}
				),
			)
		);

		$bitcoin_address_1 = $this->make(
			Bitcoin_Address::class,
			array(
				'get_raw_address' => 'bc1qaddress6',
			)
		);

		$bitcoin_address_2 = $this->make(
			Bitcoin_Address::class,
			array(
				'get_raw_address' => 'bc1qaddress7',
			)
		);

		$bitcoin_address_repository = $this->makeEmpty(
			Bitcoin_Address_Repository::class,
			array(
				'get_post_id_for_address' => Expected::exactly( 2, null ),
				'save_new'                => Expected::exactly(
					2,
					function ( Bitcoin_Wallet $wallet, int $index, string $address ) use ( $bitcoin_address_1, $bitcoin_address_2 ): Bitcoin_Address {
						if ( 6 === $index ) {
							return $bitcoin_address_1;
						} else {
							return $bitcoin_address_2;
						}
					}
				),
			)
		);

		$bitcoin_wallet_repository = $this->makeEmpty(
			Bitcoin_Wallet_Repository::class,
			array(
				'set_highest_address_index' => Expected::once(
					function ( Bitcoin_Wallet $wallet, int $index ): void {
						$this->assertEquals( 7, $index );
					}
				),
			)
		);

		$sut = $this->get_sut(
			generate_address_api: $generate_address_api,
			bitcoin_wallet_repository: $bitcoin_wallet_repository,
			bitcoin_address_repository: $bitcoin_address_repository
		);

		$result = $sut->generate_new_addresses_for_wallet( $wallet );

		$this->assertCount( 2, $result->new_addresses );
		$this->assertEquals( 7, $result->address_index );
	}

	/**
	 * Test generating custom number of addresses.
	 *
	 * @covers ::generate_new_addresses_for_wallet
	 */
	public function test_generate_new_addresses_for_wallet_custom_count(): void {
		$xpub = 'xpub6CUGRUonZSQ4TWtTMmzXdrXDtypWKiKrhko4egpiMZbpiaQL2jkwSB1icqYh2cfDfVxdx4df189oLKnC5fSwqPfgyP3hooxujYzAu3fDVmz';

		$wallet = $this->make(
			Bitcoin_Wallet::class,
			array(
				'get_xpub'          => Expected::atLeastOnce( $xpub ),
				'get_address_index' => Expected::once( null ),
			)
		);

		$generate_address_api = $this->makeEmpty(
			Generate_Address_API_Interface::class,
			array(
				'generate_address' => Expected::exactly(
					5,
					function ( string $xpub_param, int $index ) use ( $xpub ): string {
						return 'bc1qaddress' . $index;
					}
				),
			)
		);

		$bitcoin_address_repository = $this->makeEmpty(
			Bitcoin_Address_Repository::class,
			array(
				'get_post_id_for_address' => Expected::exactly( 5, null ),
				'save_new'                => Expected::exactly(
					5,
					function ( Bitcoin_Wallet $wallet, int $index, string $address ): Bitcoin_Address {
						return $this->make(
							Bitcoin_Address::class,
							array(
								'get_raw_address' => $address,
							)
						);
					}
				),
			)
		);

		$bitcoin_wallet_repository = $this->makeEmpty(
			Bitcoin_Wallet_Repository::class,
			array(
				'set_highest_address_index' => Expected::once(
					function ( Bitcoin_Wallet $wallet, int $index ): void {
						$this->assertEquals( 4, $index );
					}
				),
			)
		);

		$sut = $this->get_sut(
			generate_address_api: $generate_address_api,
			bitcoin_wallet_repository: $bitcoin_wallet_repository,
			bitcoin_address_repository: $bitcoin_address_repository
		);

		$result = $sut->generate_new_addresses_for_wallet( $wallet, 5 );

		$this->assertCount( 5, $result->new_addresses );
		$this->assertEquals( 4, $result->address_index );
	}

	/**
	 * Test handling duplicate addresses - when an address already exists, it should skip and continue.
	 *
	 * @covers ::generate_new_addresses_for_wallet
	 */
	public function test_generate_new_addresses_for_wallet_with_duplicates(): void {
		$xpub = 'xpub6CUGRUonZSQ4TWtTMmzXdrXDtypWKiKrhko4egpiMZbpiaQL2jkwSB1icqYh2cfDfVxdx4df189oLKnC5fSwqPfgyP3hooxujYzAu3fDVmz';

		$wallet = $this->make(
			Bitcoin_Wallet::class,
			array(
				'get_xpub'          => Expected::atLeastOnce( $xpub ),
				'get_address_index' => Expected::once( null ),
			)
		);

		$call_count           = 0;
		$generate_address_api = $this->makeEmpty(
			Generate_Address_API_Interface::class,
			array(
				'generate_address' => function ( string $xpub_param, int $index ) use ( $xpub, &$call_count ): string {
					++$call_count;
					return 'bc1qaddress' . $index;
				},
			)
		);

		$bitcoin_address_1 = $this->make(
			Bitcoin_Address::class,
			array(
				'get_raw_address' => 'bc1qaddress1',
			)
		);

		$bitcoin_address_2 = $this->make(
			Bitcoin_Address::class,
			array(
				'get_raw_address' => 'bc1qaddress2',
			)
		);

		$address_check_count        = 0;
		$bitcoin_address_repository = $this->makeEmpty(
			Bitcoin_Address_Repository::class,
			array(
				'get_post_id_for_address' => function ( string $address ) use ( &$address_check_count ): ?int {
					++$address_check_count;
					// First address (index 0) already exists (return post_id 123).
					if ( 'bc1qaddress0' === $address ) {
						return 123;
					}
					// Other addresses don't exist.
					return null;
				},
				'save_new'                => Expected::exactly(
					2,
					function ( Bitcoin_Wallet $wallet, int $index, string $address ) use ( $bitcoin_address_1, $bitcoin_address_2 ): Bitcoin_Address {
						// Should save indices 1 and 2 (skipping 0).
						if ( 1 === $index ) {
							return $bitcoin_address_1;
						} else {
							return $bitcoin_address_2;
						}
					}
				),
			)
		);

		$set_index_call_count      = 0;
		$bitcoin_wallet_repository = $this->makeEmpty(
			Bitcoin_Wallet_Repository::class,
			array(
				'set_highest_address_index' => function ( Bitcoin_Wallet $wallet, int $index ) use ( &$set_index_call_count ): void {
					++$set_index_call_count;
					if ( 1 === $set_index_call_count ) {
						// First call should be when we find the duplicate at index 0.
						$this->assertEquals( 0, $index );
					} elseif ( 2 === $set_index_call_count ) {
						// Final call should be at index 2.
						$this->assertEquals( 2, $index );
					}
				},
			)
		);

		$sut = $this->get_sut(
			generate_address_api: $generate_address_api,
			bitcoin_wallet_repository: $bitcoin_wallet_repository,
			bitcoin_address_repository: $bitcoin_address_repository
		);

		$result = $sut->generate_new_addresses_for_wallet( $wallet, 2 );

		$this->assertCount( 2, $result->new_addresses );
		$this->assertEquals( 2, $result->address_index );
		$this->assertEquals( 3, $call_count ); // Should have tried to generate 3 addresses.
		$this->assertEquals( 2, $set_index_call_count ); // Should have set index twice.
	}

	/**
	 * Test generating a single address.
	 *
	 * @covers ::generate_new_addresses_for_wallet
	 */
	public function test_generate_new_addresses_for_wallet_single_address(): void {
		$xpub = 'xpub6CUGRUonZSQ4TWtTMmzXdrXDtypWKiKrhko4egpiMZbpiaQL2jkwSB1icqYh2cfDfVxdx4df189oLKnC5fSwqPfgyP3hooxujYzAu3fDVmz';

		$wallet = $this->make(
			Bitcoin_Wallet::class,
			array(
				'get_xpub'          => Expected::atLeastOnce( $xpub ),
				'get_address_index' => Expected::once( null ),
			)
		);

		$generate_address_api = $this->makeEmpty(
			Generate_Address_API_Interface::class,
			array(
				'generate_address' => Expected::once( 'bc1qaddress0' ),
			)
		);

		$bitcoin_address = $this->make(
			Bitcoin_Address::class,
			array(
				'get_raw_address' => 'bc1qaddress0',
			)
		);

		$bitcoin_address_repository = $this->makeEmpty(
			Bitcoin_Address_Repository::class,
			array(
				'get_post_id_for_address' => Expected::once( null ),
				'save_new'                => Expected::once( $bitcoin_address ),
			)
		);

		$bitcoin_wallet_repository = $this->makeEmpty(
			Bitcoin_Wallet_Repository::class,
			array(
				'set_highest_address_index' => Expected::once(
					function ( Bitcoin_Wallet $wallet, int $index ): void {
						$this->assertEquals( 0, $index );
					}
				),
			)
		);

		$sut = $this->get_sut(
			generate_address_api: $generate_address_api,
			bitcoin_wallet_repository: $bitcoin_wallet_repository,
			bitcoin_address_repository: $bitcoin_address_repository
		);

		$result = $sut->generate_new_addresses_for_wallet( $wallet, 1 );

		$this->assertCount( 1, $result->new_addresses );
		$this->assertEquals( 0, $result->address_index );
		$this->assertEquals( 'bc1qaddress0', $result->new_addresses[0]->get_raw_address() );
	}
}
