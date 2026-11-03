<?php
/**
 * Tiny dependency free test runner for the plugin's pure PHP logic.
 * Stubs the few WordPress functions those methods touch.
 */
define( 'ABSPATH', __DIR__ );
function add_query_arg( $key, $value, $url ) { return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . $key . '=' . $value; }
function register_activation_hook() {}

require __DIR__ . '/../../includes/class-rmt-settings.php';
require __DIR__ . '/../../includes/class-rmt-experiments.php';
require __DIR__ . '/../../includes/class-rmt-donations.php';

$failed = 0;
$passed = 0;
function check( $name, $cond ) {
	global $failed, $passed;
	if ( $cond ) { $passed++; echo "ok   $name\n"; } else { $failed++; echo "FAIL $name\n"; }
}

// Amount parsing and validation.
check( 'parses and cleans suggested amounts', RMT_Settings::parse_amounts( '10, 25,abc,50,25,0,99999' ) === array( 10, 25, 50 ) );
check( 'falls back to defaults when nothing is valid', RMT_Settings::parse_amounts( 'x,y' ) === array( 10, 25, 50 ) );
check( 'converts dollars to cents', RMT_Donations::to_cents( 25 ) === 2500 );
check( 'rejects zero and very large amounts', null === RMT_Donations::to_cents( 0 ) && null === RMT_Donations::to_cents( 10001 ) );

// Checkout session parameters.
$once    = RMT_Donations::session_params( 2500, 'once', 'usd', 'impact', 'https://example.org/story' );
$monthly = RMT_Donations::session_params( 1000, 'monthly', 'usd', 'control', 'https://example.org/story' );
check( 'one time gift uses payment mode', 'payment' === $once['mode'] && ! isset( $once['line_items[0][price_data][recurring][interval]'] ) );
check( 'monthly gift uses subscription mode', 'subscription' === $monthly['mode'] && 'month' === $monthly['line_items[0][price_data][recurring][interval]'] );
check( 'variant travels to Stripe metadata', 'impact' === $once['metadata[variant]'] );
check( 'success url marks the thank you state', str_ends_with( $once['success_url'], 'rmt_thanks=1' ) );

// Stripe webhook signature verification.
$secret  = 'whsec_test';
$payload = '{"type":"checkout.session.completed"}';
$t       = 1700000000;
$good    = 't=' . $t . ',v1=' . hash_hmac( 'sha256', $t . '.' . $payload, $secret );
check( 'accepts a valid signature', RMT_Donations::verify_signature( $payload, $good, $secret, 300, $t + 10 ) );
check( 'rejects a tampered payload', ! RMT_Donations::verify_signature( $payload . ' ', $good, $secret, 300, $t + 10 ) );
check( 'rejects an old timestamp (replay)', ! RMT_Donations::verify_signature( $payload, $good, $secret, 300, $t + 1000 ) );
check( 'rejects a missing secret', ! RMT_Donations::verify_signature( $payload, $good, '', 300, $t ) );

// A/B assignment.
$v = RMT_Experiments::variant( 'cta_headline_v1', 'visitor-1' );
check( 'variant is stable', $v === RMT_Experiments::variant( 'cta_headline_v1', 'visitor-1' ) );
check( 'unknown experiment falls back to control', 'control' === RMT_Experiments::variant( 'nope', 'visitor-1' ) );
$counts = array( 'control' => 0, 'impact' => 0 );
for ( $i = 0; $i < 4000; $i++ ) { $counts[ RMT_Experiments::variant( 'cta_headline_v1', "v$i" ) ]++; }
check( 'traffic splits roughly evenly', abs( $counts['control'] - $counts['impact'] ) < 300 );

echo "\n$passed passed, $failed failed\n";
exit( $failed ? 1 : 0 );
