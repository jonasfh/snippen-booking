<?php
/**
 * SMS Fallback Integration Tests
 *
 * @package SnippenBooking\Tests\Integration
 */

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\AccountConfirmationService;
use SnippenBooking\Api\BookingApi;

/**
 * Integration tests for SMS fallbacks to email.
 */
class SmsFallbackTest extends TestCase {

	/**
	 * Captured emails during tests.
	 *
	 * @var array
	 */
	private static $sent_mails = array();

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}
		self::$sent_mails = array();
		add_filter( 'pre_wp_mail', array( $this, 'catch_mail' ), 10, 2 );
	}

	/**
	 * Tear down test environment.
	 */
	public function tearDown(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'catch_mail' ), 10 );
		parent::tearDown();
	}

	/**
	 * Intercept wp_mail calls and prevent actual sending.
	 *
	 * @param bool|null $send_status Current send status.
	 * @param array     $atts        Email attributes.
	 * @return bool
	 */
	public function catch_mail( $send_status, $atts ) {
		self::$sent_mails[] = $atts;
		return true; // Return true to prevent actual sending and signal success.
	}

	/**
	 * Test that a user confirmation code is sent via email if SMS confirmation is disabled.
	 */
	public function testAccountConfirmationEmailFallback() {
		// 1. Disable SMS confirmation.
		update_option( 'snippen_sms_account_confirmation_enabled', 'no' );

		// 2. Create test user.
		$username   = 'fallbacktest_' . time() . '_' . wp_rand( 0, 999 );
		$user_email = $username . '@example.com';
		$user_id    = wp_create_user( $username, 'password123', $user_email );

		if ( is_wp_error( $user_id ) ) {
			$this->fail( 'Failed to create test user: ' . $user_id->get_error_message() );
		}

		update_user_meta( $user_id, 'snippen_phone', '+4790000000' );

		// 3. Request code sending.
		$service = new AccountConfirmationService();
		$this->assertFalse( $service->is_confirmed( $user_id ) );

		$result = $service->send_code( $user_id );
		$this->assertTrue( $result );

		// 4. Assert email was intercepted.
		$this->assertCount( 1, self::$sent_mails );
		$mail = self::$sent_mails[0];

		$this->assertEquals( $user_email, $mail['to'] );
		$this->assertEquals( 'Bekreftelseskode for Snippen Booking', $mail['subject'] );

		$stored_code = get_user_meta( $user_id, 'snippen_confirmation_code', true );
		$this->assertNotEmpty( $stored_code );
		$this->assertStringContainsString( $stored_code, $mail['message'] );

		// Cleanup.
		wp_delete_user( $user_id );
	}

	/**
	 * Test that a booking confirmation email is sent to the customer if SMS confirmation is disabled.
	 */
	public function testBookingConfirmationEmailFallback() {
		global $wpdb;

		// 1. Disable SMS booking confirmation.
		update_option( 'snippen_sms_booking_confirmation_enabled', 'no' );

		// 2. Create booking objects and time slot.
		$wpdb->insert( $wpdb->prefix . 'snippen_booking_objects', array( 'name' => 'Fallback Room' ) );
		$obj_id = $wpdb->insert_id;

		$wpdb->insert(
			$wpdb->prefix . 'snippen_time_slots',
			array(
				'name'              => 'Hele dagen',
				'start_time'        => '10:00:00',
				'end_time'          => '22:00:00',
				'cleanup_hours'     => 0,
			)
		);
		$slot_id = $wpdb->insert_id;

		// 3. Create test user and set them as logged in.
		$username   = 'booker_' . time() . '_' . wp_rand( 0, 999 );
		$user_email = $username . '@example.com';
		$user_id    = wp_create_user( $username, 'password123', $user_email );

		if ( is_wp_error( $user_id ) ) {
			$this->fail( 'Failed to create test user: ' . $user_id->get_error_message() );
		}

		update_user_meta( $user_id, 'snippen_phone', '+4791111111' );
		wp_set_current_user( $user_id );

		// 4. Setup booking submission request parameters.
		$_POST['nonce']             = wp_create_nonce( 'snippen_booking_nonce' );
		$_POST['booking_object_id'] = array( $obj_id );
		$_POST['event_date']        = '2026-06-15';
		$_POST['slot_id']           = (string) $slot_id;
		$_POST['name']              = 'Fallback Customer';
		$_POST['email']             = 'fallback_customer@example.com';
		$_POST['description']       = 'Need fallback to work!';

		// 5. Submit booking and catch the expected AJAX termination exception.
		ob_start();
		try {
			BookingApi::submit_booking();
		} catch ( \Exception $e ) {
			// Catch AJAX termination/wp_die.
			$dummy = $e->getMessage();
		} catch ( \Throwable $t ) {
			// Catch other throwables if any.
			$dummy = $t->getMessage();
		}
		ob_get_clean();

		// 6. Assert emails were sent.
		// There should be 2 emails: 1 to admin_email (notification of booking request) and 1 to customer (fallback booking details).
		$customer_mail = null;
		foreach ( self::$sent_mails as $mail ) {
			if ( 'fallback_customer@example.com' === $mail['to'] ) {
				$customer_mail = $mail;
				break;
			}
		}

		$this->assertNotNull( $customer_mail, 'Customer should have received a confirmation email.' );
		$this->assertEquals( 'Bekreftelse på din bookingforespørsel', $customer_mail['subject'] );
		$this->assertStringContainsString( 'Fallback Room', $customer_mail['message'] );
		$this->assertStringContainsString( '2026-06-15', $customer_mail['message'] );
		$this->assertStringContainsString( 'booking_uuid', $customer_mail['message'] );

		// Cleanup.
		wp_delete_user( $user_id );
	}
}
