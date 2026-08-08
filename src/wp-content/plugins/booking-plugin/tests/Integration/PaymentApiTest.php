<?php

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Api\UploadPaymentReceiptApi;
use SnippenBooking\Api\UpdatePaymentStatusApi;
use SnippenBooking\Service\PaymentService;

/**
 * Integration tests for Payment APIs
 */
class PaymentApiTest extends TestCase {

	/**
	 * Test update payment status by admin
	 */
	public function test_admin_update_payment_status() {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_bookings';

		// Create a test booking
		$wpdb->insert(
			$table,
			array(
				'customer_name'  => 'Betalingstest Kunde',
				'customer_email' => 'betaling@example.com',
				'booking_date'   => '2026-09-01',
				'user_id'        => 1,
				'price'          => 500,
				'payment_status_id' => 1,
			)
		);
		$booking_id = $wpdb->insert_id;

		// Set admin user
		$admin_id = wp_create_user( 'paymentadmin', 'password', 'paymentadmin@example.com' );
		$user = get_user_by( 'id', $admin_id );
		$user->add_role( 'administrator' );
		wp_set_current_user( $admin_id );

		$_POST['nonce']             = wp_create_nonce( 'snippen_admin_nonce' );
		$_POST['booking_id']        = $booking_id;
		$_POST['payment_status_id'] = 2; // PAID
		$_POST['payment_notes']     = 'Vipps ref #987654';

		try {
			UpdatePaymentStatusApi::update_payment_status();
		} catch ( \WpHttpException $e ) {
			// Catch die inside wp_send_json
		} catch ( \Exception $e ) {
			// Catch die
		}

		$updated_booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $booking_id ) );
		$this->assertEquals( 3, (int) $updated_booking->payment_status_id );
		$this->assertEquals( 'Vipps ref #987654', $updated_booking->payment_notes );
	}

	/**
	 * Test non-admin update payment status rejected
	 */
	public function test_non_admin_update_payment_status_forbidden() {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_bookings';

		$wpdb->insert(
			$table,
			array(
				'customer_name'  => 'Gjest Kunde',
				'customer_email' => 'gjest@example.com',
				'booking_date'   => '2026-09-02',
				'user_id'        => 1,
				'price'          => 200,
				'payment_status_id' => 1,
			)
		);
		$booking_id = $wpdb->insert_id;

		// Regular subscriber user
		$user_id = wp_create_user( 'regularuser', 'password', 'regularuser@example.com' );
		wp_set_current_user( $user_id );

		$_POST['nonce']             = wp_create_nonce( 'snippen_admin_nonce' );
		$_POST['booking_id']        = $booking_id;
		$_POST['payment_status_id'] = 2;

		$response = $this->catch_json_output( function() {
			UpdatePaymentStatusApi::update_payment_status();
		} );

		$this->assertFalse( $response['success'] );
		$this->assertEquals( 'Ingen tilgang.', $response['data']['message'] );
	}

	/**
	 * Helper to catch wp_send_json output
	 */
	private function catch_json_output( callable $func ) {
		ob_start();
		try {
			$func();
		} catch ( \Exception $e ) {
			// ignore wp_die
		}
		$output = ob_get_clean();
		return json_decode( $output, true );
	}
}
