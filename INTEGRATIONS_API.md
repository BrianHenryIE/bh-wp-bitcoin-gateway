# Integrations 

This plugin is primarily about generating payment addresses, checking payments, and handling issues like rate-limiting
when querying the payment APIs.

It is written so any WordPress plugin with a checkout can rely on this plugin to get a usable address and be 
notified when payment is received.

## Hooks

On `plugins_loaded`:`0` bh-wp-bitcoin-gatway runs a filter to initialize integrations.

The point is that your plugin, e.g. `a-plugin`, may be loaded by WordPress before `bh-wp-bitcoin-gateway` plugin, so it
is good/easy to use the filter which will then call you `::register_hooks()` function only after `bh-wp-bitcoin-gateway` 
itself is loaded. I.e. if your classes use `BrianHenryIE\WP_Bitcoin_Gateway\API\API` etc. then your code does not 
attempt to use those until they are loaded.

```php
add_filter( 
    'bh_wp_bitcoin_gateway_integrations',
    fn( array $integrations ) => $integrations[MyIntegration::class]    
);
```

The main hooks are:

* `bh_wp_bitcoin_gateway_new_transactions_seen` – one or more transactions has been seen at the payment address
* `bh_wp_bitcoin_gateway_payment_received` – after new transactions were seen, the total confirmed amount is more than the target amount

```php
class MyIntegration {
	public function register_hooks(): void {
		// `add_action()`, `add_filter()` for classes that depend on `bh-wp-bitcoin-gateway` being already loaded.

		add_action( 'bh_wp_bitcoin_gateway_new_transactions_seen', array( $this, 'new_transactions_seen' ), 10, 4 );
	}

	/**
	 * Act on new transactions.
	 *
	 * @hooked bh_wp_bitcoin_gateway_new_transactions_seen
	 *
	 * @param string|class-string|null                 $integration_id Identifier for the integration the payment address was used by.
	 * @param ?int                                     $order_post_id Identifier for the order the payment address was assigned to.
	 * @param Bitcoin_Address                          $payment_address The address the transactions were found for.
	 * @param Check_Address_For_Payment_Service_Result $check_address_for_payment_service_result The detail of existing and new transactions.
	 */
	public function new_transactions_seen(
		?string $integration_id,
		?int $order_post_id,
		Bitcoin_Address $payment_address,
		Check_Address_For_Payment_Service_Result $check_address_for_payment_service_result,
	): void {

		if ( get_class( $this ) !== $integration_id ) {
			return;
		}

		// Do something for the integration's order to record the new transactions.
		// E.g. notify a customer that partial payment has been received.
	}
}
```
