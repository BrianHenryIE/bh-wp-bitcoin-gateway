<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes;

use BrianHenryIE\WP_Bitcoin_Gateway\Admin\Wallets_List_Table;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Wallet_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Factories\Bitcoin_Wallet_Factory;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use lucatume\WPBrowser\TestCase\WPTestCase;
use WP_Post_Type;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes\Post_BH_Bitcoin_Wallet
 *
 * @phpstan-import-type Wallet_List_Table_Dependencies_Array from Wallets_List_Table
 */
class Post_BH_Bitcoin_Wallet_WPUnit_Test extends WPTestCase {
	protected function setUp(): void {
		parent::setUp();

		/** @var array<string, WP_Post_Type> $wp_post_types */
		global $wp_post_types;
		unset( $wp_post_types['bh-bitcoin-wallet'] );
		unset( $wp_post_types['bh-bitcoin-address'] );
	}

	protected function get_sut(
		?API_Interface $api = null,
		?Bitcoin_Wallet_Repository $bitcoin_wallet_repository = null,
	): Post_BH_Bitcoin_Wallet {
		return new Post_BH_Bitcoin_Wallet(
			api: $api ?? $this->makeEmpty( API_Interface::class ),
			bitcoin_wallet_repository: $bitcoin_wallet_repository ?? new Bitcoin_Wallet_Repository( new Bitcoin_Wallet_Factory() ),
		);
	}

	/**
	 * @covers ::register_wallet_post_type
	 * @covers ::__construct
	 */
	public function test_wallet_inactive_status(): void {

		$sut = $this->get_sut();

		assert( ! post_type_exists( 'bh-bitcoin-wallet' ) );

		assert( ! in_array( 'inactive', get_available_post_statuses( 'bh-bitcoin-wallet' ), true ) );

		$sut->register_wallet_post_type();

		$this->assertContains( 'inactive', get_available_post_statuses( 'bh-bitcoin-wallet' ) );
	}

	/**
	 * Verify register_post_type is called with dependencies in args.
	 *
	 * @covers ::register_wallet_post_type
	 */
	public function test_register_wallet_post_type_passes_dependencies(): void {

		$api                       = $this->makeEmpty( API_Interface::class );
		$bitcoin_wallet_repository = new Bitcoin_Wallet_Repository( new Bitcoin_Wallet_Factory() );

		$sut = $this->get_sut( $api, $bitcoin_wallet_repository );

		$sut->register_wallet_post_type();

		/** @var WP_Post_Type&object{dependencies:Wallet_List_Table_Dependencies_Array} $post_type_object */
		$post_type_object = get_post_type_object( 'bh-bitcoin-wallet' );

		$this->assertNotNull( $post_type_object );
		$this->assertTrue( isset( $post_type_object->dependencies ) );
		$this->assertIsArray( $post_type_object->dependencies );

		$this->assertArrayHasKey( 'api', $post_type_object->dependencies );
		$this->assertSame( $api, $post_type_object->dependencies['api'] );

		$this->assertArrayHasKey( 'bitcoin_wallet_repository', $post_type_object->dependencies );
		$this->assertSame( $bitcoin_wallet_repository, $post_type_object->dependencies['bitcoin_wallet_repository'] );
	}
}
