<?php
/**
 * Unit tests for SnippenSmsProvider
 *
 * @package SnippenBooking\Tests\Unit\Service
 */

namespace SnippenBooking\Tests\Unit\Service;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\Notification\SnippenSmsProvider;
use SnippenBooking\Service\Notification\NotificationManager;

/**
 * SnippenSmsProviderTest
 */
class SnippenSmsProviderTest extends TestCase {

	/**
	 * Whether the test requires database setup and seed data.
	 */
	protected $requires_db = false;

	/**
	 * Test provider identity and metadata
	 */
	public function test_provider_identity() {
		$provider = new SnippenSmsProvider();
		$this->assertSame( 'snippen_sms_service', $provider->get_id() );
		$this->assertNotEmpty( $provider->get_name() );
	}

	/**
	 * Test settings schema
	 */
	public function test_settings_schema() {
		$provider = new SnippenSmsProvider();
		$schema   = $provider->get_settings_schema();

		$this->assertIsArray( $schema );
		$this->assertCount( 2, $schema );
		$keys = array_column( $schema, 'key' );
		$this->assertContains( 'snippen_sms_service_api_token', $keys );
		$this->assertContains( 'snippen_sms_service_sender', $keys );
	}

	/**
	 * Test is_configured check
	 */
	public function test_is_configured() {
		$provider = new SnippenSmsProvider();

		delete_option( 'snippen_sms_service_api_token' );
		$this->assertFalse( $provider->is_configured() );

		update_option( 'snippen_sms_service_api_token', 'super-secret-token' );
		$this->assertTrue( $provider->is_configured() );
	}

	/**
	 * Test send_sms validation
	 */
	public function test_send_sms() {
		$provider = new SnippenSmsProvider();

		// Not configured -> false
		delete_option( 'snippen_sms_service_api_token' );
		$this->assertFalse( $provider->send_sms( '+4799887766', 'Test message' ) );

		// Configured -> true
		update_option( 'snippen_sms_service_api_token', 'valid-token' );
		$this->assertTrue( $provider->send_sms( '+4799887766', 'Test message' ) );

		// Empty parameters -> false
		$this->assertFalse( $provider->send_sms( '', 'Test message' ) );
		$this->assertFalse( $provider->send_sms( '+4799887766', '' ) );
	}

	/**
	 * Test provider registration in NotificationManager
	 */
	public function test_registered_in_notification_manager() {
		$manager  = new NotificationManager();
		$provider = $manager->get_provider( 'snippen_sms_service' );

		$this->assertInstanceOf( SnippenSmsProvider::class, $provider );
	}
}
