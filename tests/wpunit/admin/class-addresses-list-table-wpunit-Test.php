<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Admin;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Factories\Bitcoin_Address_Factory;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Queries\Bitcoin_Address_Query;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Address_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Factories\Bitcoin_Wallet_Factory;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Wallet_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\API_WooCommerce_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Bitcoin_Gateway;
use BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes\Post_BH_Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes\Post_BH_Bitcoin_Wallet;
use WP_Post;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Admin\Addresses_List_Table
 */
class Addresses_List_Table_WPUnit_Test extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * The `$args` array used when constructing the Addresses_List_Table sut.
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

	public function setUp(): void {
		parent::setUp();

		wp_set_current_user( 1 );

		$bitcoin_gateway = null;

		$api = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'get_bitcoin_gateways' => array( &$bitcoin_gateway ),
			)
		);

		$bitcoin_gateway                   = new Bitcoin_Gateway( $api );
		$bitcoin_gateway->settings['xpub'] = 'xpub1a2s3d4f5gabcdef';

		\WC_Payment_Gateways::instance()->payment_gateways['bitcoin_gateway'] = $bitcoin_gateway;

		// Hopefully this is reset between tests?
		$plugin_post_address_type = new Post_BH_Bitcoin_Address( $api );
		$plugin_post_address_type->register_address_post_type();
		$plugin_post_wallet_type = new Post_BH_Bitcoin_Wallet( $api );
		$plugin_post_wallet_type->register_wallet_post_type();

		$address       = 'bc1qnlz39q0r40xnv200s9wjutj0fdxex6x8abcdef';
		$address_index = 22;

		$bitcoin_wallet_factory    = new Bitcoin_Wallet_Factory();
		$bitcoin_wallet_repository = new Bitcoin_Wallet_Repository( $bitcoin_wallet_factory );
		$wallet                    = $bitcoin_wallet_repository->save_new( 'xpub1a2s3d4f5gabcdef' );

		$bitcoin_address_factory    = new Bitcoin_Address_Factory();
		$bitcoin_address_repository = new Bitcoin_Address_Repository( $bitcoin_address_factory );

		$bitcoin_address = $bitcoin_address_repository->save_new(
			wallet: $wallet,
			derivation_path_sequence_index: $address_index,
			address: $address,
		);
		$address_post_id = $bitcoin_address->get_post_id();

		$this->post = get_post( $address_post_id );

		$screen            = \WP_Screen::get();
		$screen->post_type = 'bh-bitcoin-address'; // Which has not been registered in unit tests.

		$this->args = array(
			'screen' => $screen,
		);
	}

	public function tearDown(): void {
		parent::tearDown();

		unset( \WC_Payment_Gateways::instance()->payment_gateways['bitcoin_gateway'] );
	}

	/**
	 * Column title should be replaced with external link and target set on it.
	 *
	 * @covers ::column_title
	 */
	public function test_column_title(): void {

		$sut = new Addresses_List_Table( $this->args );

		ob_start();
		$sut->column_title( $this->post );
		$result = ob_get_clean();

		$this->assertStringContainsString( '"https://www.blockchain.com/btc/address/bc1qnlz39q0r40xnv200s9wjutj0fdxex6x8abcdef"', $result );
		$this->assertStringContainsString( 'target="_blank"', $result );
	}

	/**
	 * @covers ::column_wallet
	 */
	public function test_column_wallet(): void {

		$sut = new Addresses_List_Table( $this->args );

		ob_start();
		$sut->column_wallet( $this->post );
		$result = ob_get_clean();

		$this->assertStringContainsString( 'xpub1a2...def', $result );
		$this->assertStringContainsString( 'admin.php?page=wc-settings&#038;tab=checkout&#038;section=bitcoin_gateway', $result );
	}

	/**
	 * @covers ::column_derive_path_sequence
	 */
	public function test_column_derive_path_sequence(): void {

		$sut = new Addresses_List_Table( $this->args );

		ob_start();
		$sut->column_derive_path_sequence( $this->post );
		$result = ob_get_clean();

		$this->assertStringContainsString( '0/22', $result );
	}
}
