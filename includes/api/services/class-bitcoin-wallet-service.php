<?php
/**
 * Save xpub as Wallet object, create new payment addresses for wallets, associate transactions with payment address.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Services;

use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Actions_Handler;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address_Status;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet_Status;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Helpers\Generate_Address_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Results\Addresses_Generation_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Exceptions\BH_WP_Bitcoin_Gateway_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Address_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Wallet_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Queries\WP_Posts_Query_Order;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Results\Get_Wallet_For_Xpub_Service_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Local functions to create addresses; query addresses; update addresses.
 */
class Bitcoin_Wallet_Service implements LoggerAwareInterface {
	use LoggerAwareTrait;

	/**
	 * Constructor
	 *
	 * @param Generate_Address_API_Interface $generate_address_api Local class to derive payment addresses from a wallet's master public key.
	 * @param Bitcoin_Wallet_Repository      $bitcoin_wallet_repository Used to save, retrieve and update wallets saved as WP_Posts.
	 * @param Bitcoin_Address_Repository     $bitcoin_address_repository Used to save, retrieve and update payment addresses saved as WP_Posts.
	 */
	public function __construct(
		protected Generate_Address_API_Interface $generate_address_api,
		protected Bitcoin_Wallet_Repository $bitcoin_wallet_repository,
		protected Bitcoin_Address_Repository $bitcoin_address_repository,
	) {
	}

	/**
	 * Get or create a Bitcoin_Wallet from a master public key. Optionally associate a gateway id with it.
	 *
	 * @param string                                              $xpub The master public key – xpub/ypub/zpub.
	 * @param ?array{integration:class-string, gateway_id:string} $gateway_details Optional gateway id to associate the wallet with.
	 * @throws BH_WP_Bitcoin_Gateway_Exception If a previous bug has saved two wp_posts for the same xpub.
	 */
	public function get_or_save_wallet_for_xpub( string $xpub, ?array $gateway_details = null ): Get_Wallet_For_Xpub_Service_Result {
		$existing_wallet = $this->bitcoin_wallet_repository->get_by_xpub( $xpub );

		if ( $existing_wallet ) {

			$has_gateway_recorded = function ( Bitcoin_Wallet $wallet, array $gateway_details ): bool {
				foreach ( $wallet->get_associated_gateways_details() as $saved_gateway_detail ) {
					if (
						$saved_gateway_detail['integration'] === $gateway_details['integration']
					&&
						$saved_gateway_detail['gateway_id'] === $gateway_details['gateway_id']
					) {
						return true;
					}
				}
				return false;
			};

			if ( $gateway_details && ! $has_gateway_recorded( $existing_wallet, $gateway_details ) ) {
				$this->bitcoin_wallet_repository->append_gateway_details( $existing_wallet, $gateway_details );
			}

			return new Get_Wallet_For_Xpub_Service_Result(
				xpub: $xpub,
				gateway_details: $gateway_details,
				wallet: $existing_wallet,
				is_new: false,
			);
		}

		// TODO: Validate xpub, throw exception.

		$new_wallet = $this->bitcoin_wallet_repository->save_new( $xpub, $gateway_details );

		return new Get_Wallet_For_Xpub_Service_Result(
			xpub: $xpub,
			gateway_details: $gateway_details,
			wallet: $new_wallet,
			is_new: true,
		);
	}

	/**
	 * Given a post_id, get the Bitcoin_Wallet.
	 *
	 * @used-by Background_Jobs_Actions_Handler::single_ensure_unused_addresses() To convert the job's args to objects.
	 *
	 * @param int $wallet_post_id The WordPress post id this wallet is stored under.
	 *
	 * @throws InvalidArgumentException When the supplied post_id is not a post of this type.
	 */
	public function get_wallet_by_wp_post_id( int $wallet_post_id ): Bitcoin_Wallet {
		return $this->bitcoin_wallet_repository->get_by_wp_post_id( $wallet_post_id );
	}

	/**
	 * Get all saved wallets.
	 *
	 * @return Bitcoin_Wallet[]
	 */
	public function get_all_wallets(): array {
		return $this->bitcoin_wallet_repository->get_all(
			status: Bitcoin_Wallet_Status::ALL
		);
	}

	/**
	 * If a wallet has fewer than 20 fresh addresses available, generate some more.
	 *
	 * @return Addresses_Generation_Result[]
	 * @see API_Interface::generate_new_addresses()
	 * @used-by CLI::generate_new_addresses()
	 * @used-by Background_Jobs_Actions_Handler::generate_new_addresses()
	 * @throws BH_WP_Bitcoin_Gateway_Exception When address derivation fails or addresses cannot be saved to the database.
	 */
	public function generate_new_addresses(): array {

		/**
		 * @var array<int, Addresses_Generation_Result> $results
		 */
		$results = array();

		$wallets = $this->bitcoin_wallet_repository->get_all( Bitcoin_Wallet_Status::ALL );

		foreach ( $wallets as $wallet ) {
			$results[] = $this->generate_new_addresses_for_wallet( $wallet );
		}

		return $results;
	}

	/**
	 * Derive new Bitcoin addresses for a saved wallet.
	 *
	 * @param Bitcoin_Wallet $wallet The wallet to generate child addresses from using its master public key and current address index.
	 * @param int            $generate_count The number of sequential addresses to derive from the wallet's next available derivation path index. 20 is the standard lookahead for wallets.
	 *
	 * @throws BH_WP_Bitcoin_Gateway_Exception When address derivation fails or addresses cannot be saved to the database.
	 */
	public function generate_new_addresses_for_wallet( Bitcoin_Wallet $wallet, int $generate_count = 2 ): Addresses_Generation_Result {

		// This will start the first address creation at index 0 and others at n+1.
		$prior_index   = $wallet->get_address_index();
		$address_index = $prior_index ?? -1;

		/** @var non-empty-array<Bitcoin_Address> $generated_addresses */
		$generated_addresses       = array();
		$generated_addresses_count = 0;

		/** @var array<Bitcoin_Address> $orphaned_addresses */
		$orphaned_addresses = array();

		do {
			++$address_index;

			$new_address_string = $this->generate_address_api->generate_address( $wallet->get_xpub(), $address_index );

			$existing_address_post_id = $this->bitcoin_address_repository->get_post_id_for_address( $new_address_string );
			if ( ! is_null( $existing_address_post_id ) ) {

				$existing_address = $this->bitcoin_address_repository->get_by_post_id( $existing_address_post_id );

				// The wallet was probably deleted and left orphaned saved addresses (likely to happen during testing).
				if ( $existing_address->get_wallet_parent_post_id() !== $wallet->get_post_id() ) {
					$this->bitcoin_address_repository->set_wallet_id( $existing_address, $wallet->get_post_id() );
					$orphaned_address     = $this->bitcoin_address_repository->refresh( $existing_address );
					$orphaned_addresses[] = $orphaned_address;
				}

				// Although inefficient to run this inside the loop, overall, searching past the known index could cause a PHP timeout.
				// (emphasizing that this should be run as a scheduled task).
				$this->bitcoin_wallet_repository->set_highest_address_index( $wallet, $address_index );
				continue;
			}

			$bitcoin_address = $this->bitcoin_address_repository->save_new_address(
				wallet: $wallet,
				derivation_path_sequence_index: $address_index,
				address: $new_address_string,
			);

			$generated_addresses[] = $bitcoin_address;

			++$generated_addresses_count;

		} while ( $generated_addresses_count < $generate_count );

		$this->bitcoin_wallet_repository->set_highest_address_index( $wallet, $address_index );

		return new Addresses_Generation_Result(
			wallet: $this->bitcoin_wallet_repository->refresh( $wallet ),
			new_addresses: $generated_addresses,
			orphaned_addresses: $orphaned_addresses,
			prior_address_index: $prior_index,
		);
	}

	/**
	 * @return Bitcoin_Address[]
	 */
	public function get_assigned_bitcoin_addresses(): array {
		return $this->bitcoin_address_repository->get_assigned_bitcoin_addresses();
	}

	/**
	 * Fetch the address from the datastore again. I.e. it is immutable.
	 *
	 * @param Bitcoin_Address $address Existing object, presumably updated elsewhere.
	 */
	public function refresh_address( Bitcoin_Address $address ): Bitcoin_Address {
		return $this->bitcoin_address_repository->refresh( $address );
	}

	/**
	 * Gets previously saved addresses which have at least once been checked and see to be unused.
	 *
	 * It may be the case that they have been used in the meantime.
	 *
	 * @param ?Bitcoin_Wallet $wallet Optional wallet to filter addresses by.
	 * @return Bitcoin_Address[]
	 */
	public function get_unused_bitcoin_addresses( ?Bitcoin_Wallet $wallet = null ): array {

		return $this->bitcoin_address_repository->get_unused_bitcoin_addresses( $wallet );
	}

	/**
	 * Local db query to check is there an unused address that has recently been verified as unused.
	 *
	 * We should be making remote calls to verify an address is unused regularly, both on cron and on customer activity,
	 * so this should generally return true, but can be used to schedule a background check.
	 *
	 * @param Bitcoin_Wallet $wallet The wallet we need a payment address for.
	 */
	public function has_unused_bitcoin_address( Bitcoin_Wallet $wallet ): bool {

		$unused_addresses = $this->bitcoin_address_repository->get_unused_bitcoin_addresses(
			wallet: $wallet,
			query_order: new WP_Posts_Query_Order(
				count: 1,
				order_by: 'post_modified',
				order_direction: 'DESC',
			)
		);

		if ( empty( $unused_addresses ) ) {
			return false;
		}

		$address = $unused_addresses[ array_key_first( $unused_addresses ) ];

		$now                    = new DateTimeImmutable();
		$ten_minutes_in_seconds = ( 10 * constant( 'MINUTE_IN_SECONDS' ) );
		$seconds_difference     = $now->getTimestamp() - $address->get_modified_time()->getTimestamp();

		return $seconds_difference < $ten_minutes_in_seconds;
	}

	/**
	 * Find all generated addresses that have never been checked for transactions.
	 *
	 * @return Bitcoin_Address[]
	 */
	public function get_unknown_bitcoin_addresses(): array {
		return $this->bitcoin_address_repository->get_unknown_bitcoin_addresses();
	}

	/**
	 * Update a payment address's status, e.g. once paid, set it to used.
	 *
	 * @param Bitcoin_Address        $address Address object to update.
	 * @param Bitcoin_Address_Status $status The new status to set.
	 */
	public function set_payment_address_status(
		Bitcoin_Address $address,
		Bitcoin_Address_Status $status
	): void {
		$this->bitcoin_address_repository->set_status(
			address: $address,
			status: $status,
		);
	}

	/**
	 * Associate the Bitcoin Address with an order's post_id, set the expected amount to be paid, change the status
	 * to "assigned".
	 *
	 * @see Bitcoin_Address_Status::ASSIGNED
	 *
	 * @param Bitcoin_Address     $address The Bitcoin payment address to link.
	 * @param string|class-string $integration_id The plugin that is using this address.
	 * @param int                 $order_id The post_id (e.g. WooCommerce order id) that transactions to this address represent payment for.
	 * @param Money               $btc_total The target amount to be paid, after which the order should be updated.
	 */
	public function assign_order_to_bitcoin_payment_address(
		Bitcoin_Address $address,
		string $integration_id,
		int $order_id,
		Money $btc_total
	): Bitcoin_Address {
		$this->bitcoin_address_repository->assign_to_order(
			address: $address,
			integration_id: $integration_id,
			order_id: $order_id,
			btc_total: $btc_total,
		);
		return $this->refresh_address( $address );
	}

	/**
	 * Check do we have at least 1 assigned address, i.e. an address waiting for transactions.
	 *
	 * Across all wallets.
	 *
	 * @used-by Background_Jobs_Actions_Handler::check_assigned_addresses_for_transactions()
	 */
	public function has_assigned_bitcoin_addresses(): bool {
		return $this->bitcoin_address_repository->has_assigned_bitcoin_addresses();
	}

	/**
	 * Fetch a previously saved Bitcoin_Address object from the repository. E.g. an order may know the address as a
	 * string but not its post_id.
	 *
	 * @param string $assigned_payment_address Derived Bitcoin payment address.
	 * @throws BH_WP_Bitcoin_Gateway_Exception If the address is not found.
	 */
	public function get_saved_address_by_bitcoin_payment_address( string $assigned_payment_address ): Bitcoin_Address {
		$post_id = $this->bitcoin_address_repository->get_post_id_for_address( $assigned_payment_address );
		if ( is_null( $post_id ) ) {
			throw new BH_WP_Bitcoin_Gateway_Exception( 'No saved payment address found for: ' . $assigned_payment_address );
		}
		return $this->bitcoin_address_repository->get_by_post_id( $post_id );
	}

	/**
	 * Update a payment addresses to link to its related transactions.
	 *
	 * @see Bitcoin_Address::get_tx_ids()
	 *
	 * @param Bitcoin_Address                   $address The Bitcoin address the transactions relate to.
	 * @param array<int, Transaction_Interface> $all_transactions The transactions, indexed by their post_ids.
	 */
	public function update_address_transactions_posts( Bitcoin_Address $address, array $all_transactions ): void {
		/**
		 * @var array<int,string> $existing_meta_transactions_post_ids
		 */
		$existing_meta_transactions_post_ids = $address->get_tx_ids();

		if ( empty( $existing_meta_transactions_post_ids ) ) {
			$existing_meta_transactions_post_ids = array();
		}

		$new_transactions_post_ids = array();

		foreach ( $all_transactions as $post_id => $transaction ) {
			if ( ! isset( $existing_meta_transactions_post_ids[ $post_id ] ) ) {
				$new_transactions_post_ids[ $post_id ] = $transaction->get_txid();
			}
		}

		$updated_transactions_post_ids = $existing_meta_transactions_post_ids + $new_transactions_post_ids;

		// Even if we have no changes, we want to update the object's modified timestamp.
		$this->bitcoin_address_repository->set_transactions_post_ids_to_address( $address, $updated_transactions_post_ids );
	}
}
