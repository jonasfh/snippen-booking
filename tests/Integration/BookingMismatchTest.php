<?php

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Database\Install;
use SnippenBooking\Api\BookingApi;

class BookingMismatchTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Install::activate();
        
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}snippen_bookings");
        $wpdb->query("DELETE FROM {$wpdb->prefix}snippen_booking_objects");
        $wpdb->query("DELETE FROM {$wpdb->prefix}snippen_time_slots");
        $wpdb->query("DELETE FROM {$wpdb->prefix}snippen_time_slot_booking_objects");
    }

    public function test_booking_api_rejects_mismatched_objects() {
        global $wpdb;
        
        // Create 2 objects
        $wpdb->insert($wpdb->prefix . 'snippen_booking_objects', ['name' => 'Festsalen']);
        $festsalen_id = $wpdb->insert_id;
        
        $wpdb->insert($wpdb->prefix . 'snippen_booking_objects', ['name' => 'Peisestuen']);
        $peisestuen_id = $wpdb->insert_id;

        // Create a multi-object slot (Hele området)
        $wpdb->insert($wpdb->prefix . 'snippen_time_slots', [
            'name' => 'Hele området - Hele dagen',
            'start_time' => '11:00:00',
            'end_time' => '23:00:00'
        ]);
        $slot_id = $wpdb->insert_id;

        // Link slot to BOTH objects
        $wpdb->insert($wpdb->prefix . 'snippen_time_slot_booking_objects', [
            'time_slot_id' => $slot_id,
            'booking_object_id' => $festsalen_id
        ]);
        $wpdb->insert($wpdb->prefix . 'snippen_time_slot_booking_objects', [
            'time_slot_id' => $slot_id,
            'booking_object_id' => $peisestuen_id
        ]);

        // Create a user
        $user_id = wp_insert_user([
            'user_login' => 'testuser',
            'user_pass'  => 'password',
            'user_email' => 'test@example.com',
            'role'       => 'snippen_resident'
        ]);
        update_user_meta($user_id, 'snippen_phone', '+4799887766');
        wp_set_current_user($user_id);

        // Attempt to book ONLY festsalen using the combo slot
        $_POST = [
            'nonce' => wp_create_nonce('snippen_booking_nonce'),
            'booking_object_id' => $festsalen_id, // MISMATCH: Only booking Festsalen
            'event_date' => '2026-07-10',
            'slot_id' => $slot_id, // Trying to book the combo slot!
            'name' => 'Test User',
            'email' => 'test@example.com',
            'description' => 'Test Mismatch Booking',
            'accept_terms' => '1'
        ];

        ob_start();
        try {
            BookingApi::submit_booking();
            ob_end_clean();
            $this->fail('Expected wp_send_json_error to throw an exception or exit, but it didn\'t.');
        } catch (\Exception $e) {
            // wp_send_json_error prints the JSON and then calls wp_die, which throws the exception
            $json = ob_get_clean();
            $response = json_decode($json, true);
            $this->assertFalse($response['success']);
            $this->assertStringContainsString('Tidsluken stemmer ikke', $response['data']['message'] ?? $response['data'][0]['message'] ?? 'En eller flere tidsluker er ikke lenger tilgjengelig.');
        }
    }
    
    public function test_booking_api_accepts_matched_objects() {
        global $wpdb;
        
        // Create 2 objects
        $wpdb->insert($wpdb->prefix . 'snippen_booking_objects', ['name' => 'Festsalen']);
        $festsalen_id = $wpdb->insert_id;
        
        $wpdb->insert($wpdb->prefix . 'snippen_booking_objects', ['name' => 'Peisestuen']);
        $peisestuen_id = $wpdb->insert_id;

        // Create a multi-object slot (Hele området)
        $wpdb->insert($wpdb->prefix . 'snippen_time_slots', [
            'name' => 'Hele området - Hele dagen',
            'start_time' => '11:00:00',
            'end_time' => '23:00:00'
        ]);
        $slot_id = $wpdb->insert_id;

        // Link slot to BOTH objects
        $wpdb->insert($wpdb->prefix . 'snippen_time_slot_booking_objects', [
            'time_slot_id' => $slot_id,
            'booking_object_id' => $festsalen_id
        ]);
        $wpdb->insert($wpdb->prefix . 'snippen_time_slot_booking_objects', [
            'time_slot_id' => $slot_id,
            'booking_object_id' => $peisestuen_id
        ]);

        // Create a user
        $user_id = wp_insert_user([
            'user_login' => 'testuser2',
            'user_pass'  => 'password',
            'user_email' => 'test2@example.com',
            'role'       => 'snippen_resident'
        ]);
        update_user_meta($user_id, 'snippen_phone', '+4799887766');
        wp_set_current_user($user_id);

        // Book BOTH objects using the combo slot
        $_POST = [
            'nonce' => wp_create_nonce('snippen_booking_nonce'),
            'booking_object_id' => $festsalen_id . ',' . $peisestuen_id, // MATCH: Booking both
            'event_date' => '2026-07-11',
            'slot_id' => $slot_id,
            'name' => 'Test User',
            'email' => 'test2@example.com',
            'description' => 'Test Match Booking',
            'accept_terms' => '1'
        ];

        ob_start();
        try {
            BookingApi::submit_booking();
            $json = ob_get_clean();
        } catch (\Exception $e) {
            $json = ob_get_clean();
            $response = json_decode($json, true);
            $this->assertTrue($response['success'], 'Expected booking to succeed.');
        }
    }
}
