<?php

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Api\UploadPaymentReceiptApi;
use SnippenBooking\Api\UpdatePaymentStatusApi;
use SnippenBooking\Service\PaymentService;
use SnippenBooking\Service\Notification\MessageLoggerService;

/**
 * Integration tests for Payment APIs
 */
class PaymentApiTest extends TestCase {

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		if ( ! defined( 'DOING_AJAX' ) ) {
			define( 'DOING_AJAX', true );
		}
		add_filter(
			'wp_die_ajax_handler',
			function () {
				return function ( $message ) {
					throw new \Exception( is_string( $message ) ? $message : wp_json_encode( $message ) );
				};
			}
		);
	}
	public function test_admin_update_payment_status() {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_bookings';

		// Create a test booking
		$wpdb->insert(
			$table,
			array(
				'customer_name'     => 'Betalingstest Kunde',
				'customer_email'    => 'betaling@example.com',
				'booking_date'      => '2026-09-01',
				'user_id'           => 1,
				'price'             => 500,
				'payment_status_id' => 1,
				'status'            => 'pending',
				'created_at'        => current_time( 'mysql' ),
				'modified_at'       => current_time( 'mysql' ),
			)
		);
		$booking_id = $wpdb->insert_id;

		// Set admin user
		$admin_id = wp_create_user( 'paymentadmin', 'password', 'paymentadmin@example.com' );
		$user     = get_user_by( 'id', $admin_id );
		$user->add_role( 'administrator' );
		$user->add_cap( \SnippenBooking\Helper\Capabilities::MANAGE_BOOKINGS );
		wp_set_current_user( $admin_id );

		update_option( 'snippen_email_payment_received_enabled', 'yes' );

		$_POST['nonce']             = wp_create_nonce( 'snippen_admin_nonce' );
		$_REQUEST['nonce']          = $_POST['nonce'];
		$_POST['booking_id']        = $booking_id;
		$_POST['payment_status_id'] = 2; // PAID
		$_POST['payment_notes']     = 'Vipps ref #987654';

		ob_start();
		try {
			UpdatePaymentStatusApi::update_payment_status();
		} catch ( \Exception $e ) {
			// Intentionally empty: catch die inside wp_send_json.
			unset( $e );
		}
		ob_end_clean();

		$updated_booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $booking_id ) );
		$this->assertEquals( 2, (int) $updated_booking->payment_status_id );
		$this->assertEquals( 'confirmed', $updated_booking->status );
		$this->assertEquals( 'Vipps ref #987654', $updated_booking->payment_notes );

		// Verify that payment_received and booking_confirmed notifications were logged
		$messages = MessageLoggerService::get_messages_for_booking( $booking_id );
		$this->assertNotEmpty( $messages );

		$payment_messages = array_filter(
			$messages,
			function ( $m ) {
				return $m->event_type === 'payment_received';
			}
		);
		$this->assertNotEmpty( $payment_messages );

		$confirmed_messages = array_filter(
			$messages,
			function ( $m ) {
				return $m->event_type === 'booking_confirmed';
			}
		);
		$this->assertNotEmpty( $confirmed_messages );
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
				'customer_name'     => 'Gjest Kunde',
				'customer_email'    => 'gjest@example.com',
				'booking_date'      => '2026-09-02',
				'user_id'           => 1,
				'price'             => 200,
				'payment_status_id' => 1,
				'created_at'        => current_time( 'mysql' ),
				'modified_at'       => current_time( 'mysql' ),
			)
		);
		$booking_id = $wpdb->insert_id;

		// Regular subscriber user
		$user_id = wp_create_user( 'regularuser', 'password', 'regularuser@example.com' );
		wp_set_current_user( $user_id );

		$_POST['nonce']             = wp_create_nonce( 'snippen_admin_nonce' );
		$_REQUEST['nonce']          = $_POST['nonce'];
		$_POST['booking_id']        = $booking_id;
		$_POST['payment_status_id'] = 2;

		$response = $this->catch_json_output(
			function () {
				UpdatePaymentStatusApi::update_payment_status();
			}
		);

		$this->assertFalse( $response['success'] );
		$this->assertEquals( 'Ingen tilgang.', $response['data']['message'] );
	}

	/**
	 * Test uploading a payment receipt places file in userdata/user_id_<id>/booking_id_<id>/
	 */
	public function test_upload_payment_receipt_custom_directory() {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_bookings';

		// Create object and link
		$wpdb->insert(
			$wpdb->prefix . 'snippen_booking_objects',
			array(
				'name'       => 'Testlokale',
				'created_at' => current_time( 'mysql' ),
			)
		);
		$obj_id = $wpdb->insert_id;

		$uuid = wp_generate_uuid4();
		$wpdb->insert(
			$table,
			array(
				'uuid'              => $uuid,
				'user_id'           => 42,
				'customer_name'     => 'Isolated Upload User',
				'customer_email'    => 'isolated@example.com',
				'booking_date'      => '2026-09-05',
				'price'             => 300,
				'payment_status_id' => 1,
				'created_at'        => current_time( 'mysql' ),
				'modified_at'       => current_time( 'mysql' ),
			)
		);
		$booking_id = $wpdb->insert_id;

		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings_booking_objects',
			array(
				'booking_id'        => $booking_id,
				'booking_object_id' => $obj_id,
				'created_at'        => current_time( 'mysql' ),
			)
		);

		update_option( 'snippen_email_payment_receipt_uploaded_enabled', 'yes' );
		update_option( 'snippen_payment_admin_emails', 'admin@example.com' );

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$filter_action = function () {
			return 'wp_handle_sideload';
		};
		add_filter( 'snippen_upload_payment_receipt_action', $filter_action );

		$filter_overrides = function ( $overrides ) {
			$overrides['test_form'] = false;
			return $overrides;
		};
		add_filter( 'snippen_upload_payment_receipt_overrides', $filter_overrides );

		// Create a temporary dummy PNG file
		$temp_file = wp_tempnam( 'test_receipt.png' );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$png_content = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $temp_file, $png_content );

		$_FILES['payment_receipt'] = array(
			'name'     => 'test-receipt.png',
			'type'     => 'image/png',
			'tmp_name' => $temp_file,
			'error'    => UPLOAD_ERR_OK,
			'size'     => strlen( $png_content ),
		);

		$_POST['booking_uuid'] = $uuid;

		$response = $this->catch_json_output(
			function () {
				UploadPaymentReceiptApi::upload_receipt();
			}
		);

		remove_filter( 'snippen_upload_payment_receipt_action', $filter_action );
		remove_filter( 'snippen_upload_payment_receipt_overrides', $filter_overrides );

		$this->assertTrue( $response['success'] );
		$this->assertArrayHasKey( 'attachment_url', $response['data'] );
		$this->assertStringContainsString( '/userdata/booking_uuid_' . $uuid . '/', $response['data']['attachment_url'] );

		// Verify notification was sent and logged
		$messages         = MessageLoggerService::get_messages_for_booking( $booking_id );
		$receipt_messages = array_filter(
			$messages,
			function ( $m ) {
				return $m->event_type === 'payment_receipt_uploaded';
			}
		);
		$this->assertNotEmpty( $receipt_messages );

		// Clean up
		if ( file_exists( $temp_file ) ) {
			wp_delete_file( $temp_file );
		}
	}

	/**
	 * Helper to catch wp_send_json output
	 */
	private function catch_json_output( callable $func ) {
		ob_start();
		try {
			$func();
		} catch ( \Exception $e ) {
			// Intentionally empty: ignore wp_die exception.
			unset( $e );
		}
		$output = ob_get_clean();
		return json_decode( $output, true );
	}
}
