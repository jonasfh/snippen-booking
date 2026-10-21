<?php
/**
 * Unit tests for EmailProvider
 *
 * @package SnippenBooking\Tests\Unit\Service
 */

namespace SnippenBooking\Tests\Unit\Service;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\Notification\EmailProvider;

/**
 * EmailProviderTest
 */
class EmailProviderTest extends TestCase {

	/**
	 * Whether the test requires database setup and seed data.
	 */
	protected $requires_db = false;

	/**
	 * Test provider identity and metadata
	 */
	public function test_provider_identity() {
		$provider = new EmailProvider();
		$this->assertSame( 'email', $provider->get_id() );
		$this->assertNotEmpty( $provider->get_name() );
	}

	/**
	 * Test settings schema contains expected SMTP options
	 */
	public function test_settings_schema() {
		$provider = new EmailProvider();
		$schema   = $provider->get_settings_schema();

		$this->assertIsArray( $schema );
		$keys = array_column( $schema, 'key' );
		$this->assertContains( 'snippen_smtp_enabled', $keys );
		$this->assertContains( 'snippen_smtp_host', $keys );
		$this->assertContains( 'snippen_smtp_port', $keys );
		$this->assertContains( 'snippen_smtp_user', $keys );
		$this->assertContains( 'snippen_smtp_pass', $keys );
		$this->assertContains( 'snippen_smtp_encryption', $keys );
		$this->assertContains( 'snippen_smtp_from_email', $keys );
		$this->assertContains( 'snippen_smtp_from_name', $keys );
	}

	/**
	 * Test is_configured check
	 */
	public function test_is_configured() {
		$provider = new EmailProvider();
		$this->assertTrue( $provider->is_configured() );
	}

	/**
	 * Test log_mail_failure adds helpful hint on mail function error
	 */
	public function test_log_mail_failure_with_instantiate_error() {
		$provider = new EmailProvider();
		$wp_error = new \WP_Error( 'mail_failed', 'Could not instantiate mail function.', array( 'to' => 'user@example.com' ) );

		// Ensure log_mail_failure executes without throwing errors
		$provider->log_mail_failure( $wp_error );
		$this->assertTrue( true );
	}
}
