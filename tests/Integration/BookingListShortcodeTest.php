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
        $this->assertStringContainsString( 'id="wp-submit"', $output );
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

    /**
     * Test booking list displays user bookings sorted by closest in time first
     */
    public function test_displays_bookings_sorted_by_closest_first() {
        global $wpdb;

        // Create a test user
        $user_id = wp_insert_user([
            'user_login' => 'resident_list_sort_test',
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

        $table_bookings = $wpdb->prefix . 'snippen_bookings';

        // 1. Far future booking (in 10 days)
        $far_date = date('Y-m-d', strtotime('+10 days'));
        $wpdb->insert($table_bookings, [
            'user_id' => $user_id,
            'slot_id' => $slot_id,
            'booking_date' => $far_date,
            'status' => 'pending',
            'customer_name' => 'Far Future',
            'customer_email' => 'resident@example.com',
            'customer_phone' => '+4799887766',
            'description' => 'Far Future',
            'price' => 500,
            'created_at' => current_time('mysql'),
            'modified_at' => current_time('mysql')
        ]);

        // 2. Near future booking (in 2 days) - should be first!
        $near_date = date('Y-m-d', strtotime('+2 days'));
        $wpdb->insert($table_bookings, [
            'user_id' => $user_id,
            'slot_id' => $slot_id,
            'booking_date' => $near_date,
            'status' => 'pending',
            'customer_name' => 'Near Future',
            'customer_email' => 'resident@example.com',
            'customer_phone' => '+4799887766',
            'description' => 'Near Future',
            'price' => 500,
            'created_at' => current_time('mysql'),
            'modified_at' => current_time('mysql')
        ]);

        // 3. Past booking (2 days ago) - should be last!
        $past_date = date('Y-m-d', strtotime('-2 days'));
        $wpdb->insert($table_bookings, [
            'user_id' => $user_id,
            'slot_id' => $slot_id,
            'booking_date' => $past_date,
            'status' => 'pending',
            'customer_name' => 'Past',
            'customer_email' => 'resident@example.com',
            'customer_phone' => '+4799887766',
            'description' => 'Past',
            'price' => 500,
            'created_at' => current_time('mysql'),
            'modified_at' => current_time('mysql')
        ]);

        // Run shortcode
        $output = do_shortcode( '[snippen_booking_list]' );

        // Let's assert the order in which they appear in the HTML
        $pos_near = strpos( $output, 'Near Future' );
        $pos_far  = strpos( $output, 'Far Future' );
        $pos_past = strpos( $output, 'Past' );

        $this->assertNotFalse( $pos_near );
        $this->assertNotFalse( $pos_far );
        $this->assertNotFalse( $pos_past );

        // Near Future must be before Far Future, and both must be before Past
        $this->assertTrue( $pos_near < $pos_far );
        $this->assertTrue( $pos_far < $pos_past );

        // Logout
        wp_set_current_user( 0 );
    }
}
