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
	 * Whether the test requires database setup and seed data.
	 */
	protected $requires_db = false;

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
}
