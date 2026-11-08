<?php
/**
 * Development script: writes assets/js/icons.js from MSVCalc_Icons::shapes(),
 * so the browser and PHP share one icon set.
 *
 * Usage: php bin/build-icons.php
 *
 * Not shipped in the release zip (see .distignore).
 *
 * @package MSVCalc
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ );

require dirname( __DIR__ ) . '/includes/class-msvcalc-icons.php';

$msvcalc_json = json_encode( MSVCalc_Icons::shapes(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
$msvcalc_json = str_replace( '    ', "\t", (string) $msvcalc_json );

$msvcalc_js = "/**\n * Moving & Storage Volume Calculator: icon shapes.\n *\n * Drawn for this plugin on a 24 px grid. Generated from\n * includes/class-msvcalc-icons.php by bin/build-icons.php; do not edit by hand.\n *\n * @package MSVCalc\n */\nwindow.msvcalcIcons = " . $msvcalc_json . ";\n";

file_put_contents( dirname( __DIR__ ) . '/assets/js/icons.js', $msvcalc_js );
echo 'Wrote assets/js/icons.js with ' . count( MSVCalc_Icons::shapes() ) . " icons\n";
