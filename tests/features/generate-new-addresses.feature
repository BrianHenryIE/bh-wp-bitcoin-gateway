Feature: Generate new Bitcoin addresses for all gateways

  Background:
    Given a WP install
    And a plugin located at .
    And I run `wp plugin activate bh-wp-bitcoin-gateway`
    And I run `wp plugin activate woocommerce`

  Scenario: Generate new addresses without any gateway configured
    When I try `wp bh-bitcoin generate-new-addresses`
    Then STDOUT should contain:
      """
      Success
      """
    And the return code should be 0

  Scenario: Generate new addresses with debug flag
    When I run `wp bh-bitcoin generate-new-addresses --debug=bh-wp-bitcoin-gateway`
    Then STDOUT should contain:
      """
      Success
      """
    And the return code should be 0

  Scenario: Generate new addresses command exists and is callable
    When I run `wp help bh-bitcoin generate-new-addresses`
    Then STDOUT should contain:
      """
      Generate new addresses for all gateways
      """
    And the return code should be 0
