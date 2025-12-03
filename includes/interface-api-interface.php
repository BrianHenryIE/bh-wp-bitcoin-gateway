<?php
/**
 * The core plugin settings that may preferably be set by supplying another instance conforming to this interface.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Addresses_Generation_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Check_Assigned_Addresses_For_Transactions_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet_Generation_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Currency;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Wallet;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Bitcoin_Gateway;
use WC_Order;

/**
 * Methods in API class that are used by other classes, primarily Bitcoin_Gateway, Background_Jobs and CLI.
 */
interface API_Interface {

	/**
	 * Find what the value of 1 BTC is in the specified currency.
	 *
	 * @used-by Bitcoin_Gateway::process_payment()
	 *
	 * @param Currency $currency E.g. USD|EUR|GBP.
	 *
	 * @return ?Money `null` when unavailable, e.g. offline and requests fail.
	 */
	public function get_exchange_rate( Currency $currency ): ?Money;

	/**
	 * Get the Bitcoin value of a local currency amount.
	 *
	 * @used-by Bitcoin_Gateway::process_payment()
	 *
	 * @param Money $fiat_amount The amount to convert.
	 */
	public function convert_fiat_to_btc( Money $fiat_amount ): Money;

	/**
	 * When a new wallet address is saved in the gateway settings, generate a Wallet custom post for it, and prepare
	 * fresh addresses for use.
	 *
	 * @used-by Bitcoin_Gateway::process_admin_options()
	 *
	 * @param string  $master_public_key The wallet address to save as a wallet object cpt.
	 * @param ?string $gateway_id The Bitcoin gateway (it is presumably linked to one).
	 *
	 * @return Wallet_Generation_Result
	 */
	public function generate_new_wallet( string $master_public_key, ?string $gateway_id = null ): Wallet_Generation_Result;

	/**
	 * For each Bitcoin gateway, calls `generate_new_addresses_for_wallet()`.
	 *
	 * @return Addresses_Generation_Result[]
	 */
	public function generate_new_addresses(): array;

	/**
	 * Generate fresh addresses for a wallet.
	 *
	 * Gets the wallet object (CPT), get the last address index generated, derives the following 25 addresses for
	 * that wallet, checks the new addresses for transactions, queues a new background job to generate more if
	 * total is still below threshold.
	 *
	 * @param Bitcoin_Wallet $wallet The wallet to generate addresses for.
	 * @param int            $generate_count The number of sub-addresses to derive.
	 */
	public function generate_new_addresses_for_wallet( Bitcoin_Wallet $wallet, int $generate_count = 20 ): Addresses_Generation_Result;

	/**
	 * Get transactions for an address object, with number of confirmations for each, and show which are new or updated.
	 *
	 * @used-by CLI::check_transactions()
	 *
	 * @param Bitcoin_Address $address Address object for existing saved address (i.e. this doesn't work for arbitrary addresses).
	 *
	 * @return array{address:Bitcoin_Address, transactions:array<string, Transaction_Interface>, updated:bool, updates:array{new_transactions:array<string, array<Transaction_Interface>>, new_confirmations:array<string, array<Transaction_Interface>>}, previous_transactions:array<string, array<Transaction_Interface>>|null}
	 */
	public function update_address_transactions( Bitcoin_Address $address ): array;

	/**
	 * Validate addresses have not been used before by checking for transactions.
	 *
	 * @used-by Background_Jobs::check_new_addresses_for_transactions()
	 * @used-by API::generate_new_addresses()
	 * @used-by API::generate_new_addresses_for_wallet()
	 * @used-by API::generate_new_wallet()
	 * @used-by CLI::generate_new_addresses()
	 *
	 * @return array<string, array{address:Bitcoin_Address, transactions:array<string, Transaction_Interface>}>
	 */
	public function check_new_addresses_for_transactions(): Check_Assigned_Addresses_For_Transactions_Result;

	/**
	 * The main function for checking for payments received.
	 */
	public function check_assigned_addresses_for_transactions(): Check_Assigned_Addresses_For_Transactions_Result;
}
