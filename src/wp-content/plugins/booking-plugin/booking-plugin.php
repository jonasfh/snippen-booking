<?php
/**
 * Plugin Name: Snippen Booking
 * Description: Booking plugin for Snippen community house.
 * Version: 1.23.5
 * Author: Snippen
 * Text Domain: snippen-booking
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Global helper for resident phone numbers (Issue #37)
if ( ! function_exists( 'snippen_save_phone_number' ) ) {
	function snippen_save_phone_number( $user_id, $phone_string ) {
		update_user_meta( $user_id, 'snippen_phone', $phone_string );
	}
}

// Define version constant
define( 'SNIPPEN_BOOKING_VERSION', '1.23.5' );
// Load autoloader
require_once __DIR__ . '/autoloader.php';

// Initialize plugin
\SnippenBooking\Plugin::init();

// Initialize Plugin Update Checker for automatic updates via GitHub
require_once __DIR__ . '/lib/plugin-update-checker/plugin-update-checker.php';
$myUpdateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/jonasfh/snippen-booking/',
    __FILE__,
    'snippen-booking'
);

// get the release from an asset using regex to match versioned zip files
$myUpdateChecker->getVcsApi()->enableReleaseAssets('/^snippen-booking-.*\.zip$/i');

// Force update check in development mode for easier testing
if ( defined( 'WP_DEBUG' ) && WP_DEBUG && isset( $_GET['check_booking_updates'] ) ) {
    add_action( 'admin_init', function () use ( $myUpdateChecker ) {
        $myUpdateChecker->getScheduler()->checkForUpdates();
    } );
}
