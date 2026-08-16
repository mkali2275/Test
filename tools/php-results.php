<?php
/**
 * Run tools/scenarios.json through the PHP sizing engine and print the
 * results as JSON, so tools/test-parity.js can compare them with the
 * browser engine.
 *
 * Usage: php tools/php-results.php
 */

define( 'ABSPATH', __DIR__ );

function apply_filters( $hook, $value ) { return $value; }
function get_option( $name, $default = false ) { return 'admin_email' === $name ? 'admin@example.com' : $default; }
function number_format_i18n( $number, $decimals = 0 ) { return number_format( (float) $number, $decimals ); }
function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, is_array( $args ) ? $args : array() );
}

$plugin_dir = dirname( __DIR__ ) . '/solar-power-calculator';
require_once $plugin_dir . '/includes/class-spc-data.php';
require_once $plugin_dir . '/includes/class-spc-calculator.php';

$scenarios = json_decode( file_get_contents( __DIR__ . '/scenarios.json' ), true );
$output    = array();

foreach ( $scenarios as $scenario ) {
	$results = SPC_Calculator::calculate( $scenario['items'], $scenario['config'] );

	unset( $results['breakdown'] );

	$output[] = array(
		'name'    => $scenario['name'],
		'results' => $results,
	);
}

echo json_encode( $output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), "\n";
