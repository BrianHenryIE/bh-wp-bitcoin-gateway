<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Helpers\JsonMapper\JsonMapper_Helper;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Exceptions\BH_WP_Bitcoin_Gateway_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address_Status;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address_WP_Post_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Factories\Bitcoin_Address_Factory;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Factories\Bitcoin_Wallet_Factory;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use lucatume\WPBrowser\TestCase\WPTestCase;
use wpdb;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Address_Repository
 */
class Bitcoin_Address_Repository_WPUnit_Test extends WPTestCase {

	protected Bitcoin_Address_Repository $sut;
	protected Bitcoin_Wallet_Repository $wallet_repository;

	protected function setUp(): void {
		parent::setUp();

		$bitcoin_address_factory = new Bitcoin_Address_Factory( new JsonMapper_Helper()->build() );
		$this->sut               = new Bitcoin_Address_Repository( $bitcoin_address_factory );

		$bitcoin_wallet_factory  = new Bitcoin_Wallet_Factory();
		$this->wallet_repository = new Bitcoin_Wallet_Repository( $bitcoin_wallet_factory );
	}

	/**
	 * Test saving a new Bitcoin address and retrieving it by post_id.
	 *
	 * @covers ::save_new_address
	 * @covers ::get_by_post_id
	 */
	public function test_save_new_and_get_by_post_id(): void {
		$wallet = $this->wallet_repository->save_new( 'xpub_test_123' );

		$address_string   = 'bc1qtest123456';
		$derivation_index = 5;

		$saved_address = $this->sut->save_new_address( $wallet, $derivation_index, $address_string );

		$this->assertEquals( $address_string, $saved_address->get_raw_address() );
		$this->assertEquals( $derivation_index, $saved_address->get_derivation_path_sequence_number() );
		$this->assertEquals( Bitcoin_Address_Status::UNKNOWN, $saved_address->get_status() );
		$this->assertEquals( $wallet->get_post_id(), $saved_address->get_wallet_parent_post_id() );

		// Test get_by_post_id.
		$retrieved_address = $this->sut->get_by_post_id( $saved_address->get_post_id() );

		$this->assertEquals( $saved_address->get_post_id(), $retrieved_address->get_post_id() );
		$this->assertEquals( $address_string, $retrieved_address->get_raw_address() );
	}

	/**
	 * Test get_post_id_for_address returns the correct post_id for a saved address.
	 *
	 * @covers ::get_post_id_for_address
	 */
	public function test_get_post_id_for_address(): void {
		$wallet = $this->wallet_repository->save_new( 'xpub_test_456' );

		$address_string = 'bc1qtest789012';
		$saved_address  = $this->sut->save_new_address( $wallet, 0, $address_string );

		$post_id = $this->sut->get_post_id_for_address( $address_string );

		$this->assertEquals( $saved_address->get_post_id(), $post_id );
	}

	/**
	 * Test get_post_id_for_address returns null for non-existent address.
	 *
	 * @covers ::get_post_id_for_address
	 */
	public function test_get_post_id_for_address_not_found(): void {
		$post_id = $this->sut->get_post_id_for_address( 'bc1qnonexistent' );

		$this->assertNull( $post_id );
	}

	/**
	 * Test save_new throws exception when attempting to save duplicate address.
	 *
	 * @covers ::save_new_address
	 */
	public function test_save_new_throws_exception_for_duplicate(): void {
		$wallet = $this->wallet_repository->save_new( 'xpub_test_duplicate' );

		$address_string = 'bc1qduplicate';
		$this->sut->save_new_address( $wallet, 0, $address_string );

		$this->expectException( BH_WP_Bitcoin_Gateway_Exception::class );
		$this->expectExceptionMessage( 'Attempted to save a payment address that already exists' );

		$this->sut->save_new_address( $wallet, 1, $address_string );
	}

	/**
	 * Test refresh method reloads address from database.
	 *
	 * @covers ::refresh
	 */
	public function test_refresh(): void {
		$wallet  = $this->wallet_repository->save_new( 'xpub_test_refresh' );
		$address = $this->sut->save_new_address( $wallet, 0, 'bc1qrefresh' );

		// Change status directly in database.
		wp_update_post(
			array(
				'ID'          => $address->get_post_id(),
				'post_status' => Bitcoin_Address_Status::UNUSED->value,
			)
		);

		// Original object still has old status.
		$this->assertEquals( Bitcoin_Address_Status::UNKNOWN, $address->get_status() );

		// Refresh should get new status.
		$refreshed_address = $this->sut->refresh( $address );
		$this->assertEquals( Bitcoin_Address_Status::UNUSED, $refreshed_address->get_status() );
	}

	/**
	 * Test set_status updates the address status.
	 *
	 * @covers ::set_status
	 */
	public function test_set_status(): void {
		$wallet  = $this->wallet_repository->save_new( 'xpub_test_status' );
		$address = $this->sut->save_new_address( $wallet, 0, 'bc1qstatus' );

		$this->assertEquals( Bitcoin_Address_Status::UNKNOWN, $address->get_status() );

		$this->sut->set_status( $address, Bitcoin_Address_Status::UNUSED );

		$updated_address = $this->sut->get_by_post_id( $address->get_post_id() );
		$this->assertEquals( Bitcoin_Address_Status::UNUSED, $updated_address->get_status() );
	}

	/**
	 * Test assign_to_order updates address with order details.
	 *
	 * @covers ::assign_to_order
	 */
	public function test_assign_to_order(): void {
		$wallet  = $this->wallet_repository->save_new( 'xpub_test_assign' );
		$address = $this->sut->save_new_address( $wallet, 0, 'bc1qassign' );

		$order_id  = 123;
		$btc_total = Money::of( '0.001', 'BTC' );

		$this->sut->assign_to_order( $address, get_class( $this ), $order_id, $btc_total );

		$updated_address = $this->sut->get_by_post_id( $address->get_post_id() );

		$this->assertEquals( Bitcoin_Address_Status::ASSIGNED, $updated_address->get_status() );
		$this->assertEquals( $order_id, $updated_address->get_order_id() );
		$this->assertTrue( $btc_total->isEqualTo( $updated_address->get_target_amount() ) );
	}

	/**
	 * Test set_wallet_id updates the wallet parent post_id.
	 *
	 * @covers ::set_wallet_id
	 */
	public function test_set_wallet_id(): void {
		$wallet1 = $this->wallet_repository->save_new( 'xpub_test_wallet1' );
		$wallet2 = $this->wallet_repository->save_new( 'xpub_test_wallet2' );

		$address = $this->sut->save_new_address( $wallet1, 0, 'bc1qwalletid' );

		$this->assertEquals( $wallet1->get_post_id(), $address->get_wallet_parent_post_id() );

		$this->sut->set_wallet_id( $address, $wallet2->get_post_id() );

		$updated_address = $this->sut->get_by_post_id( $address->get_post_id() );
		$this->assertEquals( $wallet2->get_post_id(), $updated_address->get_wallet_parent_post_id() );
	}

	/**
	 * Test set_transactions_post_ids_to_address links transactions to address.
	 *
	 * @covers ::set_transactions_post_ids_to_address
	 */
	public function test_set_transactions_post_ids_to_address(): void {
		$wallet  = $this->wallet_repository->save_new( 'xpub_test_tx' );
		$address = $this->sut->save_new_address( $wallet, 0, 'bc1qtransactions' );

		$this->assertNull( $address->get_tx_ids() );

		$transactions_post_ids = array(
			101 => 'txid_abc123',
			102 => 'txid_def456',
		);

		$this->sut->set_transactions_post_ids_to_address( $address, $transactions_post_ids );

		$updated_address = $this->sut->get_by_post_id( $address->get_post_id() );
		$this->assertEquals( $transactions_post_ids, $updated_address->get_tx_ids() );
	}

	/**
	 * Test get_unused_bitcoin_addresses returns only unused addresses.
	 *
	 * @covers ::get_unused_bitcoin_addresses
	 */
	public function test_get_unused_bitcoin_addresses(): void {
		$wallet = $this->wallet_repository->save_new( 'xpub_test_unused' );

		// Create addresses with different statuses.
		$unused_address1 = $this->sut->save_new_address( $wallet, 0, 'bc1qunused1' );
		$this->sut->set_status( $unused_address1, Bitcoin_Address_Status::UNUSED );

		$unused_address2 = $this->sut->save_new_address( $wallet, 1, 'bc1qunused2' );
		$this->sut->set_status( $unused_address2, Bitcoin_Address_Status::UNUSED );

		$assigned_address = $this->sut->save_new_address( $wallet, 2, 'bc1qassigned_test' );
		$this->sut->set_status( $assigned_address, Bitcoin_Address_Status::ASSIGNED );

		$unknown_address = $this->sut->save_new_address( $wallet, 3, 'bc1qunknown_test' );

		$unused_addresses = $this->sut->get_unused_bitcoin_addresses();

		$unused_ids = array_map( fn( $addr ) => $addr->get_post_id(), $unused_addresses );
		$this->assertContains( $unused_address1->get_post_id(), $unused_ids );
		$this->assertContains( $unused_address2->get_post_id(), $unused_ids );

		// Verify the addresses we created have unused status.
		$unused1_retrieved = $this->sut->get_by_post_id( $unused_address1->get_post_id() );
		$unused2_retrieved = $this->sut->get_by_post_id( $unused_address2->get_post_id() );
		$this->assertEquals( Bitcoin_Address_Status::UNUSED, $unused1_retrieved->get_status() );
		$this->assertEquals( Bitcoin_Address_Status::UNUSED, $unused2_retrieved->get_status() );
	}

	/**
	 * Test get_unused_bitcoin_addresses with wallet filter.
	 *
	 * @covers ::get_unused_bitcoin_addresses
	 */
	public function test_get_unused_bitcoin_addresses_with_wallet_filter(): void {
		$wallet1 = $this->wallet_repository->save_new( 'xpub_test_wallet_filter1' );
		$wallet2 = $this->wallet_repository->save_new( 'xpub_test_wallet_filter2' );

		$unused_wallet1 = $this->sut->save_new_address( $wallet1, 0, 'bc1qunused_w1' );
		$this->sut->set_status( $unused_wallet1, Bitcoin_Address_Status::UNUSED );

		$unused_wallet2 = $this->sut->save_new_address( $wallet2, 0, 'bc1qunused_w2' );
		$this->sut->set_status( $unused_wallet2, Bitcoin_Address_Status::UNUSED );

		$unused_addresses_wallet1 = $this->sut->get_unused_bitcoin_addresses( $wallet1 );

		$unused_ids_w1 = array_map( fn( $addr ) => $addr->get_post_id(), $unused_addresses_wallet1 );
		$this->assertContains( $unused_wallet1->get_post_id(), $unused_ids_w1 );

		// Verify the address we created belongs to wallet1 and is unused.
		$unused_w1_retrieved = $this->sut->get_by_post_id( $unused_wallet1->get_post_id() );
		$this->assertEquals( $wallet1->get_post_id(), $unused_w1_retrieved->get_wallet_parent_post_id() );
		$this->assertEquals( Bitcoin_Address_Status::UNUSED, $unused_w1_retrieved->get_status() );
	}

	/**
	 * Test get_assigned_bitcoin_addresses returns only assigned addresses.
	 *
	 * @covers ::get_assigned_bitcoin_addresses
	 * @covers ::get_addresses_query
	 */
	public function test_get_assigned_bitcoin_addresses(): void {
		$wallet = $this->wallet_repository->save_new( 'xpub_test_assigned_list' );

		$assigned1 = $this->sut->save_new_address( $wallet, 0, 'bc1qassigned1' );
		$this->sut->set_status( $assigned1, Bitcoin_Address_Status::ASSIGNED );

		$assigned2 = $this->sut->save_new_address( $wallet, 1, 'bc1qassigned2' );
		$this->sut->set_status( $assigned2, Bitcoin_Address_Status::ASSIGNED );

		$unused = $this->sut->save_new_address( $wallet, 2, 'bc1qunused_test' );
		$this->sut->set_status( $unused, Bitcoin_Address_Status::UNUSED );

		$assigned_addresses = $this->sut->get_assigned_bitcoin_addresses();

		$this->assertGreaterThanOrEqual( 2, count( $assigned_addresses ) );
		$assigned_ids = array_map( fn( $addr ) => $addr->get_post_id(), $assigned_addresses );
		$this->assertContains( $assigned1->get_post_id(), $assigned_ids );
		$this->assertContains( $assigned2->get_post_id(), $assigned_ids );
	}

	/**
	 * Test has_assigned_bitcoin_addresses returns true when assigned addresses exist.
	 *
	 * @covers ::has_assigned_bitcoin_addresses
	 */
	public function test_has_assigned_bitcoin_addresses_true(): void {
		$wallet = $this->wallet_repository->save_new( 'xpub_test_has_assigned' );

		$address = $this->sut->save_new_address( $wallet, 0, 'bc1qhas_assigned' );
		$this->sut->set_status( $address, Bitcoin_Address_Status::ASSIGNED );

		$this->assertTrue( $this->sut->has_assigned_bitcoin_addresses() );
	}

	/**
	 * Test has_assigned_bitcoin_addresses returns false when no assigned addresses exist.
	 *
	 * @covers ::has_assigned_bitcoin_addresses
	 */
	public function test_has_assigned_bitcoin_addresses_false(): void {
		/**
		 * Clean up any existing assigned addresses from other tests.
		 *
		 * @var wpdb $wpdb
		 */
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM %i WHERE post_type = %s AND post_status = 'assigned'",
				$wpdb->posts,
				Bitcoin_Address_WP_Post_Interface::POST_TYPE,
			)
		);

		$this->assertFalse( $this->sut->has_assigned_bitcoin_addresses() );
	}

	/**
	 * Test get_unknown_bitcoin_addresses returns only unknown addresses.
	 *
	 * @covers ::get_unknown_bitcoin_addresses
	 */
	public function test_get_unknown_bitcoin_addresses(): void {
		$wallet = $this->wallet_repository->save_new( 'xpub_test_unknown_list' );

		$unknown1 = $this->sut->save_new_address( $wallet, 0, 'bc1qunknown1' );
		$unknown2 = $this->sut->save_new_address( $wallet, 1, 'bc1qunknown2' );

		$unused = $this->sut->save_new_address( $wallet, 2, 'bc1qunused_for_unknown' );
		$this->sut->set_status( $unused, Bitcoin_Address_Status::UNUSED );

		$this->wallet_repository->set_highest_address_index( $wallet, 2 );

		$unknown_addresses = $this->sut->get_unknown_bitcoin_addresses();

		$unknown_ids = array_map( fn( $addr ) => $addr->get_post_id(), $unknown_addresses );
		$this->assertContains( $unknown1->get_post_id(), $unknown_ids );
		$this->assertContains( $unknown2->get_post_id(), $unknown_ids );

		// Verify the addresses we created are actually unknown status.
		$unknown1_retrieved = $this->sut->get_by_post_id( $unknown1->get_post_id() );
		$unknown2_retrieved = $this->sut->get_by_post_id( $unknown2->get_post_id() );
		$this->assertEquals( Bitcoin_Address_Status::UNKNOWN, $unknown1_retrieved->get_status() );
		$this->assertEquals( Bitcoin_Address_Status::UNKNOWN, $unknown2_retrieved->get_status() );
	}

	/**
	 * Test get_addresses can retrieve addresses by wallet.
	 *
	 * @covers ::get_addresses
	 */
	public function test_get_addresses_no_filters(): void {
		$wallet = $this->wallet_repository->save_new( 'xpub_test_get_all' );

		$address1 = $this->sut->save_new_address( $wallet, 0, 'bc1qgetall1' );
		$address2 = $this->sut->save_new_address( $wallet, 1, 'bc1qgetall2' );

		$result = $this->sut->get_addresses();

		// Verify addresses were created and can be retrieved individually.
		$retrieved1 = $result[0];
		$retrieved2 = $result[1];
		$this->assertEquals( $address1->get_raw_address(), $retrieved1->get_raw_address() );
		$this->assertEquals( $address2->get_raw_address(), $retrieved2->get_raw_address() );
		$this->assertEquals( $wallet->get_post_id(), $retrieved1->get_wallet_parent_post_id() );
		$this->assertEquals( $wallet->get_post_id(), $retrieved2->get_wallet_parent_post_id() );
	}

	/**
	 * Test addresses belong to correct wallets.
	 *
	 * @covers ::get_addresses
	 */
	public function test_get_addresses_with_wallet_filter(): void {
		$wallet1 = $this->wallet_repository->save_new( 'xpub_test_filter_wallet1' );
		$wallet2 = $this->wallet_repository->save_new( 'xpub_test_filter_wallet2' );

		$this->sut->save_new_address( $wallet1, 0, 'bc1qfilter_w1_addr' );
		$this->sut->save_new_address( $wallet2, 0, 'bc1qfilter_w2_addr' );

		$result = $this->sut->get_addresses( wallet: $wallet1 );

		$this->assertCount( 1, $result );

		// Verify addresses were created and belong to correct wallets.
		$address_w1_retrieved = $result[0];
		$this->assertEquals( $wallet1->get_post_id(), $address_w1_retrieved->get_wallet_parent_post_id() );
	}

	/**
	 * Test get_addresses with status filter.
	 *
	 * @covers ::get_addresses
	 */
	public function test_get_addresses_with_status_filter(): void {
		$wallet = $this->wallet_repository->save_new( 'xpub_test_filter_status' );

		$unused = $this->sut->save_new_address( $wallet, 0, 'bc1qfilter_unused_addr' );
		$this->sut->set_status( $unused, Bitcoin_Address_Status::UNUSED );

		$unknown = $this->sut->save_new_address( $wallet, 1, 'bc1qfilter_unknown_addr' );

		// Filter by wallet and status to avoid pollution.
		$unused_addresses = $this->sut->get_addresses( $wallet, Bitcoin_Address_Status::UNUSED );

		$unused_ids = array_map( fn( $addr ) => $addr->get_post_id(), $unused_addresses );
		$this->assertContains( $unused->get_post_id(), $unused_ids );

		// Verify the address we created is unused status.
		$unused_retrieved = $this->sut->get_by_post_id( $unused->get_post_id() );
		$this->assertEquals( Bitcoin_Address_Status::UNUSED, $unused_retrieved->get_status() );
	}

	/**
	 * Test get_addresses with both wallet and status filters.
	 *
	 * @covers ::get_addresses
	 */
	public function test_get_addresses_with_wallet_and_status_filters(): void {
		$wallet1 = $this->wallet_repository->save_new( 'xpub_test_both_filters1' );
		$wallet2 = $this->wallet_repository->save_new( 'xpub_test_both_filters2' );

		$unused_w1 = $this->sut->save_new_address( $wallet1, 0, 'bc1qboth_unused_w1_addr' );
		$this->sut->set_status( $unused_w1, Bitcoin_Address_Status::UNUSED );

		$unknown_w1 = $this->sut->save_new_address( $wallet1, 1, 'bc1qboth_unknown_w1_addr' );

		$unused_w2 = $this->sut->save_new_address( $wallet2, 0, 'bc1qboth_unused_w2_addr' );
		$this->sut->set_status( $unused_w2, Bitcoin_Address_Status::UNUSED );

		$addresses = $this->sut->get_addresses( $wallet1, Bitcoin_Address_Status::UNUSED );

		$address_ids = array_map( fn( $addr ) => $addr->get_post_id(), $addresses );
		$this->assertContains( $unused_w1->get_post_id(), $address_ids );

		// Verify the address we created matches both filters.
		$unused_w1_retrieved = $this->sut->get_by_post_id( $unused_w1->get_post_id() );
		$this->assertEquals( $wallet1->get_post_id(), $unused_w1_retrieved->get_wallet_parent_post_id() );
		$this->assertEquals( Bitcoin_Address_Status::UNUSED, $unused_w1_retrieved->get_status() );
	}
}
