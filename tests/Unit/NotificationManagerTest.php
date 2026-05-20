<?php
/**
 * NotificationManager Unit Tests
 *
 * @package SnippenBooking\Tests\Unit
 */

namespace SnippenBooking\Tests\Unit;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\Notification\NotificationManager;
use SnippenBooking\Service\Notification\EmailProvider;
use SnippenBooking\Service\Notification\KeySmsProvider;

/**
 * Class NotificationManagerTest
 */
class NotificationManagerTest extends TestCase {

	/**
	 * Test provider discovery and defaults.
	 */
	public function testGetProviders() {
		$manager   = new NotificationManager();
		$providers = $manager->get_providers();

		$this->assertIsArray( $providers );
		$this->assertCount( 2, $providers );

		$ids = array_map( function( $provider ) {
			return $provider->get_id();
		}, $providers );

		$this->assertContains( 'email', $ids );
		$this->assertContains( 'keysms', $ids );
	}

	/**
	 * Test fetching a provider by ID.
	 */
	public function testGetProvider() {
		$manager = new NotificationManager();

		$email = $manager->get_provider( 'email' );
		$this->assertInstanceOf( EmailProvider::class, $email );

		$keysms = $manager->get_provider( 'keysms' );
		$this->assertInstanceOf( KeySmsProvider::class, $keysms );

		$invalid = $manager->get_provider( 'non_existent_provider' );
		$this->assertNull( $invalid );
	}

	/**
	 * Test active provider resolution and automatic migration.
	 */
	public function testGetActiveProviderWithMigration() {
		$manager = new NotificationManager();

		// Clean up option state
		delete_option( 'snippen_active_notification_provider' );
		delete_option( 'snippen_sms_booking_confirmation_enabled' );
		delete_option( 'snippen_sms_account_confirmation_enabled' );

		// Scenario A: Legacy SMS is disabled -> active defaults to 'email'
		$this->assertEquals( 'email', $manager->get_active_provider_id() );

		// Reset for Scenario B
		delete_option( 'snippen_active_notification_provider' );
		update_option( 'snippen_sms_booking_confirmation_enabled', 'yes' );

		// Scenario B: Legacy SMS enabled -> active defaults to 'keysms'
		$this->assertEquals( 'keysms', $manager->get_active_provider_id() );

		// Scenario C: Explicitly configured provider persists
		update_option( 'snippen_active_notification_provider', 'email' );
		$this->assertEquals( 'email', $manager->get_active_provider_id() );

		// Clean up
		delete_option( 'snippen_active_notification_provider' );
		delete_option( 'snippen_sms_booking_confirmation_enabled' );
	}

	/**
	 * Test channel routing and backward compatible defaults.
	 */
	public function testGetChannelRoute() {
		$manager = new NotificationManager();

		delete_option( 'snippen_route_user_activation' );
		delete_option( 'snippen_sms_account_confirmation_enabled' );

		// Default A: Legacy account confirmation disabled -> route is email
		$this->assertEquals( 'email', $manager->get_channel_route( NotificationManager::TYPE_USER_ACTIVATION ) );

		// Default B: Legacy account confirmation enabled -> route is sms
		delete_option( 'snippen_route_user_activation' );
		update_option( 'snippen_sms_account_confirmation_enabled', 'yes' );
		$this->assertEquals( 'sms', $manager->get_channel_route( NotificationManager::TYPE_USER_ACTIVATION ) );

		// Custom override
		update_option( 'snippen_route_user_activation', 'email' );
		$this->assertEquals( 'email', $manager->get_channel_route( NotificationManager::TYPE_USER_ACTIVATION ) );

		// Clean up
		delete_option( 'snippen_route_user_activation' );
		delete_option( 'snippen_sms_account_confirmation_enabled' );
	}

	/**
	 * Test sandbox mode query helper.
	 */
	public function testIsSandboxMode() {
		$manager = new NotificationManager();

		update_option( 'snippen_sms_sandbox_mode', 'no' );
		$this->assertFalse( $manager->is_sandbox_mode() );

		update_option( 'snippen_sms_sandbox_mode', 'yes' );
		$this->assertTrue( $manager->is_sandbox_mode() );

		delete_option( 'snippen_sms_sandbox_mode' );
		$this->assertFalse( $manager->is_sandbox_mode() );
	}
}
