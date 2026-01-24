<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Exceptions\BH_WP_Bitcoin_Gateway_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet_Status;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet_WP_Post_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Factories\Bitcoin_Wallet_Factory;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\WooCommerce_Integration;
use InvalidArgumentException;
use lucatume\WPBrowser\TestCase\WPTestCase;
use wpdb;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Wallet_Repository
 */
class Bitcoin_Wallet_Repository_WPUnit_Test extends WPTestCase {

	protected Bitcoin_Wallet_Repository $sut;

	protected function setUp(): void {
		parent::setUp();

		$bitcoin_wallet_factory = new Bitcoin_Wallet_Factory();
		$this->sut              = new Bitcoin_Wallet_Repository( $bitcoin_wallet_factory );
	}

	/**
	 * Test saving a new Bitcoin wallet and retrieving it by xpub.
	 *
	 * @covers ::save_new
	 * @covers ::get_by_xpub
	 * @covers ::get_post_id_for_master_public_key
	 */
	public function test_save_new_and_get_by_xpub(): void {
		$xpub = 'xpub6CUGRUonZSQ4TWtTMmzXdrXDtypWKiKrhko4egpiMZbpiaQL2jkwSB1icqYh2cfDfVxdx4df189oLKnC5fSwqPfgyP3hooxujYzAu3fDVmz';

		$saved_wallet = $this->sut->save_new( $xpub );

		$this->assertEquals( $xpub, $saved_wallet->get_xpub() );
		$this->assertEquals( Bitcoin_Wallet_Status::INACTIVE, $saved_wallet->get_status() );
		$this->assertNull( $saved_wallet->get_address_index() );
		$this->assertNull( $saved_wallet->get_balance() );

		// Test get_by_xpub.
		$retrieved_wallet = $this->sut->get_by_xpub( $xpub );

		$this->assertNotNull( $retrieved_wallet );
		$this->assertEquals( $saved_wallet->get_post_id(), $retrieved_wallet->get_post_id() );
		$this->assertEquals( $xpub, $retrieved_wallet->get_xpub() );
	}

	/**
	 * Test saving a new Bitcoin wallet with a gateway_id makes it active.
	 *
	 * @covers ::save_new
	 */
	public function test_save_new_with_gateway_id_sets_active_status(): void {
		$xpub       = 'xpub6BosfCnifzxcFwrSzQiqu2DBVTshkCXacvNsWGYJVVhhawA7d4R5WSWGFNbi8Aw6ZRc1brxMyWMzG3DSSSSoekkudhUd9yLb6qx39T9nMdj';
		$gateway_id = 'bh_bitcoin_gateway';

		$gateway = array(
			'integration' => WooCommerce_Integration::class,
			'gateway_id'  => $gateway_id,
		);

		$saved_wallet = $this->sut->save_new( $xpub, $gateway );

		$this->assertEquals( Bitcoin_Wallet_Status::ACTIVE, $saved_wallet->get_status() );
	}

	/**
	 * Test save_new returns existing wallet if it already exists.
	 *
	 * @covers ::save_new
	 * @covers ::get_by_xpub
	 */
	public function test_save_new_returns_existing_wallet(): void {
		$xpub = 'xpub6ASuArnXKPbfEwhqN6e3mwBcDTgzisQN1wXN9BJcM47sSikHjJf3UFHKkNAWbWMiGj7Wf5uMash7SyYq527Hqck2AxYysAA7xmALppuCkwQ';

		$first_wallet  = $this->sut->save_new( $xpub );
		$second_wallet = $this->sut->save_new( $xpub );

		$this->assertEquals( $first_wallet->get_post_id(), $second_wallet->get_post_id() );
	}

	/**
	 * Test get_by_xpub returns null for non-existent wallet.
	 *
	 * @covers ::get_by_xpub
	 * @covers ::get_post_id_for_master_public_key
	 */
	public function test_get_by_xpub_returns_null_for_nonexistent(): void {
		$result = $this->sut->get_by_xpub( 'xpub_nonexistent' );

		$this->assertNull( $result );
	}

	/**
	 * Test get_post_id_for_master_public_key uses cache.
	 *
	 * @covers ::get_post_id_for_master_public_key
	 * @covers ::get_by_xpub
	 */
	public function test_get_post_id_for_master_public_key_uses_cache(): void {
		$xpub = 'xpub6D4BDPcP2GT577Vvch3R8wDkScZWzQzMMUm3PWbmWvVJrZwQY4VUNgqFJPMM3No2dFDFGTsxxpG5uJh7n7epu4trkrX7x7DogT5Uv6fcLW5';

		$wallet = $this->sut->save_new( $xpub );

		// Clear any existing cache.
		wp_cache_delete( $xpub, Bitcoin_Wallet_WP_Post_Interface::POST_TYPE );

		// First call should cache the result.
		$first_result = $this->sut->get_by_xpub( $xpub );

		// Verify the cache was set.
		$cached_value = wp_cache_get( $xpub, Bitcoin_Wallet_WP_Post_Interface::POST_TYPE );
		$this->assertEquals( $wallet->get_post_id(), $cached_value );

		// Clear cache and verify we still get the correct wallet from the database.
		wp_cache_delete( $xpub, Bitcoin_Wallet_WP_Post_Interface::POST_TYPE );
		$uncached_result = $this->sut->get_by_xpub( $xpub );
		$this->assertEquals( $wallet->get_post_id(), $uncached_result?->get_post_id() );
	}

	/**
	 * Test get_post_id_for_master_public_key throws exception for duplicate wallets.
	 *
	 * @covers ::get_post_id_for_master_public_key
	 * @covers ::get_by_xpub
	 */
	public function test_get_post_id_for_master_public_key_throws_exception_for_duplicates(): void {
		$xpub = 'xpub6FHa3pjLCk84BayeJxFW2SP4XRrFd1JYnxeLeU8EqN3vDfZmbqBqaGJAyiLjTAwm6ZLRQUMv1ZACTj37sR62cfN7fe5JnJ7dh8zL4fiyLHV';

		// Create first wallet.
		$this->sut->save_new( $xpub );

		// Manually create a duplicate post with same post_name.
		/** @var wpdb $wpdb */
		global $wpdb;
		$wpdb->insert(
			$wpdb->posts,
			array(
				'post_title'  => $xpub,
				'post_name'   => sanitize_title( $xpub ),
				'post_type'   => Bitcoin_Wallet_WP_Post_Interface::POST_TYPE,
				'post_status' => Bitcoin_Wallet_Status::INACTIVE->value,
			)
		);

		// Clear cache to force database query.
		wp_cache_delete( $xpub, Bitcoin_Wallet_WP_Post_Interface::POST_TYPE );

		$this->expectException( BH_WP_Bitcoin_Gateway_Exception::class );
		$this->expectExceptionMessage( '2 Bitcoin_Wallets found, only one expected, for ' . $xpub );

		$this->sut->get_by_xpub( $xpub );
	}

	/**
	 * Test get_by_wp_post_id retrieves wallet by post ID.
	 *
	 * @covers ::get_by_wp_post_id
	 */
	public function test_get_by_wp_post_id(): void {
		$xpub   = 'xpub6ERApfZwUNrhLCkDtcHTcxd75RbzS1ed54G1LkBUHQVHQKqhMkhgbmJbZRkrgZw4koxb5JaHWkY4ALHY2grBGRjaDMzQLcgJvLJuZZvRcEL';
		$wallet = $this->sut->save_new( $xpub );

		$retrieved_wallet = $this->sut->get_by_wp_post_id( $wallet->get_post_id() );

		$this->assertEquals( $wallet->get_post_id(), $retrieved_wallet->get_post_id() );
		$this->assertEquals( $xpub, $retrieved_wallet->get_xpub() );
	}

	/**
	 * Test get_by_wp_post_id throws exception for invalid post ID.
	 *
	 * @covers ::get_by_wp_post_id
	 */
	public function test_get_by_wp_post_id_throws_exception_for_invalid_id(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'is not a bh-bitcoin-wallet post object' );

		$this->sut->get_by_wp_post_id( 999999 );
	}

	/**
	 * Test get_all returns wallets filtered by status.
	 *
	 * @covers ::get_all
	 */
	public function test_get_all_active_wallets(): void {

		$gateway = array(
			'integration' => WooCommerce_Integration::class,
			'gateway_id'  => 'gateway_id',
		);

		// Create active wallet.
		$active_xpub = 'xpub6H1LXWLaKsWFhvm6RVpEL9P4KfRZSW7abD2ttkWP3SSQvnyA8FSVqNTEcYFgJS2UaFcxupHiYkro49S8yGasTvXEYBVPamhGW6cFJodrTHy';
		$this->sut->save_new( $active_xpub, $gateway );

		// Create inactive wallet.
		$inactive_xpub = 'xpub6FnCn6nSzZAw5Tw7cgR9bi15UV96gLZhjDstkXXxvCLsUXBGXPdSnLFbdpq8p9HmGsApME5hQTZ3emM2rnY5agb9rXpVGyy3bdW6EEgAtqt';
		$this->sut->save_new( $inactive_xpub );

		$active_wallets = $this->sut->get_all( Bitcoin_Wallet_Status::ACTIVE );

		$this->assertGreaterThanOrEqual( 1, count( $active_wallets ) );

		$active_xpubs = array_map( fn( $wallet ) => $wallet->get_xpub(), $active_wallets );
		$this->assertContains( $active_xpub, $active_xpubs );
	}

	/**
	 * Test get_all returns inactive wallets.
	 *
	 * @covers ::get_all
	 */
	public function test_get_all_inactive_wallets(): void {
		$inactive_xpub = 'xpub6ASAVgeehLbnwdqV6UKMHVzgqAG8Gr6riv3Fxxpj8ksbH9ebxaEyBLZ85ySDhKiLDBrQSARLq1uNRts8RuJiHjaDMBU4Zn9h8LZNnBC5y4a';
		$this->sut->save_new( $inactive_xpub );

		$inactive_wallets = $this->sut->get_all( Bitcoin_Wallet_Status::INACTIVE );

		$this->assertGreaterThanOrEqual( 1, count( $inactive_wallets ) );

		$inactive_xpubs = array_map( fn( $wallet ) => $wallet->get_xpub(), $inactive_wallets );
		$this->assertContains( $inactive_xpub, $inactive_xpubs );
	}

	/**
	 * Test set_highest_address_index updates the address index.
	 *
	 * @covers ::set_highest_address_index
	 */
	public function test_set_highest_address_index(): void {
		$xpub   = 'xpub6FHaFy1DnX5KNXVTz6LviYKzSMq1yzQzJCRgNjULaWrm5a9HH8NtXNZJvZAkm7L8bz8PGdR3EfRqGRyZLFz9xGVJFfKNvjY5fBqQk8XpXcP';
		$wallet = $this->sut->save_new( $xpub );

		$this->assertNull( $wallet->get_address_index() );

		$this->sut->set_highest_address_index( $wallet, 42 );

		$updated_wallet = $this->sut->get_by_wp_post_id( $wallet->get_post_id() );
		$this->assertEquals( 42, $updated_wallet->get_address_index() );
	}

	/**
	 * Test refresh reloads wallet from database.
	 *
	 * @covers ::refresh
	 */
	public function test_refresh(): void {
		$xpub   = 'xpub6ASuArnXKPbfEVRpCesNx4V35cYN3KUZhZW3eCjkHvJDEqmDZjYqCgqJtaQrQsZz3Z1p2J1JhWiNqCXqVCJnwMvQp9uYJB1HxYbQGfW7VJc';
		$wallet = $this->sut->save_new( $xpub );

		// Update the wallet's status directly in the database.
		wp_update_post(
			array(
				'ID'          => $wallet->get_post_id(),
				'post_status' => Bitcoin_Wallet_Status::ACTIVE->value,
			)
		);

		// Original object still has old status.
		$this->assertEquals( Bitcoin_Wallet_Status::INACTIVE, $wallet->get_status() );

		// Refresh should get new status.
		$refreshed_wallet = $this->sut->refresh( $wallet );
		$this->assertEquals( Bitcoin_Wallet_Status::ACTIVE, $refreshed_wallet->get_status() );
	}
}
