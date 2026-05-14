<?php

namespace SnippenBooking\Tests\Unit\Service;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\AvailabilityService;
use SnippenBooking\Database\Install;

/**
 * Unit tests for AvailabilityService logic
 * Note: These are "integration" style unit tests as they use the DB for realistic slot data
 */
class AvailabilityServiceTest extends TestCase {

    private $service;
    private $objectId = 1;

    /**
     * Set up the test environment
     */
    protected function setUp(): void {
        parent::setUp();
        global $wpdb;
        
        // Ensure tables and seed data exist
        Install::activate();
        
        $this->service = new AvailabilityService();
        
        // Clear bookings for test isolation
        $wpdb->query("DELETE FROM " . $wpdb->prefix . "snippen_bookings");
    }

    /**
     * Test simple same-day overlap
     */
    public function test_same_day_overlap() {
        global $wpdb;
        $date = '2026-06-01';
        
        // Book "Formiddag" (Slot 2)
        $wpdb->insert($wpdb->prefix . "snippen_bookings", [
            'booking_date' => $date,
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com'
        ]);
        $booking_id = $wpdb->insert_id;
        $wpdb->insert($wpdb->prefix . "snippen_bookings_booking_objects", [
            'booking_id' => $booking_id,
            'booking_object_id' => $this->objectId,
            'slot_id' => 2
        ]);

        // 1. Same slot should be unavailable
        $this->assertFalse($this->service->isSlotAvailable($this->objectId, $date, 2), 'Same slot should be unavailable');
        
        // 2. Overlapping slot "Hele dagen" (Slot 1) should be unavailable
        $this->assertFalse($this->service->isSlotAvailable($this->objectId, $date, 1), 'Hele dagen should be unavailable due to overlap');
        
        // 3. Non-overlapping slot "Ettermiddag" (Slot 3) should be available
        $this->assertTrue($this->service->isSlotAvailable($this->objectId, $date, 3), 'Ettermiddag should be available');
    }

    /**
     * Test cleanup hours extending into the next day
     */
    public function test_cleanup_extension_overlap() {
        global $wpdb;
        $day1 = '2026-07-01';
        $day2 = '2026-07-02';
        
        // Book "Hele dagen" (Slot 1) on Day 1
        // Window: 00:00 - 23:00 + 13h cleanup = Occupied until Day 2 12:00
        $wpdb->insert($wpdb->prefix . "snippen_bookings", [
            'booking_date' => $day1,
            'customer_name' => 'Occupant',
            'customer_email' => 'occ@example.com'
        ]);
        $booking_id = $wpdb->insert_id;
        $wpdb->insert($wpdb->prefix . "snippen_bookings_booking_objects", [
            'booking_id' => $booking_id,
            'booking_object_id' => $this->objectId,
            'slot_id' => 1
        ]);

        // 1. "Formiddag" (Slot 2, starts 08:00) on Day 2 should be unavailable
        $this->assertFalse($this->service->isSlotAvailable($this->objectId, $day2, 2), 'Formiddag should be blocked by yesterday cleanup');
        
        // 2. "Ettermiddag" (Slot 3, starts 16:00) on Day 2 should be available
        $this->assertTrue($this->service->isSlotAvailable($this->objectId, $day2, 3), 'Ettermiddag should be free after cleanup finishes at 12:00');
    }

    /**
     * Test cleanup from ettermiddag blocking next morning
     */
    public function test_ettermiddag_cleanup_overlap() {
        global $wpdb;
        $day1 = '2026-08-01';
        $day2 = '2026-08-02';
        
        // Book "Ettermiddag" (Slot 3, 16:00-23:00) on Day 1
        // 9h cleanup = Occupied until Day 2 08:00
        $wpdb->insert($wpdb->prefix . "snippen_bookings", [
            'booking_date' => $day1,
            'customer_name' => 'Late Night',
            'customer_email' => 'late@example.com'
        ]);
        $booking_id = $wpdb->insert_id;
        $wpdb->insert($wpdb->prefix . "snippen_bookings_booking_objects", [
            'booking_id' => $booking_id,
            'booking_object_id' => $this->objectId,
            'slot_id' => 3
        ]);

        // "Formiddag" (Slot 2, starts 08:00) on Day 2 should be available (exact edge case)
        // Interval [08:00, 16:00] vs [..., 08:00]. 08:00 < 08:00 is FALSE. No overlap.
        $this->assertTrue($this->service->isSlotAvailable($this->objectId, $day2, 2), 'Formiddag should be available as cleanup ends exactly at its start');
    }

    /**
     * Test that availability check is isolated to the booking object
     */
    public function test_object_isolation() {
        global $wpdb;
        $date = '2026-09-01';
        
        // Book "Formiddag" on Object 1
        $wpdb->insert($wpdb->prefix . "snippen_bookings", [
            'booking_date' => $date,
            'customer_name' => 'Obj1 User',
            'customer_email' => 'obj1@example.com'
        ]);
        $booking_id = $wpdb->insert_id;
        $wpdb->insert($wpdb->prefix . "snippen_bookings_booking_objects", [
            'booking_id' => $booking_id,
            'booking_object_id' => 1,
            'slot_id' => 2
        ]);

        // Slot 5 is "Formiddag" for Object 2 (based on seed logic)
        $this->assertTrue($this->service->isSlotAvailable(2, $date, 5), 'Object 2 should be available even if Object 1 is booked');
    }
}
