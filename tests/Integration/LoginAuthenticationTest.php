<?php
/**
 * Login Authentication Integration Tests
 *
 * @package SnippenBooking\Tests\Integration
 */

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\PhoneAuthenticationService;
use SnippenBooking\Helper\PhoneHelper;

/**
 * Integration tests for username, email, and phone authentication.
 */
class LoginAuthenticationTest extends TestCase {

	/**
	 * Created user IDs.
	 *
	 * @var array
	 */
	private $created_user_ids = array();

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}
		$this->created_user_ids = array();

		// Ensure hooks are registered
		PhoneAuthenticationService::register();
	}

	/**
	 * Tear down test environment.
	 */
	public function tearDown(): void {
		foreach ( $this->created_user_ids as $user_id ) {
			wp_delete_user( $user_id );
		}
		parent::tearDown();
	}

	/**
	 * Test normalize_phone ignores emails and usernames containing letters.
	 */
	public function test_normalize_phone_rejects_emails_and_usernames() {
		$this->assertFalse( PhoneHelper::normalize_phone( 'user12345678@example.com' ) );
		$this->assertFalse( PhoneHelper::normalize_phone( 'user12345678' ) );
		$this->assertEquals( '+4791234567', PhoneHelper::normalize_phone( '91234567' ) );
	}

	/**
	 * Test login using username.
	 */
	public function test_login_by_username() {
		$username = 'loginuser_' . time();
		$email    = $username . '@example.com';
		$password = 'secretpass123';
		$user_id  = wp_create_user( $username, $password, $email );
		$this->created_user_ids[] = $user_id;

		$auth_user = wp_authenticate( $username, $password );

		$this->assertInstanceOf( '\WP_User', $auth_user );
		$this->assertEquals( $user_id, $auth_user->ID );
	}

	/**
	 * Test login using email.
	 */
	public function test_login_by_email() {
		$username = 'loginemail_' . time();
		$email    = $username . '@example.com';
		$password = 'secretpass123';
		$user_id  = wp_create_user( $username, $password, $email );
		$this->created_user_ids[] = $user_id;

		$auth_user = wp_authenticate( $email, $password );

		$this->assertInstanceOf( '\WP_User', $auth_user );
		$this->assertEquals( $user_id, $auth_user->ID );
	}

	/**
	 * Test login using phone number.
	 */
	public function test_login_by_phone() {
		$username = 'loginphone_' . time();
		$email    = $username . '@example.com';
		$password = 'secretpass123';
		$phone    = '+4741122334';
		$user_id  = wp_create_user( $username, $password, $email );
		$this->created_user_ids[] = $user_id;
		update_user_meta( $user_id, 'snippen_phone', $phone );

		// Authenticate with phone number
		$auth_user = wp_authenticate( '41122334', $password );

		$this->assertInstanceOf( '\WP_User', $auth_user );
		$this->assertEquals( $user_id, $auth_user->ID );
	}
}
