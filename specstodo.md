A list of tests that should be written.


* When a WooCommerce order is placed, the email sent should contain the Bitcoin payment instructions
* WooCommerce admin order ui should correctly display the last checked time
* WooCommerce admin order ui should display existing data without an API call, then JS AJAX/REST query for new transactions if the last checked time is older than ten minutes old
* Playwright should check query monitor on every pageload to ensure no unapproved external HTTP calls
* Exchange rate should save to wp_option not to transient
* Exchange rate should be saved to address during `::assign_to_order()`
* CLI tests
* Thank You page display and refresh button (old+block theme)
* My Account display and refresh button (old+block theme)
* Wallet post list table entries should link to filtered Bitcoin Address list
* Gateway settings page correctly links to order confirmation edit page (blocks)
* Checking for transactions should log as comments on a Bitcoin_Address page
* Is Bitcoin_Address modified time changed after querying for trandsactaions?
* What should happen on activation?
* What should happen on deactivation?
* Test I18n



Code architecture:
* API functions should all return strongly typed responses (with goal of intents/mcp)


Outside project:
* bh-wp-logger needs testing
* wc-filter-orders-by-payment doesn't work with HPOS
* 
