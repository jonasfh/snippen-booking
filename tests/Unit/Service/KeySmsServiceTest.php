<?php
/**
 * Unit tests for KeySmsService
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
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		update_option( 'snippen_sms_enabled', 'yes' );
		update_option( 'snippen_keysms_api_key', 'test_api_key' );
		update_option( 'snippen_sms_sender', 'Snippen' );
	}

	/**
	 * Test successful SMS sending
	 */
	public function test_send_success() {
		add_filter( 'pre_http_request', function( $pre, $args, $url ) {
			if ( strpos( $url, 'api.keysms.no' ) !== false ) {
				// Verify request args
				$body = json_decode( $args['body'], true );
				if ( $body['message'] === 'Test message' && $body['receivers'][0] === '+4799887766' ) {
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => '{"status":"ok"}',
					);
				}
			}
			return $pre;
		}, 10, 3 );

		$service = new KeySmsService();
		$result  = $service->send( '+4799887766', 'Test message' );

		$this->assertTrue( $result );
		
		// Clean up filter
		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * Test sending when disabled
	 */
	public function test_send_disabled() {
		update_option( 'snippen_sms_enabled', 'no' );

		$service = new KeySmsService();
		$result  = $service->send( '+4799887766', 'Test message' );

		$this->assertFalse( $result );
	}

	/**
	 * Test sending with missing API key
	 */
	public function test_send_missing_api_key() {
		update_option( 'snippen_keysms_api_key', '' );

		$service = new KeySmsService();
		$result  = $service->send( '+4799887766', 'Test message' );

		$this->assertFalse( $result );
	}

	/**
	 * Test HTTP error handling
	 */
	public function test_send_http_error() {
		add_filter( 'pre_http_request', function( $pre, $args, $url ) {
			return array(
				'response' => array( 'code' => 401 ),
				'body'     => '{"error":"Unauthorized"}',
			);
		}, 10, 3 );

		$service = new KeySmsService();
		$result  = $service->send( '+4799887766', 'Test message' );

		$this->assertFalse( $result );
		
		remove_all_filters( 'pre_http_request' );
	}
}
