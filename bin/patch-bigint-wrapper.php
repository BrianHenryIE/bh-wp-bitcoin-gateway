#!/usr/bin/env php
<?php
/**
 * Patch the prefixed simplito/bigint-wrapper-php so it accepts a bcmath polyfill.
 *
 * The wrapper chooses its big-integer backend with `extension_loaded("bcmath")`, so on a host without the bcmath
 * extension it refuses to work even though nanasess/bcmath-polyfill provides the `bc*` functions. Widen the two
 * checks to also accept `function_exists("bcadd")`.
 *
 * Strauss regenerates vendor-prefixed/ on every `composer install|update`, so this runs from the Composer
 * post-install-cmd and post-update-cmd scripts, after Strauss. It is idempotent.
 *
 * Usage: php bin/patch-bigint-wrapper.php [path/to/BigInteger.php]
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

$bh_wp_bitcoin_gateway_target = $argv[1] ?? dirname( __DIR__ ) . '/vendor-prefixed/simplito/bigint-wrapper-php/lib/BigInteger.php';

if ( ! file_exists( $bh_wp_bitcoin_gateway_target ) ) {
	// Not an error: e.g. the package was removed, or Strauss has not run yet.
	echo "patch-bigint-wrapper: {$bh_wp_bitcoin_gateway_target} not found, nothing to do.\n";
	exit( 0 );
}

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
$bh_wp_bitcoin_gateway_contents = file_get_contents( $bh_wp_bitcoin_gateway_target );

if ( false === $bh_wp_bitcoin_gateway_contents ) {
	echo "patch-bigint-wrapper: could not read {$bh_wp_bitcoin_gateway_target}.\n";
	exit( 1 );
}

/**
 * Each original conditional and its replacement. Only these exact strings are touched, so an already patched
 * file, or a future upstream change to the checks, is left alone.
 */
$bh_wp_bitcoin_gateway_replacements = array(
	'(extension_loaded("bcmath"))'  => '(extension_loaded("bcmath") || function_exists("bcadd"))',
	'(!extension_loaded("bcmath"))' => '(!extension_loaded("bcmath") && !function_exists("bcadd"))',
);

$bh_wp_bitcoin_gateway_patched = str_replace(
	array_keys( $bh_wp_bitcoin_gateway_replacements ),
	array_values( $bh_wp_bitcoin_gateway_replacements ),
	$bh_wp_bitcoin_gateway_contents,
	$bh_wp_bitcoin_gateway_count
);

if ( 0 === $bh_wp_bitcoin_gateway_count ) {
	$bh_wp_bitcoin_gateway_already = 0;
	foreach ( $bh_wp_bitcoin_gateway_replacements as $bh_wp_bitcoin_gateway_replacement ) {
		$bh_wp_bitcoin_gateway_already += substr_count( $bh_wp_bitcoin_gateway_contents, $bh_wp_bitcoin_gateway_replacement );
	}
	if ( $bh_wp_bitcoin_gateway_already > 0 ) {
		echo "patch-bigint-wrapper: already patched.\n";
		exit( 0 );
	}
	// Upstream changed the code we expect; a human needs to look.
	echo "patch-bigint-wrapper: expected bcmath conditionals not found in {$bh_wp_bitcoin_gateway_target}.\n";
	exit( 1 );
}

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
if ( false === file_put_contents( $bh_wp_bitcoin_gateway_target, $bh_wp_bitcoin_gateway_patched ) ) {
	echo "patch-bigint-wrapper: could not write {$bh_wp_bitcoin_gateway_target}.\n";
	exit( 1 );
}

echo "patch-bigint-wrapper: replaced {$bh_wp_bitcoin_gateway_count} bcmath conditional(s) in {$bh_wp_bitcoin_gateway_target}.\n";
