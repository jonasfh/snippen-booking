<?php
/**
 * Vipps Settings & Test Connection Integration Tests
 *
 * @package SnippenBooking\Tests\Integration
 */

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Admin\Pages\SettingsPage;
use SnippenBooking\Api\VippsTestConnectionApi;

/**
 * VippsSettingsIntegrationTest
 */
class VippsSettingsIntegrationTest extends TestCase {

	/**
	/**
	 * WP die callback.
	 *
	 * @var callable|null
	 */
	protected $wp_die_callback = null;

	/**
	 * HTTP mock callback.
	 *
	 * @var callable|null
	 */
	protected $http_mock_callback = null;

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
		VippsTestConnectionApi::register();
	}

	/**
	 * Set mock HTTP filter.
	 *
	 * @param callable $callback Filter callback.
	 */
	protected function set_http_mock( callable $callback ) {
		if ( null !== $this->http_mock_callback ) {
			remove_filter( 'pre_http_request', $this->http_mock_callback, 10 );
		}
		$this->http_mock_callback = $callback;
		add_filter( 'pre_http_request', $this->http_mock_callback, 10, 3 );
	}

	/**
	 * Tear down after each test
	 */
	protected function tearDown(): void {
		if ( null !== $this->wp_die_callback ) {
			remove_filter( 'wp_die_ajax_handler', $this->wp_die_callback );
			$this->wp_die_callback = null;
		}
		if ( null !== $this->http_mock_callback ) {
			remove_filter( 'pre_http_request', $this->http_mock_callback, 10 );
			$this->http_mock_callback = null;
		}
		delete_option( 'snippen_vipps_enabled' );
		delete_option( 'snippen_vipps_environment' );
		delete_option( 'snippen_vipps_client_id' );
		delete_option( 'snippen_vipps_client_secret' );
		delete_option( 'snippen_vipps_subscription_key' );
		delete_option( 'snippen_vipps_msn' );
		$_POST    = array();
		$_REQUEST = array();
		parent::tearDown();
	}

	/**
	 * Test that SettingsPage renders Vipps fields
	 */
	public function test_settings_page_renders_vipps_fields() {
		$page = new SettingsPage();

		ob_start();
		$page->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'name="snippen_vipps_enabled"', $output );
		$this->assertStringContainsString( 'name="snippen_vipps_environment"', $output );
		$this->assertStringContainsString( 'name="snippen_vipps_client_id"', $output );
		$this->assertStringContainsString( 'name="snippen_vipps_client_secret"', $output );
		$this->assertStringContainsString( 'name="snippen_vipps_subscription_key"', $output );
		$this->assertStringContainsString( 'name="snippen_vipps_msn"', $output );
		$this->assertStringContainsString( 'id="snippen-vipps-test-btn"', $output );
	}

	/**
	 * Test saving Vipps settings via SettingsPage
	 */
	public function test_settings_page_saves_vipps_options() {
		$_POST['snippen_settings_nonce']         = wp_create_nonce( 'snippen_save_settings' );
		$_POST['snippen_vipps_enabled']          = 'yes';
		$_POST['snippen_vipps_environment']      = 'prod';
		$_POST['snippen_vipps_client_id']        = 'prod-client-uuid-123';
		$_POST['snippen_vipps_client_secret']    = 'prod-secret-abc';
		$_POST['snippen_vipps_subscription_key'] = 'prod-sub-key-456';
		$_POST['snippen_vipps_msn']              = '654321';

		$page = new SettingsPage();

		ob_start();
		$page->render();
		ob_get_clean();

		$this->assertEquals( 'yes', get_option( 'snippen_vipps_enabled' ) );
		$this->assertEquals( 'prod', get_option( 'snippen_vipps_environment' ) );
		$this->assertEquals( 'prod-client-uuid-123', get_option( 'snippen_vipps_client_id' ) );
		$this->assertEquals( 'prod-secret-abc', get_option( 'snippen_vipps_client_secret' ) );
		$this->assertEquals( 'prod-sub-key-456', get_option( 'snippen_vipps_subscription_key' ) );
		$this->assertEquals( '654321', get_option( 'snippen_vipps_msn' ) );
	}

	/**
	 * Test AJAX test connection endpoint requires proper capability
	 */
	public function test_ajax_test_connection_capability_check() {
		// Non-admin user
		$user_id = username_exists( 'vippsreguser' ) ?: wp_create_user( 'vippsreguser', 'password123', 'vippsreguser@example.test' );
		wp_set_current_user( $user_id );

		$_POST['nonce']    = wp_create_nonce( 'snippen_admin_nonce' );
		$_REQUEST['nonce'] = $_POST['nonce'];

		$res = $this->catch_json_output(
			function () {
				VippsTestConnectionApi::test_connection();
			}
		);

		$this->assertIsArray( $res );
		$this->assertFalse( $res['success'] );
		$this->assertEquals( 'Ingen tilgang.', $res['data']['message'] );
	}

	/**
	 * Test AJAX test connection endpoint success response
	 */
	public function test_ajax_test_connection_success() {
		// Set admin user
		$admin_id = username_exists( 'vippsadmin1' ) ?: wp_create_user( 'vippsadmin1', 'pass123', 'vippsadmin1@example.test' );
		$user     = new \WP_User( $admin_id );
		$user->add_cap( 'manage_options' );
		wp_set_current_user( $admin_id );

		$this->set_http_mock(
			function ( $pre, $args, $url ) {
				if ( strpos( $url, '/accesstoken/get' ) !== false ) {
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode(
							array(
								'token_type'   => 'Bearer',
								'expires_in'   => 3600,
								'access_token' => 'test-valid-token',
							)
						),
					);
				}
				return $pre;
			}
		);

		$_POST['nonce']            = wp_create_nonce( 'snippen_admin_nonce' );
		$_REQUEST['nonce']         = $_POST['nonce'];
		$_POST['client_id']        = 'test-id';
		$_POST['client_secret']    = 'test-secret';
		$_POST['subscription_key'] = 'test-sub';
		$_POST['msn']              = '123456';
		$_POST['environment']      = 'test';

		$res = $this->catch_json_output(
			function () {
				VippsTestConnectionApi::test_connection();
			}
		);

		$this->assertIsArray( $res );
		$this->assertTrue( $res['success'] );
		$this->assertStringContainsString( 'Tilkobling vellykket', $res['data']['message'] );
	}

	/**
	 * Test AJAX test connection endpoint error response
	 */
	public function test_ajax_test_connection_failure() {
		$admin_id = username_exists( 'vippsadmin2' ) ?: wp_create_user( 'vippsadmin2', 'pass123', 'vippsadmin2@example.test' );
		$user     = new \WP_User( $admin_id );
		$user->add_cap( 'manage_options' );
		wp_set_current_user( $admin_id );

		$this->set_http_mock(
			function ( $pre, $args, $url ) {
				if ( strpos( $url, '/accesstoken/get' ) !== false ) {
					return array(
						'response' => array( 'code' => 401 ),
						'body'     => wp_json_encode(
							array(
								'message' => 'Invalid credentials',
							)
						),
					);
				}
				return $pre;
			}
		);

		$_POST['nonce']            = wp_create_nonce( 'snippen_admin_nonce' );
		$_REQUEST['nonce']         = $_POST['nonce'];
		$_POST['client_id']        = 'wrong-id';
		$_POST['client_secret']    = 'wrong-secret';
		$_POST['subscription_key'] = 'wrong-sub';
		$_POST['msn']              = '123456';
		$_POST['environment']      = 'test';

		$res = $this->catch_json_output(
			function () {
				VippsTestConnectionApi::test_connection();
			}
		);

		$this->assertIsArray( $res );
		$this->assertFalse( $res['success'] );
		$this->assertStringContainsString( 'HTTP 401', $res['data']['message'] );
	}

	/**
	 * Test that SettingsPage does not expose raw secret values in HTML.
	 */
	public function test_settings_page_masks_secrets_in_html() {
		update_option( 'snippen_vipps_client_secret', 'ultra-secret-test-key-123' );
		update_option( 'snippen_vipps_subscription_key', 'sub-key-super-secret-456' );

		$page = new SettingsPage();
		ob_start();
		$page->render();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'ultra-secret-test-key-123', $output );
		$this->assertStringNotContainsString( 'sub-key-super-secret-456', $output );
		$this->assertStringContainsString( 'placeholder="••••••••••••••••"', $output );
		$this->assertStringContainsString( 'name="snippen_vipps_client_secret" id="snippen_vipps_client_secret" value=""', $output );
		$this->assertStringContainsString( 'name="snippen_vipps_subscription_key" id="snippen_vipps_subscription_key" value=""', $output );
	}

	/**
	 * Test that submitting empty secret fields retains previously stored secrets.
	 */
	public function test_settings_page_preserves_existing_secrets_when_blank_submitted() {
		update_option( 'snippen_vipps_client_secret', 'original-client-secret-123' );
		update_option( 'snippen_vipps_subscription_key', 'original-subscription-key-456' );

		$_POST['snippen_settings_nonce']         = wp_create_nonce( 'snippen_save_settings' );
		$_POST['snippen_vipps_enabled']          = 'yes';
		$_POST['snippen_vipps_environment']      = 'test';
		$_POST['snippen_vipps_client_id']        = 'updated-client-id';
		$_POST['snippen_vipps_client_secret']    = '';
		$_POST['snippen_vipps_subscription_key'] = '';
		$_POST['snippen_vipps_msn']              = '123456';

		$page = new SettingsPage();
		ob_start();
		$page->render();
		ob_get_clean();

		$this->assertEquals( 'updated-client-id', get_option( 'snippen_vipps_client_id' ) );
		$this->assertEquals( 'original-client-secret-123', get_option( 'snippen_vipps_client_secret' ) );
		$this->assertEquals( 'original-subscription-key-456', get_option( 'snippen_vipps_subscription_key' ) );
	}

	/**
	 * Test that SettingsPage displays security mode indicator and readonly attributes when configured via environment.
	 */
	public function test_settings_page_shows_secure_indicator_and_readonly_when_env_defined() {
		putenv( 'SNIPPEN_VIPPS_CLIENT_SECRET=env-super-secret' );
		putenv( 'SNIPPEN_VIPPS_SUBSCRIPTION_KEY=env-sub-key' );

		$page = new SettingsPage();
		ob_start();
		$page->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Konfigurert via miljøvariabler / wp-config.php (sikker modus)', $output );
		$this->assertStringContainsString( 'placeholder="•••••••••••••••• (Definert i miljø / wp-config.php)"', $output );
		$this->assertStringContainsString( 'readonly="readonly"', $output );

		// Attempt to overwrite via POST while env is active
		$_POST['snippen_settings_nonce']         = wp_create_nonce( 'snippen_save_settings' );
		$_POST['snippen_vipps_enabled']          = 'yes';
		$_POST['snippen_vipps_client_secret']    = 'attempted-post-secret';
		$_POST['snippen_vipps_subscription_key'] = 'attempted-post-subkey';

		ob_start();
		$page->render();
		ob_get_clean();

		$this->assertNotEquals( 'attempted-post-secret', get_option( 'snippen_vipps_client_secret' ) );
		$this->assertNotEquals( 'attempted-post-subkey', get_option( 'snippen_vipps_subscription_key' ) );

		putenv( 'SNIPPEN_VIPPS_CLIENT_SECRET' );
		putenv( 'SNIPPEN_VIPPS_SUBSCRIPTION_KEY' );
	}

	/**
	 * Helper to catch wp_send_json output
	 *
	 * @param callable $func Callback to invoke.
	 * @return array
	 */
	private function catch_json_output( callable $func ) {
		ob_start();
		try {
			$func();
		} catch ( \Throwable $e ) {
			unset( $e );
		}
		$output = ob_get_clean();
		return json_decode( $output, true );
	}
}
