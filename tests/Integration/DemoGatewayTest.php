<?php
/**
 * Integration test for SMS Gateway demo script and provisioning.
 *
 * @package SnippenBooking\Tests\Integration
 */

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Api\SmsGatewayApi;

/**
 * Class DemoGatewayTest
 */
class DemoGatewayTest extends TestCase {

	/**
	 * Requires database
	 */
	protected $requires_db = true;

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_messages" );
		$wpdb->query( 'COMMIT' );
	}

	/**
	 * Test that demo-gateway provisions options, user, booking, and outbox message
	 */
	public function test_demo_gateway_execution_and_rest_endpoints() {
		// Execute the demo gateway script
		$output = array();
		$code   = 0;
		exec( 'php ' . escapeshellarg( __DIR__ . '/../../bin/demo-gateway.php' ), $output, $code );
		$this->assertSame( 0, $code, 'demo-gateway.php should exit with 0' );

		// Flush options cache and sync transaction since external process modified DB
		global $wpdb;
		$wpdb->query( 'COMMIT' );
		wp_cache_flush();
		wp_load_alloptions( true );

		// 1. Verify Options
		$token = get_option( 'snippen_sms_service_api_token' );
		$this->assertNotEmpty( $token );
		$this->assertSame( 'snippen_sms_service', get_option( 'snippen_sms_provider' ) );
		$this->assertSame( 'snippen_sms_service', get_option( 'snippen_active_notification_provider' ) );
		$this->assertSame( 'yes', get_option( 'snippen_sms_booking_confirmation_enabled' ) );

		// 2. Verify User
		$user = get_user_by( 'login', 'test.guest' );
		$this->assertNotNull( $user );
		$this->assertSame( '+4799887766', get_user_meta( $user->ID, 'snippen_phone', true ) );

		// 3. Verify Booking
		global $wpdb;
		$table_bookings = $wpdb->prefix . 'snippen_bookings';
		$booking        = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_bookings} WHERE customer_phone = %s AND deleted_at IS NULL",
				'+4799887766'
			)
		);
		$this->assertNotNull( $booking );
		$this->assertSame( 'Ola Nordmann (E2E Test)', $booking->customer_name );
		$this->assertSame( 'confirmed', $booking->status );

		// 4. Verify REST Outbox endpoint with seeded message
		SmsGatewayApi::register();
		do_action( 'rest_api_init' );

		$request = new \WP_REST_Request( 'GET', '/snippen/v1/sms/outbox' );
		$request->add_header( 'Authorization', 'Bearer ' . $token );

		$response = SmsGatewayApi::get_outbox( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertNotEmpty( $data['messages'] );

		$found = false;
		foreach ( $data['messages'] as $msg ) {
			if ( $msg['recipient'] === '+4799887766' ) {
				$found = true;
				$this->assertSame( 'Din adgangskode til Snippen er 4821', $msg['body'] );
				break;
			}
		}
		$this->assertTrue( $found, 'Outbound queued message should be returned in /outbox' );

		// 5. Verify REST Bookings lookup endpoint
		$bookings_request = new \WP_REST_Request( 'GET', '/snippen/v1/sms/bookings' );
		$bookings_request->set_param( 'phone', '+4799887766' );
		$bookings_request->add_header( 'Authorization', 'Bearer ' . $token );

		$bookings_response = SmsGatewayApi::get_bookings( $bookings_request );
		$this->assertSame( 200, $bookings_response->get_status() );
		$bookings_data = $bookings_response->get_data();
		$this->assertNotEmpty( $bookings_data['bookings'] );
		$this->assertSame( '+4799887766', $bookings_data['bookings'][0]['customer_phone'] );

		// Clean up external modifications
		delete_option( 'snippen_sms_service_api_token' );
		delete_option( 'snippen_sms_service_sender' );
		delete_option( 'snippen_sms_provider' );
		delete_option( 'snippen_active_notification_provider' );
		delete_option( 'snippen_sms_booking_confirmation_enabled' );
		delete_option( 'snippen_sms_admin_booking_enabled' );
		delete_option( 'snippen_sms_user_activation_enabled' );
		delete_option( 'snippen_sms_payment_reminder_enabled' );
		delete_option( 'snippen_sms_payment_receipt_uploaded_enabled' );
		if ( $user ) {
			if ( ! function_exists( 'wp_delete_user' ) ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
			}
			wp_delete_user( $user->ID );
		}
		$wpdb->query( "DELETE FROM {$table_bookings} WHERE customer_phone = '+4799887766'" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_messages" );
		self::$db_seeded = false;
	}
}
