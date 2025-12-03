# Contributing

## Rough notes.

List scripts:

`composer run --list`

```bash
# nvm use
npm install
npx wp-env start --xdebug
```
```bash
npx wp-env destroy
```

### PHPUnit Tests with Codeception/WP-Browser

```bash
composer test
```

### E2E testing with wp-env and Playwright

```bash
npx playwright install
npx playwright test --config ./playwright.config.ts
```

```
# Start the playwright test runner UI and return to the Terminal (otherwise Terminal is unavailable until the application is exited).
npx playwright test --ui &;

We do this because 8889 is the port used for the tests instance which has the plugin zip installed, and 8888 is the 
port used for development work, so if the intent is to edit code as we re-run tests, we need to use the 8888 port.
BASEURL=http://localhost:8888 npx playwright test --ui &;

# Start browser and record Playwright steps
npx playwright codegen -o tests/e2e-pw/example.spec.js

# Run WP CLI commands on the tests instance
npx wp-env run tests-cli wp option get rewrite_rules
```

Tests not working? It's possibly due to rate limiting:

```
npx wp-env run tests-cli wp transient delete --all;
npx wp-env run cli wp transient delete --all;
```


Reset between tests:

```
rm -rf wp-content/uploads/logs;
rm wp-content/debug.log;
wp user delete $(wp user list --role=subscriber --format=ids) --yes;
wp transient delete --all;
```
```bash
wp config set WP_DEBUG true --raw;
wp config set WP_DEBUG_LOG true --raw; 
wp config set SCRIPT_DEBUG true --raw
```
```
wp config set WP_DEBUG_LOG "/var/www/html/wp-content/debug.log"
```

Don't have xdebug enabled in IDE when starting wp-env.

