<?php

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Database\Install;
use SnippenBooking\Shortcode\BookingListShortcode;

class BookingListShortcodeTest extends TestCase {

    /**
     * Set up the test environment
     */
    protected function setUp(): void {
        parent::setUp();
        Install::activate();
        BookingListShortcode::register();
    }

    /**
     * Test when guest is not logged in and login-form is disabled (0)
     */
    public function test_guest_returns_empty_when_form_disabled() {
        wp_set_current_user( 0 );
        $output = do_shortcode( '[snippen_booking_list login-form="0"]' );
        $this->assertEquals( '', $output );
    }

    /**
     * Test when guest is not logged in and login-form is enabled (1)
     */
    public function test_guest_returns_login_form_when_form_enabled() {
        wp_set_current_user( 0 );
        $output = do_shortcode( '[snippen_booking_list login-form="1"]' );
        
        $this->assertStringContainsString( 'class="snippen-booking-login-card"', $output );
        $this->assertStringContainsString( 'name="loginform"', $output );
        $this->assertStringContainsString( 'id="user_login"', $output );
        $this->assertStringContainsString( 'id="user_pass"', $output );
        $this->assertStringContainsString( 'class="vipps-login-btn"', $output );
    }

    /**
     * Test booking list displays user bookings when logged in
     */
    public function test_displays_bookings_when_logged_in() {
        global $wpdb;

        // Create a test user
        $user_id = wp_insert_user([
            'user_login' => 'resident_list_test',
            'user_pass'  => 'password',
            'role'       => 'subscriber'
        ]);
        wp_set_current_user( $user_id );

        // Seed slots if empty
        $table_slots = $wpdb->prefix . 'snippen_time_slots';
        $wpdb->insert($table_slots, [
            'name' => 'Kveld',
            'start_time' => '16:00:00',
            'end_time' => '22:00:00',
            'created_at' => current_time('mysql'),
            'modified_at' => current_time('mysql')
        ]);
        $slot_id = $wpdb->insert_id;

        // Seed objects if empty
        $table_objects = $wpdb->prefix . 'snippen_booking_objects';
        $wpdb->insert($table_objects, [
            'name' => 'Felleshus',
            'description' => 'Snippen Community House',
            'created_at' => current_time('mysql'),
            'modified_at' => current_time('mysql')
        ]);
        $object_id = $wpdb->insert_id;

        // Seed bookings
        $table_bookings = $wpdb->prefix . 'snippen_bookings';
        $wpdb->insert($table_bookings, [
            'user_id' => $user_id,
            'slot_id' => $slot_id,
            'booking_date' => date('Y-m-d', strtotime('+3 days')),
            'status' => 'pending',
            'customer_name' => 'Resident List Test',
            'customer_email' => 'resident@example.com',
            'customer_phone' => '+4799887766',
            'description' => 'Bursdagsfeiring',
            'price' => 500,
            'created_at' => current_time('mysql'),
            'modified_at' => current_time('mysql')
        ]);
        $booking_id = $wpdb->insert_id;

        // Seed booking junction table
        $table_junction = $wpdb->prefix . 'snippen_bookings_booking_objects';
        $wpdb->insert($table_junction, [
            'booking_id' => $booking_id,
            'booking_object_id' => $object_id
        ]);

        // Run shortcode
        $output = do_shortcode( '[snippen_booking_list]' );

        $this->assertStringContainsString( 'class="snippen-booking-list-container"', $output );
        $this->assertStringContainsString( 'Mine Bookinger', $output );
        $this->assertStringContainsString( 'class="booking-list-card"', $output );
        $this->assertStringContainsString( 'Felleshus', $output );
        $this->assertStringContainsString( 'Bursdagsfeiring', $output );
        $this->assertStringContainsString( '500,-', $output );
        $this->assertStringContainsString( 'Venter', $output );
        $this->assertStringContainsString( 'snippen-btn-cancel-booking', $output );

        // Logout
        wp_set_current_user( 0 );
    }
}
