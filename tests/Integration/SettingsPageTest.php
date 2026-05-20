<?php
/**
 * Settings Page Integration Tests
 *
 * @package SnippenBooking\Tests\Integration
 */

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Admin\Pages\SettingsPage;
use SnippenBooking\Plugin;

class SettingsPageTest extends TestCase {

	/**
	 * Tear down after each test.
	 */
	protected function tearDown(): void {
		// Clean up settings to avoid leakage
		delete_option( 'snippen_smtp_enabled' );
		delete_option( 'snippen_smtp_host' );
		delete_option( 'snippen_smtp_port' );
		delete_option( 'snippen_smtp_user' );
		delete_option( 'snippen_smtp_pass' );
		delete_option( 'snippen_smtp_encryption' );
		delete_option( 'snippen_smtp_from_email' );
		delete_option( 'snippen_smtp_from_name' );
		parent::tearDown();
	}

	/**
	 * Test that the settings form renders the SMTP input fields.
	 */
	public function test_settings_page_renders_smtp_fields() {
		$page = new SettingsPage();

		ob_start();
		$page->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Innstillinger for Kun E-post', $output );
		$this->assertStringContainsString( 'name="snippen_smtp_enabled"', $output );
		$this->assertStringContainsString( 'name="snippen_smtp_host"', $output );
		$this->assertStringContainsString( 'name="snippen_smtp_port"', $output );
		$this->assertStringContainsString( 'name="snippen_smtp_user"', $output );
		$this->assertStringContainsString( 'name="snippen_smtp_pass"', $output );
		$this->assertStringContainsString( 'name="snippen_smtp_encryption"', $output );
		$this->assertStringContainsString( 'name="snippen_smtp_from_email"', $output );
		$this->assertStringContainsString( 'name="snippen_smtp_from_name"', $output );
	}

	/**
	 * Test that POST request saves the SMTP settings.
	 */
	public function test_settings_page_saves_smtp_options() {
		// Create a mock POST request
		$_POST['snippen_settings_nonce'] = wp_create_nonce( 'snippen_save_settings' );
		$_POST['snippen_smtp_enabled'] = 'yes';
		$_POST['snippen_smtp_host'] = 'smtp.example.com';
		$_POST['snippen_smtp_port'] = '465';
		$_POST['snippen_smtp_user'] = 'testuser';
		$_POST['snippen_smtp_pass'] = 'securepass123';
		$_POST['snippen_smtp_encryption'] = 'ssl';
		$_POST['snippen_smtp_from_email'] = 'testfrom@example.com';
		$_POST['snippen_smtp_from_name'] = 'Test Sender';

		$page = new SettingsPage();

		ob_start();
		$page->render();
		ob_get_clean();

		// Clean up POST mock
		$_POST = array();

		// Assert options are updated in database
		$this->assertEquals( 'yes', get_option( 'snippen_smtp_enabled' ) );
		$this->assertEquals( 'smtp.example.com', get_option( 'snippen_smtp_host' ) );
		$this->assertEquals( 465, get_option( 'snippen_smtp_port' ) );
		$this->assertEquals( 'testuser', get_option( 'snippen_smtp_user' ) );
		$this->assertEquals( 'securepass123', get_option( 'snippen_smtp_pass' ) );
		$this->assertEquals( 'ssl', get_option( 'snippen_smtp_encryption' ) );
		$this->assertEquals( 'testfrom@example.com', get_option( 'snippen_smtp_from_email' ) );
		$this->assertEquals( 'Test Sender', get_option( 'snippen_smtp_from_name' ) );
	}

	/**
	 * Test that the configure_smtp hook correctly populates the PHPMailer properties.
	 */
	public function test_configure_smtp_action() {
		update_option( 'snippen_smtp_host', 'smtp.testmail.com' );
		update_option( 'snippen_smtp_port', 2525 );
		update_option( 'snippen_smtp_user', 'maileruser' );
		update_option( 'snippen_smtp_pass', 'mailerpass' );
		update_option( 'snippen_smtp_encryption', 'tls' );

		// Create a mock phpmailer object
		$phpmailer = new \stdClass();
		$phpmailer->Host = '';
		$phpmailer->SMTPAuth = false;
		$phpmailer->Port = 0;
		$phpmailer->Username = '';
		$phpmailer->Password = '';
		$phpmailer->SMTPSecure = '';

		// We need to define method isSMTP for configure_smtp
		$mock_phpmailer = $this->getMockBuilder( \stdClass::class )
			->addMethods( array( 'isSMTP' ) )
			->getMock();

		$mock_phpmailer->expects( $this->once() )
			->method( 'isSMTP' );

		// Bind properties
		$mock_phpmailer->Host = '';
		$mock_phpmailer->SMTPAuth = false;
		$mock_phpmailer->Port = 0;
		$mock_phpmailer->Username = '';
		$mock_phpmailer->Password = '';
		$mock_phpmailer->SMTPSecure = '';

		Plugin::configure_smtp( $mock_phpmailer );

		$this->assertEquals( 'smtp.testmail.com', $mock_phpmailer->Host );
		$this->assertTrue( $mock_phpmailer->SMTPAuth );
		$this->assertEquals( 2525, $mock_phpmailer->Port );
		$this->assertEquals( 'maileruser', $mock_phpmailer->Username );
		$this->assertEquals( 'mailerpass', $mock_phpmailer->Password );
		$this->assertEquals( 'tls', $mock_phpmailer->SMTPSecure );
	}

	/**
	 * Test that the wp_mail filters use the configured SMTP from identity.
	 */
	public function test_smtp_mail_from_filters() {
		update_option( 'snippen_smtp_from_email', 'noreply@snippentest.com' );
		update_option( 'snippen_smtp_from_name', 'Snippen Test Office' );

		$this->assertEquals( 'noreply@snippentest.com', Plugin::get_mail_from( 'original@wp.com' ) );
		$this->assertEquals( 'Snippen Test Office', Plugin::get_mail_from_name( 'Original WordPress Name' ) );

		// Test fallbacks when settings are empty
		delete_option( 'snippen_smtp_from_email' );
		delete_option( 'snippen_smtp_from_name' );

		$this->assertEquals( 'original@wp.com', Plugin::get_mail_from( 'original@wp.com' ) );
		$this->assertEquals( 'Original WordPress Name', Plugin::get_mail_from_name( 'Original WordPress Name' ) );
	}
}
