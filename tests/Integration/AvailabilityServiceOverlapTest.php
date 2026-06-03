<?php

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\AvailabilityService;

class AvailabilityServiceOverlapTest extends TestCase {

    public function testConsecutiveWholeDayBookingsInGetUnavailableSlots() {
        global $wpdb;
        $service = new AvailabilityService();
        
        // 1. Setup objects and slots
        $wpdb->insert($wpdb->prefix . 'snippen_booking_objects', ['name' => 'Test Object']);
        $obj_id = $wpdb->insert_id;
        
        $wpdb->insert($wpdb->prefix . 'snippen_time_slots', [
            'name' => 'Hele dagen',
            'start_time' => '11:00:00',
            'end_time' => '23:00:00',
            'cleanup_hours' => 12
        ]);
        $slot_id = $wpdb->insert_id;
        
        // 2. Insert booking on Day 1
        $wpdb->insert($wpdb->prefix . 'snippen_bookings', [
            'slot_id' => $slot_id,
            'booking_date' => '2026-05-20',
            'customer_name' => 'Existing',
            'customer_email' => 'test@example.com'
        ]);
        $booking_id = $wpdb->insert_id;
        $wpdb->insert($wpdb->prefix . 'snippen_bookings_booking_objects', [
            'booking_id' => $booking_id,
            'booking_object_id' => $obj_id
        ]);
        
        // 3. Check unavailable slots for Day 2
        $unavailable = $service->getUnavailableSlots($obj_id, '2026-05-21', '2026-05-21');
        
        $this->assertNotContains($slot_id, $unavailable['2026-05-21'], 'Hele dagen on Day 2 should be available');
    }

    public function testCancelledBookingsAreNotReturnedInGetUnavailableSlots() {
        global $wpdb;
        $service = new AvailabilityService();
        
        // 1. Setup objects and slots
        $wpdb->insert($wpdb->prefix . 'snippen_booking_objects', ['name' => 'Test Object 2']);
        $obj_id = $wpdb->insert_id;
        
        $wpdb->insert($wpdb->prefix . 'snippen_time_slots', [
            'name' => 'Kveld',
            'start_time' => '17:00:00',
            'end_time' => '23:00:00',
            'cleanup_hours' => 2
        ]);
        $slot_id = $wpdb->insert_id;
        
        // 2. Insert a cancelled booking on Day 1
        $wpdb->insert($wpdb->prefix . 'snippen_bookings', [
            'slot_id' => $slot_id,
            'booking_date' => '2026-05-20',
            'status' => 'cancelled',
            'customer_name' => 'Cancelled Booking',
            'customer_email' => 'cancelled@example.com'
        ]);
        $booking_id = $wpdb->insert_id;
        $wpdb->insert($wpdb->prefix . 'snippen_bookings_booking_objects', [
            'booking_id' => $booking_id,
            'booking_object_id' => $obj_id
        ]);
        
        // 3. Check unavailable slots for Day 1
        $unavailable = $service->getUnavailableSlots($obj_id, '2026-05-20', '2026-05-20');
        
        $this->assertNotContains($slot_id, $unavailable['2026-05-20'], 'Cancelled slot should be available');
    }
}

