<?php
/**
 * Ajax Login API Integration Tests
 *
 * @package SnippenBooking\Tests\Integration
 */

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Api\UserApi;

/**
 * Integration tests for UserApi::login AJAX endpoint.
 */
class LoginApiTest extends TestCase {

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

		\SnippenBooking\Service\PhoneAuthenticationService::register();
		UserApi::register();
	}

	/**
	 * Tear down test environment.
	 */
	public function tearDown(): void {
		foreach ( $this->created_user_ids as $user_id ) {
			wp_delete_user( $user_id );
		}
		$_POST = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * Helper to call AJAX endpoint and capture json output.
	 *
	 * @return array
	 */
	private function call_ajax_login() {
		try {
			ob_start();
			UserApi::login();
			$output = ob_get_clean();
		} catch ( \WPAjaxDieContinueException $e ) {
			$output = ob_get_clean();
		} catch ( \Exception $e ) {
			$output = ob_get_clean();
		}

		return json_decode( $output, true );
	}

	/**
	 * Test login via AJAX with username.
	 */
	public function test_ajax_login_with_username() {
		$username = 'ajaxuser_' . time();
		$email    = $username . '@example.com';
		$password = 'secretpass123!';
		$user_id  = wp_create_user( $username, $password, $email );
		$this->created_user_ids[] = $user_id;

		$_POST['action']      = 'snippen_login';
		$_POST['nonce']       = wp_create_nonce( 'snippen_login_nonce' );
		$_POST['log']         = $username;
		$_POST['pwd']         = $password;
		$_POST['redirect_to'] = 'https://example.com/custom-redirect';

		$res = $this->call_ajax_login();

		$this->assertTrue( $res['success'] );
		$this->assertEquals( 'https://example.com/custom-redirect', $res['data']['redirect_url'] );
		$this->assertEquals( $user_id, get_current_user_id() );
	}

	/**
	 * Test login via AJAX with email.
	 */
	public function test_ajax_login_with_email() {
		$username = 'ajaxemail_' . time();
		$email    = $username . '@example.com';
		$password = 'secretpass123!';
		$user_id  = wp_create_user( $username, $password, $email );
		$this->created_user_ids[] = $user_id;

		$_POST['action'] = 'snippen_login';
		$_POST['nonce']  = wp_create_nonce( 'snippen_login_nonce' );
		$_POST['log']    = $email;
		$_POST['pwd']    = $password;

		$res = $this->call_ajax_login();

		$this->assertTrue( $res['success'] );
		$this->assertEquals( $user_id, get_current_user_id() );
	}

	/**
	 * Test login via AJAX with phone number.
	 */
	public function test_ajax_login_with_phone() {
		$username = 'ajaxphone_' . time();
		$email    = $username . '@example.com';
		$password = 'secretpass123!';
		$phone    = '+4798765432';
		$user_id  = wp_create_user( $username, $password, $email );
		$this->created_user_ids[] = $user_id;
		update_user_meta( $user_id, 'snippen_phone', $phone );

		$_POST['action'] = 'snippen_login';
		$_POST['nonce']  = wp_create_nonce( 'snippen_login_nonce' );
		$_POST['log']    = '98765432';
		$_POST['pwd']    = $password;

		$res = $this->call_ajax_login();

		$this->assertTrue( $res['success'] );
		$this->assertEquals( $user_id, get_current_user_id() );
	}

	/**
	 * Test login via AJAX with invalid password.
	 */
	public function test_ajax_login_invalid_password() {
		$username = 'ajaxbadpass_' . time();
		$email    = $username . '@example.com';
		$password = 'secretpass123!';
		$user_id  = wp_create_user( $username, $password, $email );
		$this->created_user_ids[] = $user_id;

		$_POST['action'] = 'snippen_login';
		$_POST['nonce']  = wp_create_nonce( 'snippen_login_nonce' );
		$_POST['log']    = $username;
		$_POST['pwd']    = 'wrongpass';

		$res = $this->call_ajax_login();

		$this->assertFalse( $res['success'] );
		$this->assertNotEmpty( $res['data']['message'] );
	}
}
