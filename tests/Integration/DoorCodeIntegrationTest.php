<?php

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Plugin;
use SnippenBooking\Admin\Pages\UserBookingsPage;

class DoorCodeIntegrationTest extends TestCase {

	private $objectId = 1;

	protected function setUp(): void {
		parent::setUp();
		// Ensure migration is run and database is up to date
		Plugin::check_for_updates();

		global $wpdb;
		// Clear test data
		$wpdb->query( "DELETE FROM " . $wpdb->prefix . "snippen_bookings" );
		$wpdb->query( "DELETE FROM " . $wpdb->prefix . "snippen_bookings_booking_objects" );

		// Reset settings options
		delete_option( 'snippen_door_code_hours_before' );
		delete_option( 'snippen_door_code_hours_after' );
	}

	/**
	 * Test that migration 1.4.0 added the door_code columns successfully
	 */
	public function test_migration_adds_door_code_columns() {
		global $wpdb;
		$table_objects  = $wpdb->prefix . 'snippen_booking_objects';
		$table_bookings = $wpdb->prefix . 'snippen_bookings';

		$row_objects = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_objects' AND COLUMN_NAME = 'door_code'" );
		$this->assertNotEmpty( $row_objects, 'door_code column should exist on snippen_booking_objects table' );

		$row_bookings = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_bookings' AND COLUMN_NAME = 'door_code'" );
		$this->assertNotEmpty( $row_bookings, 'door_code column should exist on snippen_bookings table' );
	}

	/**
	 * Test that updating the booking object's door code propagates to upcoming bookings in window
	 */
	public function test_updating_object_door_code_propagates() {
		global $wpdb;

		// Set active door code on the object
		$wpdb->update(
			$wpdb->prefix . 'snippen_booking_objects',
			array( 'door_code' => '4321' ),
			array( 'id' => $this->objectId )
		);

		$mysql_now = current_time( 'mysql' );

		// Create booking in the window (starts in 1 hour)
		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'user_id'        => 1,
				'slot_id'        => 1,
				'booking_date'   => date( 'Y-m-d', strtotime( $mysql_now . ' + 1 hours' ) ),
				'customer_name'  => 'Test User',
				'customer_email' => 'test@example.com',
				'status'         => 'confirmed',
				'door_code'      => null,
			)
		);
		$booking_id = $wpdb->insert_id;

		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings_booking_objects',
			array(
				'booking_id'        => $booking_id,
				'booking_object_id' => $this->objectId,
			)
		);

		// 1. First set active door code to '8888' in the DB (like the edit controller does)
		$wpdb->update(
			$wpdb->prefix . 'snippen_booking_objects',
			array( 'door_code' => '8888' ),
			array( 'id' => $this->objectId )
		);

		// 2. Trigger active door code propagation on the booking object
		\SnippenBooking\Service\DoorCodeService::handle_object_door_code_change( $this->objectId, '8888' );

		// Retrieve database value and verify it was updated
		$db_door_code = $wpdb->get_var( $wpdb->prepare( "SELECT door_code FROM {$wpdb->prefix}snippen_bookings WHERE id = %d", $booking_id ) );
		$this->assertEquals( '8888', $db_door_code, 'Door code should propagate to booking when updated' );
	}

	/**
	 * Test that front-end booking details popup renders the door code / placeholder correctly
	 */
	public function test_front_end_popup_renders_door_code_correctly() {
		global $wpdb;

		$uuid = wp_generate_uuid4();
		$mysql_now = current_time( 'mysql' );

		// Set active door code on the object
		$wpdb->update(
			$wpdb->prefix . 'snippen_booking_objects',
			array( 'door_code' => '5555' ),
			array( 'id' => $this->objectId )
		);

		// Create active booking
		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'uuid'           => $uuid,
				'user_id'        => 1,
				'slot_id'        => 1,
				'booking_date'   => date( 'Y-m-d', strtotime( $mysql_now . ' + 1 hours' ) ),
				'customer_name'  => 'John Doe',
				'customer_email' => 'john@doe.com',
				'status'         => 'confirmed',
				'door_code'      => null,
			)
		);
		$booking_id = $wpdb->insert_id;

		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings_booking_objects',
			array(
				'booking_id'        => $booking_id,
				'booking_object_id' => $this->objectId,
			)
		);

		// Set current user as administrator to bypass views restrictions safely
		$user = get_user_by( 'login', 'admin_door_code_test' );
		if ( $user ) {
			$admin_id = $user->ID;
		} else {
			$admin_id = wp_insert_user( array(
				'user_login' => 'admin_door_code_test',
				'user_pass'  => 'password',
				'role'       => 'administrator',
			) );
		}
		$user_obj = get_userdata( $admin_id );
		$user_obj->add_cap('manage_snippen_bookings');
		wp_set_current_user( $admin_id );

		$_GET['booking_uuid'] = $uuid;

		// 1. Render in window -> should display '5555'
		ob_start();
		Plugin::render_booking_popup();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Dørkode', $output );
		$this->assertStringContainsString( '5555', $output );

		// 2. Render out of window -> should display the placeholder
		// Change booking date to 3 days from now
		$wpdb->update(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'booking_date' => date( 'Y-m-d', strtotime( $mysql_now . ' + 3 days' ) ),
				'door_code'    => null,
			),
			array( 'id' => $booking_id )
		);

		ob_start();
		Plugin::render_booking_popup();
		$output_out = ob_get_clean();

		$this->assertStringContainsString( 'Dørkode', $output_out );
		$this->assertStringContainsString( '&lt;Koden er ikke tilgjengelig før nærmere booking start&gt;', $output_out );
	}

	/**
	 * Test that user bookings row expanded details displays the correct door code or placeholder
	 */
	public function test_user_bookings_row_renders_door_code_correctly() {
		global $wpdb;

		$mysql_now = current_time( 'mysql' );

		// Set active door code on the object
		$wpdb->update(
			$wpdb->prefix . 'snippen_booking_objects',
			array( 'door_code' => '7777' ),
			array( 'id' => $this->objectId )
		);

		// Create booking in window
		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'user_id'        => 1,
				'slot_id'        => 1,
				'booking_date'   => date( 'Y-m-d', strtotime( $mysql_now . ' + 1 hours' ) ),
				'customer_name'  => 'Jane Doe',
				'customer_email' => 'jane@doe.com',
				'status'         => 'confirmed',
				'door_code'      => null,
			)
		);
		$booking_id = $wpdb->insert_id;

		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings_booking_objects',
			array(
				'booking_id'        => $booking_id,
				'booking_object_id' => $this->objectId,
			)
		);

		// Get booking row
		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT b.*, s.name as slot_name, s.start_time, s.end_time 
				 FROM {$wpdb->prefix}snippen_bookings b
				 JOIN {$wpdb->prefix}snippen_time_slots s ON b.slot_id = s.id
				 WHERE b.id = %d",
				$booking_id
			)
		);

		// Fake/force times to be in window
		$booking->booking_date = date( 'Y-m-d', strtotime( $mysql_now . ' + 1 hours' ) );
		$booking->start_time   = date( 'H:i:s', strtotime( $mysql_now . ' + 1 hours' ) );
		$booking->end_time     = date( 'H:i:s', strtotime( $mysql_now . ' + 4 hours' ) );

		$user_bookings_page = new UserBookingsPage();
		$reflection = new \ReflectionClass( UserBookingsPage::class );
		$method = $reflection->getMethod( 'render_booking_row' );
		$method->setAccessible( true );

		// 1. Render in window -> should display '7777'
		ob_start();
		$method->invoke( $user_bookings_page, $booking );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Dørkode:', $output );
		$this->assertStringContainsString( '7777', $output );

		// 2. Render out of window -> should display the placeholder
		$booking->booking_date = date( 'Y-m-d', strtotime( $mysql_now . ' + 5 days' ) );
		$booking->start_time   = date( 'H:i:s', strtotime( $mysql_now . ' + 5 days' ) );
		$booking->end_time     = date( 'H:i:s', strtotime( $mysql_now . ' + 5 days' ) );
		$booking->door_code    = null;

		ob_start();
		$method->invoke( $user_bookings_page, $booking );
		$output_out = ob_get_clean();

		$this->assertStringContainsString( 'Dørkode:', $output_out );
		$this->assertStringContainsString( '&lt;Koden er ikke tilgjengelig før nærmere booking start&gt;', $output_out );
	}
}
