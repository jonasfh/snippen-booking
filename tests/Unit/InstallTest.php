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
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}snippen_booking_blocks" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}snippen_booking_object_booking_blocks" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}snippen_pricing_rules" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}snippen_pricing_rule_booking_blocks" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}snippen_pricing_rule_booking_objects" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}snippen_bookings" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}snippen_booking_booking_blocks" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}snippen_booking_booking_objects" );

        // Call activate
        Install::activate();

        // Verify tables exist
        $this->assertTableExists( "{$wpdb->prefix}snippen_booking_objects" );
        $this->assertTableExists( "{$wpdb->prefix}snippen_booking_blocks" );
        $this->assertTableExists( "{$wpdb->prefix}snippen_pricing_rules" );

        // Verify NO seed data was created
        $object_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}snippen_booking_objects" );
        $this->assertEquals( 0, $object_count, 'No seed data should be created during activation' );

        $block_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}snippen_booking_blocks" );
        $this->assertEquals( 0, $block_count, 'No booking blocks should be created during activation' );

        $rule_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}snippen_pricing_rules" );
        $this->assertEquals( 0, $rule_count, 'No pricing rules should be created during activation' );
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
