<?php
/**
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 * @author  BrianHenryIE <BrianHenryIE@gmail.com>
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway;

use lucatume\WPBrowser\TestCase\WPTestCase;

class Rest_Integration_Test extends WPTestCase {
	public function test_post_type_is_available_in_rest(): void {

		$rest_server = rest_get_server();

		$bitcoin_gateway_routes = $rest_server->get_routes( 'bh-wp-bitcoin-gateway/v1' );

		$this->assertArrayHasKey( '/bh-wp-bitcoin-gateway/v1/bh-bitcoin-address', $bitcoin_gateway_routes );
	}
}
