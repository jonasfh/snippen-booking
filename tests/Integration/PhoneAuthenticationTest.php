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
		$user = PhoneAuthenticationService::reset_password_by_phone( false, '90011223' );
		$this->assertInstanceOf( \WP_User::class, $user );
		$this->assertEquals( $this->user_id, $user->ID );
	}

	public function test_reset_password_by_phone_invalid_number() {
		$result = PhoneAuthenticationService::reset_password_by_phone( false, '1234' );
		
		$this->assertFalse( $result );
	}

	public function test_reset_password_by_phone_not_found() {
		$result = PhoneAuthenticationService::reset_password_by_phone( false, '99988777' );
		
		$this->assertFalse( $result );
	}
}
