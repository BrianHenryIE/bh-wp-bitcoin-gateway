<?php
/**
 * Test general behaviour of repository classes.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Helpers\JsonMapper\JsonMapper_Helper;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address_Status;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Factories\Bitcoin_Address_Factory;
use lucatume\WPBrowser\TestCase\WPTestCase;
use WP_Post;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\WP_Post_Repository_Abstract
 */
class WP_Post_Repository_WP_Unit_Test extends WPTestCase {

	/**
	 * When using `update_post_meta()` the last modified time of the post does not change. This
	 * is a test to see will it update if we use `update_post( array( 'meta_input' => array() )` instead.
	 *
	 * @covers ::update
	 */
	public function test_last_modified_time_is_updated(): void {

		$bitcoin_address_factory    = new Bitcoin_Address_Factory(
			json_mapper: new JsonMapper_Helper()->build(),
			logger: new ColorLogger()
		);
		$bitcoin_address_repository = new Bitcoin_Address_Repository( $bitcoin_address_factory );

		$wallet = $this->makeEmpty( Bitcoin_Wallet::class );

		$bitcoin_address = $bitcoin_address_repository->save_new_address(
			wallet:             $wallet,
			derivation_path_sequence_index: 2,
			address: 'address',
		);

		/** @var WP_Post $bitcoin_address_post */
		$bitcoin_address_post = get_post( $bitcoin_address->get_post_id() );

		$last_modified_time_before = $bitcoin_address_post->post_modified_gmt;

		$bitcoin_address_object = $bitcoin_address_repository->get_by_post_id( $bitcoin_address->get_post_id() );

		sleep( 1 );

		$bitcoin_address_repository->set_status( $bitcoin_address_object, Bitcoin_Address_Status::USED );

		/** @var WP_Post $bitcoin_address_post */
		$bitcoin_address_post = get_post( $bitcoin_address->get_post_id() );

		$last_modified_time_after = $bitcoin_address_post->post_modified_gmt;

		$this->assertNotEquals( $last_modified_time_before, $last_modified_time_after );
	}
}
