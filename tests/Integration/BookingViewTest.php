<?php

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Plugin;

class BookingViewTest extends TestCase {

	public function setUp(): void {
		parent::setUp();
		// Ensure migration is run and database is up to date
		Plugin::check_for_updates();
	}

	/**
	 * Test that migration ran successfully and column exists
	 */
	public function test_migration_adds_uuid_column() {
		global $wpdb;
		$table_bookings = $wpdb->prefix . 'snippen_bookings';

		$row = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_bookings' AND COLUMN_NAME = 'uuid'" );
		$this->assertNotEmpty( $row, 'uuid column should exist on snippen_bookings table' );
	}

	/**
	 * Test that nothing is rendered when booking_uuid is not set
	 */
	public function test_does_not_render_without_uuid() {
		if ( isset( $_GET['booking_uuid'] ) ) {
			unset( $_GET['booking_uuid'] );
		}

		ob_start();
		Plugin::render_booking_popup();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Test that "Booking ikke funnet" is rendered for non-existent UUID
	 */
	public function test_renders_not_found_for_invalid_uuid() {
		$_GET['booking_uuid'] = 'non-existent-uuid-12345';

		ob_start();
		Plugin::render_booking_popup();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Booking ikke funnet', $output );
		$this->assertStringContainsString( 'snippen-booking-modal-overlay', $output );
	}

	/**
	 * Test that booking details are rendered for guests (logged-out users) with a valid UUID token
	 */
	public function test_renders_booking_details_for_guest_with_uuid() {
		// Mock NOT logged in
		wp_set_current_user( 0 );
		$uuid = wp_generate_uuid4();
		$this->create_test_booking_with_uuid( 1, $uuid, 'pending', 'Guest UUID Test Booking' );

		$_GET['booking_uuid'] = $uuid;

		ob_start();
		Plugin::render_booking_popup();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Bookingdetaljer', $output );
		$this->assertStringContainsString( 'Guest UUID Test Booking', $output );
		$this->assertStringNotContainsString( 'Logg inn kreves', $output );
	}

	/**
	 * Test that any user with a valid UUID token can view the booking
	 */
	public function test_renders_booking_details_for_other_user_with_uuid() {
		$owner_id = wp_insert_user([
			'user_login' => 'owner_user_' . uniqid(),
			'user_pass' => 'password',
			'role' => 'subscriber'
		]);

		$other_id = wp_insert_user([
			'user_login' => 'other_user_' . uniqid(),
			'user_pass' => 'password',
			'role' => 'subscriber'
		]);

		$uuid = wp_generate_uuid4();
		$this->create_test_booking_with_uuid( $owner_id, $uuid, 'pending', 'Other User UUID Booking' );

		// Set current user to other user
		wp_set_current_user( $other_id );
		$_GET['booking_uuid'] = $uuid;

		ob_start();
		Plugin::render_booking_popup();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Bookingdetaljer', $output );
		$this->assertStringContainsString( 'Other User UUID Booking', $output );
		$this->assertStringNotContainsString( 'Ingen tilgang', $output );
	}

	/**
	 * Test that owner can view their own booking
	 */
	public function test_owner_can_view_booking() {
		$owner_id = wp_insert_user([
			'user_login' => 'owner_user_2_' . uniqid(),
			'user_pass' => 'password',
			'role' => 'subscriber'
		]);

		$uuid = wp_generate_uuid4();
		$this->create_test_booking_with_uuid( $owner_id, $uuid, 'pending', 'My Booking Description' );

		wp_set_current_user( $owner_id );
		$_GET['booking_uuid'] = $uuid;

		ob_start();
		Plugin::render_booking_popup();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Bookingdetaljer', $output );
		$this->assertStringContainsString( 'My Booking Description', $output );
		$this->assertStringContainsString( 'snippen-status-pending', $output );
		$this->assertStringNotContainsString( 'Ingen tilgang', $output );
		$this->assertStringNotContainsString( 'Logg inn kreves', $output );
	}

	/**
	 * Test that administrator can view any booking
	 */
	public function test_admin_can_view_any_booking() {
		$owner_id = wp_insert_user([
			'user_login' => 'owner_user_3_' . uniqid(),
			'user_pass' => 'password',
			'role' => 'subscriber'
		]);

		$admin_id = wp_insert_user([
			'user_login' => 'admin_user_view_' . uniqid(),
			'user_pass' => 'password',
			'role' => 'administrator'
		]);
		$admin_user = get_userdata( $admin_id );
		$admin_user->add_cap( 'manage_snippen_bookings' );

		$uuid = wp_generate_uuid4();
		$this->create_test_booking_with_uuid( $owner_id, $uuid, 'confirmed', 'Admin Viewable Booking' );

		wp_set_current_user( $admin_id );
		$_GET['booking_uuid'] = $uuid;

		ob_start();
		Plugin::render_booking_popup();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Bookingdetaljer', $output );
		$this->assertStringContainsString( 'Admin Viewable Booking', $output );
		$this->assertStringContainsString( 'snippen-status-confirmed', $output );
		$this->assertStringNotContainsString( 'Ingen tilgang', $output );
	}

	/**
	 * Helper to create a test booking with a UUID
	 */
	private function create_test_booking_with_uuid( $user_id, $uuid, $status = 'pending', $desc = 'Test Booking' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_bookings';
		$wpdb->insert( $table, [
			'uuid' => $uuid,
			'user_id' => $user_id,
			'slot_id' => 1,
			'booking_date' => date('Y-m-d'),
			'status' => $status,
			'customer_name' => 'John Doe',
			'customer_email' => 'john@doe.com',
			'customer_phone' => '12345678',
			'description' => $desc,
			'price' => 250.00,
			'created_at' => current_time('mysql'),
			'modified_at' => current_time('mysql')
		] );
		return $wpdb->insert_id;
	}
}
