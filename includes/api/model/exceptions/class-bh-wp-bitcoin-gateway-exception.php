<?php
/**
 * Base exception class for the Bitcoin Gateway plugin.
 *
 * Allows us to catch expected exceptions and unexpected exceptions separately.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Exceptions;

use Exception;

/**
 * Generic exception thrown by Bitcoin Gateway operations.
 *
 * Used throughout the plugin when Bitcoin-related operations fail, such as
 * address generation, blockchain API calls, or wallet operations.
 */
class BH_WP_Bitcoin_Gateway_Exception extends Exception {}
