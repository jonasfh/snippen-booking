<?php

/**
 * Bootstrap tests for Snippen Booking Plugin
 */

// Define WordPress constants for testing
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', getenv( 'WP_ABSPATH' ) ?: '/wordpress/' );
}

// Disable automatic WP-Cron execution during test suite loading/execution
if ( ! defined( 'DISABLE_WP_CRON' ) ) {
    define( 'DISABLE_WP_CRON', true );
}

// Define custom pluggable wp_mail to prevent actual mail sending during tests
if ( ! function_exists( 'wp_mail' ) ) {
    function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {
        // Allow tests to intercept it via the standard 'pre_wp_mail' filter
        $atts = compact( 'to', 'subject', 'message', 'headers', 'attachments' );
        $preemption = apply_filters( 'pre_wp_mail', null, $atts );
        if ( null !== $preemption ) {
            return $preemption;
        }
        return true; // Simulate successful mail delivery
    }
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
