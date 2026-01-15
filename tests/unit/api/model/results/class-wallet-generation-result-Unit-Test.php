<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Results;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Wallet;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Results\Get_Wallet_For_Xpub_Service_Result;

class Wallet_Generation_Result_Unit_Test extends \Codeception\Test\Unit {

	/**
	 * Testing to see if array destructuring for parent constructor works well.
	 */
	public function test_construct(): void {

		$get_wallet_for_xpub_service_result = new Get_Wallet_For_Xpub_Service_Result(
			xpub: 'xpub1234',
			gateway_id:null,
			wallet: $this->make( Bitcoin_Wallet::class ),
			is_new: false
		);

		$result = new Wallet_Generation_Result(
			get_wallet_for_xpub_service_result: $get_wallet_for_xpub_service_result,
			did_schedule_ensure_addresses: false,
		);

		$this->assertEquals( 'xpub1234', $result->xpub );
	}
}
