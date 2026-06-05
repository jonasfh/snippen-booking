<?php

namespace SnippenBooking\Tests\Unit\Service;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\DoorCodeService;
use SnippenBooking\Database\Install;

/**
 * Unit tests for DoorCodeService logic
 */
class DoorCodeServiceTest extends TestCase {

	private $objectId = 1;

	/**
	 * Set up the test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		global $wpdb;

		// Ensure tables and seed data exist
		Install::activate();

		// Clear bookings for test isolation
		$wpdb->query( "DELETE FROM " . $wpdb->prefix . "snippen_bookings" );
		$wpdb->query( "DELETE FROM " . $wpdb->prefix . "snippen_booking_booking_blocks" );
		$wpdb->query( "DELETE FROM " . $wpdb->prefix . "snippen_booking_booking_objects" );

		// Reset options
		delete_option( 'snippen_door_code_hours_before' );
		delete_option( 'snippen_door_code_hours_after' );
	}

	/**
	 * Test default settings and custom settings
	 */
	public function test_settings_handling() {
		// 1. Defaults
		$this->assertEquals( 24, DoorCodeService::get_hours_before() );
		$this->assertEquals( 2, DoorCodeService::get_hours_after() );

		// 2. Custom values
		update_option( 'snippen_door_code_hours_before', 12 );
		update_option( 'snippen_door_code_hours_after', 4 );

		$this->assertEquals( 12, DoorCodeService::get_hours_before() );
		$this->assertEquals( 4, DoorCodeService::get_hours_after() );
	}

	/**
	 * Test is_in_window logic
	 */
	public function test_is_in_window() {
		global $wpdb;

		// Configure window: 24h before to 2h after
		update_option( 'snippen_door_code_hours_before', 24 );
		update_option( 'snippen_door_code_hours_after', 2 );

		$mysql_now = current_time( 'mysql' );

		// 1. Booking in progress / upcoming (starts in 5 hours, ends in 8 hours)
		$booking_in = (object) array(
			'booking_date' => date( 'Y-m-d', strtotime( $mysql_now . ' + 5 hours' ) ),
			'start_time'   => date( 'H:i:s', strtotime( $mysql_now . ' + 5 hours' ) ),
			'end_time'     => date( 'H:i:s', strtotime( $mysql_now . ' + 8 hours' ) ),
		);
		$this->assertTrue( DoorCodeService::is_in_window( $booking_in ), 'Booking starting in 5 hours should be inside the 24h window.' );

		// 2. Booking starts in 30 hours (too far in future)
		$booking_far = (object) array(
			'booking_date' => date( 'Y-m-d', strtotime( $mysql_now . ' + 30 hours' ) ),
			'start_time'   => date( 'H:i:s', strtotime( $mysql_now . ' + 30 hours' ) ),
			'end_time'     => date( 'H:i:s', strtotime( $mysql_now . ' + 34 hours' ) ),
		);
		$this->assertFalse( DoorCodeService::is_in_window( $booking_far ), 'Booking starting in 30 hours should be outside the 24h window.' );

		// 3. Booking ended 5 hours ago (too far in past)
		$booking_past = (object) array(
			'booking_date' => date( 'Y-m-d', strtotime( $mysql_now . ' - 6 hours' ) ),
			'start_time'   => date( 'H:i:s', strtotime( $mysql_now . ' - 6 hours' ) ),
			'end_time'     => date( 'H:i:s', strtotime( $mysql_now . ' - 3 hours' ) ),
		);
		$this->assertFalse( DoorCodeService::is_in_window( $booking_past ), 'Booking ended 3 hours ago should be outside the 2h after window.' );
	}

	/**
	 * Test synchronization of booking door codes
	 */
	public function test_sync_booking_door_code() {
		global $wpdb;

		// Set active door code on the object
		$wpdb->update(
			$wpdb->prefix . 'snippen_booking_objects',
			array( 'door_code' => '9876' ),
			array( 'id' => $this->objectId )
		);

		// Get weekday block
		$block_id = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}snippen_booking_blocks LIMIT 1" );

		$mysql_now = current_time( 'mysql' );

		// Create a booking that is in the window (starts in 2 hours)
		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'uuid'           => 'doorcode-uuid-1',
				'user_id'        => 1,
				'booking_date'   => date( 'Y-m-d', strtotime( $mysql_now . ' + 2 hours' ) ),
				'customer_name'  => 'John Doe',
				'customer_email' => 'john@example.com',
				'status'         => 'confirmed',
				'door_code'      => null,
			)
		);
		$booking_id = $wpdb->insert_id;

		$wpdb->insert(
			$wpdb->prefix . 'snippen_booking_booking_objects',
			array(
				'booking_id'        => $booking_id,
				'booking_object_id' => $this->objectId,
			)
		);

		$wpdb->insert(
			$wpdb->prefix . 'snippen_booking_booking_blocks',
			array(
				'booking_id'       => $booking_id,
				'booking_block_id' => $block_id,
			)
		);

		// Retrieve booking
		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT b.*, MIN(s.start_time) as start_time, MAX(s.end_time) as end_time 
				 FROM {$wpdb->prefix}snippen_bookings b
				 JOIN {$wpdb->prefix}snippen_booking_booking_blocks bb ON b.id = bb.booking_id
				 JOIN {$wpdb->prefix}snippen_booking_blocks s ON bb.booking_block_id = s.id
				 WHERE b.id = %d
				 GROUP BY b.id",
				$booking_id
			)
		);

		// Fake/force slot times so it is definitely in the window
		$booking->booking_date = date( 'Y-m-d', strtotime( $mysql_now . ' + 12 hours' ) );
		$booking->start_time   = '12:00:00';
		$booking->end_time     = '16:00:00';

		// Sync door code
		DoorCodeService::sync_booking_door_code( $booking );

		// Verify database value updated to 9876
		$db_door_code = $wpdb->get_var( $wpdb->prepare( "SELECT door_code FROM {$wpdb->prefix}snippen_bookings WHERE id = %d", $booking_id ) );
		$this->assertEquals( '9876', $db_door_code );
		$this->assertEquals( '9876', $booking->door_code );

		// Now force the slot times so it is outside the window (past)
		$booking->booking_date = date( 'Y-m-d', strtotime( $mysql_now . ' - 3 days' ) );
		$booking->start_time   = '12:00:00';
		$booking->end_time     = '16:00:00';

		// Sync again
		DoorCodeService::sync_booking_door_code( $booking );

		// Verify database value is cleared
		$db_door_code = $wpdb->get_var( $wpdb->prepare( "SELECT door_code FROM {$wpdb->prefix}snippen_bookings WHERE id = %d", $booking_id ) );
		$this->assertNull( $db_door_code );
	}

	/**
	 * Test handling multiple booking objects and deduplicating equal codes
	 */
	public function test_multiple_objects_deduplication() {
		global $wpdb;

		// Seed a second booking object
		$wpdb->insert(
			$wpdb->prefix . 'snippen_booking_objects',
			array(
				'name'        => 'Lillesalen',
				'description' => 'Mindre sal',
				'door_code'   => '1234',
			)
		);
		$object2_id = $wpdb->insert_id;

		// Set object 1 door code to also be '1234'
		$wpdb->update(
			$wpdb->prefix . 'snippen_booking_objects',
			array( 'door_code' => '1234' ),
			array( 'id' => $this->objectId )
		);

		// Get weekday block
		$block_id = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}snippen_booking_blocks LIMIT 1" );

		$mysql_now = current_time( 'mysql' );

		// Create a booking
		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'uuid'           => 'doorcode-uuid-2',
				'user_id'        => 1,
				'booking_date'   => date( 'Y-m-d', strtotime( $mysql_now . ' + 1 hours' ) ),
				'customer_name'  => 'Duet User',
				'customer_email' => 'duet@example.com',
				'status'         => 'confirmed',
				'door_code'      => null,
			)
		);
		$booking_id = $wpdb->insert_id;

		// Link to BOTH objects
		$wpdb->insert(
			$wpdb->prefix . 'snippen_booking_booking_objects',
			array(
				'booking_id'        => $booking_id,
				'booking_object_id' => $this->objectId,
			)
		);
		$wpdb->insert(
			$wpdb->prefix . 'snippen_booking_booking_objects',
			array(
				'booking_id'        => $booking_id,
				'booking_object_id' => $object2_id,
			)
		);

		$wpdb->insert(
			$wpdb->prefix . 'snippen_booking_booking_blocks',
			array(
				'booking_id'       => $booking_id,
				'booking_block_id' => $block_id,
			)
		);

		// Retrieve booking
		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT b.*, MIN(s.start_time) as start_time, MAX(s.end_time) as end_time 
				 FROM {$wpdb->prefix}snippen_bookings b
				 JOIN {$wpdb->prefix}snippen_booking_booking_blocks bb ON b.id = bb.booking_id
				 JOIN {$wpdb->prefix}snippen_booking_blocks s ON bb.booking_block_id = s.id
				 WHERE b.id = %d
				 GROUP BY b.id",
				$booking_id
			)
		);

		// Fake/force slot times so it is inside the window
		$booking->booking_date = date( 'Y-m-d', strtotime( $mysql_now . ' + 12 hours' ) );
		$booking->start_time   = '12:00:00';
		$booking->end_time     = '16:00:00';

		// Sync door code
		DoorCodeService::sync_booking_door_code( $booking );

		// Since both objects have '1234' as code, it should deduplicate to a single '1234'
		$this->assertEquals( '1234', $booking->door_code );

		// Now change object 2 code to '5678'
		$wpdb->update(
			$wpdb->prefix . 'snippen_booking_objects',
			array( 'door_code' => '5678' ),
			array( 'id' => $object2_id )
		);

		// Sync again
		DoorCodeService::sync_booking_door_code( $booking );

		// Should combine them separated by comma
		$this->assertEquals( '1234, 5678', $booking->door_code );
	}
}
