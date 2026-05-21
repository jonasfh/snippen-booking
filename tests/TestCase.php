<?php

namespace SnippenBooking\Tests;

/**
 * Base test case class for all tests
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase {

    /**
     * Whether to create seed data before each test.
     */
    protected $requires_seed_data = true;

    /**
     * Set up test environment
     */
    protected function setUp(): void {
        parent::setUp();
        global $wpdb;
        
        // Prevent translations from loading during tests to keep original Norwegian strings for assertions
        unload_textdomain('snippen-booking');
        
        if (isset($wpdb)) {
            $wpdb->query("DELETE FROM {$wpdb->users} WHERE ID > 1");
            $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE user_id > 1");
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_bookings");
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_bookings_booking_objects");
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_booking_objects");
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_time_slots");
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_prices");
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_price_booking_objects");
            
            if ($this->requires_seed_data) {
                \SnippenBooking\Admin\SetupWizard::create_starter_setup();
            }
        }
    }

    /**
     * Tear down test environment
     */
    protected function tearDown(): void {
        parent::tearDown();
    }
}
