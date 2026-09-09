<?php
/**
 * Integration tests for Vipps REST Webhook API
 *
 * @package SnippenBooking\Tests\Integration
 */

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Api\VippsWebhookApi;
use SnippenBooking\Service\Vipps\VippsService;
use SnippenBooking\Service\Vipps\VippsClient;

/**
 * VippsWebhookIntegrationTest
 */
class VippsWebhookIntegrationTest extends TestCase {

	/**
	 * HTTP mock callback.
	 *
	 * @var callable|null
	 */
	protected $http_mock_callback = null;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();
		VippsWebhookApi::register();
		update_option( 'snippen_vipps_enabled', 'yes' );
		update_option( 'snippen_vipps_client_id', 'test_client_id' );
		update_option( 'snippen_vipps_client_secret', 'test_client_secret' );
		update_option( 'snippen_vipps_subscription_key', 'test_sub_key' );
		update_option( 'snippen_vipps_msn', '123456' );
	}

	/**
	 * Set mock HTTP filter.
	 *
	 * @param callable $callback Filter callback.
	 */
	protected function set_http_mock( callable $callback ) {
		if ( null !== $this->http_mock_callback ) {
			remove_filter( 'pre_http_request', $this->http_mock_callback, 10 );
		}
		$this->http_mock_callback = $callback;
		add_filter( 'pre_http_request', $this->http_mock_callback, 10, 3 );
	}

	/**
	 * Tear down test environment
	 */
	protected function tearDown(): void {
		if ( null !== $this->http_mock_callback ) {
			remove_filter( 'pre_http_request', $this->http_mock_callback, 10 );
			$this->http_mock_callback = null;
		}
		delete_option( 'snippen_vipps_enabled' );
		delete_option( 'snippen_vipps_client_id' );
		delete_option( 'snippen_vipps_client_secret' );
		delete_option( 'snippen_vipps_subscription_key' );
		delete_option( 'snippen_vipps_msn' );
		parent::tearDown();
	}

	/**
	 * Test that missing reference returns 400
	 */
	public function test_webhook_missing_reference_returns_400() {
		$request = new \WP_REST_Request( 'POST', '/snippen/v1/vipps/webhook' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'name' => 'epayments.payment.authorized' ) ) );

		$response = rest_do_request( $request );
		$this->assertEquals( 400, $response->get_status() );
		$data = $response->get_data();
		$this->assertFalse( $data['success'] );
	}

	/**
	 * Test that unknown booking reference returns 404
	 */
	public function test_webhook_unknown_booking_returns_404() {
		$request = new \WP_REST_Request( 'POST', '/snippen/v1/vipps/webhook' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'reference' => 'snippen-999999-12345678-123',
					'name'      => 'epayments.payment.authorized',
				)
			)
		);

		$response = rest_do_request( $request );
		$this->assertEquals( 404, $response->get_status() );
		$data = $response->get_data();
		$this->assertFalse( $data['success'] );
	}

	/**
	 * Test that authorized payment captures and confirms booking
	 */
	public function test_webhook_authorized_captures_payment_and_confirms_booking() {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_bookings';

		$uuid = wp_generate_uuid4();
		$wpdb->insert(
			$table,
			array(
				'uuid'              => $uuid,
				'user_id'           => 1,
				'booking_date'      => '2026-11-01',
				'customer_name'     => 'Webhook User',
				'customer_email'    => 'webhook@example.com',
				'customer_phone'    => '99887766',
				'booking_type'      => 'private',
				'price'             => 600.0,
				'payment_status_id' => 1, // UNPAID
				'status'            => 'pending_payment',
			)
		);
		$booking_id = (int) $wpdb->insert_id;
		$reference  = sprintf( 'snippen-%d-1718000000-456', $booking_id );

		$wpdb->update(
			$table,
			array( 'payment_notes' => 'Vipps ref: ' . $reference ),
			array( 'id' => $booking_id )
		);

		// Mock Vipps HTTP endpoints
		$captured = false;
		$this->set_http_mock(
			function ( $preempt, $parsed_args, $url ) use ( $reference, &$captured ) {
				if ( strpos( $url, '/accesstoken/get' ) !== false ) {
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode(
							array(
								'access_token' => 'mock_token_wh',
								'expires_in'   => 3600,
							)
						),
					);
				}
				if ( strpos( $url, '/capture' ) !== false ) {
					$captured = true;
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode(
							array(
								'reference' => $reference,
								'state'     => 'CAPTURED',
							)
						),
					);
				}
				if ( strpos( $url, '/epayment/v1/payments/' . $reference ) !== false ) {
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode(
							array(
								'reference' => $reference,
								'state'     => 'AUTHORIZED',
							)
						),
					);
				}
				return $preempt;
			}
		);

		$request = new \WP_REST_Request( 'POST', '/snippen/v1/vipps/webhook' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'reference' => $reference,
					'name'      => 'epayments.payment.authorized',
				)
			)
		);

		$response = rest_do_request( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertTrue( $captured, 'Capture should have been invoked' );

		// Check booking updated in DB
		$updated = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $booking_id ) );
		$this->assertEquals( 'confirmed', $updated->status );
		$this->assertEquals( 2, (int) $updated->payment_status_id ); // PAID
		$this->assertNotNull( $updated->payment_updated_at );
	}

	/**
	 * Test idempotency when receiving multiple authorized webhooks for same booking
	 */
	public function test_webhook_authorized_is_idempotent() {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_bookings';

		$uuid = wp_generate_uuid4();
		$wpdb->insert(
			$table,
			array(
				'uuid'              => $uuid,
				'user_id'           => 1,
				'booking_date'      => '2026-11-02',
				'customer_name'     => 'Idempotent User',
				'customer_email'    => 'idempotent@example.com',
				'customer_phone'    => '99887766',
				'booking_type'      => 'private',
				'price'             => 600.0,
				'payment_status_id' => 2, // Already PAID
				'status'            => 'confirmed', // Already confirmed
			)
		);
		$booking_id = (int) $wpdb->insert_id;
		$reference  = sprintf( 'snippen-%d-1718000000-789', $booking_id );

		$request = new \WP_REST_Request( 'POST', '/snippen/v1/vipps/webhook' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'reference' => $reference,
					'name'      => 'epayments.payment.authorized',
				)
			)
		);

		$response = rest_do_request( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertEquals( 'Booking already confirmed.', $data['message'] );
	}

	/**
	 * Test that terminated/cancelled payment marks pending booking as cancelled
	 */
	public function test_webhook_terminated_cancels_pending_booking() {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_bookings';

		$uuid = wp_generate_uuid4();
		$wpdb->insert(
			$table,
			array(
				'uuid'              => $uuid,
				'user_id'           => 1,
				'booking_date'      => '2026-11-03',
				'customer_name'     => 'Terminated User',
				'customer_email'    => 'terminated@example.com',
				'customer_phone'    => '99887766',
				'booking_type'      => 'private',
				'price'             => 600.0,
				'payment_status_id' => 1,
				'status'            => 'pending_payment',
			)
		);
		$booking_id = (int) $wpdb->insert_id;
		$reference  = sprintf( 'snippen-%d-1718000000-000', $booking_id );

		$request = new \WP_REST_Request( 'POST', '/snippen/v1/vipps/webhook' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'reference' => $reference,
					'name'      => 'epayments.payment.terminated',
				)
			)
		);

		$response = rest_do_request( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );

		$updated = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $booking_id ) );
		$this->assertEquals( 'cancelled', $updated->status );
		$this->assertStringContainsString( 'Vipps-betaling ble avbrutt', $updated->rejection_reason );
	}
}
