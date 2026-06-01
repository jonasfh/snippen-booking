<?php

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;

class ActivationTest extends TestCase {

    /**
     * Test if the plugin tables exist in the database
     */
    public function test_tables_exist() {
        global $wpdb;

        $table_slots = $wpdb->prefix . 'snippen_time_slots';
        $table_bookings = $wpdb->prefix . 'snippen_bookings';

        $this->assertEquals( $table_slots, $wpdb->get_var( "SHOW TABLES LIKE '$table_slots'" ) );
        $this->assertEquals( $table_bookings, $wpdb->get_var( "SHOW TABLES LIKE '$table_bookings'" ) );
    }

    /**
     * Test if the default time slot was created
     */
    public function test_default_slot_exists() {
        global $wpdb;

        $table_slots = $wpdb->prefix . 'snippen_time_slots';
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_slots WHERE name LIKE %s", 'Hele dagen%' ) );

        $this->assertGreaterThan( 0, $exists );
    }
}
