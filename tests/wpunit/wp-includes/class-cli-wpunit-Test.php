<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address_Query;
use Codeception\Stub\Expected;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Address_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Wallet;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes\CLI
 */
class CLI_WPUnit_Test extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * @covers ::check_transactions
	 */
	public function test_update_address_post_id(): void {

		$this->markTestIncomplete();

		$logger   = new ColorLogger();
		$settings = $this->makeEmpty( Settings_Interface::class );
		$api      = $this->makeEmpty(
			API_Interface::class,
			array( 'update_address' => Expected::once( array() ) )
		);

		$factory = new Bitcoin_Address_Repository();
		$wallet  = $this->makeEmpty( Bitcoin_Wallet::class );

		$post_id = (string) $factory->save_new(
			new Bitcoin_Address_Query(
				wallet_wp_post_parent_id: $wallet->get_post_id(),
				xpub: 'mockaddress',
				derivation_path_sequence_index: 0
			)
		);

		$sut = new CLI( $api, $settings, $logger );

		$sut->check_transactions( array( $post_id ), array() );
	}
}
