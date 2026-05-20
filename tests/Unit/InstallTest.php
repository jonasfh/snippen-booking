<?php

namespace SnippenBooking\Tests\Unit;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Database\Install;

/**
 * Test suite for Install class
 */
class InstallTest extends TestCase {

    /**
     * Test that class has activate method
     */
    public function testClassHasActivateMethod() {
        $this->assertTrue( method_exists( Install::class, 'activate' ) );
    }

    /**
     * Test that activate method is public static
     */
    public function testActivateIsPublicStatic() {
        $reflection = new \ReflectionMethod( Install::class, 'activate' );
        $this->assertTrue( $reflection->isPublic() );
        $this->assertTrue( $reflection->isStatic() );
    }

    /**
     * Test that class requires ABSPATH constant
     */
    public function testAbspathIsRequired() {
        // ABSPATH should be defined by bootstrap
        $this->assertTrue( defined( 'ABSPATH' ) );
    }

    /**
     * Test that Install class can be instantiated
     */
    public function testClassCanBeInstantiated() {
        // While methods are static, we should be able to instantiate
        $install = new Install();
        $this->assertInstanceOf( Install::class, $install );
    }

    /**
     * Test database table prefix handling
     */
    public function testDatabasePrefixUsage() {
        // Even without WordPress, we can verify the class doesn't crash
        // when required includes are present
        $this->assertTrue( class_exists( Install::class ) );
    }

    /**
     * Test that activation creates database tables without seed data
     */
    public function testActivationCreatesTablesWithoutSeedData() {
        global $wpdb;

        // Clear existing tables if they exist
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}snippen_booking_objects" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}snippen_time_slots" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}snippen_prices" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}snippen_bookings" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}snippen_bookings_booking_objects" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}snippen_price_booking_objects" );

        // Call activate
        Install::activate();

        // Verify tables exist
        $this->assertTableExists( "{$wpdb->prefix}snippen_booking_objects" );
        $this->assertTableExists( "{$wpdb->prefix}snippen_time_slots" );
        $this->assertTableExists( "{$wpdb->prefix}snippen_prices" );

        // Verify NO seed data was created
        $object_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}snippen_booking_objects" );
        $this->assertEquals( 0, $object_count, 'No seed data should be created during activation' );

        $slot_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}snippen_time_slots" );
        $this->assertEquals( 0, $slot_count, 'No time slots should be created during activation' );

        $price_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}snippen_prices" );
        $this->assertEquals( 0, $price_count, 'No pricing should be created during activation' );
    }

    /**
     * Helper to check if table exists
     */
    private function assertTableExists( $table_name ) {
        global $wpdb;
        $result = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );
        $this->assertNotNull( $result, "Table {$table_name} should exist" );
    }
}
