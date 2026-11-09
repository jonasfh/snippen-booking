<?php
/**
 * DoormanApiKeyApi Integration Tests
 *
 * @package SnippenBooking\Tests\Integration
 */

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Api\DoormanApiKeyApi;

/**
 * Class DoormanApiKeyApiTest
 */
class DoormanApiKeyApiTest extends TestCase {

	/**
	 * WP die callback.
	 *
	 * @var callable|null
	 */
	protected $wp_die_callback = null;

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->wp_die_callback = function () {
			return function ( $message ) {
				throw new \Exception( is_string( $message ) ? $message : wp_json_encode( $message ) );
			};
		};
		add_filter( 'wp_die_ajax_handler', $this->wp_die_callback );
		DoormanApiKeyApi::register();
	}

	/**
	 * Tear down test environment
	 */
	protected function tearDown(): void {
		if ( null !== $this->wp_die_callback ) {
			remove_filter( 'wp_die_ajax_handler', $this->wp_die_callback );
			$this->wp_die_callback = null;
		}
		delete_option( 'snippen_doorman_api_keys' );
		delete_option( 'snippen_doorman_api_token' );
		$_POST    = array();
		$_REQUEST = array();
		parent::tearDown();
	}

	/**
	 * Helper to catch JSON output from AJAX endpoints
	 *
	 * @param callable $func Callback to execute.
	 * @return array Decoded JSON response.
	 */
	private function catch_json_output( callable $func ) {
		ob_start();
		try {
			$func();
		} catch ( \Exception $e ) {
			// Intentionally empty: ignore wp_die exception.
			unset( $e );
		}
		$output = ob_get_clean();
		return json_decode( $output, true );
	}

	/**
	 * Test hashing token uses HMAC-SHA256 with WordPress auth salt.
	 */
	public function test_hash_token_uses_auth_salt() {
		$token    = 'sdo_test_token_123456789';
		$expected = hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );

		$this->assertSame( $expected, DoormanApiKeyApi::hash_token( $token ) );
	}

	/**
	 * Test token masking for various string lengths.
	 */
	public function test_mask_token() {
		$long_token = 'sdo_1234567890abcdef1234567890abcdef';
		$this->assertSame( 'sdo_1234••••••••••••••••cdef', DoormanApiKeyApi::mask_token( $long_token ) );

		$mid_token = 'abcdef';
		$this->assertSame( 'ab••••••••ef', DoormanApiKeyApi::mask_token( $mid_token ) );

		$short_token = 'abc';
		$this->assertSame( '••••••••', DoormanApiKeyApi::mask_token( $short_token ) );
	}

	/**
	 * Test token generation format.
	 */
	public function test_generate_token_format() {
		$token1 = DoormanApiKeyApi::generate_token();
		$token2 = DoormanApiKeyApi::generate_token();

		$this->assertStringStartsWith( 'sdo_', $token1 );
		$this->assertSame( 36, strlen( $token1 ) ); // 'sdo_' (4) + 32 hex chars
		$this->assertNotSame( $token1, $token2 );
	}

	/**
	 * Test auto-migration of legacy plaintext keys in snippen_doorman_api_keys.
	 */
	public function test_get_keys_auto_migrates_legacy_plaintext_tokens() {
		$legacy_keys = array(
			array(
				'id'         => 'key_legacy_1',
				'name'       => 'Old Door Node',
				'token'      => 'plain-secret-token-xyz',
				'created_at' => '2026-11-01 12:00:00',
			),
		);
		update_option( 'snippen_doorman_api_keys', $legacy_keys );

		$migrated = DoormanApiKeyApi::get_keys();

		$this->assertCount( 1, $migrated );
		$this->assertSame( 'key_legacy_1', $migrated[0]['id'] );
		$this->assertSame( 'Old Door Node', $migrated[0]['name'] );
		$this->assertArrayNotHasKey( 'token', $migrated[0] );
		$this->assertSame( DoormanApiKeyApi::hash_token( 'plain-secret-token-xyz' ), $migrated[0]['token_hash'] );
		$this->assertSame( DoormanApiKeyApi::mask_token( 'plain-secret-token-xyz' ), $migrated[0]['masked_token'] );

		// Verify option in database was updated
		$db_keys = get_option( 'snippen_doorman_api_keys' );
		$this->assertArrayNotHasKey( 'token', $db_keys[0] );
		$this->assertSame( $migrated[0]['token_hash'], $db_keys[0]['token_hash'] );
	}

	/**
	 * Test creating API key requires admin permissions.
	 */
	public function test_ajax_create_key_unauthorized() {
		$user_id = username_exists( 'nonadminuser' ) ?: wp_create_user( 'nonadminuser', 'pass123', 'nonadmin@test.local' );
		wp_set_current_user( $user_id );

		$_POST['nonce']    = wp_create_nonce( 'snippen_admin_nonce' );
		$_REQUEST['nonce'] = $_POST['nonce'];
		$_POST['name']     = 'Test Node';

		$res = $this->catch_json_output(
			function () {
				DoormanApiKeyApi::ajax_create_key();
			}
		);

		$this->assertIsArray( $res );
		$this->assertFalse( $res['success'] );
		$this->assertSame( 'Ingen tilgang.', $res['data']['message'] );
	}

	/**
	 * Test creating API key successfully via AJAX.
	 */
	public function test_ajax_create_key_success() {
		$admin_id = username_exists( 'adminkeyuser' ) ?: wp_create_user( 'adminkeyuser', 'pass123', 'adminkey@test.local' );
		$user     = new \WP_User( $admin_id );
		$user->add_cap( 'manage_options' );
		wp_set_current_user( $admin_id );

		$_POST['nonce']    = wp_create_nonce( 'snippen_admin_nonce' );
		$_REQUEST['nonce'] = $_POST['nonce'];
		$_POST['name']     = 'Grendehus Hoveddør';

		$res = $this->catch_json_output(
			function () {
				DoormanApiKeyApi::ajax_create_key();
			}
		);

		$this->assertIsArray( $res );
		$this->assertTrue( $res['success'] );
		$this->assertArrayHasKey( 'key', $res['data'] );

		$key = $res['data']['key'];
		$this->assertSame( 'Grendehus Hoveddør', $key['name'] );
		$this->assertStringStartsWith( 'sdo_', $key['plain_token'] );
		$this->assertSame( DoormanApiKeyApi::mask_token( $key['plain_token'] ), $key['masked_token'] );

		// Verify database option only stores hash and masked token
		$db_keys = get_option( 'snippen_doorman_api_keys' );
		$this->assertCount( 1, $db_keys );
		$this->assertSame( $key['id'], $db_keys[0]['id'] );
		$this->assertSame( 'Grendehus Hoveddør', $db_keys[0]['name'] );
		$this->assertArrayNotHasKey( 'plain_token', $db_keys[0] );
		$this->assertArrayNotHasKey( 'token', $db_keys[0] );
		$this->assertSame( DoormanApiKeyApi::hash_token( $key['plain_token'] ), $db_keys[0]['token_hash'] );
		$this->assertSame( $key['masked_token'], $db_keys[0]['masked_token'] );
	}

	/**
	 * Test deleting API key requires admin permissions.
	 */
	public function test_ajax_delete_key_unauthorized() {
		$user_id = username_exists( 'nonadminuser2' ) ?: wp_create_user( 'nonadminuser2', 'pass123', 'nonadmin2@test.local' );
		wp_set_current_user( $user_id );

		$_POST['nonce']    = wp_create_nonce( 'snippen_admin_nonce' );
		$_REQUEST['nonce'] = $_POST['nonce'];
		$_POST['key_id']   = 'some_key_id';

		$res = $this->catch_json_output(
			function () {
				DoormanApiKeyApi::ajax_delete_key();
			}
		);

		$this->assertIsArray( $res );
		$this->assertFalse( $res['success'] );
		$this->assertSame( 'Ingen tilgang.', $res['data']['message'] );
	}

	/**
	 * Test deleting a key that does not exist returns 404 error.
	 */
	public function test_ajax_delete_key_not_found() {
		$admin_id = username_exists( 'adminkeyuser2' ) ?: wp_create_user( 'adminkeyuser2', 'pass123', 'adminkey2@test.local' );
		$user     = new \WP_User( $admin_id );
		$user->add_cap( 'manage_options' );
		wp_set_current_user( $admin_id );

		$_POST['nonce']    = wp_create_nonce( 'snippen_admin_nonce' );
		$_REQUEST['nonce'] = $_POST['nonce'];
		$_POST['key_id']   = 'non_existent_key';

		$res = $this->catch_json_output(
			function () {
				DoormanApiKeyApi::ajax_delete_key();
			}
		);

		$this->assertIsArray( $res );
		$this->assertFalse( $res['success'] );
		$this->assertSame( 'Nøkkel ikke funnet.', $res['data']['message'] );
	}

	/**
	 * Test deleting API key successfully via AJAX.
	 */
	public function test_ajax_delete_key_success() {
		$admin_id = username_exists( 'adminkeyuser3' ) ?: wp_create_user( 'adminkeyuser3', 'pass123', 'adminkey3@test.local' );
		$user     = new \WP_User( $admin_id );
		$user->add_cap( 'manage_options' );
		wp_set_current_user( $admin_id );

		update_option(
			'snippen_doorman_api_keys',
			array(
				array(
					'id'           => 'key_to_delete',
					'name'         => 'Delete Me',
					'token_hash'   => DoormanApiKeyApi::hash_token( 'some-token' ),
					'masked_token' => DoormanApiKeyApi::mask_token( 'some-token' ),
					'created_at'   => '2026-11-09 10:00:00',
				),
				array(
					'id'           => 'key_to_keep',
					'name'         => 'Keep Me',
					'token_hash'   => DoormanApiKeyApi::hash_token( 'keep-token' ),
					'masked_token' => DoormanApiKeyApi::mask_token( 'keep-token' ),
					'created_at'   => '2026-11-09 10:00:00',
				),
			)
		);

		$_POST['nonce']    = wp_create_nonce( 'snippen_admin_nonce' );
		$_REQUEST['nonce'] = $_POST['nonce'];
		$_POST['key_id']   = 'key_to_delete';

		$res = $this->catch_json_output(
			function () {
				DoormanApiKeyApi::ajax_delete_key();
			}
		);

		$this->assertIsArray( $res );
		$this->assertTrue( $res['success'] );

		$db_keys = get_option( 'snippen_doorman_api_keys' );
		$this->assertCount( 1, $db_keys );
		$this->assertSame( 'key_to_keep', $db_keys[0]['id'] );
	}
}
