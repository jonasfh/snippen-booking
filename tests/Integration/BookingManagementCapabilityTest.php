<?php

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Admin\UserProfile;
use SnippenBooking\Admin\AdminLoader;
use SnippenBooking\Api\BookingActionsApi;
use SnippenBooking\Api\BookingApi;

class BookingManagementCapabilityTest extends TestCase {

	private static $intercepted_emails = [];

	protected function setUp(): void {
		parent::setUp();
		UserProfile::register();
		AdminLoader::register();
		BookingActionsApi::register();
		BookingApi::register();

		self::$intercepted_emails = [];
		add_filter( 'pre_wp_mail', [ __CLASS__, 'intercept_wp_mail' ], 10, 2 );
	}

	protected function tearDown(): void {
		remove_filter( 'pre_wp_mail', [ __CLASS__, 'intercept_wp_mail' ], 10 );
		$_POST = [];
		parent::tearDown();
	}

	public static function intercept_wp_mail( $null, $args ) {
		self::$intercepted_emails[] = $args;
		return true; // prevent actual sending
	}

	public function test_user_profile_screen_shows_checkbox_to_allowed_users() {
		// Create a user with edit_users capability (e.g., admin)
		$admin_id = wp_insert_user( [
			'user_login' => 'profile_admin',
			'user_pass'  => 'password',
			'role'       => 'administrator',
		] );
		wp_set_current_user( $admin_id );

		$user_id = wp_insert_user( [
			'user_login' => 'profile_test_user',
			'user_pass'  => 'password',
			'role'       => 'subscriber',
		] );

		$user = get_userdata( $user_id );

		ob_start();
		UserProfile::render_user_fields( $user );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'name="manage_snippen_bookings"', $output );
		$this->assertStringContainsString( 'Booking administrator', $output );
		$this->assertStringContainsString( 'Can manage bookings and receive booking notifications.', $output );

		// Clean up
		wp_delete_user( $admin_id );
		wp_delete_user( $user_id );
	}

	public function test_user_profile_screen_saves_checkbox_value_and_grants_capability() {
		$admin_id = wp_insert_user( [
			'user_login' => 'profile_admin_2',
			'user_pass'  => 'password',
			'role'       => 'administrator',
		] );
		wp_set_current_user( $admin_id );

		$user_id = wp_insert_user( [
			'user_login' => 'profile_test_user_2',
			'user_pass'  => 'password',
			'role'       => 'subscriber',
		] );

		$user = get_userdata( $user_id );
		$this->assertFalse( user_can( $user, 'manage_snippen_bookings' ) );

		$_POST['snippen_booking_user_fields_nonce'] = wp_create_nonce( 'snippen_booking_user_fields' );
		$_POST['manage_snippen_bookings']           = 'yes';

		UserProfile::save_user_fields( $user_id );

		// Refresh user
		clean_user_cache( $user_id );
		$user = get_userdata( $user_id );
		$this->assertTrue( user_can( $user, 'manage_snippen_bookings' ) );

		// Clean up
		wp_delete_user( $admin_id );
		wp_delete_user( $user_id );
	}

	public function test_user_profile_screen_removes_capability_when_unchecked() {
		$admin_id = wp_insert_user( [
			'user_login' => 'profile_admin_3',
			'user_pass'  => 'password',
			'role'       => 'administrator',
		] );
		wp_set_current_user( $admin_id );

		$user_id = wp_insert_user( [
			'user_login' => 'profile_test_user_3',
			'user_pass'  => 'password',
			'role'       => 'subscriber',
		] );

		$user = get_userdata( $user_id );
		$user->add_cap( 'manage_snippen_bookings' );

		// Verify it was added
		clean_user_cache( $user_id );
		$user = get_userdata( $user_id );
		$this->assertTrue( user_can( $user, 'manage_snippen_bookings' ) );

		$_POST['snippen_booking_user_fields_nonce'] = wp_create_nonce( 'snippen_booking_user_fields' );
		// $_POST['manage_snippen_bookings'] is NOT set (meaning unchecked)

		UserProfile::save_user_fields( $user_id );

		// Refresh user
		clean_user_cache( $user_id );
		$user = get_userdata( $user_id );
		$this->assertFalse( user_can( $user, 'manage_snippen_bookings' ) );

		// Clean up
		wp_delete_user( $admin_id );
		wp_delete_user( $user_id );
	}

	public function test_administrator_implicitly_has_manage_snippen_bookings_dynamically() {
		$admin_id = wp_insert_user( [
			'user_login' => 'profile_admin_4',
			'user_pass'  => 'password',
			'role'       => 'administrator',
		] );

		$user = get_userdata( $admin_id );
		
		// It shouldn't be stored in DB permanently
		$stored_caps = get_user_meta( $admin_id, $user->cap_key, true );
		$this->assertArrayNotHasKey( 'manage_snippen_bookings', (array) $stored_caps );

		// Administrators no longer implicitly have this capability. They must be explicitly assigned.
		$this->assertFalse( user_can( $user, 'manage_snippen_bookings' ) );

		// Clean up
		wp_delete_user( $admin_id );
	}

	public function test_non_admin_with_capability_can_approve_or_cancel_bookings() {
		$user_id = wp_insert_user( [
			'user_login' => 'manager_user',
			'user_pass'  => 'password',
			'role'       => 'subscriber',
		] );
		$user = get_userdata( $user_id );
		$user->add_cap( 'manage_snippen_bookings' );
		wp_set_current_user( $user_id );

		// Create a booking owned by someone else
		$other_id = wp_insert_user( [
			'user_login' => 'other_user',
			'user_pass'  => 'password',
			'role'       => 'subscriber',
		] );
		$booking_id = $this->create_test_booking( $other_id );

		$_POST['id']     = $booking_id;
		$_POST['status'] = 'confirmed';
		$_POST['nonce']  = wp_create_nonce( 'snippen_admin_nonce' );
		$_REQUEST['nonce'] = $_POST['nonce'];

		ob_start();
		try {
			BookingActionsApi::update_status();
		} catch ( \Exception $e ) {}
		ob_get_clean();

		global $wpdb;
		$status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$wpdb->prefix}snippen_bookings WHERE id = %d", $booking_id ) );
		$this->assertEquals( 'confirmed', $status );

		// Clean up
		wp_delete_user( $user_id );
		wp_delete_user( $other_id );
	}

	public function test_non_admin_without_capability_cannot_approve_bookings() {
		$user_id = wp_insert_user( [
			'user_login' => 'regular_user',
			'user_pass'  => 'password',
			'role'       => 'subscriber',
		] );
		wp_set_current_user( $user_id );

		$other_id = wp_insert_user( [
			'user_login' => 'other_user_2',
			'user_pass'  => 'password',
			'role'       => 'subscriber',
		] );
		$booking_id = $this->create_test_booking( $other_id );

		$_POST['id']     = $booking_id;
		$_POST['status'] = 'confirmed';
		$_POST['nonce']  = wp_create_nonce( 'snippen_admin_nonce' );
		$_REQUEST['nonce'] = $_POST['nonce'];

		ob_start();
		try {
			BookingActionsApi::update_status();
		} catch ( \Exception $e ) {}
		ob_get_clean();

		global $wpdb;
		$status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$wpdb->prefix}snippen_bookings WHERE id = %d", $booking_id ) );
		$this->assertEquals( 'pending', $status ); // Remains pending, not allowed to approve

		// Clean up
		wp_delete_user( $user_id );
		wp_delete_user( $other_id );
	}

	public function test_booking_notification_sent_only_to_users_with_capability() {
		$user_id_1 = wp_insert_user( [
			'user_login' => 'mgr_1_' . uniqid(),
			'user_email' => 'mgr1@example.com',
			'user_pass'  => 'password',
			'role'       => 'subscriber',
		] );
		$user1 = get_userdata( $user_id_1 );
		$user1->add_cap( 'manage_snippen_bookings' );

		// Regular administrator without explicit cap assigned (implicitly has it for checks but is NOT in get_users)
		$admin_id = wp_insert_user( [
			'user_login' => 'admin_no_explicit_cap_' . uniqid(),
			'user_email' => 'admin_no_cap@example.com',
			'user_pass'  => 'password',
			'role'       => 'administrator',
		] );

		// Regular subscriber
		$sub_id = wp_insert_user( [
			'user_login' => 'subscriber_no_cap_' . uniqid(),
			'user_email' => 'sub_no_cap@example.com',
			'user_pass'  => 'password',
			'role'       => 'subscriber',
		] );
		update_user_meta( $sub_id, 'snippen_phone', '+4799887766' );

		// Enable admin emails explicitly
		update_option( 'snippen_email_admin_booking_enabled', 'yes' );

		// Make a booking
		wp_set_current_user( $sub_id );

		global $wpdb;

		$slot_id = 1;
		$object_id = 1;

		$_POST['nonce'] = wp_create_nonce( 'snippen_booking_nonce' );
		$_POST['booking_object_id'] = $object_id;
		$_POST['event_date'] = date( 'Y-m-d', strtotime( '+2 days' ) );
		$_POST['slot_id'] = $slot_id;
		$_POST['name'] = 'Beboer Test';
		$_POST['email'] = 'sub_no_cap@example.com';
		$_POST['description'] = 'Party';
		$_POST['accept_terms'] = '1';

		ob_start();
		try {
			BookingApi::submit_booking();
		} catch ( \Exception $e ) {}
		ob_get_clean();

		// Trigger scheduled background notifications
		$booking = $wpdb->get_row("SELECT id, uuid FROM {$wpdb->prefix}snippen_bookings ORDER BY id DESC LIMIT 1");
		if ( $booking ) {
			do_action( 'snippen_booking_send_notifications', $booking->id, $booking->uuid );
		}

		$this->assertNotEmpty( self::$intercepted_emails );
		$recipient_emails = [];
		foreach ( self::$intercepted_emails as $email ) {
			if ( ( strpos( $email['subject'], 'Ny Bookingforespørsel' ) !== false || strpos( $email['subject'], 'New Booking Request' ) !== false ) && strpos( $email['subject'], 'Bekreftelse' ) === false ) {
				if ( is_array( $email['to'] ) ) {
					$recipient_emails = array_merge( $recipient_emails, $email['to'] );
				} else {
					$recipient_emails[] = $email['to'];
				}
			}
		}

		$this->assertContains( 'mgr1@example.com', $recipient_emails );
		$this->assertNotContains( 'admin_no_cap@example.com', $recipient_emails );
		$this->assertNotContains( 'sub_no_cap@example.com', $recipient_emails );

		// Clean up
		wp_delete_user( $user_id_1 );
		wp_delete_user( $admin_id );
		wp_delete_user( $sub_id );
	}

	private function create_test_booking( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_bookings';
		$wpdb->insert( $table, [
			'user_id'        => $user_id,
			'slot_id'        => 1,
			'booking_date'   => date( 'Y-m-d' ),
			'status'         => 'pending',
			'customer_name'  => 'Test',
			'customer_email' => 'test@test.com',
			'customer_phone' => '12345678',
			'created_at'     => current_time( 'mysql' ),
			'modified_at'    => current_time( 'mysql' ),
		] );
		return $wpdb->insert_id;
	}
}
