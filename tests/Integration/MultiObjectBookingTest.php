<?php

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Api\BookingApi;
use SnippenBooking\Database\Install;

class MultiObjectBookingTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        global $wpdb;
        
        // Ensure tables exist
        Install::activate();
        
        // Clear data
        $wpdb->query("DELETE FROM " . $wpdb->prefix . "snippen_bookings_booking_objects");
        $wpdb->query("DELETE FROM " . $wpdb->prefix . "snippen_bookings");
        
        // Mock current user as logged in for BookingApi
        wp_set_current_user(1);
    }

    /**
     * Test booking multiple objects in a single request
     */
    public function test_multi_object_booking_submission() {
        global $wpdb;
        
        // Setup request data
        // Object 1: Festsalen, Slot 1 (Hele dagen)
        // Object 2: Peisestuen, Slot 4 (Hele dagen for obj 2)
        $_POST['nonce'] = wp_create_nonce('snippen_booking_nonce');
        $_POST['booking_object_id'] = [1, 2];
        $_POST['slot_id'] = '1,4';
        $_POST['event_date'] = '2026-10-10';
        $_POST['name'] = 'Test User';
        $_POST['email'] = 'test@example.com';
        $_POST['phone'] = '12345678';
        $_POST['description'] = 'Multi-object booking test';

        // Capture output
        ob_start();
        try {
            BookingApi::submit_booking();
        } catch (\Exception $e) {
            // wp_send_json_success/error throws if not in AJAX context in some setups, 
            // but here it should just die/exit. We use a mock or check results.
        }
        $output = ob_get_clean();
        
        // Check database
        $bookings = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "snippen_bookings");
        $this->assertCount(1, $bookings, 'Should have exactly 1 booking record');
        
        $booking_id = $bookings[0]->id;
        $junctions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . $wpdb->prefix . "snippen_bookings_booking_objects WHERE booking_id = %d",
            $booking_id
        ));
        
        $this->assertCount(2, $junctions, 'Should have 2 junction records for the booking');
        
        // Verify object and slot mapping
        $obj1_found = false;
        $obj2_found = false;
        foreach ($junctions as $j) {
            if ($j->booking_object_id == 1 && $j->slot_id == 1) $obj1_found = true;
            if ($j->booking_object_id == 2 && $j->slot_id == 4) $obj2_found = true;
        }
        
        $this->assertTrue($obj1_found, 'Object 1 (Festsalen) with Slot 1 not found in junction table');
        $this->assertTrue($obj2_found, 'Object 2 (Peisestuen) with Slot 4 not found in junction table');
    }
}
