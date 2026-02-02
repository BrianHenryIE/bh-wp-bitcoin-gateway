Feature: Check Bitcoin transactions for addresses or orders

  Background:
    Given a WP install
    And a plugin located at .
    And I try `wp plugin activate bh-wp-bitcoin-gateway`
    And a plugin located at wp-content/plugins/woocommerce
    And I try `wp plugin activate woocommerce`

  Scenario: Check help command is available
    When I run `wp help bh-bitcoin check-transactions`
    Then STDOUT should contain:
      """
      Query the blockchain for updates for an address or order
      """
    And the return code should be 0

  Scenario: Check transactions with non-existent order ID
    When I try `wp bh-bitcoin check-transactions 99999`
    Then the return code should not be 0

  Scenario: Check transactions with invalid Bitcoin address
    When I try `wp bh-bitcoin check-transactions invalid-address`
    Then the return code should not be 0

  Scenario: Check transactions with table format (default)
    Given I run `wp wc shop_order create --status=pending --user=1 --porcelain`
    And save STDOUT as {ORDER_ID}

    When I try `wp bh-bitcoin check-transactions {ORDER_ID}`
    Then the return code should not be 0

  Scenario: Check transactions with JSON format
    Given I run `wp wc shop_order create --status=pending --user=1 --porcelain`
    And save STDOUT as {ORDER_ID}

    When I try `wp bh-bitcoin check-transactions {ORDER_ID} --format=json`
    Then the return code should not be 0

  Scenario: Check transactions with CSV format
    Given I run `wp wc shop_order create --status=pending --user=1 --porcelain`
    And save STDOUT as {ORDER_ID}

    When I try `wp bh-bitcoin check-transactions {ORDER_ID} --format=csv`
    Then the return code should not be 0

  Scenario: Check transactions with YAML format
    Given I run `wp wc shop_order create --status=pending --user=1 --porcelain`
    And save STDOUT as {ORDER_ID}

    When I try `wp bh-bitcoin check-transactions {ORDER_ID} --format=yaml`
    Then the return code should not be 0

  Scenario: Check transactions with debug flag
    Given I run `wp wc shop_order create --status=pending --user=1 --porcelain`
    And save STDOUT as {ORDER_ID}

    When I try `wp bh-bitcoin check-transactions {ORDER_ID} --debug=bh-wp-bitcoin-gateway`
    Then the return code should not be 0

  Scenario: Check transactions with valid Bitcoin address format
    When I try `wp bh-bitcoin check-transactions bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq`
    Then the return code should not be 0
