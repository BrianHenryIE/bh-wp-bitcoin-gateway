A list of tests that should be written.

* Outgoing rate limits – as though every outgoing request resulted in a 429 

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
* Is Bitcoin_Address modified time changed after querying for transactions?
* What should happen on activation?
* What should happen on deactivation?
* Test I18n
* Exchange rate should update via Action Scheduler.

API T&Cs need to be linked from somewhere
API logos need to be displayed (certainly for CoinGecko exchange rate)


GitHub Actions phpcbf should not commit on PRs, only on merge to main.

Code architecture:
* API functions should all return strongly typed responses (with goal of intents/mcp)


Outside project:
* bh-wp-logger needs testing
* bh-wp-private-uploads needs wpcs/phpstan/testing
* wc-filter-orders-by-payment doesn't work with HPOS
* 


Website to market it:
* must have the version number
* must have the changelog
* free to download from GitHub
* pay for support
* later, pay for access to Bitcoin node ()
* explain in detail the support system
* publish support statistics – time to resolve

Broad plan:
* brush up on design patterns
* brush up on system design
* rename the plugin to more generic 'crypto' rather than 'bitcoin'
* add Monero as a payment method (which seems more orientated a daily transactions than HODL)
* 


test setting post_meta – does setting it multiple times erase any/all previous?


Try out:

https://github.com/pdepend/pdepend
Rector
https://github.com/bmitch/churn-php

must run wordpress e2e tests with plugin activated

