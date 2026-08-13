<?php
/**
 * Loaded via `<autoload>` in phpcs.xml.
 *
 * The PluginCheck standard is referenced in phpcs.xml by path because it lives inside the
 * plugin-check plugin's own vendor directory, where dealerdirect/phpcodesniffer-composer-installer
 * cannot register it in `installed_paths`. PHPCS only registers a standard's autoload namespace
 * for top-level standards (Ruleset::__construct), not for path-based `<rule ref>` includes, so
 * without this the sniffs' helper classes (PluginCheckCS\PluginCheck\Helpers\*) fail to load.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

\PHP_CodeSniffer\Autoload::addSearchPath(
	dirname( __DIR__ ) . '/wp-content/plugins/plugin-check/vendor/plugin-check/phpcs-sniffs/PluginCheck',
	'PluginCheckCS\\PluginCheck'
);
