<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use lucatume\WPBrowser\TestCase\WPTestCase;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Addresses_List_Table
 */
class Address_List_Table_WPUnit_Test extends WPTestCase {

	/**
	 * A registered Bitcoin_Gateway instance used to populate WC_Payment_Gateways.
	 *
	 * @var Bitcoin_Gateway
	 */
	protected Bitcoin_Gateway $bitcoin_gateway;

	public function setUp(): void {
		parent::setUp();

		$this->bitcoin_gateway        = new Bitcoin_Gateway(
			api:             $this->makeEmpty( API_Interface::class ),
			api_woocommerce: $this->makeEmpty( API_WooCommerce_Interface::class ),
			plugin_settings: $this->makeEmpty( Settings_Interface::class ),
			logger:          new ColorLogger(),
		);
		$this->bitcoin_gateway->title = 'Bitcoin';

		\WC_Payment_Gateways::instance()->payment_gateways[ $this->bitcoin_gateway->id ] = $this->bitcoin_gateway;

		// Bypass Bitcoin_Gateway::is_available() which checks transients, unused addresses,
		// and exchange rates.  The filter is the last step in get_available_payment_gateways().
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'inject_available_gateway' ) );
	}

	/**
	 * Inject the test gateway into the available-gateways array so it is found regardless of
	 * the result from Bitcoin_Gateway::is_available().
	 *
	 * @param array<string,\WC_Payment_Gateway> $gateways WooCommerce available payment gateways.
	 * @return array<string,\WC_Payment_Gateway>
	 */
	public function inject_available_gateway( array $gateways ): array {
		$gateways[ $this->bitcoin_gateway->id ] = $this->bitcoin_gateway;
		return $gateways;
	}

	#[\Override]
	public function tearDown(): void {
		parent::tearDown();

		remove_filter( 'woocommerce_available_payment_gateways', array( $this, 'inject_available_gateway' ) );
		unset( \WC_Payment_Gateways::instance()->payment_gateways[ $this->bitcoin_gateway->id ] );
	}

	/**
	 * When the integration string does not match WooCommerce_Integration, the input array is
	 * returned unchanged — including any href already present.
	 *
	 * @covers ::woocommerce_gateway_link
	 */
	public function test_returns_filtered_result_unchanged_when_integration_does_not_match(): void {

		$sut = new Addresses_List_Table();

		$filtered_result = array(
			'href' => 'https://example.com/other-gateway',
			'text' => 'Other integration',
		);
		$bitcoin_wallet  = $this->makeEmpty( Bitcoin_Wallet::class );

		$result = $sut->woocommerce_gateway_link(
			$filtered_result,
			'Some\Other\Integration',
			$this->bitcoin_gateway->id, // @phpstan-ignore argument.type
			$bitcoin_wallet,
		);

		$this->assertSame( $filtered_result, $result );
	}

	/**
	 * When the integration matches but the gateway_id is not registered as an available
	 * WooCommerce gateway, return a text-only array with no href.
	 *
	 * @covers ::woocommerce_gateway_link
	 */
	public function test_returns_unavailable_text_when_gateway_not_registered(): void {

		$sut = new Addresses_List_Table();

		$bitcoin_wallet = $this->makeEmpty( Bitcoin_Wallet::class );

		$result = $sut->woocommerce_gateway_link(
			array( 'text' => '' ),
			WooCommerce_Integration::class,
			'nonexistent_gateway',
			$bitcoin_wallet,
		);

		$this->assertArrayNotHasKey( 'href', $result );
		$this->assertEquals( 'WooCommerce: nonexistent_gateway (unavailable)', $result['text'] );
	}

	/**
	 * When the gateway is registered and available, the href points to the WooCommerce
	 * checkout settings page for that gateway and the text includes the gateway title.
	 *
	 * @covers ::woocommerce_gateway_link
	 */
	public function test_returns_gateway_link_when_gateway_is_available(): void {

		$sut = new Addresses_List_Table();

		$bitcoin_wallet = $this->makeEmpty( Bitcoin_Wallet::class );

		$result = $sut->woocommerce_gateway_link(
			array( 'text' => '' ),
			WooCommerce_Integration::class,
			$this->bitcoin_gateway->id, // @phpstan-ignore argument.type
			$bitcoin_wallet,
		);

		$this->assertArrayHasKey( 'href', $result );
		$this->assertStringContainsString(
			'admin.php?page=wc-settings&tab=checkout&section=' . $this->bitcoin_gateway->id,
			$result['href']
		);
		$this->assertEquals( 'WooCommerce: Bitcoin', $result['text'] );
	}

	/**
	 * The optional $_address parameter is not used by this method; the result should be
	 * identical whether it is passed or omitted.
	 *
	 * @covers ::woocommerce_gateway_link
	 */
	public function test_address_parameter_does_not_affect_result(): void {

		$sut = new Addresses_List_Table();

		$bitcoin_wallet  = $this->makeEmpty( Bitcoin_Wallet::class );
		$bitcoin_address = $this->makeEmpty( Bitcoin_Address::class );

		$result_without = $sut->woocommerce_gateway_link(
			array( 'text' => '' ),
			WooCommerce_Integration::class,
			$this->bitcoin_gateway->id, // @phpstan-ignore argument.type
			$bitcoin_wallet,
		);

		$result_with = $sut->woocommerce_gateway_link(
			array( 'text' => '' ),
			WooCommerce_Integration::class,
			$this->bitcoin_gateway->id, // @phpstan-ignore argument.type
			$bitcoin_wallet,
			$bitcoin_address,
		);

		$this->assertSame( $result_without, $result_with );
	}
}
