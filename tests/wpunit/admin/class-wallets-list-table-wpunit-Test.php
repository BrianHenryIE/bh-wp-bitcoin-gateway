<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Admin;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Wallet_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Factories\Bitcoin_Wallet_Factory;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes\Post_BH_Bitcoin_Wallet;
use lucatume\WPBrowser\TestCase\WPTestCase;
use WP_Post;
use WP_Post_Type;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Admin\Wallets_List_Table
 *
 * @phpstan-import-type Wallet_List_Table_Dependencies_Array from Wallets_List_Table
 */
class Wallets_List_Table_WPUnit_Test extends WPTestCase {

	/**
	 * The `$args` array used when constructing the Wallets_List_Table sut.
	 *
	 * @var array{screen:\WP_Screen}
	 */
	protected array $args;

	/**
	 * The sample WP_Post whose data we will "display".
	 *
	 * @var WP_Post
	 */
	protected WP_Post $post;

	/**
	 * @var Bitcoin_Wallet_Repository
	 */
	protected Bitcoin_Wallet_Repository $bitcoin_wallet_repository;

	/**
	 * @var API_Interface
	 */
	protected API_Interface $api;

	public function setUp(): void {
		parent::setUp();

		wp_set_current_user( 1 );

		$this->api = $this->makeEmpty( API_Interface::class );

		$bitcoin_wallet_factory          = new Bitcoin_Wallet_Factory();
		$this->bitcoin_wallet_repository = new Bitcoin_Wallet_Repository( $bitcoin_wallet_factory );

		$plugin_post_wallet_type = new Post_BH_Bitcoin_Wallet( $this->api, $this->bitcoin_wallet_repository );
		$plugin_post_wallet_type->register_wallet_post_type();

		$wallet = $this->bitcoin_wallet_repository->save_new( 'xpub1a2s3d4f5gabcdef' );

		$this->post = get_post( $wallet->get_post_id() );

		$screen            = \WP_Screen::get();
		$screen->post_type = 'bh-bitcoin-wallet';

		$this->args = array(
			'screen' => $screen,
		);
	}

	#[\Override]
	public function tearDown(): void {
		parent::tearDown();

		/** @var array<string, WP_Post_Type> $wp_post_types */
		global $wp_post_types;
		unset( $wp_post_types['bh-bitcoin-wallet'] );
	}

	/**
	 * Verify the constructor retrieves the dependencies from the post type object.
	 *
	 * The dependencies are registered via Post_BH_Bitcoin_Wallet::register_wallet_post_type()
	 * and should be accessible on the WP_Post_Type object.
	 *
	 * @see Post_BH_Bitcoin_Wallet::register_wallet_post_type()
	 *
	 * @covers ::__construct
	 */
	public function test_constructor_retrieves_dependencies_from_post_type(): void {

		/** @var WP_Post_Type&object{dependencies:Wallet_List_Table_Dependencies_Array} $post_type_object */
		$post_type_object = get_post_type_object( 'bh-bitcoin-wallet' );

		// The list table should be constructable and have the dependencies available.
		$sut = new Wallets_List_Table( $this->args );

		// Use reflection to verify the dependencies were set.
		$reflection = new \ReflectionClass( $sut );

		$api_property = $reflection->getProperty( 'api' );
		$api_value    = $api_property->getValue( $sut );
		$this->assertInstanceOf( API_Interface::class, $api_value );
		$this->assertSame( $post_type_object->dependencies['api'], $api_value );

		$bitcoin_wallet_repository_property = $reflection->getProperty( 'bitcoin_wallet_repository' );
		$bitcoin_wallet_repository_value    = $bitcoin_wallet_repository_property->getValue( $sut );
		$this->assertInstanceOf( Bitcoin_Wallet_Repository::class, $bitcoin_wallet_repository_value );
		$this->assertSame( $post_type_object->dependencies['bitcoin_wallet_repository'], $bitcoin_wallet_repository_value );
	}

	/**
	 * @covers ::column_status
	 */
	public function test_column_status(): void {

		$sut = new Wallets_List_Table( $this->args );

		ob_start();
		$sut->column_status( $this->post );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'inactive', $output );
	}
}
