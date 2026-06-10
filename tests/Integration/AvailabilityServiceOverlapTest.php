<?php

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\AvailabilityService;

class AvailabilityServiceOverlapTest extends TestCase {

    public function testConsecutiveWholeDayBookingsInGetUnavailableBlocks() {
        global $wpdb;
        $service = new AvailabilityService();
        
        // 1. Setup objects and blocks
        $wpdb->insert($wpdb->prefix . 'snippen_booking_objects', ['name' => 'Test Object']);
        $obj_id = $wpdb->insert_id;
        
        $wpdb->insert($wpdb->prefix . 'snippen_booking_blocks', [
            'name' => 'Full Day',
            'start_time' => '08:00:00',
            'end_time' => '23:00:00'
        ]);
        $block_id = $wpdb->insert_id;
        
        // 2. Insert booking on Day 1
        $wpdb->insert($wpdb->prefix . 'snippen_bookings', [
            'uuid' => 'dummy-uuid-integration-1',
            'booking_date' => '2026-05-20',
            'user_id' => 1,
            'customer_name' => 'Existing',
            'customer_email' => 'test@example.com'
        ]);
        $booking_id = $wpdb->insert_id;
        $wpdb->insert($wpdb->prefix . 'snippen_booking_booking_objects', [
            'booking_id' => $booking_id,
            'booking_object_id' => $obj_id
        ]);
        $wpdb->insert($wpdb->prefix . 'snippen_booking_booking_blocks', [
            'booking_id' => $booking_id,
            'booking_block_id' => $block_id
        ]);
        
        // 3. Check unavailable blocks for Day 2
        $unavailable = $service->getUnavailableBlocks($obj_id, '2026-05-21', '2026-05-21');
        
        $this->assertNotContains($block_id, $unavailable['2026-05-21'], 'Full Day on Day 2 should be available');
    }

    public function testCancelledBookingsAreNotReturnedInGetUnavailableBlocks() {
        global $wpdb;
        $service = new AvailabilityService();
        
        // 1. Setup objects and blocks
        $wpdb->insert($wpdb->prefix . 'snippen_booking_objects', ['name' => 'Test Object 2']);
        $obj_id = $wpdb->insert_id;
        
        $wpdb->insert($wpdb->prefix . 'snippen_booking_blocks', [
            'name' => 'Evening',
            'start_time' => '17:00:00',
            'end_time' => '23:00:00'
        ]);
        $block_id = $wpdb->insert_id;
        
        // 2. Insert a cancelled booking on Day 1
        $wpdb->insert($wpdb->prefix . 'snippen_bookings', [
            'uuid' => 'dummy-uuid-integration-2',
            'booking_date' => '2026-05-20',
            'status' => 'cancelled',
            'user_id' => 1,
            'customer_name' => 'Cancelled Booking',
            'customer_email' => 'cancelled@example.com'
        ]);
        $booking_id = $wpdb->insert_id;
        $wpdb->insert($wpdb->prefix . 'snippen_booking_booking_objects', [
            'booking_id' => $booking_id,
            'booking_object_id' => $obj_id
        ]);
        $wpdb->insert($wpdb->prefix . 'snippen_booking_booking_blocks', [
            'booking_id' => $booking_id,
            'booking_block_id' => $block_id
        ]);
        
        // 3. Check unavailable blocks for Day 1
        $unavailable = $service->getUnavailableBlocks($obj_id, '2026-05-20', '2026-05-20');
        
        $this->assertNotContains($block_id, $unavailable['2026-05-20'], 'Cancelled block should be available');
    }
}
