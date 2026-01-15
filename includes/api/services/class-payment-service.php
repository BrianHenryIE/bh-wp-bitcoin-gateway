<?php
/**
 * Checks the blockchain for transactions; calculates total amount received.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Services;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Blockchain_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\BH_WP_Bitcoin_Gateway_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Rate_Limit_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_VOut;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Update_Address_Transactions_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Transaction_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Results\Check_Address_For_Payment_Service_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Exception\MoneyMismatchException;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Exception\UnknownCurrencyException;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use DateInterval;
use DateTimeImmutable;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

class Payment_Service implements LoggerAwareInterface {
	use LoggerAwareTrait;

	/**
	 * Constructor
	 *
	 * @param Blockchain_API_Interface       $blockchain_api External API for querying the blockchain height and for new transactions.
	 * @param Bitcoin_Transaction_Repository $bitcoin_transaction_repository Used to save, retrieve and update transactions saved as WP_Posts.
	 * @param LoggerInterface                $logger A PSR logger for debug/error.
	 */
	public function __construct(
		protected Blockchain_API_Interface $blockchain_api,
		protected Bitcoin_Transaction_Repository $bitcoin_transaction_repository,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );
	}

	/**
	 * Check a Bitcoin address for payment and mark as paid if sufficient funds received.
	 *
	 * @param Bitcoin_Address $bitcoin_address The Bitcoin address to check.
	 */
	public function check_address_for_payment( Bitcoin_Address $bitcoin_address ): Check_Address_For_Payment_Service_Result {

		$update_address_transactions_result = $this->update_address_transactions( $bitcoin_address );

		$blockchain_height = $this->get_blockchain_height();

		$required_confirmations = 3; // TODO: get from global / address.

		$total_received = $this->get_address_confirmed_balance(
			raw_address: $bitcoin_address->get_raw_address(),
			blockchain_height: $blockchain_height,
			required_confirmations: $required_confirmations,
			transactions: $update_address_transactions_result->all_transactions
		);

		return new Check_Address_For_Payment_Service_Result(
			update_address_transactions_result: $update_address_transactions_result,
			blockchain_height: $blockchain_height,
			required_confirmations: $required_confirmations,
			total_received: $total_received,
		);
	}

	/**
	 * Fetch the current blockchain height from the API (no more than once every ten minutes).
	 *
	 * Needed to compare transactions' block heights to the number of required confirmations.
	 *
	 * TODO: The time of the block would be useful to know so as not to check again for ten minutes. For now, we are
	 * using the time we checked at.
	 *
	 * @throws Rate_Limit_Exception If the API in use returns a 429 response.
	 */
	protected function get_blockchain_height(): int {
		$option_name                  = 'bh_wp_bitcoin_gateway_blockchain_height';
		$saved_blockchain_height_json = get_option( $option_name );
		if ( is_string( $saved_blockchain_height_json ) ) {
			/** @var ?array{blockchain_height?:int, time?:string} $saved_blockchain_height_array */
			$saved_blockchain_height_array = json_decode( $saved_blockchain_height_json, true );
			if ( is_array( $saved_blockchain_height_array ) && isset( $saved_blockchain_height_array['time'], $saved_blockchain_height_array['blockchain_height'] ) ) {
				try {
					$saved_blockchain_height_date_time = new DateTimeImmutable( $saved_blockchain_height_array['time'] );
					$ten_minutes_ago                   = new DateTimeImmutable()->sub( new DateInterval( 'PT10M' ) );
					if ( $saved_blockchain_height_date_time > $ten_minutes_ago ) {
						return (int) $saved_blockchain_height_array['blockchain_height'];
					}
				} catch ( \Exception $e ) {
					// The stored time is invalid, so we'll fetch a new value.
					$this->logger->warning( 'Could not parse stored blockchain height time. Refetching.', array( 'error' => $e->getMessage() ) );
				}
			}
		}
		$latest_block_height = $this->blockchain_api->get_blockchain_height();

		update_option(
			$option_name,
			wp_json_encode(
				array(
					'blockchain_height' => $latest_block_height,
					'time'              => new DateTimeImmutable()->format( \DateTimeInterface::ATOM ),
				)
			)
		);

		return $latest_block_height;
	}

	/**
	 * Remotely check/fetch the latest data for an address.
	 *
	 * TODO: return an object show the initial and final state in a way the changes made can be logged. E.g. was it ever checked before?
	 *
	 * @param Bitcoin_Address $address The Bitcoin address to query the blockchain API for, retrieving all transactions where this address received funds.
	 *
	 * @throws Rate_Limit_Exception When the blockchain API returns HTTP 429, indicating too many requests and the service should back off until the reset time.
	 */
	public function update_address_transactions( Bitcoin_Address $address ): Update_Address_Transactions_Result {
		// TODO: sort by last updated.
		// TODO: retry on rate limit.

		/** @var array<int, Bitcoin_Transaction> $transactions_by_post_ids */
		$transactions_by_post_ids = array();

		/** @var array<int, string> $tx_ids_by_post_ids */
		$tx_ids_by_post_ids = array();

		$updated_transactions = $this->blockchain_api->get_transactions_received( btc_address: $address->get_raw_address() );

		foreach ( $updated_transactions as $transaction ) {
			$saved_transaction                                       = $this->bitcoin_transaction_repository->save_new(
				$transaction,
				$address
			);
			$tx_ids_by_post_ids[ $saved_transaction->get_post_id() ] = $saved_transaction->get_txid();
			$transactions_by_post_ids[ $saved_transaction->get_post_id() ] = $saved_transaction;
		}

		/**
		 * Save an array of post_id:tx_id to the address object for quick reference, e.g. before/after checks.
		 */
		$this->bitcoin_transaction_repository->associate_transactions_post_ids_to_address( $tx_ids_by_post_ids, $address );
		// TODO: refresh. make sure to record changes for the result object.
		// $address = $this->bitcoin_address_repository->refresh($address).

		// TODO: run a check on the address to see has the amount been paid, then  update the address status/state.

		// TODO: do_action on changes for logging.

		// TODO: Check are any previous transactions no longer present!!! (unlikely?).

		return new Update_Address_Transactions_Result(
			queried_address: $address,
			known_tx_ids_before: $address->get_tx_ids(),
			all_transactions: $transactions_by_post_ids,
		);

		// Throws when e.g. API is offline.
		// TODO: log, rate limit, notify.
	}

	/**
	 * From the received transactions, sum those who have enough confirmations.
	 *
	 * @param string                  $raw_address The raw Bitcoin address to calculate balance for.
	 * @param int                     $blockchain_height The current blockchain height. (TODO: explain why).
	 * @param int                     $required_confirmations A confirmation is a subsequent block mined after the transaction.
	 * @param Transaction_Interface[] $transactions Array of transactions to calculate balance from.
	 *
	 * @throws MoneyMismatchException If the calculations were somehow using two different currencies.
	 * @throws UnknownCurrencyException If `BTC` has not correctly been added to Money's currency list.
	 */
	public function get_address_confirmed_balance( string $raw_address, int $blockchain_height, int $required_confirmations, array $transactions ): Money {
		return array_reduce(
			$transactions,
			function ( Money $carry, Transaction_Interface $transaction ) use ( $raw_address, $blockchain_height, $required_confirmations ) {
				if ( ( $blockchain_height - $transaction->get_block_height() ) >= $required_confirmations ) {
					return $carry->plus( $this->get_value_for_transaction( $raw_address, $transaction ) );
				}
				return $carry;
			},
			Money::of( 0, 'BTC' )
		);
	}

	/**
	 * Sum the transaction's vector-outputs sent to a specific address.
	 *
	 * @param string                $to_address The Bitcoin address to calculate value for.
	 * @param Transaction_Interface $transaction The transaction to calculate value from.
	 */
	protected function get_value_for_transaction( string $to_address, Transaction_Interface $transaction ): Money {

		$value_including_fee = array_reduce(
			$transaction->get_v_out(),
			function ( Money $carry, Transaction_VOut $out ) use ( $to_address ) {
				if ( $to_address === $out->scriptpubkey_address ) {
					return $carry->plus( $out->value );
				}
				return $carry;
			},
			Money::of( 0, 'BTC' )
		);

		return $value_including_fee->dividedBy( 100_000_000 );
	}

	/**
	 * Return the previously saved transactions by their post_id.
	 *
	 * The Bitcoin_Address's wp_post has a meta key that holds an array of post ids for saved transactions.
	 *
	 * @see Addresses_List_Table::column_transactions_count() When displaying all addresses.
	 * @used-by API::get_saved_transactions() When displaying all addresses.
	 *
	 * @param int[] $transaction_post_ids A list of known post ids, presumably linked to an address.
	 *
	 * @return null|array<int, Bitcoin_Transaction> Post_id:transaction object; where null suggests there was nothing saved before, and an empty array suggests it has been checked but no transactions had been seen.
	 * @throws BH_WP_Bitcoin_Gateway_Exception When a stored transaction post ID cannot be converted to a Bitcoin_Transaction object.
	 */
	public function get_saved_transactions(
		array $transaction_post_ids,
	): ?array {

		$transaction_by_post_ids = array();
		foreach ( $transaction_post_ids as $transaction_post_id ) {
			$transaction_by_post_ids[ $transaction_post_id ] = $this->bitcoin_transaction_repository->get_by_post_id( $transaction_post_id );
		}

		return $transaction_by_post_ids;
	}
}
