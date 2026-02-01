# Behat WP-CLI Tests

This directory contains Behat feature tests for the WP-CLI commands provided by the BH WP Bitcoin Gateway plugin.

## Prerequisites

The tests require:
- WP-CLI Bundle (already in `composer.json` as a dev dependency)
- WP-CLI Tests framework (already in `composer.json` as a dev dependency)
- Behat (already in `composer.json` as a dev dependency)

**No database setup required!** Tests use SQLite by default, which means no MySQL/MariaDB installation is needed.

## Running the Tests

### Run all Behat tests (using SQLite - default)

```bash
composer behat
```

Or directly:

```bash
WP_CLI_TEST_DBTYPE=sqlite vendor/bin/behat
```

### Run with MySQL (if you prefer)

First, set up the MySQL database:

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS wp_cli_test;"
mysql -u root -p -e "GRANT ALL PRIVILEGES ON wp_cli_test.* TO 'wp_cli_test'@'localhost' IDENTIFIED BY 'password1';"
```

Then run:

```bash
composer behat-mysql
```

Or directly:

```bash
WP_CLI_TEST_DBTYPE=mysql vendor/bin/behat
```

### Run a specific feature file

```bash
WP_CLI_TEST_DBTYPE=sqlite vendor/bin/behat tests/features/generate-new-addresses.feature
WP_CLI_TEST_DBTYPE=sqlite vendor/bin/behat tests/features/check-transactions.feature
```

### Run with verbose output

```bash
WP_CLI_TEST_DBTYPE=sqlite vendor/bin/behat --format=pretty --verbose
```

### Run with code coverage

```bash
composer behat-coverage
```

This will:
- Run all Behat tests with code coverage enabled (using SQLite)
- Generate an HTML coverage report at `tests/_output/behat-html/index.html`
- Generate a Clover XML report at `tests/_output/behat-clover.xml` (for CI/CD)
- Generate a PHP coverage object at `tests/_output/behat-coverage.php`
- Display coverage summary in the terminal

To view the HTML report after running:

```bash
open tests/_output/behat-html/index.html
```

**Note:** Code coverage requires Xdebug or PCOV to be installed and enabled in your PHP installation.

## Database Configuration

### SQLite (Default - Recommended)

Tests use SQLite by default, which provides several advantages:

- **No database server required** - Tests run faster without network overhead
- **No setup needed** - No database creation or user permissions required
- **Portable** - Works consistently across different environments
- **Isolated** - Each test run uses a fresh SQLite database
- **CI/CD friendly** - Perfect for GitHub Actions and other CI environments

The WP-CLI test framework automatically downloads and configures the [SQLite Database Integration](https://wordpress.org/plugins/sqlite-database-integration/) plugin when `WP_CLI_TEST_DBTYPE=sqlite` is set.

### MySQL (Optional)

If you need to test against MySQL specifically (e.g., for MySQL-specific queries), you can use the `composer behat-mysql` command. This requires a MySQL server with the test database set up as shown in the "Run with MySQL" section above.

## Test Coverage

### 1. Generate New Addresses Command (`generate-new-addresses.feature`)

Tests for `wp bh-bitcoin generate-new-addresses`:
- Command executes successfully without gateway configuration
- Debug flag (`--debug=bh-wp-bitcoin-gateway`) works correctly
- Help text is available and accurate

### 2. Check Transactions Command (`check-transactions.feature`)

Tests for `wp bh-bitcoin check-transactions <input>`:
- Command requires an input argument
- Handles non-existent order IDs appropriately
- Handles invalid Bitcoin addresses appropriately
- Output format options work (`--format=table|json|csv|yaml`)
- Debug flag (`--debug=bh-wp-bitcoin-gateway`) works correctly
- Accepts valid Bitcoin address formats

## Writing New Tests

When adding new WP-CLI commands, create a new `.feature` file in this directory following the Gherkin syntax:

```gherkin
Feature: Description of the feature

  Background:
    Given a WP install
    And I run `wp plugin activate bh-wp-bitcoin-gateway`

  Scenario: Description of test scenario
    When I run `wp bh-bitcoin your-command`
    Then STDOUT should contain:
      """
      Expected output
      """
    And the return code should be 0
```

## Behat Context

The tests use `WP_CLI\Tests\Context\FeatureContext` which provides:
- WordPress installation setup
- WP-CLI command execution
- Output assertions (STDOUT, STDERR)
- Return code verification
- File system operations

For more information on available step definitions, see the [WP-CLI Behat documentation](https://make.wordpress.org/cli/handbook/misc/plugin-unit-tests/#behat-tests).

## Troubleshooting

### Tests are slow

Make sure you're using SQLite (default). MySQL tests will be slower due to database connection overhead.

### "Database connection error"

If you see database connection errors:

1. **For SQLite** (default): This should not happen. Make sure `WP_CLI_TEST_DBTYPE=sqlite` is set.
2. **For MySQL**: Verify the test database and user are created correctly:
   ```bash
   mysql -u wp_cli_test -ppassword1 wp_cli_test -e "SELECT 1;"
   ```

### Clear test cache

If tests behave unexpectedly, clear the test cache:

```bash
rm -rf /tmp/behat-wordpress-*
rm -rf ~/.wp-cli/cache/behat-*
```

## Environment Variables

The following environment variables can be used to customize test execution:

- `WP_CLI_TEST_DBTYPE` - Database type: `sqlite` (default) or `mysql`
- `WP_CLI_TEST_DBNAME` - Database name (default: `wp_cli_test`)
- `WP_CLI_TEST_DBUSER` - Database user (default: `wp_cli_test`)
- `WP_CLI_TEST_DBPASS` - Database password (default: `password1`)
- `WP_CLI_TEST_DBHOST` - Database host (default: `127.0.0.1`)
- `BEHAT_CODE_COVERAGE` - Enable code coverage: `1` or `true` (automatically set by `composer behat-coverage`)

## Code Coverage

The Behat tests are configured with code coverage using `dvdoug/behat-code-coverage`. The coverage configuration is set in `behat.yml` and includes:

### Coverage Scope

**Included directories:**
- `includes/` - Main plugin code

**Excluded directories:**
- `vendor/` - Third-party dependencies
- `vendor-prefixed/` - Prefixed dependencies (via Strauss)
- `tests/` - Test code itself

### Generated Reports

1. **HTML Report** (`tests/_output/behat-html/index.html`)
   - Visual, browsable coverage report
   - Shows line-by-line coverage
   - Color-coded coverage indicators

2. **Clover XML** (`tests/_output/behat-clover.xml`)
   - Machine-readable format
   - Used by CI/CD tools and coverage services
   - Compatible with PHPUnit's clover format

3. **PHP Object** (`tests/_output/behat-coverage.php`)
   - Serialized PHP coverage data
   - Can be merged with other coverage data using `phpcov`

4. **Text Summary**
   - Displayed in terminal after test run
   - Shows percentage coverage per directory/file

### Merging Coverage with PHPUnit

To get combined coverage from both PHPUnit and Behat tests:

```bash
# Run PHPUnit tests with coverage
composer test-coverage

# Run Behat tests with coverage
composer behat-coverage

# Merge coverage reports (requires phpunit/phpcov)
phpcov merge --clover tests/_output/merged-clover.xml \
  --html tests/_output/merged-html \
  tests/_output
```

## References

- [WP-CLI Testing Framework](https://github.com/wp-cli/wp-cli-tests)
- [WordPress SQLite Integration](https://wordpress.org/plugins/sqlite-database-integration/)
- [Behat Documentation](https://docs.behat.org/)
- [WP-CLI Behat Tests Guide](https://make.wordpress.org/cli/handbook/guides/behat-tests/)
- [dvdoug/behat-code-coverage](https://github.com/dvdoug/behat-code-coverage)
