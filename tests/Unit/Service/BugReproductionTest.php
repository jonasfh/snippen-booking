<?php

namespace SnippenBooking\Tests\Unit\Service;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\AvailabilityService;
use SnippenBooking\Database\Install;

class BugReproductionTest extends TestCase {

    private $service;
    private $objectId = 1;

    protected function setUp(): void {
        parent::setUp();
        global $wpdb;
        Install::activate();
        $this->service = new AvailabilityService();
        $wpdb->query("DELETE FROM " . $wpdb->prefix . "snippen_bookings");
    }

    public function test_edge_to_edge_overlap_is_allowed() {
        global $wpdb;
        $day1 = '2026-05-14'; // Torsdag
        $day2 = '2026-05-15'; // Fredag
        
        // Slot 1: Hele dagen (11:00-23:00, 12h cleanup -> ender 11:00 neste dag)
        // Ensure Slot 1 has these values in the DB for the test to be valid
        $table_slots = $wpdb->prefix . 'snippen_time_slots';
        $wpdb->update($table_slots, [
            'start_time' => '11:00:00',
            'end_time' => '23:00:00',
            'cleanup_hours' => 12
        ], ['id' => 1]);

        // Legg inn booking for torsdag
        $wpdb->insert($wpdb->prefix . "snippen_bookings", [
            'slot_id' => 1, 
            'booking_date' => $day1,
            'customer_name' => 'Torsdag Booking',
            'customer_email' => 'torsdag@example.com'
        ]);
        $booking_id = $wpdb->insert_id;
        $wpdb->insert($wpdb->prefix . "snippen_bookings_booking_objects", [
            'booking_id' => $booking_id,
            'booking_object_id' => $this->objectId
        ]);

        // Sjekk at Hele dagen (starter 11:00) er tilgjengelig fredag
        // Fordi utvask fra torsdag ender 11:00, og vi tillater "kant i kant"
        $this->assertTrue($this->service->isSlotAvailable($this->objectId, $day2, 1), 'Fredag Hele dagen skal være ledig, selv om torsdag sin utvask slutter nøyaktig når fredag starter');
    }

    /**
     * Another possibility: Is there an overlap with a booking on Friday itself?
     * User says "ingen annen booking den dagen".
     */
    public function test_isolation_from_other_days() {
        global $wpdb;
        $day = '2026-05-15';
        
        // No bookings at all.
        $this->assertTrue($this->service->isSlotAvailable($this->objectId, $day, 1), 'Should be available when no bookings exist');
    }
}
