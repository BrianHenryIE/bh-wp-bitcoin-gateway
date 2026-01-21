<?php
/**
 * Additional API features implemented for contract tests. Not used in main plugin.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Clients\Blockchain;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Clients\Blockchain_API_Extended_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Exceptions\BH_WP_Bitcoin_Gateway_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;

class Blockchain_Info_Api_Extended extends Blockchain_Info_Api implements Blockchain_API_Extended_Interface {

	/**
	 *
	 * @param string $btc_address
	 * @param bool   $confirmed
	 *
	 * @return string
	 * @throws BH_WP_Bitcoin_Gateway_Exception
	 */
	public function get_received_by_address( string $btc_address, bool $confirmed ): Money {

		$minimum_confirmations = $confirmed ? 1 : 0;

		$url = "https://blockchain.info/q/getreceivedbyaddress/{$btc_address}?confirmations={$minimum_confirmations}";

		$request_response = wp_remote_get( $url );

		if ( is_wp_error( $request_response ) || 200 !== $request_response['response']['code'] ) {
			throw new BH_WP_Bitcoin_Gateway_Exception();
		}

		return Money::of(
			amount: $request_response['body'] / 100_000_000,
			currency: 'BTC',
		);
	}
}
