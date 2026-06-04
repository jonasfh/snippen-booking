<?php

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\PhoneAuthenticationService;

class PhoneAuthenticationTest extends TestCase {
	
	public function setUp(): void {
		parent::setUp();

		// Create a test user with a phone number
		$uniq = uniqid();
		$this->user_id = wp_insert_user(
			array(
				'user_login' => 'testuser_' . $uniq,
				'user_pass'  => 'password123',
				'user_email' => 'test_' . $uniq . '@example.com',
			)
		);
		
		if ( is_wp_error( $this->user_id ) ) {
			$this->fail( 'Failed to create user: ' . $this->user_id->get_error_message() );
		}
		
		update_user_meta( $this->user_id, 'snippen_phone', '+4790011223' );
	}

	public function test_authenticate_by_phone_success() {
		$user = PhoneAuthenticationService::authenticate_by_phone( null, '90011223', 'password123' );
		$this->assertInstanceOf( \WP_User::class, $user );
		$this->assertEquals( $this->user_id, $user->ID );
	}

	public function test_authenticate_by_phone_with_prefix_success() {
		$user = PhoneAuthenticationService::authenticate_by_phone( null, '+4790011223', 'password123' );
		$this->assertInstanceOf( \WP_User::class, $user );
		$this->assertEquals( $this->user_id, $user->ID );
	}

	public function test_authenticate_by_phone_wrong_password() {
		$result = PhoneAuthenticationService::authenticate_by_phone( null, '90011223', 'wrongpassword' );
		
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'incorrect_password', $result->get_error_code() );
	}

	public function test_authenticate_by_phone_invalid_number() {
		$result = PhoneAuthenticationService::authenticate_by_phone( null, '1234', 'password123' );
		
		$this->assertNull( $result );
	}

	public function test_authenticate_by_phone_not_found() {
		$result = PhoneAuthenticationService::authenticate_by_phone( null, '99988777', 'password123' );
		
		$this->assertNull( $result );
	}

	public function test_reset_password_by_phone_success() {
		$_POST['user_login'] = '90011223';
		$user = PhoneAuthenticationService::reset_password_by_phone( false, new \WP_Error() );
		$this->assertInstanceOf( \WP_User::class, $user );
		$this->assertEquals( $this->user_id, $user->ID );
		unset( $_POST['user_login'] );
	}

	public function test_reset_password_by_phone_invalid_number() {
		$_POST['user_login'] = '1234';
		$result = PhoneAuthenticationService::reset_password_by_phone( false, new \WP_Error() );
		$this->assertFalse( $result );
		unset( $_POST['user_login'] );
	}

	public function test_reset_password_by_phone_not_found() {
		$_POST['user_login'] = '99988777';
		$result = PhoneAuthenticationService::reset_password_by_phone( false, new \WP_Error() );
		$this->assertFalse( $result );
		unset( $_POST['user_login'] );
	}

	public function test_filter_password_reset_title() {
		$user_data = get_userdata( $this->user_id );
		$title = PhoneAuthenticationService::filter_password_reset_title( 'Default Title', 'test_user', $user_data );
		
		// It should replace the title with the template subject
		$this->assertEquals( 'Tilbakestill passord', $title );
	}

	public function test_filter_password_reset_message_email() {
		$user_data = get_userdata( $this->user_id );
		
		// Simulate POST missing user_login or not a phone
		$_POST['user_login'] = 'test_user';
		
		$message = PhoneAuthenticationService::filter_password_reset_message( 'Default Message', 'testkey', 'test_user', $user_data );
		
		$this->assertStringContainsString( 'Noen har bedt om å tilbakestille passordet', $message );
		$this->assertStringContainsString( 'action=rp&key=testkey', $message );
	}

	public function test_filter_password_reset_message_sms() {
		$user_data = get_userdata( $this->user_id );
		
		// Simulate POST with phone number
		$_POST['user_login'] = '90011223';
		
		// We mock the notification provider using the same approach as NotificationPluggableTest
		require_once __DIR__ . '/NotificationPluggableTest.php';
		\SnippenBooking\Tests\Integration\MockSmsProvider::$last_sms = null;
		
		$add_mock = function( $providers ) {
			$providers[] = new \SnippenBooking\Tests\Integration\MockSmsProvider();
			return $providers;
		};
		add_filter( 'snippen_booking_notification_providers', $add_mock );
		update_option( 'snippen_active_notification_provider', 'mock_sms' );
		update_option( 'snippen_sms_password_reset_enabled', 'yes' );
		update_option( 'snippen_email_password_reset_enabled', 'no' );
		
		$result = PhoneAuthenticationService::filter_password_reset_message( 'Default Message', 'testkey', 'test_user', $user_data );
		
		// Should return false because SMS was sent, aborting email
		$this->assertFalse( $result );
		
		// Check if SMS was sent
		$this->assertNotNull( \SnippenBooking\Tests\Integration\MockSmsProvider::$last_sms );
		$this->assertEquals( '+4790011223', \SnippenBooking\Tests\Integration\MockSmsProvider::$last_sms['to'] );
		$this->assertStringContainsString( 'tilbakestille passordet ditt', \SnippenBooking\Tests\Integration\MockSmsProvider::$last_sms['message'] );
		$this->assertStringContainsString( 'action=rp&key=testkey', \SnippenBooking\Tests\Integration\MockSmsProvider::$last_sms['message'] );
		
		// Cleanup
		remove_filter( 'snippen_booking_notification_providers', $add_mock );
		unset( $_POST['user_login'] );
	}
}
