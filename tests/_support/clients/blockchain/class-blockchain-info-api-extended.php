<?php
/**
 * Additional API features implemented for contract tests. Not used in main plugin.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Clients\Blockchain;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Clients\Blockchain_API_Extended_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Exceptions\BH_WP_Bitcoin_Gateway_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Exchange_Rate_Service;
use BrianHenryIE\WP_Bitcoin_Gateway\BlockchainInfo\BlockchainInfoApi;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use Codeception\Stub\Expected;

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
			amount: $request_response['body'] / Exchange_Rate_Service::SATOSHI_RATE,
			currency: 'BTC',
		);
	}

	/**
	 * @covers ::__construct
	 */
	public function test_get_received_by_address(): void {

		// Old test – new we only fetch transactions and calculate the amount received based on the desired number of confirmations.

		$logger = new ColorLogger();

		$mock_api = $this->make(
			BlockchainInfoApi::class,
			array(
				'getReceivedByAddress' => Expected::once( '10432394445' ),
			)
		);

		$sut = new Blockchain_Info_Api( $logger, $mock_api );

		// Satoshi Nakamoto's address.
		$address = '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa';

		$result = $sut->get_received_by_address( $address, false );

		$this->assertEquals( '104.32394445', (string) $result->getAmount() );
	}

	public function test_get_address_received(): void {

		// Old test – new we only fetch transactions and calculate the amount received based on the desired number of confirmations.

		$logger = new ColorLogger();

		$mock_api = $this->make(
			BlockchainInfoApi::class,
			array(
				'getAddressBalance' => 18142,
			)
		);

		$sut = new Blockchain_Info_Api( $logger, $mock_api );

		// The pizza address.
		$address = '1XPTgDRhN8RFnzniWCddobD9iKZatrvH4';

		$result = $sut->get_address_balance( $address, 1 );

		$this->assertEquals( '0.00018142', (string) $result->get_confirmed_balance()->getAmount() );
	}
}
