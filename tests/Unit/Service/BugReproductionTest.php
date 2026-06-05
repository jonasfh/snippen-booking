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
		$wpdb->query("DELETE FROM " . $wpdb->prefix . "snippen_booking_booking_blocks");
		$wpdb->query("DELETE FROM " . $wpdb->prefix . "snippen_booking_booking_objects");
	}

	private function getBlockId( $name ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}snippen_booking_blocks WHERE name = %s LIMIT 1", $name ) );
	}

	public function test_edge_to_edge_overlap_is_allowed() {
		global $wpdb;
		$day1 = '2026-05-14'; // Thursday

		$block_08_09 = $this->getBlockId('08-09');
		$block_09_10 = $this->getBlockId('09-10');

		// Insert booking for block 08-09 on Day 1
		$wpdb->insert($wpdb->prefix . "snippen_bookings", [
			'uuid'           => 'bug-uuid-1',
			'booking_date'   => $day1,
			'user_id'        => 1,
			'customer_name'  => 'Thursday Booking',
			'customer_email' => 'torsdag@example.com'
		]);
		$booking_id = $wpdb->insert_id;
		$wpdb->insert($wpdb->prefix . "snippen_booking_booking_objects", [
			'booking_id'        => $booking_id,
			'booking_object_id' => $this->objectId
		]);
		$wpdb->insert($wpdb->prefix . "snippen_booking_booking_blocks", [
			'booking_id'       => $booking_id,
			'booking_block_id' => $block_08_09
		]);

		// Check that block 09-10 is available (edge-to-edge)
		$this->assertTrue( $this->service->isBlockAvailable( $this->objectId, $day1, $block_09_10 ), '09-10 block should be available even if 08-09 is booked' );
	}

	public function test_isolation_from_other_days() {
		$day = '2026-05-15';
		$block_08_09 = $this->getBlockId('08-09');
		$this->assertTrue( $this->service->isBlockAvailable( $this->objectId, $day, $block_08_09 ), 'Should be available when no bookings exist' );
	}
}
