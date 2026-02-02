<?php
/**
 * Rector rules to automatically refactor code to modern syntax.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php54\Rector\Array_\LongArrayToShortArrayRector;
use Rector\Php80\Rector\Switch_\ChangeSwitchToMatchRector;

return RectorConfig::configure()
	->withPaths(
		array(
			__DIR__ . '/includes',
			__DIR__ . '/templates',
			__DIR__ . '/development-plugin',
			__DIR__ . '/tests/contract',
			__DIR__ . '/tests/integration',
			__DIR__ . '/tests/unit',
			__DIR__ . '/tests/unit-patchwork',
			__DIR__ . '/tests/wpunit',
		)
	)
	->withSkip(
		array(
			__DIR__ . '/includes/vendor',
			__DIR__ . '/includes/vendor-prefixed',
		)
	)
	->withSkip(
		array(
			LongArrayToShortArrayRector::class,
			ChangeSwitchToMatchRector::class,
		)
	)
	->withPhpSets(
        php83: true,
		php82: true,
		php81: true,
		php80: true,
		php74: true,
		php73: true,
		php72: true,
		php71: true,
		php70: true,
		php56: true,
		php55: true,
		php54: true,
		php53: true,
		php84: true,
	)
	->withPreparedSets(
		deadCode: false,
		codeQuality: false,
		codingStyle: false,
		typeDeclarations: false,
		privatization: false,
		naming: false,
		instanceOf: false,
		earlyReturn: false,
		strictBooleans: false,
	);
