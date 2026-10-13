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
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'snippen_bookings' );
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'snippen_bookings_booking_objects' );

		// Reset settings options
		update_option( 'snippen_enable_door_code', 'yes' );
		delete_option( 'snippen_enable_doorman_api' );
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
	 * Test that applying a door code updates the booking and stores updated_at timestamp
	 */
	public function test_apply_door_code_updates_booking() {
		global $wpdb;

		$mysql_now = current_time( 'mysql' );

		// Create booking without door code
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

		// Apply door code via DoorCodeService
		$result = \SnippenBooking\Service\DoorCodeService::apply_door_code( $booking_id, '8888', false );
		$this->assertTrue( $result, 'apply_door_code should return true on success' );

		// Retrieve database value and verify it was updated
		$db_door_code = $wpdb->get_var( $wpdb->prepare( "SELECT door_code FROM {$wpdb->prefix}snippen_bookings WHERE id = %d", $booking_id ) );
		$this->assertEquals( '8888', $db_door_code, 'Door code should be saved to booking' );
	}

	/**
	 * Test that front-end booking details popup renders the door code / placeholder correctly
	 */
	public function test_front_end_popup_renders_door_code_correctly() {
		global $wpdb;

		$uuid      = wp_generate_uuid4();
		$mysql_now = current_time( 'mysql' );

		// Create active booking with door code
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
				'door_code'      => '5555',
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
			$admin_id = wp_insert_user(
				array(
					'user_login' => 'admin_door_code_test',
					'user_pass'  => 'password',
					'role'       => 'administrator',
				)
			);
		}
		$user_obj = get_userdata( $admin_id );
		$user_obj->add_cap( 'manage_snippen_bookings' );
		wp_set_current_user( $admin_id );

		$_GET['booking_uuid'] = $uuid;

		// 1. Render with code -> should display '5555'
		ob_start();
		Plugin::render_booking_popup();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Dørkode', $output );
		$this->assertStringContainsString( '5555', $output );

		// 2. Render without code -> should display placeholder 'Tildeles før leiestart'
		$wpdb->update(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'door_code' => null,
			),
			array( 'id' => $booking_id )
		);

		ob_start();
		Plugin::render_booking_popup();
		$output_out = ob_get_clean();

		$this->assertStringContainsString( 'Dørkode', $output_out );
		$this->assertStringContainsString( 'Tildeles før leiestart', $output_out );
	}

	/**
	 * Test that user bookings row expanded details displays the correct door code or placeholder
	 */
	public function test_user_bookings_row_renders_door_code_correctly() {
		global $wpdb;

		$mysql_now = current_time( 'mysql' );

		// Create booking with door code
		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'user_id'        => 1,
				'slot_id'        => 1,
				'booking_date'   => date( 'Y-m-d', strtotime( $mysql_now . ' + 1 hours' ) ),
				'customer_name'  => 'Jane Doe',
				'customer_email' => 'jane@doe.com',
				'status'         => 'confirmed',
				'door_code'      => '7777',
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

		$user_bookings_page = new UserBookingsPage();
		$reflection         = new \ReflectionClass( UserBookingsPage::class );
		$method             = $reflection->getMethod( 'render_booking_row' );
		$method->setAccessible( true );

		// 1. Render with code -> should display '7777'
		ob_start();
		$method->invoke( $user_bookings_page, $booking );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Dørkode:', $output );
		$this->assertStringContainsString( '7777', $output );

		// 2. Render without code -> should display the placeholder 'Tildeles før leiestart'
		$booking->door_code = null;

		ob_start();
		$method->invoke( $user_bookings_page, $booking );
		$output_out = ob_get_clean();

		$this->assertStringContainsString( 'Dørkode:', $output_out );
		$this->assertStringContainsString( 'Tildeles før leiestart', $output_out );
	}

	/**
	 * Test that door code fields and values are NOT rendered when door code system is disabled
	 */
	public function test_door_code_not_rendered_when_disabled() {
		global $wpdb;

		// Disable door code system
		update_option( 'snippen_enable_door_code', 'no' );
		update_option( 'snippen_enable_doorman_api', 'no' );

		// 1. Check popup details
		$uuid      = wp_generate_uuid4();
		$mysql_now = current_time( 'mysql' );
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
				'door_code'      => '9999',
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

		// Fake logged in user as admin
		$admin_id = wp_insert_user(
			array(
				'user_login' => 'admin_door_code_disabled_test',
				'user_pass'  => 'password',
				'role'       => 'administrator',
			)
		);
		wp_set_current_user( $admin_id );
		$_GET['booking_uuid'] = $uuid;

		ob_start();
		Plugin::render_booking_popup();
		$popup_output = ob_get_clean();

		$this->assertStringNotContainsString( 'Dørkode', $popup_output );

		// 2. Check user bookings row details
		$booking               = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT b.*, s.name as slot_name, s.start_time, s.end_time 
				 FROM {$wpdb->prefix}snippen_bookings b
				 JOIN {$wpdb->prefix}snippen_time_slots s ON b.slot_id = s.id
				 WHERE b.id = %d",
				$booking_id
			)
		);
		$booking->booking_date = date( 'Y-m-d', strtotime( $mysql_now . ' + 1 hours' ) );
		$booking->start_time   = date( 'H:i:s', strtotime( $mysql_now . ' + 1 hours' ) );
		$booking->end_time     = date( 'H:i:s', strtotime( $mysql_now . ' + 4 hours' ) );

		$user_bookings_page = new UserBookingsPage();
		$reflection         = new \ReflectionClass( UserBookingsPage::class );
		$method             = $reflection->getMethod( 'render_booking_row' );
		$method->setAccessible( true );

		ob_start();
		$method->invoke( $user_bookings_page, $booking );
		$row_output = ob_get_clean();

		$this->assertStringNotContainsString( 'Dørkode:', $row_output );
		$this->assertStringNotContainsString( '9999', $row_output );
	}
}
