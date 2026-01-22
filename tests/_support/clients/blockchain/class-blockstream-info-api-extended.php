<?php
/**
 * Additional API features implemented for contract tests. Not used in main plugin.
 *
 * @see https://github.com/Blockstream/esplora/blob/master/API.md
 * @see https://dashboard.blockstream.info
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Clients\Blockchain;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Clients\Blockchain_API_Extended_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Exceptions\BH_WP_Bitcoin_Gateway_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Exchange_Rate_Service;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;

class Blockstream_Info_API_Extended extends Blockstream_Info_API implements Blockchain_API_Extended_Interface {

	/**
	 * The total amount in BTC received at this address.
	 *
	 * @param string $btc_address The Bitcoin address.
	 */
	public function get_received_by_address( string $btc_address, bool $confirmed ): Money {

		$address_info_url = 'https://blockstream.info/api/address/' . $btc_address;

		$this->logger->debug( 'URL: ' . $address_info_url );

		$request_response = wp_remote_get( $address_info_url );

		if ( is_wp_error( $request_response ) || 200 !== $request_response['response']['code'] ) {
			throw new BH_WP_Bitcoin_Gateway_Exception();
		}

		$address_info = json_decode( $request_response['body'], true );

		if ( $confirmed ) {
			$amount = $address_info['chain_stats']['funded_txo_sum'] / Exchange_Rate_Service::SATOSHI_RATE;
		} else {
			$amount = ( $address_info['chain_stats']['funded_txo_sum'] + $address_info['mempool_stats']['funded_txo_sum'] ) / Exchange_Rate_Service::SATOSHI_RATE;
		}

		return Money::of( $amount, 'BTC' );
	}
}
