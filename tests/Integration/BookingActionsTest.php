<?php

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Api\BookingActionsApi;

class BookingActionsTest extends TestCase {

    public function setUp(): void {
        parent::setUp();
        BookingActionsApi::register();

        if (!defined('DOING_AJAX')) {
            define('DOING_AJAX', true);
        }

        add_filter('wp_die_ajax_handler', function() {
            return function($message, $title, $args) {
                throw new \Exception(is_string($message) ? $message : wp_json_encode($message));
            };
        });
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
        $user = get_user_by('id', $user_id);
        $user->add_cap('manage_snippen_bookings');
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
        $_REQUEST['nonce'] = $_POST['nonce'];

        // Capture output
        ob_start();
        try {
            BookingActionsApi::update_status();
        } catch (\Throwable $e) { echo "\nError: " . $e->getMessage() . "\n"; }
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
        $_REQUEST['nonce'] = $_POST['nonce'];

        ob_start();
        try {
            BookingActionsApi::update_status();
        } catch (\Throwable $e) { echo "\nError: " . $e->getMessage() . "\n"; }
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
        $_REQUEST['nonce'] = $_POST['nonce'];

        // We expect an error response
        // In WP test suite, wp_send_json_error often dies or throws
        // We can check the database status after the call

        ob_start();
        try {
            BookingActionsApi::update_status();
        } catch (\Throwable $e) { echo "\nError: " . $e->getMessage() . "\n"; }
        ob_get_clean();

        global $wpdb;
        $status = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}snippen_bookings WHERE id = %d", $booking_id));
        $this->assertEquals('pending', $status); // Should still be pending
    }

    /**
     * Test that a subscriber cannot cancel a confirmed booking
     */
    public function test_user_cannot_cancel_confirmed_booking() {
        $user_id = wp_insert_user([
            'user_login' => 'user_test_confirmed',
            'user_pass' => 'password',
            'role' => 'subscriber'
        ]);
        wp_set_current_user($user_id);

        $booking_id = $this->create_test_booking($user_id);
        global $wpdb;
        $wpdb->update($wpdb->prefix . 'snippen_bookings', ['status' => 'confirmed'], ['id' => $booking_id]);

        $_POST['id'] = $booking_id;
        $_POST['status'] = 'cancelled';
        $_POST['nonce'] = wp_create_nonce('snippen_admin_nonce');

        ob_start();
        try {
            BookingActionsApi::update_status();
        } catch (\Throwable $e) {}
        ob_get_clean();

        $status = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}snippen_bookings WHERE id = %d", $booking_id));
        $this->assertEquals('confirmed', $status);
    }

    /**
     * Test that a subscriber cannot cancel a paid booking
     */
    public function test_user_cannot_cancel_paid_booking() {
        $user_id = wp_insert_user([
            'user_login' => 'user_test_paid',
            'user_pass' => 'password',
            'role' => 'subscriber'
        ]);
        wp_set_current_user($user_id);

        $booking_id = $this->create_test_booking($user_id);
        global $wpdb;
        $wpdb->update($wpdb->prefix . 'snippen_bookings', ['payment_status_id' => 3], ['id' => $booking_id]); // PAID status

        $_POST['id'] = $booking_id;
        $_POST['status'] = 'cancelled';
        $_POST['nonce'] = wp_create_nonce('snippen_admin_nonce');

        ob_start();
        try {
            BookingActionsApi::update_status();
        } catch (\Throwable $e) {}
        ob_get_clean();

        $status = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}snippen_bookings WHERE id = %d", $booking_id));
        $this->assertEquals('pending', $status);
    }

    /**
     * Test that a subscriber cannot cancel booking closer than configured cancellation deadline
     */
    public function test_user_cannot_cancel_past_deadline_booking() {
        $user_id = wp_insert_user([
            'user_login' => 'user_test_deadline',
            'user_pass' => 'password',
            'role' => 'subscriber'
        ]);
        wp_set_current_user($user_id);

        $booking_id = $this->create_test_booking($user_id);
        global $wpdb;
        // Set date to 5 days in future (default limit is 14 days)
        $wpdb->update($wpdb->prefix . 'snippen_bookings', ['booking_date' => date('Y-m-d', strtotime('+5 days'))], ['id' => $booking_id]);

        $_POST['id'] = $booking_id;
        $_POST['status'] = 'cancelled';
        $_POST['nonce'] = wp_create_nonce('snippen_admin_nonce');

        ob_start();
        try {
            BookingActionsApi::update_status();
        } catch (\Throwable $e) {}
        ob_get_clean();

        $status = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}snippen_bookings WHERE id = %d", $booking_id));
        $this->assertEquals('pending', $status);
    }

    /**
     * Test that an admin can update door code
     */
    public function test_admin_can_update_door_code() {
        $user_id = wp_insert_user([
            'user_login' => 'admin_test_door',
            'user_pass' => 'password',
            'role' => 'administrator'
        ]);
        $user = get_user_by('id', $user_id);
        $user->add_cap('manage_snippen_bookings');
        wp_set_current_user($user_id);

        $booking_id = $this->create_test_booking($user_id);

        $_POST['id'] = $booking_id;
        $_POST['door_code'] = '123456';
        $_POST['nonce'] = wp_create_nonce('snippen_admin_nonce');
        $_REQUEST['nonce'] = $_POST['nonce'];

        ob_start();
        try {
            BookingActionsApi::update_door_code();
        } catch (\Throwable $e) {}
        ob_get_clean();

        global $wpdb;
        $door_code = $wpdb->get_var($wpdb->prepare("SELECT door_code FROM {$wpdb->prefix}snippen_bookings WHERE id = %d", $booking_id));
        $this->assertEquals('123456', $door_code);
    }

    /**
     * Test that a non-admin cannot update door code
     */
    public function test_user_cannot_update_door_code() {
        $user_id = wp_insert_user([
            'user_login' => 'user_test_door',
            'user_pass' => 'password',
            'role' => 'subscriber'
        ]);
        wp_set_current_user($user_id);

        $booking_id = $this->create_test_booking($user_id);

        $_POST['id'] = $booking_id;
        $_POST['door_code'] = '654321';
        $_POST['nonce'] = wp_create_nonce('snippen_admin_nonce');
        $_REQUEST['nonce'] = $_POST['nonce'];

        ob_start();
        try {
            BookingActionsApi::update_door_code();
        } catch (\Throwable $e) {}
        ob_get_clean();

        global $wpdb;
        $door_code = $wpdb->get_var($wpdb->prepare("SELECT door_code FROM {$wpdb->prefix}snippen_bookings WHERE id = %d", $booking_id));
        $this->assertNull($door_code);
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
            'booking_date' => date('Y-m-d', strtotime('+30 days')),
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
