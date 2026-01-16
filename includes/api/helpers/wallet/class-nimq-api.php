<?php
/**
 * The local PHP library for generating addresses.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Helpers\Wallet;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Generate_Address_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Exceptions\BH_WP_Bitcoin_Gateway_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\Nimiq\XPub;
use Exception;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Use nimiq/php-xpub to generate public addresses.
 */
class Nimq_API implements Generate_Address_API_Interface {
	use LoggerAwareTrait;

	/**
	 * Constructor.
	 *
	 * @param LoggerInterface $logger PSR Logger.
	 */
	public function __construct( LoggerInterface $logger ) {
		$this->setLogger( $logger );
	}

	/**
	 * Generate the nth address for the given xpub|ypub|zpub.
	 *
	 * @param string $public_address The wallet address.
	 * @param int    $nth Derive path nth address in sequence.
	 *
	 * @return string
	 * @throws BH_WP_Bitcoin_Gateway_Exception Failed to generate address.
	 */
	public function generate_address( string $public_address, int $nth ): string {

		$xpub = XPub::fromString( $public_address );

		$xpub_i_k = $xpub->derive( array( 0, $nth ) );

		return $xpub_i_k->toAddress( 'btc' );
	}
}
