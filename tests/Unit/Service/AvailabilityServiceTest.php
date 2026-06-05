<?php

namespace SnippenBooking\Tests\Unit\Service;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\AvailabilityService;
use SnippenBooking\Database\Install;

/**
 * Unit tests for AvailabilityService logic using blocks
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
		$wpdb->query("DELETE FROM " . $wpdb->prefix . "snippen_booking_booking_blocks");
		$wpdb->query("DELETE FROM " . $wpdb->prefix . "snippen_booking_booking_objects");
	}

	private function getBlockId( $name ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}snippen_booking_blocks WHERE name = %s LIMIT 1", $name ) );
	}

	/**
	 * Test simple same-day overlap
	 */
	public function test_same_day_overlap() {
		global $wpdb;
		$date = '2026-06-01'; // Monday

		$block_08_09 = $this->getBlockId('08-09');
		$block_09_10 = $this->getBlockId('09-10');

		// Book "08-09"
		$wpdb->insert( $wpdb->prefix . "snippen_bookings", [
			'uuid'           => 'test-uuid-1',
			'booking_date'   => $date,
			'user_id'        => 1,
			'customer_name'  => 'John Doe',
			'customer_email' => 'john@example.com'
		]);
		$booking_id = $wpdb->insert_id;
		$wpdb->insert( $wpdb->prefix . "snippen_booking_booking_objects", [
			'booking_id'        => $booking_id,
			'booking_object_id' => $this->objectId
		]);
		$wpdb->insert( $wpdb->prefix . "snippen_booking_booking_blocks", [
			'booking_id'       => $booking_id,
			'booking_block_id' => $block_08_09
		]);

		// Same block should be unavailable
		$this->assertFalse( $this->service->isBlockAvailable( $this->objectId, $date, $block_08_09 ), 'Same block should be unavailable' );

		// Non-overlapping block "09-10" should be available
		$this->assertTrue( $this->service->isBlockAvailable( $this->objectId, $date, $block_09_10 ), 'Non-overlapping block should be available' );
	}

	/**
	 * Test that availability check is isolated to the booking object
	 */
	public function test_object_isolation() {
		global $wpdb;
		$date = '2026-09-01'; // Tuesday

		$block_08_09 = $this->getBlockId('08-09');

		// Book on Object 1
		$wpdb->insert( $wpdb->prefix . "snippen_bookings", [
			'uuid'           => 'test-uuid-2',
			'booking_date'   => $date,
			'user_id'        => 1,
			'customer_name'  => 'Obj1 User',
			'customer_email' => 'obj1@example.com'
		]);
		$booking_id = $wpdb->insert_id;
		$wpdb->insert( $wpdb->prefix . "snippen_booking_booking_objects", [
			'booking_id'        => $booking_id,
			'booking_object_id' => 1
		]);
		$wpdb->insert( $wpdb->prefix . "snippen_booking_booking_blocks", [
			'booking_id'       => $booking_id,
			'booking_block_id' => $block_08_09
		]);

		// Object 2 should still be available
		$this->assertTrue( $this->service->isBlockAvailable( 2, $date, $block_08_09 ), 'Object 2 should be available even if Object 1 is booked' );
	}

	/**
	 * Test that cancelled bookings do not block slots
	 */
	public function test_cancelled_booking_does_not_block_slot() {
		global $wpdb;
		$date = '2026-10-01'; // Thursday

		$block_08_09 = $this->getBlockId('08-09');

		// Book and mark as cancelled
		$wpdb->insert( $wpdb->prefix . "snippen_bookings", [
			'uuid'           => 'test-uuid-3',
			'booking_date'   => $date,
			'user_id'        => 1,
			'customer_name'  => 'Cancelled User',
			'customer_email' => 'cancelled@example.com',
			'status'         => 'cancelled'
		]);
		$booking_id = $wpdb->insert_id;
		$wpdb->insert( $wpdb->prefix . "snippen_booking_booking_objects", [
			'booking_id'        => $booking_id,
			'booking_object_id' => $this->objectId
		]);
		$wpdb->insert( $wpdb->prefix . "snippen_booking_booking_blocks", [
			'booking_id'       => $booking_id,
			'booking_block_id' => $block_08_09
		]);

		// Block should be available
		$this->assertTrue( $this->service->isBlockAvailable( $this->objectId, $date, $block_08_09 ), 'Block should be available if the booking is cancelled' );
	}

	/**
	 * Test that holiday logic correctly ignores normal weekdays and only applies '7'
	 */
	public function test_christmas_eve_holiday_logic() {
		$date_str = '2026-12-24'; // Christmas Eve 2026 (Thursday)
		$is_holiday = true;

		// 1. Block with ONLY weekdays (1,2,3,4) should NOT match on Christmas Eve holiday
		$weekday_block = (object) [
			'id'           => 1,
			'days_of_week' => '1,2,3,4',
		];
		$this->assertFalse(
			$this->service->isBlockApplicable( $weekday_block, $date_str, $is_holiday ),
			'Weekday block should NOT be applicable on a holiday even if it falls on a Thursday'
		);

		// 2. Block with ONLY holidays (7) SHOULD match on Christmas Eve
		$holiday_block = (object) [
			'id'           => 2,
			'days_of_week' => '7',
		];
		$this->assertTrue(
			$this->service->isBlockApplicable( $holiday_block, $date_str, $is_holiday ),
			'Holiday block SHOULD be applicable on a holiday'
		);
	}
}
