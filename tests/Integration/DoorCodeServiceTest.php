<?php

namespace SnippenBooking\Tests\Integration\Service;

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
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'snippen_bookings' );
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'snippen_booking_booking_blocks' );
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'snippen_booking_booking_objects' );

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
	 * Test is_in_window logic: true when enabled and code is present, false otherwise
	 */
	public function test_is_in_window() {
		// When disabled
		update_option( 'snippen_enable_door_code', 'no' );
		update_option( 'snippen_enable_doorman_api', 'no' );

		$booking_with_code = (object) array( 'door_code' => '1234' );
		$this->assertFalse( DoorCodeService::is_in_window( $booking_with_code ) );

		// When enabled
		update_option( 'snippen_enable_door_code', 'yes' );

		$this->assertTrue( DoorCodeService::is_in_window( $booking_with_code ) );

		$booking_without_code = (object) array( 'door_code' => null );
		$this->assertFalse( DoorCodeService::is_in_window( $booking_without_code ) );

		$booking_empty_code = (object) array( 'door_code' => '' );
		$this->assertFalse( DoorCodeService::is_in_window( $booking_empty_code ) );
	}

	/**
	 * Test get_access_window calculation including buffer and grace
	 */
	public function test_get_access_window() {
		global $wpdb;

		update_option( 'snippen_enable_door_code', 'yes' );
		update_option( 'snippen_doorman_buffer_minutes_before', 30 );
		update_option( 'snippen_doorman_grace_minutes_after', 120 );

		$booking_date = date( 'Y-m-d', strtotime( '+1 day' ) );

		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'uuid'             => 'test-uuid-access-window',
				'user_id'          => 1,
				'booking_date'     => $booking_date,
				'customer_name'    => 'Test Person',
				'customer_email'   => 'test@example.com',
				'status'           => 'confirmed',
				'booking_snapshot' => wp_json_encode(
					array(
						'start_time' => '12:00:00',
						'end_time'   => '16:00:00',
					)
				),
			)
		);
		$booking_id = $wpdb->insert_id;

		$window = DoorCodeService::get_access_window( $booking_id );

		$expected_start_ts = strtotime( $booking_date . ' 12:00:00' ) - ( 30 * 60 );
		$expected_end_ts   = strtotime( $booking_date . ' 16:00:00' ) + ( 120 * 60 );

		$this->assertEquals( $expected_start_ts, $window['calc_start'] );
		$this->assertEquals( $expected_end_ts, $window['calc_end'] );
		$this->assertNotEmpty( $window['valid_from'] );
		$this->assertNotEmpty( $window['valid_to'] );
		$this->assertNotEmpty( $window['validity_period'] );
		$this->assertContains( $booking_id, $window['booking_ids'] );
	}

	/**
	 * Test apply_door_code updates chain and handles validation
	 */
	public function test_apply_door_code_and_chain() {
		global $wpdb;

		$booking_date = date( 'Y-m-d', strtotime( '+2 days' ) );

		// Create booking 1 for user 1
		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'user_id'          => 1,
				'booking_date'     => $booking_date,
				'customer_name'    => 'Ola Nordmann',
				'customer_email'   => 'ola@example.com',
				'status'           => 'confirmed',
				'booking_snapshot' => wp_json_encode(
					array(
						'start_time' => '10:00:00',
						'end_time'   => '14:00:00',
					)
				),
			)
		);
		$booking1_id = $wpdb->insert_id;

		// Create contiguous booking 2 for user 1 on same day
		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'user_id'          => 1,
				'booking_date'     => $booking_date,
				'customer_name'    => 'Ola Nordmann',
				'customer_email'   => 'ola@example.com',
				'status'           => 'confirmed',
				'booking_snapshot' => wp_json_encode(
					array(
						'start_time' => '14:00:00',
						'end_time'   => '18:00:00',
					)
				),
			)
		);
		$booking2_id = $wpdb->insert_id;

		// 1. Validation error for invalid code format
		$err = DoorCodeService::apply_door_code( $booking1_id, '12', false );
		$this->assertInstanceOf( \WP_Error::class, $err );
		$this->assertEquals( 'rest_invalid_param', $err->get_error_code() );

		// 2. Successful apply updates both bookings in chain
		$res = DoorCodeService::apply_door_code( $booking1_id, '4567', false );
		$this->assertTrue( $res );

		$code1 = $wpdb->get_var( $wpdb->prepare( "SELECT door_code FROM {$wpdb->prefix}snippen_bookings WHERE id = %d", $booking1_id ) );
		$code2 = $wpdb->get_var( $wpdb->prepare( "SELECT door_code FROM {$wpdb->prefix}snippen_bookings WHERE id = %d", $booking2_id ) );

		$this->assertEquals( '4567', $code1 );
		$this->assertEquals( '4567', $code2 );
	}
}
