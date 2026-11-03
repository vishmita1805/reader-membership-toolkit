<?php
/**
 * Plugin Name:       Reader Membership Toolkit
 * Description:       Donation calls to action, a soft article meter, Stripe Checkout donations, consent aware event tracking, and simple A/B tests for nonprofit newsrooms.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Vishmita Mattam
 * License:           MIT
 * Text Domain:       rmt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RMT_VERSION', '0.1.0' );
define( 'RMT_DIR', plugin_dir_path( __FILE__ ) );
define( 'RMT_URL', plugin_dir_url( __FILE__ ) );

require_once RMT_DIR . 'includes/class-rmt-settings.php';
require_once RMT_DIR . 'includes/class-rmt-experiments.php';
require_once RMT_DIR . 'includes/class-rmt-donations.php';
require_once RMT_DIR . 'includes/class-rmt-cta.php';

add_action( 'plugins_loaded', function () {
	RMT_Settings::init();
	RMT_Donations::init();
	RMT_CTA::init();
} );
