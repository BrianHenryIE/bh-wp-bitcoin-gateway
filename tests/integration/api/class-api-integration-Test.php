<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Factories\Bitcoin_Address_Factory;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Queries\Bitcoin_Address_Query;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Address_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address_Status;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Wallet_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use lucatume\WPBrowser\TestCase\WPTestCase;

/**
 * @coversNothing
 */
class API_Integration_Test extends WPTestCase {

	public function test_update_address(): void {

		$this->markTestIncomplete();

		$test_wallet_address = 'bc1qkj5texg9utllnqknt9uggfa2jlgmlrs7hzrmu9';

		$logger   = new ColorLogger();
		$settings = $this->makeEmpty( Settings_Interface::class );

		$bitcoin_wallet_repository  = $this->makeEmpty( Bitcoin_Wallet_Repository::class );
		$bitcoin_address_repository = $this->makeEmpty( Bitcoin_Address_Repository::class );

		$sut = new API( $settings, $logger, $bitcoin_wallet_repository, $bitcoin_address_repository );

		$bitcoin_address_factory    = new Bitcoin_Address_Factory();
		$bitcoin_address_repository = new Bitcoin_Address_Repository( $bitcoin_address_factory );
		$post_id                    = $bitcoin_address_repository->save_new(
			new Bitcoin_Address_Query(
				wallet_wp_post_parent_id: $wallet->get_post_id(),
				status: Bitcoin_Address_Status::UNKNOWN,
				xpub: $test_wallet_address,
				derivation_path_sequence_index: $address_index,
			)
		);
		$bitcoin_address            = new Bitcoin_Address( $post_id );

		$result = $sut->update_address_transactions( $bitcoin_address );
	}
}
