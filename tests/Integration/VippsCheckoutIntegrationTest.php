<?php
/**
 * Integration tests for Vipps frontend checkout and booking submission
 *
 * @package SnippenBooking\Tests\Integration
 */

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Api\BookingApi;
use SnippenBooking\Service\Vipps\VippsService;
use SnippenBooking\Service\Vipps\VippsClient;
use SnippenBooking\Shortcode\BookingShortcode;

/**
 * VippsCheckoutIntegrationTest
 */
class VippsCheckoutIntegrationTest extends TestCase {

	/**
	 * WP die callback.
	 *
	 * @var callable|null
	 */
	protected $wp_die_callback = null;

	/**
	 * HTTP mock callback.
	 *
	 * @var callable|null
	 */
	protected $http_mock_callback = null;

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		BookingApi::register();

		$this->wp_die_callback = function () {
			return function ( $message ) {
				throw new \Exception( is_string( $message ) ? $message : wp_json_encode( $message ) );
			};
		};
		add_filter( 'wp_die_ajax_handler', $this->wp_die_callback );
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
		if ( null !== $this->wp_die_callback ) {
			remove_filter( 'wp_die_ajax_handler', $this->wp_die_callback );
			$this->wp_die_callback = null;
		}
		if ( null !== $this->http_mock_callback ) {
			remove_filter( 'pre_http_request', $this->http_mock_callback, 10 );
			$this->http_mock_callback = null;
		}
		delete_option( 'snippen_vipps_enabled' );
		delete_option( 'snippen_vipps_client_id' );
		delete_option( 'snippen_vipps_client_secret' );
		delete_option( 'snippen_vipps_subscription_key' );
		delete_option( 'snippen_vipps_msn' );
		$_POST    = array();
		$_GET     = array();
		$_REQUEST = array();
		parent::tearDown();
	}

	/**
	 * Configure Vipps options for tests
	 */
	private function enable_vipps() {
		update_option( 'snippen_vipps_enabled', 'yes' );
		update_option( 'snippen_vipps_client_id', 'test_client_id' );
		update_option( 'snippen_vipps_client_secret', 'test_client_secret' );
		update_option( 'snippen_vipps_subscription_key', 'test_sub_key' );
		update_option( 'snippen_vipps_msn', '123456' );
	}

	/**
	 * Test that submitting private booking with Vipps enabled creates pending_payment booking and returns redirect_url
	 */
	public function test_submit_private_booking_with_vipps_redirects_and_sets_pending_payment() {
		$this->enable_vipps();

		// Mock Vipps HTTP requests for token and create payment
		$captured_payload = null;
		$this->set_http_mock(
			function ( $preempt, $parsed_args, $url ) use ( &$captured_payload ) {
				if ( strpos( $url, '/accesstoken/get' ) !== false ) {
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode(
							array(
								'access_token' => 'mock_token_123',
								'expires_in'   => 3600,
							)
						),
					);
				}
				if ( strpos( $url, '/epayment/v1/payments' ) !== false ) {
					$captured_payload = json_decode( $parsed_args['body'] ?? '', true );
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode(
							array(
								'reference'   => 'snippen-test-ref-1',
								'redirectUrl' => 'https://checkout.vipps.no/checkout-page',
							)
						),
					);
				}
				return $preempt;
			}
		);

		$login   = 'resident_vipps_' . uniqid();
		$user_id = wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => 'password',
				'user_email' => $login . '@example.com',
				'role'       => 'subscriber',
			)
		);
		update_user_meta( $user_id, 'snippen_phone', '99887766' );
		wp_set_current_user( $user_id );

		global $wpdb;
		$block_id  = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}snippen_booking_blocks LIMIT 1" );
		$object_id = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}snippen_booking_objects LIMIT 1" );

		$_POST['nonce']             = wp_create_nonce( 'snippen_booking_nonce' );
		$_POST['event_date']        = '2026-10-19';
		$_POST['booking_object_id'] = array( $object_id );
		$_POST['block_ids']         = array( $block_id );
		$_POST['name']              = 'Vipps Resident';
		$_POST['email']             = 'vipps@example.com';
		$_POST['description']       = 'Fest med Vipps-betaling';
		$_POST['booking_type']      = 'private';
		$_POST['accept_terms']      = '1';
		$_POST['return_url']        = 'https://example.com/booking-page/';

		ob_start();
		try {
			BookingApi::submit_booking();
		} catch ( \Throwable $e ) {
			unset( $e );
		}
		$output = ob_get_clean();
		$data   = json_decode( $output, true );

		$this->assertTrue( $data['success'], 'Expected successful response. Output: ' . $output );
		$this->assertEquals( 'https://checkout.vipps.no/checkout-page', $data['data']['redirect_url'] );

		// Verify booking in database has status pending_payment
		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}snippen_bookings WHERE user_id = %d AND booking_date = %s ORDER BY id DESC LIMIT 1",
				$user_id,
				'2026-10-19'
			)
		);

		$this->assertNotNull( $booking );
		$this->assertEquals( 'pending_payment', $booking->status );
		$this->assertStringContainsString( 'Vipps ref: ', $booking->payment_notes );

		// Verify returnUrl in Vipps payload contains booking_uuid and payment_provider=vipps
		$this->assertNotNull( $captured_payload );
		$this->assertArrayHasKey( 'returnUrl', $captured_payload );
		$this->assertStringContainsString( 'booking_uuid=' . $booking->uuid, $captured_payload['returnUrl'] );
		$this->assertStringContainsString( 'payment_provider=vipps', $captured_payload['returnUrl'] );
		$this->assertStringStartsWith( 'https://example.com/booking-page/', $captured_payload['returnUrl'] );

		// Notifications should NOT be scheduled or sent while payment is pending
		$this->assertFalse( wp_next_scheduled( 'snippen_booking_send_notifications', array( (int) $booking->id, $booking->uuid ) ) );
	}

	/**
	 * Test that open booking flow is unaffected by Vipps being enabled
	 */
	public function test_submit_open_booking_ignores_vipps_and_uses_pending_status() {
		$this->enable_vipps();

		$login   = 'resident_open_vipps_' . uniqid();
		$user_id = wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => 'password',
				'user_email' => $login . '@example.com',
				'role'       => 'subscriber',
			)
		);
		update_user_meta( $user_id, 'snippen_phone', '99887766' );
		wp_set_current_user( $user_id );

		global $wpdb;
		$block_id  = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}snippen_booking_blocks LIMIT 1" );
		$object_id = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}snippen_booking_objects LIMIT 1" );

		$_POST['nonce']             = wp_create_nonce( 'snippen_booking_nonce' );
		$_POST['event_date']        = '2026-10-26';
		$_POST['booking_object_id'] = array( $object_id );
		$_POST['block_ids']         = array( $block_id );
		$_POST['name']              = 'Open Resident';
		$_POST['email']             = 'open@example.com';
		$_POST['description']       = 'Åpent arrangement';
		$_POST['booking_type']      = 'open';
		$_POST['accept_terms']      = '1';

		ob_start();
		try {
			BookingApi::submit_booking();
		} catch ( \Throwable $e ) {
			unset( $e );
		}
		$output = ob_get_clean();
		$data   = json_decode( $output, true );

		$this->assertTrue( $data['success'] );
		$this->assertArrayNotHasKey( 'redirect_url', $data['data'] );

		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}snippen_bookings WHERE user_id = %d AND booking_date = %s ORDER BY id DESC LIMIT 1",
				$user_id,
				'2026-10-26'
			)
		);

		$this->assertNotNull( $booking );
		$this->assertEquals( 'pending', $booking->status );
		$this->assertEquals( 3, (int) $booking->payment_status_id ); // EXEMPT
	}

	/**
	 * Test that Vipps API error cancels the booking and returns error message
	 */
	public function test_vipps_api_error_cancels_booking() {
		$this->enable_vipps();

		$this->set_http_mock(
			function ( $preempt, $parsed_args, $url ) {
				if ( strpos( $url, '/accesstoken/get' ) !== false ) {
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode(
							array(
								'access_token' => 'mock_token_123',
								'expires_in'   => 3600,
							)
						),
					);
				}
				if ( strpos( $url, '/epayment/v1/payments' ) !== false ) {
					return array(
						'response' => array( 'code' => 400 ),
						'body'     => wp_json_encode(
							array(
								'message' => 'Invalid customer phone number',
							)
						),
					);
				}
				return $preempt;
			}
		);

		$login   = 'resident_fail_' . uniqid();
		$user_id = wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => 'password',
				'user_email' => $login . '@example.com',
				'role'       => 'subscriber',
			)
		);
		update_user_meta( $user_id, 'snippen_phone', '99887766' );
		wp_set_current_user( $user_id );

		global $wpdb;
		$block_id  = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}snippen_booking_blocks LIMIT 1" );
		$object_id = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}snippen_booking_objects LIMIT 1" );

		$_POST['nonce']             = wp_create_nonce( 'snippen_booking_nonce' );
		$_POST['event_date']        = '2026-10-27';
		$_POST['booking_object_id'] = array( $object_id );
		$_POST['block_ids']         = array( $block_id );
		$_POST['name']              = 'Fail Resident';
		$_POST['email']             = 'fail@example.com';
		$_POST['booking_type']      = 'private';
		$_POST['accept_terms']      = '1';

		ob_start();
		try {
			BookingApi::submit_booking();
		} catch ( \Throwable $e ) {
			unset( $e );
		}
		$output = ob_get_clean();
		$data   = json_decode( $output, true );

		$this->assertFalse( $data['success'] );
		$this->assertStringContainsString( 'Kunne ikke opprette Vipps-betaling', $data['data']['message'] );

		// Booking should be cancelled
		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}snippen_bookings WHERE user_id = %d AND booking_date = %s ORDER BY id DESC LIMIT 1",
				$user_id,
				'2026-10-27'
			)
		);

		$this->assertNotNull( $booking );
		$this->assertEquals( 'cancelled', $booking->status );
	}

	/**
	 * Test return page receipt rendering
	 */
	public function test_return_page_renders_receipt_when_confirmed() {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_bookings';

		$uuid = wp_generate_uuid4();
		$wpdb->insert(
			$table,
			array(
				'uuid'              => $uuid,
				'user_id'           => 1,
				'booking_date'      => '2026-10-28',
				'customer_name'     => 'Ola Nordmann',
				'customer_email'    => 'ola@example.com',
				'customer_phone'    => '99887766',
				'booking_type'      => 'private',
				'price'             => 500.0,
				'payment_status_id' => 2, // PAID
				'status'            => 'confirmed',
				'payment_notes'     => 'Vipps ref: snippen-1-12345678-123',
			)
		);

		$_GET['booking_uuid']     = $uuid;
		$_GET['payment_provider'] = 'vipps';

		$html = BookingShortcode::handle_and_render_vipps_return();
		$this->assertStringContainsString( 'Betaling fullført og reservasjon bekreftet', $html );
		$this->assertStringContainsString( 'Ola Nordmann', $html );
		$this->assertStringContainsString( 'snippen-1-12345678-123', $html );
	}

	/**
	 * Test return page handles authorized pending payment by capturing and confirming
	 */
	public function test_return_page_captures_and_confirms_pending_authorized_payment() {
		$this->enable_vipps();

		global $wpdb;
		$table = $wpdb->prefix . 'snippen_bookings';

		$uuid = wp_generate_uuid4();
		$wpdb->insert(
			$table,
			array(
				'uuid'              => $uuid,
				'user_id'           => 1,
				'booking_date'      => '2026-10-29',
				'customer_name'     => 'Kari Nordmann',
				'customer_email'    => 'kari@example.com',
				'customer_phone'    => '99887766',
				'booking_type'      => 'private',
				'price'             => 400.0,
				'payment_status_id' => 1, // UNPAID
				'status'            => 'pending_payment',
				'payment_notes'     => 'Vipps ref: snippen-99-12345678-999',
			)
		);
		$booking_id = $wpdb->insert_id;

		// Mock token, get_payment (AUTHORIZED) and capture
		$this->set_http_mock(
			function ( $preempt, $parsed_args, $url ) {
				if ( strpos( $url, '/accesstoken/get' ) !== false ) {
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode(
							array(
								'access_token' => 'mock_token_123',
								'expires_in'   => 3600,
							)
						),
					);
				}
				if ( strpos( $url, '/capture' ) !== false ) {
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode(
							array(
								'reference' => 'snippen-99-12345678-999',
								'state'     => 'CAPTURED',
							)
						),
					);
				}
				if ( strpos( $url, '/epayment/v1/payments/snippen-99-12345678-999' ) !== false ) {
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode(
							array(
								'reference' => 'snippen-99-12345678-999',
								'state'     => 'AUTHORIZED',
							)
						),
					);
				}
				return $preempt;
			}
		);

		$_GET['booking_uuid']     = $uuid;
		$_GET['payment_provider'] = 'vipps';

		$html = BookingShortcode::handle_and_render_vipps_return();
		$this->assertStringContainsString( 'Betaling fullført og reservasjon bekreftet', $html );

		$updated = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $booking_id ) );
		$this->assertEquals( 'confirmed', $updated->status );
		$this->assertEquals( 2, (int) $updated->payment_status_id );
	}

	/**
	 * Test that VippsService::create_booking_payment builds returnUrl with query args even with complex base URLs
	 */
	public function test_vipps_service_builds_return_url_preserving_query_parameters() {
		$this->enable_vipps();

		$captured_payload = null;
		$this->set_http_mock(
			function ( $preempt, $parsed_args, $url ) use ( &$captured_payload ) {
				if ( strpos( $url, '/accesstoken/get' ) !== false ) {
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode(
							array(
								'access_token' => 'mock_token_123',
								'expires_in'   => 3600,
							)
						),
					);
				}
				if ( strpos( $url, '/epayment/v1/payments' ) !== false ) {
					$captured_payload = json_decode( $parsed_args['body'] ?? '', true );
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode(
							array(
								'reference'   => 'snippen-test-ref-2',
								'redirectUrl' => 'https://checkout.vipps.no/checkout-page',
							)
						),
					);
				}
				return $preempt;
			}
		);

		$service = new VippsService();
		$booking = (object) array(
			'id'   => 42,
			'uuid' => 'test-uuid-42',
		);

		$res = $service->create_booking_payment( $booking, 500, '99887766', null, 'https://example.com/kalender/?tab=booking&lang=no' );
		$this->assertFalse( is_wp_error( $res ) );
		$this->assertNotNull( $captured_payload );
		$this->assertStringContainsString( 'booking_uuid=test-uuid-42', $captured_payload['returnUrl'] );
		$this->assertStringContainsString( 'payment_provider=vipps', $captured_payload['returnUrl'] );
		$this->assertStringContainsString( 'tab=booking', $captured_payload['returnUrl'] );
		$this->assertStringContainsString( 'lang=no', $captured_payload['returnUrl'] );
	}
}
