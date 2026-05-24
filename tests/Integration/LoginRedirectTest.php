<?php
/**
 * Login Redirect Integration Tests
 *
 * @package SnippenBooking\Tests\Integration
 */

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Plugin;

/**
 * Integration tests for the login_redirect filter implementation.
 */
class LoginRedirectTest extends TestCase {

	/**
	 * Created user IDs during tests.
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

		// Activate plugin to register custom role
		\SnippenBooking\Database\Install::activate();
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
	 * Test that users with the 'snippen_resident' role are redirected to the front page.
	 */
	public function test_snippen_resident_redirects_to_front_page() {
		$username = 'resident_' . time();
		$email    = $username . '@example.com';
		$user_id  = wp_create_user( $username, 'password123', $email );
		$this->created_user_ids[] = $user_id;

		$user = new \WP_User( $user_id );
		$user->set_role( 'snippen_resident' );

		$requested_redirect = 'http://example.com/wp-admin/profile.php';
		
		// Apply the login_redirect filter
		$final_redirect = apply_filters( 'login_redirect', $requested_redirect, $requested_redirect, $user );

		// Should always be home_url('/') for snippen_resident
		$this->assertEquals( home_url( '/' ), $final_redirect );
	}

	/**
	 * Test that regular users keep their original redirect URL.
	 */
	public function test_regular_user_keeps_default_redirect() {
		$username = 'regular_' . time();
		$email    = $username . '@example.com';
		$user_id  = wp_create_user( $username, 'password123', $email );
		$this->created_user_ids[] = $user_id;

		$user = new \WP_User( $user_id );
		$user->set_role( 'subscriber' );

		$requested_redirect = 'http://example.com/wp-admin/profile.php';

		// Apply the login_redirect filter
		$final_redirect = apply_filters( 'login_redirect', $requested_redirect, $requested_redirect, $user );

		// Should preserve the original redirect URL for other roles
		$this->assertEquals( $requested_redirect, $final_redirect );
	}

	/**
	 * Test that when a login error occurs, the original redirect is preserved.
	 */
	public function test_login_error_keeps_default_redirect() {
		$error = new \WP_Error( 'invalid_username', 'Invalid username.' );
		$requested_redirect = 'http://example.com/wp-admin/profile.php';

		// Apply the login_redirect filter
		$final_redirect = apply_filters( 'login_redirect', $requested_redirect, $requested_redirect, $error );

		// Should preserve the original redirect URL on authentication errors
		$this->assertEquals( $requested_redirect, $final_redirect );
	}
}
