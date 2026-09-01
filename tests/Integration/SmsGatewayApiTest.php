<?php
/**
 * Integration tests for SmsGatewayApi
 *
 * @package SnippenBooking\Tests\Integration
 */

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Api\SmsGatewayApi;
use SnippenBooking\Service\Notification\MessageLoggerService;
use SnippenBooking\Service\Notification\NotificationManager;
use SnippenBooking\Database\Repository\BookingRepository;

/**
 * SmsGatewayApiTest
 */
class SmsGatewayApiTest extends TestCase {

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
		update_option( 'snippen_sms_service_api_token', 'test-secret-token-123' );
		update_option( 'snippen_sms_service_sender', 'Snippen' );
		SmsGatewayApi::register();
		do_action( 'rest_api_init' );
	}

	/**
	 * Test token verification with missing configured token
	 */
	public function test_verify_token_unconfigured() {
		delete_option( 'snippen_sms_service_api_token' );

		$request = new \WP_REST_Request( 'GET', '/snippen/v1/sms/outbox' );
		$request->add_header( 'Authorization', 'Bearer test-secret-token-123' );

		$result = SmsGatewayApi::verify_token( $request );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	/**
	 * Test token verification with invalid or missing headers
	 */
	public function test_verify_token_invalid_or_missing() {
		// Missing header
		$request = new \WP_REST_Request( 'GET', '/snippen/v1/sms/outbox' );
		$result  = SmsGatewayApi::verify_token( $request );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 401, $result->get_error_data()['status'] );

		// Wrong token in Bearer header
		$request->add_header( 'Authorization', 'Bearer wrong-token' );
		$result = SmsGatewayApi::verify_token( $request );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 401, $result->get_error_data()['status'] );

		// Wrong token in X-API-Key header
		$request = new \WP_REST_Request( 'GET', '/snippen/v1/sms/outbox' );
		$request->add_header( 'X-API-Key', 'wrong-token' );
		$result = SmsGatewayApi::verify_token( $request );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 401, $result->get_error_data()['status'] );
	}

	/**
	 * Test token verification with valid headers
	 */
	public function test_verify_token_valid() {
		// Bearer header
		$req1 = new \WP_REST_Request( 'GET', '/snippen/v1/sms/outbox' );
		$req1->add_header( 'Authorization', 'Bearer test-secret-token-123' );
		$this->assertTrue( SmsGatewayApi::verify_token( $req1 ) );

		// X-API-Key header
		$req2 = new \WP_REST_Request( 'GET', '/snippen/v1/sms/outbox' );
		$req2->add_header( 'X-API-Key', 'test-secret-token-123' );
		$this->assertTrue( SmsGatewayApi::verify_token( $req2 ) );
	}

	/**
	 * Test GET /outbox returns only queued messages in expected format
	 */
	public function test_get_outbox() {
		// Seed 1 queued SMS, 1 sent SMS, 1 queued email
		$msg_id1 = MessageLoggerService::log_message(
			10,
			1,
			'sms',
			'+4799887766',
			null,
			'Queued SMS body 1',
			'booking_confirmation',
			'queued'
		);
		$msg_id2 = MessageLoggerService::log_message(
			10,
			1,
			'sms',
			'+4799887766',
			null,
			'Sent SMS body',
			'booking_confirmation',
			'sent'
		);
		$msg_id3 = MessageLoggerService::log_message(
			10,
			1,
			'sms',
			'+4711223344',
			null,
			'Queued SMS body 2',
			'payment_reminder',
			'queued'
		);
		$msg_id4 = MessageLoggerService::log_message(
			10,
			1,
			'email',
			'test@example.com',
			'Subject',
			'Queued email body',
			'booking_confirmation',
			'queued'
		);

		$request = new \WP_REST_Request( 'GET', '/snippen/v1/sms/outbox' );
		$request->add_header( 'Authorization', 'Bearer test-secret-token-123' );

		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'messages', $data );
		$this->assertCount( 2, $data['messages'] );

		$first = $data['messages'][0];
		$this->assertSame( (int) $msg_id1, $first['id'] );
		$this->assertSame( (string) $msg_id1, $first['external_id'] );
		$this->assertSame( '+4799887766', $first['recipient'] );
		$this->assertSame( 'Queued SMS body 1', $first['body'] );
		$this->assertSame( 'Snippen', $first['sender'] );
		$this->assertSame( '10', $first['booking_id'] );

		$second = $data['messages'][1];
		$this->assertSame( (int) $msg_id3, $second['id'] );
		$this->assertSame( '+4711223344', $second['recipient'] );
	}

	/**
	 * Test POST /outbox/status updates status from queued to sent and failed
	 */
	public function test_update_outbox_status() {
		$msg_id1 = MessageLoggerService::log_message(
			12,
			1,
			'sms',
			'+4799887766',
			null,
			'SMS 1',
			'booking_confirmation',
			'queued'
		);
		$msg_id2 = MessageLoggerService::log_message(
			12,
			1,
			'sms',
			'+4711223344',
			null,
			'SMS 2',
			'booking_confirmation',
			'queued'
		);

		$payload = array(
			'statuses' => array(
				array(
					'external_id'      => (string) $msg_id1,
					'gateway_id'       => 101,
					'status'           => 'sent',
					'modem_message_id' => 'modem-msg-1',
				),
				array(
					'external_id'   => (string) $msg_id2,
					'gateway_id'    => 102,
					'status'        => 'failed',
					'error_message' => 'Network failure / SIM busy',
				),
			),
		);

		$request = new \WP_REST_Request( 'POST', '/snippen/v1/sms/outbox/status' );
		$request->add_header( 'Authorization', 'Bearer test-secret-token-123' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $payload ) );

		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertSame( 2, $data['updated_count'] );

		// Verify DB state
		$m1 = MessageLoggerService::get_message( (int) $msg_id1 );
		$this->assertSame( 'sent', $m1->status );
		$meta1 = json_decode( $m1->metadata, true );
		$this->assertSame( 101, $meta1['gateway_id'] );
		$this->assertSame( 'modem-msg-1', $meta1['modem_message_id'] );

		$m2 = MessageLoggerService::get_message( (int) $msg_id2 );
		$this->assertSame( 'failed', $m2->status );
		$meta2 = json_decode( $m2->metadata, true );
		$this->assertSame( 'Network failure / SIM busy', $meta2['error_message'] );
	}

	/**
	 * Test POST /inbox records incoming messages and returns processed_ids
	 */
	public function test_report_inbox() {
		$payload = array(
			'messages' => array(
				array(
					'gateway_id'       => 501,
					'sender'           => '+4799887766',
					'recipient'        => 'snippen-sms-service',
					'body'             => 'Hei, jeg har et spørsmål om nøkkelen.',
					'booking_id'       => 15,
					'modem_message_id' => 'modem-inbound-501',
					'received_at'      => '2026-09-01T14:30:00+00:00',
				),
			),
		);

		$request = new \WP_REST_Request( 'POST', '/snippen/v1/sms/inbox' );
		$request->add_header( 'Authorization', 'Bearer test-secret-token-123' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $payload ) );

		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertSame( array( 501 ), $data['processed_ids'] );

		// Check database record
		$messages = MessageLoggerService::get_messages_for_booking( 15 );
		$this->assertCount( 1, $messages );
		$inbound = $messages[0];
		$this->assertSame( 'sms', $inbound->channel );
		$this->assertSame( '+4799887766', $inbound->recipient );
		$this->assertSame( 'Hei, jeg har et spørsmål om nøkkelen.', $inbound->message );
		$this->assertSame( 'inbound_sms', $inbound->event_type );
		$this->assertSame( 'received', $inbound->status );
		$meta = json_decode( $inbound->metadata, true );
		$this->assertSame( 501, $meta['gateway_id'] );
		$this->assertSame( 'inbound', $meta['direction'] );
	}

	/**
	 * Test GET /bookings phone lookup
	 */
	public function test_get_bookings_by_phone() {
		global $wpdb;

		// Create a test booking
		$booking_repo = new BookingRepository();
		$booking_id   = $booking_repo->create(
			array(
				'uuid'           => wp_generate_uuid4(),
				'booking_date'   => '2026-09-10',
				'user_id'        => 1,
				'customer_name'  => 'Kari Nordmann',
				'customer_email' => 'kari@example.com',
				'customer_phone' => '+4798765432',
				'status'         => 'confirmed',
			),
			array( 1 ),
			array()
		);

		$request = new \WP_REST_Request( 'GET', '/snippen/v1/sms/bookings' );
		$request->add_header( 'Authorization', 'Bearer test-secret-token-123' );
		$request->set_param( 'phone', '+4798765432' );

		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertArrayHasKey( 'bookings', $data );
		$this->assertNotEmpty( $data['bookings'] );
		$this->assertSame( (string) $booking_id, $data['bookings'][0]['id'] );
		$this->assertSame( 'Kari Nordmann', $data['bookings'][0]['customer_name'] );
		$this->assertSame( '+4798765432', $data['bookings'][0]['customer_phone'] );
	}

	/**
	 * Test NotificationManager with SnippenSmsProvider sets initial status as queued
	 */
	public function test_notification_manager_queues_sms() {
		update_option( 'snippen_active_notification_provider', 'snippen_sms_service' );
		update_option( 'snippen_sms_user_activation_enabled', 'yes' );

		$manager = new NotificationManager();
		$user_id = wp_create_user( 'smstestuser', 'pass123', 'smsuser@example.com' );
		update_user_meta( $user_id, 'snippen_phone', '+4799001122' );

		$sent = $manager->send_account_confirmation( $user_id, '123456' );
		$this->assertTrue( $sent );

		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}snippen_messages WHERE user_id = %d AND channel = 'sms'", $user_id ) );
		$this->assertNotNull( $row );
		$this->assertSame( 'queued', $row->status );
		$this->assertSame( '+4799001122', $row->recipient );
	}
}
