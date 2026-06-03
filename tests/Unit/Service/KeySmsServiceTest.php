<?php
/**
 * Unit tests for KeySmsService (Signed Payload)
 *
 * @package SnippenBooking\Tests\Unit\Service
 */

namespace SnippenBooking\Tests\Unit\Service;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\KeySmsService;

/**
 * KeySmsServiceTest
 */
class KeySmsServiceTest extends TestCase {

	/**
	 * Whether the test requires database setup and seed data.
	 */
	protected $requires_db = false;

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		update_option( 'snippen_sms_booking_confirmation_enabled', 'yes' );
		update_option( 'snippen_keysms_username', 'test_user' );
		update_option( 'snippen_keysms_api_key', 'test_api_key' );
		update_option( 'snippen_sms_sender', 'Snippen' );
	}

	/**
	 * Test successful SMS sending
	 */
	public function test_send_success() {
		add_filter( 'pre_http_request', function( $pre, $args, $url ) {
			if ( strpos( $url, 'app.keysms.no' ) !== false ) {
				$body = json_decode( $args['body'], true );
				$payload = json_decode( $body['payload'], true );
				
				// Verify signature
				$expected_signature = md5( $body['payload'] . 'test_api_key' );
				
				if ( $body['username'] === 'test_user' && 
					 $body['signature'] === $expected_signature &&
					 $payload['message'] === 'Test message' &&
					 $payload['receivers'][0] === '4799887766' ) { // '+' should be stripped
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => '{"ok":true}',
					);
				}
			}
			return $pre;
		}, 10, 3 );

		$service = new KeySmsService();
		$result  = $service->send( '+4799887766', 'Test message' );

		$this->assertTrue( $result );
		
		remove_all_filters( 'pre_http_request' );
	}


	/**
	 * Test sending with missing credentials
	 */
	public function test_send_missing_credentials() {
		update_option( 'snippen_keysms_username', '' );

		$service = new KeySmsService();
		$result  = $service->send( '+4799887766', 'Test message' );

		$this->assertFalse( $result );
	}

	/**
	 * Test API error response
	 */
	public function test_send_api_error() {
		add_filter( 'pre_http_request', function( $pre, $args, $url ) {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => '{"ok":false, "error":"invalid_receiver"}',
			);
		}, 10, 3 );

		$service = new KeySmsService();
		$result  = $service->send( '+4799887766', 'Test message' );

		$this->assertFalse( $result );
		
		remove_all_filters( 'pre_http_request' );
	}
}
