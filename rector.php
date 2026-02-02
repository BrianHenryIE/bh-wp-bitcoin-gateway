<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
	->withPaths([
		__DIR__ . '/includes',
		__DIR__ . '/templates',
		__DIR__ . '/development-plugin',
	])
	->withSkip([
		__DIR__ . '/includes/vendor',
		__DIR__ . '/includes/vendor-prefixed',
	])
	->withPhpSets(
		php70: true,
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
