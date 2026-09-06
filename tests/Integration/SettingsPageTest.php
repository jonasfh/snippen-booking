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
		delete_option( 'snippen_notification_dispatch_method' );
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

		$this->assertStringContainsString( 'E-post-varsler', $output );
		$this->assertStringContainsString( 'id="tab-email"', $output );
		$this->assertStringContainsString( 'id="tab-keysms"', $output );
		$this->assertStringContainsString( 'id="tab-payment"', $output );
		$this->assertStringContainsString( 'id="tab-general"', $output );
		$this->assertStringContainsString( 'name="snippen_payment_bank_account"', $output );
		$this->assertStringContainsString( 'name="snippen_payment_vipps_number"', $output );
		$this->assertStringContainsString( 'name="snippen_smtp_enabled"', $output );
		$this->assertStringContainsString( 'name="snippen_smtp_host"', $output );
		$this->assertStringContainsString( 'name="snippen_smtp_port"', $output );
		$this->assertStringContainsString( 'name="snippen_smtp_user"', $output );
		$this->assertStringContainsString( 'name="snippen_smtp_pass"', $output );
		$this->assertStringContainsString( 'name="snippen_smtp_encryption"', $output );
		$this->assertStringContainsString( 'name="snippen_smtp_from_email"', $output );
		$this->assertStringContainsString( 'name="snippen_notification_dispatch_method"', $output );
		$this->assertStringContainsString( 'name="snippen_email_booking_confirmed_enabled"', $output );
		$this->assertStringContainsString( 'name="snippen_email_payment_received_enabled"', $output );
		$this->assertStringContainsString( 'name="snippen_sms_booking_confirmed_enabled"', $output );
		$this->assertStringContainsString( 'name="snippen_sms_payment_received_enabled"', $output );
	}

	/**
	 * Test that POST request saves the SMTP settings.
	 */
	public function test_settings_page_saves_smtp_options() {
		// Create a mock POST request
		$_POST['snippen_settings_nonce'] = wp_create_nonce( 'snippen_save_settings' );
		$_POST['snippen_email_booking_confirmation_enabled'] = 'yes';
		$_POST['snippen_email_admin_booking_enabled'] = 'yes';
		$_POST['snippen_email_user_activation_enabled'] = 'yes';
		$_POST['snippen_email_password_reset_enabled'] = 'yes';
		$_POST['snippen_sms_booking_confirmation_enabled'] = 'yes';
		$_POST['snippen_sms_admin_booking_enabled'] = 'yes';
		$_POST['snippen_sms_user_activation_enabled'] = 'yes';
		$_POST['snippen_sms_password_reset_enabled'] = 'yes';
		$_POST['snippen_email_booking_confirmed_enabled'] = 'yes';
		$_POST['snippen_email_payment_received_enabled'] = 'yes';
		$_POST['snippen_sms_booking_confirmed_enabled'] = 'yes';
		$_POST['snippen_sms_payment_received_enabled'] = 'yes';
		$_POST['snippen_smtp_enabled'] = 'yes';
		$_POST['snippen_smtp_host'] = 'smtp.example.com';
		$_POST['snippen_smtp_port'] = '465';
		$_POST['snippen_smtp_user'] = 'testuser';
		$_POST['snippen_smtp_pass'] = 'securepass123';
		$_POST['snippen_smtp_encryption'] = 'ssl';
		$_POST['snippen_smtp_from_email'] = 'testfrom@example.com';
		$_POST['snippen_smtp_from_name'] = 'Test Sender';
		$_POST['snippen_notification_dispatch_method'] = 'sync';

		$page = new SettingsPage();

		ob_start();
		$page->render();
		ob_get_clean();

		// Clean up POST mock
		$_POST = array();

		// Assert options are updated in database
		$this->assertEquals( 'yes', get_option( 'snippen_email_booking_confirmation_enabled' ) );
		$this->assertEquals( 'yes', get_option( 'snippen_email_admin_booking_enabled' ) );
		$this->assertEquals( 'yes', get_option( 'snippen_email_user_activation_enabled' ) );
		$this->assertEquals( 'yes', get_option( 'snippen_email_password_reset_enabled' ) );
		$this->assertEquals( 'yes', get_option( 'snippen_sms_booking_confirmation_enabled' ) );
		$this->assertEquals( 'yes', get_option( 'snippen_sms_admin_booking_enabled' ) );
		$this->assertEquals( 'yes', get_option( 'snippen_sms_user_activation_enabled' ) );
		$this->assertEquals( 'yes', get_option( 'snippen_sms_password_reset_enabled' ) );
		$this->assertEquals( 'yes', get_option( 'snippen_email_booking_confirmed_enabled' ) );
		$this->assertEquals( 'yes', get_option( 'snippen_email_payment_received_enabled' ) );
		$this->assertEquals( 'yes', get_option( 'snippen_sms_booking_confirmed_enabled' ) );
		$this->assertEquals( 'yes', get_option( 'snippen_sms_payment_received_enabled' ) );
		$this->assertEquals( 'yes', get_option( 'snippen_smtp_enabled' ) );
		$this->assertEquals( 'smtp.example.com', get_option( 'snippen_smtp_host' ) );
		$this->assertEquals( 465, get_option( 'snippen_smtp_port' ) );
		$this->assertEquals( 'testuser', get_option( 'snippen_smtp_user' ) );
		$this->assertEquals( 'securepass123', get_option( 'snippen_smtp_pass' ) );
		$this->assertEquals( 'ssl', get_option( 'snippen_smtp_encryption' ) );
		$this->assertEquals( 'testfrom@example.com', get_option( 'snippen_smtp_from_email' ) );
		$this->assertEquals( 'Test Sender', get_option( 'snippen_smtp_from_name' ) );
		$this->assertEquals( 'sync', get_option( 'snippen_notification_dispatch_method' ) );
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

	/**
	 * Test that the settings page renders the door code toggle.
	 */
	public function test_settings_page_renders_door_code_toggle() {
		$page = new SettingsPage();

		ob_start();
		$page->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'name="snippen_enable_door_code"', $output );
		$this->assertStringContainsString( 'Aktiver dørkode-system', $output );
	}

	/**
	 * Test saving the enable door code option.
	 */
	public function test_settings_page_saves_door_code_toggle() {
		$_POST['snippen_settings_nonce'] = wp_create_nonce( 'snippen_save_settings' );
		$_POST['snippen_enable_door_code'] = 'yes';
		$_POST['snippen_door_code_hours_before'] = '12';
		$_POST['snippen_door_code_hours_after'] = '4';

		$page = new SettingsPage();

		ob_start();
		$page->render();
		ob_get_clean();

		$_POST = array();

		$this->assertEquals( 'yes', get_option( 'snippen_enable_door_code' ) );
		$this->assertEquals( 12, get_option( 'snippen_door_code_hours_before' ) );
		$this->assertEquals( 4, get_option( 'snippen_door_code_hours_after' ) );

		// Clean up
		delete_option( 'snippen_enable_door_code' );
		delete_option( 'snippen_door_code_hours_before' );
		delete_option( 'snippen_door_code_hours_after' );
	}
}

