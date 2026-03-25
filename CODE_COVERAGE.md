# Code Coverage for Behat Tests

## The Issue

When running Behat with code coverage using `XDEBUG_MODE=coverage vendor/bin/behat`, you see the warning:
```
No code coverage driver is available. No code coverage driver available
```

## Why This Happens

The `dvdoug/behat-code-coverage` extension checks for the code coverage driver during Symfony's dependency injection container compilation phase. At this point, even though `XDEBUG_MODE=coverage` is set as an environment variable, the extension's initialization happens in a context where it cannot detect the driver properly.

## Solution

Use the provided wrapper script that sets the environment variable correctly:

```bash
./bin/behat-coverage
```

Or export the environment variable before running behat:

```bash
export XDEBUG_MODE=coverage
export WP_CLI_TEST_DBTYPE=sqlite
vendor/bin/behat
```

##  Alternative: Update PHP Configuration

For a permanent solution, configure Xdebug to always include coverage mode in your PHP configuration:

1. Find your php.ini location:
   ```bash
   php --ini
   ```

2. Edit the php.ini or create a new configuration file in the `conf.d` directory:
   ```bash
   echo "xdebug.mode=develop,coverage,debug" > /opt/homebrew/etc/php/8.4/conf.d/xdebug.ini
   ```

3. Verify the change:
   ```bash
   php -i | grep xdebug.mode
   ```

## Verifying Code Coverage Works

Even if you see the warning, check if coverage files are generated:

```bash
./bin/behat-coverage
ls -la tests/_output/behat-*
```

If coverage files exist (`behat-clover.xml`, `behat-html/`, etc.), then code coverage is working despite the warning.

##  Troubleshooting

1. **Confirm Xdebug is installed:**
   ```bash
   php -m | grep xdebug
   ```

2. **Check Xdebug version (needs v3.x):**
   ```bash
   php -v
   ```

3. **Test driver creation directly:**
   ```bash
   XDEBUG_MODE=coverage php -r "
   require 'vendor/autoload.php';
   \$driver = (new \SebastianBergmann\CodeCoverage\Driver\Selector())->forLineCoverage(new \SebastianBergmann\CodeCoverage\Filter());
   echo 'Driver: ' . \$driver->nameAndVersion() . PHP_EOL;
   "
   ```
