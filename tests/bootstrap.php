<?php

/**
 * Bootstrap tests for Snippen Booking Plugin
 */

// Define WordPress constants for testing
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', getenv( 'WP_ABSPATH' ) ?: '/wordpress/' );
}

// Load WordPress test utilities if available (for integration tests)
if ( file_exists( ABSPATH . 'wp-load.php' ) ) {
    require_once ABSPATH . 'wp-load.php';
}

// Load the plugin's autoloader
require_once __DIR__ . '/../src/wp-content/plugins/booking-plugin/autoloader.php';

// Define test fixtures directory
define( 'SNIPPEN_BOOKING_TESTS_DIR', __DIR__ );

// Ensure tables and seed data exist for all tests
\SnippenBooking\Database\Install::activate();
