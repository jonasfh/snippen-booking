<?php

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Api\BookingActionsApi;

class BookingActionsTest extends TestCase {

    public function setUp(): void {
        parent::setUp();
        BookingActionsApi::register();
        
        // Mock global wpdb and current user if needed
        // TestCase base class usually handles some of this
    }

    /**
     * Test that an admin can cancel any booking
     */
    public function test_admin_can_cancel_booking() {
        $user_id = wp_insert_user([
            'user_login' => 'admin_test',
            'user_pass' => 'password',
            'role' => 'administrator'
        ]);
        wp_set_current_user($user_id);
        
        $customer_id = wp_insert_user([
            'user_login' => 'subscriber_test',
            'user_pass' => 'password',
            'role' => 'subscriber'
        ]);
        $booking_id = $this->create_test_booking($customer_id);

        $_POST['id'] = $booking_id;
        $_POST['status'] = 'cancelled';
        $_POST['nonce'] = wp_create_nonce('snippen_admin_nonce');

        // Capture output
        ob_start();
        try {
            BookingActionsApi::update_status();
        } catch (\Exception $e) {
            // wp_send_json_success/error will throw if not in AJAX context in some test setups
        }
        $output = ob_get_clean();
        
        global $wpdb;
        $status = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}snippen_bookings WHERE id = %d", $booking_id));
        $this->assertEquals('cancelled', $status);
    }

    /**
     * Test that a subscriber can cancel their own booking
     */
    public function test_user_can_cancel_own_booking() {
        $user_id = wp_insert_user([
            'user_login' => 'user_test_1',
            'user_pass' => 'password',
            'role' => 'subscriber'
        ]);
        wp_set_current_user($user_id);
        
        $booking_id = $this->create_test_booking($user_id);

        $_POST['id'] = $booking_id;
        $_POST['status'] = 'cancelled';
        $_POST['nonce'] = wp_create_nonce('snippen_admin_nonce');

        ob_start();
        try {
            BookingActionsApi::update_status();
        } catch (\Exception $e) {}
        ob_get_clean();
        
        global $wpdb;
        $status = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}snippen_bookings WHERE id = %d", $booking_id));
        $this->assertEquals('cancelled', $status);
    }

    /**
     * Test that a subscriber cannot cancel someone else's booking
     */
    public function test_user_cannot_cancel_others_booking() {
        $user_id = wp_insert_user([
            'user_login' => 'user_test_2',
            'user_pass' => 'password',
            'role' => 'subscriber'
        ]);
        wp_set_current_user($user_id);
        
        $other_id = wp_insert_user([
            'user_login' => 'other_test',
            'user_pass' => 'password',
            'role' => 'subscriber'
        ]);
        $booking_id = $this->create_test_booking($other_id);

        $_POST['id'] = $booking_id;
        $_POST['status'] = 'cancelled';
        $_POST['nonce'] = wp_create_nonce('snippen_admin_nonce');

        // We expect an error response
        // In WP test suite, wp_send_json_error often dies or throws
        // We can check the database status after the call
        
        ob_start();
        try {
            BookingActionsApi::update_status();
        } catch (\Exception $e) {}
        ob_get_clean();
        
        global $wpdb;
        $status = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}snippen_bookings WHERE id = %d", $booking_id));
        $this->assertEquals('pending', $status); // Should still be pending
    }

    /**
     * Helper to create a test booking
     */
    private function create_test_booking($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'snippen_bookings';
        $wpdb->insert($table, [
            'user_id' => $user_id,
            'slot_id' => 1,
            'booking_date' => date('Y-m-d'),
            'status' => 'pending',
            'customer_name' => 'Test',
            'customer_email' => 'test@test.com',
            'customer_phone' => '12345678',
            'created_at' => current_time('mysql'),
            'modified_at' => current_time('mysql')
        ]);
        return $wpdb->insert_id;
    }
}
