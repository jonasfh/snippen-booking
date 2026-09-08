<?php
/**
 * Unit tests for VippsService
 *
 * @package SnippenBooking\Tests\Unit\Service
 */

namespace SnippenBooking\Tests\Unit\Service;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\Vipps\VippsService;
use SnippenBooking\Service\Vipps\VippsClient;

/**
 * VippsServiceTest
 */
class VippsServiceTest extends TestCase {

	/**
	 * Whether the test requires database setup and seed data.
	 */
	protected $requires_db = false;

	/**
	 * Test format_amount_to_ore with various inputs
	 */
	public function test_format_amount_to_ore() {
		$this->assertEquals( 10000, VippsService::format_amount_to_ore( 100 ) );
		$this->assertEquals( 15050, VippsService::format_amount_to_ore( 150.50 ) );
		$this->assertEquals( 29999, VippsService::format_amount_to_ore( '299.99' ) );
		$this->assertEquals( 0, VippsService::format_amount_to_ore( 0 ) );
	}

	/**
	 * Test generate_reference meets Vipps regex constraints
	 */
	public function test_generate_reference() {
		$ref1 = VippsService::generate_reference( 42 );
		$ref2 = VippsService::generate_reference( 42 );

		// Must match ^[a-zA-Z0-9_-]{1,50}$
		$this->assertMatchesRegularExpression( '/^[a-zA-Z0-9_-]{1,50}$/', $ref1 );
		$this->assertStringStartsWith( 'snippen-42-', $ref1 );
		$this->assertLessThanOrEqual( 50, strlen( $ref1 ) );

		// Consecutive calls should not generate duplicate references
		$this->assertNotEquals( $ref1, $ref2 );
	}

	/**
	 * Test build_return_url
	 */
	public function test_build_return_url() {
		$uuid = '550e8400-e29b-41d4-a716-446655440000';
		$url  = VippsService::build_return_url( $uuid );

		$this->assertStringContainsString( 'booking_uuid=' . $uuid, $url );
		$this->assertStringContainsString( 'payment_provider=vipps', $url );
	}

	/**
	 * Test is_enabled toggle logic
	 */
	public function test_is_enabled() {
		update_option( 'snippen_vipps_enabled', 'no' );
		$client  = new VippsClient(
			array(
				'client_id'        => 'id',
				'client_secret'    => 'secret',
				'subscription_key' => 'sub',
				'msn'              => '123',
			)
		);
		$service = new VippsService( $client );
		$this->assertFalse( $service->is_enabled() );

		// Enable feature toggle
		update_option( 'snippen_vipps_enabled', 'yes' );
		$this->assertTrue( $service->is_enabled() );

		// If client is missing credentials, is_enabled must be false
		$incomplete_client  = new VippsClient( array( 'client_id' => '' ) );
		$incomplete_service = new VippsService( $incomplete_client );
		$this->assertFalse( $incomplete_service->is_enabled() );
	}

	/**
	 * Test create_booking_payment validations
	 */
	public function test_create_booking_payment_validations() {
		$service = new VippsService();

		// Invalid booking object
		$res1 = $service->create_booking_payment( null, 100 );
		$this->assertTrue( is_wp_error( $res1 ) );
		$this->assertEquals( 'invalid_booking', $res1->get_error_code() );

		// Invalid amount <= 0
		$booking = (object) array(
			'id'   => 12,
			'uuid' => 'test-uuid-123',
		);
		$res2    = $service->create_booking_payment( $booking, 0 );
		$this->assertTrue( is_wp_error( $res2 ) );
		$this->assertEquals( 'invalid_amount', $res2->get_error_code() );
	}

	/**
	 * Test create_booking_payment execution with phone formatting
	 */
	public function test_create_booking_payment_execution() {
		$captured_payload = null;

		$mock_client = $this->createMock( VippsClient::class );
		$mock_client->expects( $this->once() )
			->method( 'create_payment' )
			->willReturnCallback(
				function ( $payload ) use ( &$captured_payload ) {
					$captured_payload = $payload;
					return array(
						'reference'   => $payload['reference'],
						'redirectUrl' => 'https://api.vipps.no/checkout/abc',
					);
				}
			);

		$service = new VippsService( $mock_client );
		$booking = (object) array(
			'id'   => 99,
			'uuid' => 'uuid-99',
		);

		$res = $service->create_booking_payment( $booking, 250, '+47 90 68 80 31' );

		$this->assertIsArray( $res );
		$this->assertEquals( 'https://api.vipps.no/checkout/abc', $res['redirectUrl'] );
		$this->assertEquals( 25000, $captured_payload['amount']['value'] );
		$this->assertEquals( 'NOK', $captured_payload['amount']['currency'] );
		$this->assertEquals( '4790688031', $captured_payload['customer']['phoneNumber'] );
		$this->assertEquals( 'WEB_REDIRECT', $captured_payload['userFlow'] );
	}

	/**
	 * Test capture and cancel delegation
	 */
	public function test_capture_and_cancel_delegation() {
		$mock_client = $this->createMock( VippsClient::class );
		$mock_client->expects( $this->once() )
			->method( 'capture_payment' )
			->with( 'ref-abc', 10000 )
			->willReturn( array( 'state' => 'AUTHORIZED' ) );

		$mock_client->expects( $this->once() )
			->method( 'cancel_payment' )
			->with( 'ref-abc' )
			->willReturn( array( 'state' => 'TERMINATED' ) );

		$service = new VippsService( $mock_client );

		$res1 = $service->capture_booking_payment( 'ref-abc', 100 );
		$this->assertEquals( 'AUTHORIZED', $res1['state'] );

		$res2 = $service->cancel_booking_payment( 'ref-abc' );
		$this->assertEquals( 'TERMINATED', $res2['state'] );
	}
}
