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

    private function getSlotId($name) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}snippen_time_slots WHERE name = %s LIMIT 1", $name));
    }

    /**
     * Test simple same-day overlap
     */
    public function test_same_day_overlap() {
        global $wpdb;
        $date = '2026-06-01'; // Monday

        $slot_formiddag = $this->getSlotId('Festsalen - Formiddag (Hverdag)');
        $slot_hele_dagen = $this->getSlotId('Festsalen - Hele dagen (Hverdag)');
        $slot_ettermiddag = $this->getSlotId('Festsalen - Ettermiddag (Hverdag)');
        
        // Book "Formiddag"
        $wpdb->insert($wpdb->prefix . "snippen_bookings", [
            'slot_id' => $slot_formiddag,
            'booking_date' => $date,
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com'
        ]);
        $booking_id = $wpdb->insert_id;
        $wpdb->insert($wpdb->prefix . "snippen_bookings_booking_objects", [
            'booking_id' => $booking_id,
            'booking_object_id' => $this->objectId
        ]);

        // 1. Same slot should be unavailable
        $this->assertFalse($this->service->isSlotAvailable($this->objectId, $date, $slot_formiddag), 'Same slot should be unavailable');
        
        // 2. Overlapping slot "Hele dagen" should be unavailable
        $this->assertFalse($this->service->isSlotAvailable($this->objectId, $date, $slot_hele_dagen), 'Hele dagen should be unavailable due to overlap');
        
        // 3. Non-overlapping slot "Ettermiddag" should be available
        $this->assertTrue($this->service->isSlotAvailable($this->objectId, $date, $slot_ettermiddag), 'Ettermiddag should be available');
    }

    /**
     * Test cleanup hours extending into the next day
     */
    public function test_cleanup_extension_overlap() {
        global $wpdb;
        $day1 = '2026-07-01'; // Wednesday
        $day2 = '2026-07-02'; // Thursday

        $slot_hele_dagen = $this->getSlotId('Festsalen - Hele dagen (Hverdag)');
        $slot_formiddag = $this->getSlotId('Festsalen - Formiddag (Hverdag)');
        $slot_ettermiddag = $this->getSlotId('Festsalen - Ettermiddag (Hverdag)');
        
        // Book "Hele dagen" on Day 1
        // Window: 00:00 - 23:00 + 13h cleanup = Occupied until Day 2 12:00
        $wpdb->insert($wpdb->prefix . "snippen_bookings", [
            'slot_id' => $slot_hele_dagen,
            'booking_date' => $day1,
            'customer_name' => 'Occupant',
            'customer_email' => 'occ@example.com'
        ]);
        $booking_id = $wpdb->insert_id;
        $wpdb->insert($wpdb->prefix . "snippen_bookings_booking_objects", [
            'booking_id' => $booking_id,
            'booking_object_id' => $this->objectId
        ]);

        // 1. "Formiddag" (starts 08:00) on Day 2 should be unavailable
        $this->assertFalse($this->service->isSlotAvailable($this->objectId, $day2, $slot_formiddag), 'Formiddag should be blocked by yesterday cleanup');
        
        // 2. "Ettermiddag" (starts 16:00) on Day 2 should be available
        $this->assertTrue($this->service->isSlotAvailable($this->objectId, $day2, $slot_ettermiddag), 'Ettermiddag should be free after cleanup finishes at 12:00');
    }

    /**
     * Test cleanup from ettermiddag blocking next morning
     */
    public function test_ettermiddag_cleanup_overlap() {
        global $wpdb;
        $day1 = '2026-08-01'; // Saturday
        $day2 = '2026-08-02'; // Sunday

        $slot_ettermiddag = $this->getSlotId('Festsalen - Ettermiddag (Helg)');
        $slot_formiddag = $this->getSlotId('Festsalen - Formiddag (Helg)');
        
        // Book "Ettermiddag" (16:00-23:00) on Day 1
        // 9h cleanup = Occupied until Day 2 08:00
        $wpdb->insert($wpdb->prefix . "snippen_bookings", [
            'slot_id' => $slot_ettermiddag,
            'booking_date' => $day1,
            'customer_name' => 'Late Night',
            'customer_email' => 'late@example.com'
        ]);
        $booking_id = $wpdb->insert_id;
        $wpdb->insert($wpdb->prefix . "snippen_bookings_booking_objects", [
            'booking_id' => $booking_id,
            'booking_object_id' => $this->objectId
        ]);

        // "Formiddag" (starts 08:00) on Day 2 should be available (exact edge case)
        $this->assertTrue($this->service->isSlotAvailable($this->objectId, $day2, $slot_formiddag), 'Formiddag should be available as cleanup ends exactly at its start');
    }

    /**
     * Test that availability check is isolated to the booking object
     */
    public function test_object_isolation() {
        global $wpdb;
        $date = '2026-09-01'; // Tuesday

        $slot_formiddag_obj1 = $this->getSlotId('Festsalen - Formiddag (Hverdag)');
        $slot_formiddag_obj2 = $this->getSlotId('Peisestuen - Formiddag (Hverdag)');
        
        // Book "Formiddag" on Object 1
        $wpdb->insert($wpdb->prefix . "snippen_bookings", [
            'slot_id' => $slot_formiddag_obj1,
            'booking_date' => $date,
            'customer_name' => 'Obj1 User',
            'customer_email' => 'obj1@example.com'
        ]);
        $booking_id = $wpdb->insert_id;
        $wpdb->insert($wpdb->prefix . "snippen_bookings_booking_objects", [
            'booking_id' => $booking_id,
            'booking_object_id' => 1
        ]);

        // Object 2 should still be available
		$this->assertTrue($this->service->isSlotAvailable(2, $date, $slot_formiddag_obj2), 'Object 2 should be available even if Object 1 is booked');
	}

	/**
	 * Test that cancelled bookings do not block slots
	 */
	public function test_cancelled_booking_does_not_block_slot() {
		global $wpdb;
		$date = '2026-10-01'; // Thursday

		$slot_formiddag = $this->getSlotId('Festsalen - Formiddag (Hverdag)');

		// Book "Formiddag" and mark as cancelled
		$wpdb->insert($wpdb->prefix . "snippen_bookings", [
			'slot_id' => $slot_formiddag,
			'booking_date' => $date,
			'customer_name' => 'Cancelled User',
			'customer_email' => 'cancelled@example.com',
			'status' => 'cancelled'
		]);
		$booking_id = $wpdb->insert_id;
		$wpdb->insert($wpdb->prefix . "snippen_bookings_booking_objects", [
			'booking_id' => $booking_id,
			'booking_object_id' => $this->objectId
		]);

		// Slot should be available
		$this->assertTrue($this->service->isSlotAvailable($this->objectId, $date, $slot_formiddag), 'Slot should be available if the booking is cancelled');
	}

	/**
	 * Test that holiday logic correctly ignores normal weekdays and only applies '7'
	 */
	public function test_christmas_eve_holiday_logic() {
		// Christmas Eve 2026 is a Thursday (day 4), but it's a holiday (is_holiday = true)
		$date_str = '2026-12-24';
		$is_holiday = true;

		// 1. Slot with ONLY weekdays (1,2,3,4) should NOT match on Christmas Eve
		$weekday_slot = (object) [
			'id' => 1,
			'days_of_week' => '1,2,3,4',
			'date_start' => null,
			'date_end' => null
		];
		$this->assertFalse(
			$this->service->isSlotApplicable($weekday_slot, $date_str, $is_holiday),
			'Weekday slot should NOT be applicable on a holiday even if it falls on a Thursday'
		);

		// 2. Slot with ONLY holidays (7) SHOULD match on Christmas Eve
		$holiday_slot = (object) [
			'id' => 2,
			'days_of_week' => '7',
			'date_start' => null,
			'date_end' => null
		];
		$this->assertTrue(
			$this->service->isSlotApplicable($holiday_slot, $date_str, $is_holiday),
			'Holiday slot SHOULD be applicable on a holiday'
		);

		// 3. Slot with both weekdays and holidays (1,2,3,4,7) SHOULD match on Christmas Eve
		$mixed_slot = (object) [
			'id' => 3,
			'days_of_week' => '1,2,3,4,7',
			'date_start' => null,
			'date_end' => null
		];
		$this->assertTrue(
			$this->service->isSlotApplicable($mixed_slot, $date_str, $is_holiday),
			'Mixed slot SHOULD be applicable on a holiday if 7 is included'
		);

		// 4. Test date_start filtering
		$future_slot = (object) [
			'id' => 4,
			'days_of_week' => '7',
			'date_start' => '2027-01-01',
			'date_end' => null
		];
		$this->assertFalse(
			$this->service->isSlotApplicable($future_slot, $date_str, $is_holiday),
			'Slot with future date_start should NOT be applicable'
		);

		// 5. Normal Thursday (not a holiday)
		$normal_thursday = '2026-12-10'; // A regular Thursday
		$this->assertTrue(
			$this->service->isSlotApplicable($weekday_slot, $normal_thursday, false),
			'Weekday slot SHOULD be applicable on a regular Thursday'
		);
		$this->assertFalse(
			$this->service->isSlotApplicable($holiday_slot, $normal_thursday, false),
			'Holiday slot should NOT be applicable on a regular Thursday'
		);
	}
}
