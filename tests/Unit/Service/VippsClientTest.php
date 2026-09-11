<?php
/**
 * Unit tests for VippsClient
 *
 * @package SnippenBooking\Tests\Unit\Service
 */

namespace SnippenBooking\Tests\Unit\Service;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\Vipps\VippsClient;

/**
 * VippsClientTest
 */
class VippsClientTest extends TestCase {

	/**
	 * Whether the test requires database setup and seed data.
	 */
	protected $requires_db = false;

	/**
	 * Active HTTP mock callback.
	 *
	 * @var callable|null
	 */
	protected $http_mock_callback = null;

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		update_option( 'snippen_vipps_client_id', 'test-client-id' );
		update_option( 'snippen_vipps_client_secret', 'test-client-secret' );
		update_option( 'snippen_vipps_subscription_key', 'test-sub-key' );
		update_option( 'snippen_vipps_msn', '123456' );
		update_option( 'snippen_vipps_environment', 'test' );
		delete_transient( 'snippen_vp_token_' . md5( 'testtest-client-idtest-sub-key123456' ) );
	}

	/**
	 * Set a mock HTTP response filter and track it for cleanup.
	 *
	 * @param callable $callback Callback function.
	 * @param int      $priority Filter priority.
	 */
	protected function set_http_mock( callable $callback, int $priority = 10 ) {
		if ( null !== $this->http_mock_callback ) {
			remove_filter( 'pre_http_request', $this->http_mock_callback, 10 );
		}
		$this->http_mock_callback = $callback;
		add_filter( 'pre_http_request', $this->http_mock_callback, $priority, 3 );
	}

	/**
	 * Clean up after test
	 */
	protected function tearDown(): void {
		if ( null !== $this->http_mock_callback ) {
			remove_filter( 'pre_http_request', $this->http_mock_callback, 10 );
			$this->http_mock_callback = null;
		}
		delete_option( 'snippen_vipps_client_id' );
		delete_option( 'snippen_vipps_client_secret' );
		delete_option( 'snippen_vipps_subscription_key' );
		delete_option( 'snippen_vipps_msn' );
		delete_option( 'snippen_vipps_environment' );
		delete_transient( 'snippen_vp_token_' . md5( 'testtest-client-idtest-sub-key123456' ) );
		parent::tearDown();
	}

	/**
	 * Test configuration and base URL resolution
	 */
	public function test_configuration_and_base_urls() {
		$client = new VippsClient();
		$this->assertTrue( $client->is_configured() );
		$this->assertEquals( 'test', $client->get_environment() );
		$this->assertEquals( 'https://apitest.vipps.no', $client->get_base_url() );

		$prod_client = new VippsClient( array( 'environment' => 'prod' ) );
		$this->assertEquals( 'prod', $prod_client->get_environment() );
		$this->assertEquals( 'https://api.vipps.no', $prod_client->get_base_url() );

		$unconfigured = new VippsClient(
			array(
				'client_id'        => '',
				'client_secret'    => '',
				'subscription_key' => '',
				'msn'              => '',
			)
		);
		$this->assertFalse( $unconfigured->is_configured() );
	}

	/**
	 * Test access token retrieval and transient caching
	 */
	public function test_get_access_token_caching() {
		$http_call_count = 0;

		$this->set_http_mock(
			function ( $pre, $args, $url ) use ( &$http_call_count ) {
				if ( strpos( $url, '/accesstoken/get' ) !== false ) {
					$http_call_count++;
					$this->assertEquals( 'test-client-id', $args['headers']['client_id'] );
					$this->assertEquals( 'test-client-secret', $args['headers']['client_secret'] );
					$this->assertEquals( 'test-sub-key', $args['headers']['Ocp-Apim-Subscription-Key'] );
					$this->assertEquals( '123456', $args['headers']['Merchant-Serial-Number'] );

					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode(
							array(
								'token_type'   => 'Bearer',
								'expires_in'   => 3600,
								'access_token' => 'mocked-jwt-token-12345',
							)
						),
					);
				}
				return $pre;
			}
		);

		$client = new VippsClient();

		// First call should make an HTTP request
		$token1 = $client->get_access_token();
		$this->assertEquals( 'mocked-jwt-token-12345', $token1 );
		$this->assertEquals( 1, $http_call_count );

		// Second call should return cached token without HTTP request
		$token2 = $client->get_access_token();
		$this->assertEquals( 'mocked-jwt-token-12345', $token2 );
		$this->assertEquals( 1, $http_call_count );

		// Force refresh should bypass cache and trigger HTTP request
		$token3 = $client->get_access_token( true );
		$this->assertEquals( 'mocked-jwt-token-12345', $token3 );
		$this->assertEquals( 2, $http_call_count );
	}

	/**
	 * Test token request failure when credentials missing
	 */
	public function test_get_access_token_unconfigured() {
		$client = new VippsClient( array( 'client_id' => '' ) );
		$token  = $client->get_access_token();
		$this->assertNull( $token );
	}

	/**
	 * Test token request failure on HTTP error
	 */
	public function test_get_access_token_http_error() {
		$this->set_http_mock(
			function ( $pre, $args, $url ) {
				if ( strpos( $url, '/accesstoken/get' ) !== false ) {
					return array(
						'response' => array( 'code' => 401 ),
						'body'     => wp_json_encode( array( 'error' => 'invalid_client' ) ),
					);
				}
				return $pre;
			}
		);

		$client = new VippsClient();
		$token  = $client->get_access_token( true );
		$this->assertNull( $token );
	}

	/**
	 * Test test_connection helper method
	 */
	public function test_connection_helper() {
		$this->set_http_mock(
			function ( $pre, $args, $url ) {
				if ( strpos( $url, '/accesstoken/get' ) !== false ) {
					if ( $args['headers']['client_id'] === 'valid-id' ) {
						return array(
							'response' => array( 'code' => 200 ),
							'body'     => wp_json_encode( array( 'access_token' => 'ok-token' ) ),
						);
					} else {
						return array(
							'response' => array( 'code' => 403 ),
							'body'     => wp_json_encode( array( 'message' => 'Invalid subscription key' ) ),
						);
					}
				}
				return $pre;
			}
		);

		// Missing fields
		$res1 = VippsClient::test_connection( '', 'secret', 'sub', '123' );
		$this->assertFalse( $res1['success'] );

		// Valid
		$res2 = VippsClient::test_connection( 'valid-id', 'valid-secret', 'valid-sub', '123456', 'test' );
		$this->assertTrue( $res2['success'] );
		$this->assertStringContainsString( 'Vipps MobilePay', $res2['message'] );

		// Invalid credentials
		$res3 = VippsClient::test_connection( 'bad-id', 'bad-secret', 'bad-sub', '123456', 'test' );
		$this->assertFalse( $res3['success'] );
		$this->assertStringContainsString( 'Invalid subscription key', $res3['message'] );
	}

	/**
	 * Test create_payment method
	 */
	public function test_create_payment() {
		$created_payload = null;

		$this->set_http_mock(
			function ( $pre, $args, $url ) use ( &$created_payload ) {
				if ( strpos( $url, '/accesstoken/get' ) !== false ) {
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode( array( 'access_token' => 'test-token' ) ),
					);
				}
				if ( strpos( $url, '/epayment/v1/payments' ) !== false ) {
					$created_payload = json_decode( $args['body'], true );
					$this->assertEquals( 'Bearer test-token', $args['headers']['Authorization'] );
					$this->assertEquals( 'test-sub-key', $args['headers']['Ocp-Apim-Subscription-Key'] );
					$this->assertEquals( '123456', $args['headers']['Merchant-Serial-Number'] );
					$this->assertNotEmpty( $args['headers']['Idempotency-Key'] );

					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode(
							array(
								'reference'   => 'snippen-test-ref-1',
								'redirectUrl' => 'https://api.vipps.no/epayment/v1/checkout/12345',
								'state'       => 'CREATED',
							)
						),
					);
				}
				return $pre;
			}
		);

		$client = new VippsClient();
		$result = $client->create_payment(
			array(
				'amount'    => array(
					'value'    => 15000,
					'currency' => 'NOK',
				),
				'reference' => 'snippen-test-ref-1',
				'userFlow'  => 'WEB_REDIRECT',
			)
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'snippen-test-ref-1', $result['reference'] );
		$this->assertEquals( 'https://api.vipps.no/epayment/v1/checkout/12345', $result['redirectUrl'] );
		$this->assertEquals( 15000, $created_payload['amount']['value'] );
	}

	/**
	 * Test get_payment, capture_payment, and cancel_payment
	 */
	public function test_payment_operations() {
		$this->set_http_mock(
			function ( $pre, $args, $url ) {
				if ( strpos( $url, '/accesstoken/get' ) !== false ) {
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode( array( 'access_token' => 'test-token' ) ),
					);
				}
				if ( strpos( $url, '/epayment/v1/payments/ref-123/capture' ) !== false ) {
					$body = json_decode( $args['body'], true );
					$this->assertEquals( 5000, $body['modificationAmount']['value'] );
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode( array( 'state' => 'AUTHORIZED' ) ),
					);
				}
				if ( strpos( $url, '/epayment/v1/payments/ref-123/cancel' ) !== false ) {
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode( array( 'state' => 'TERMINATED' ) ),
					);
				}
				if ( strpos( $url, '/epayment/v1/payments/ref-123' ) !== false ) {
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode(
							array(
								'reference' => 'ref-123',
								'state'     => 'AUTHORIZED',
							)
						),
					);
				}
				return $pre;
			}
		);

		$client = new VippsClient();

		$get_res = $client->get_payment( 'ref-123' );
		$this->assertIsArray( $get_res );
		$this->assertEquals( 'AUTHORIZED', $get_res['state'] );

		$capture_res = $client->capture_payment( 'ref-123', 5000 );
		$this->assertIsArray( $capture_res );
		$this->assertEquals( 'AUTHORIZED', $capture_res['state'] );

		$cancel_res = $client->cancel_payment( 'ref-123' );
		$this->assertIsArray( $cancel_res );
		$this->assertEquals( 'TERMINATED', $cancel_res['state'] );
	}

	/**
	 * Test list_webhooks, register_webhook, and delete_webhook
	 */
	public function test_webhook_operations() {
		$registered_payload = null;

		$this->set_http_mock(
			function ( $pre, $args, $url ) use ( &$registered_payload ) {
				if ( strpos( $url, '/accesstoken/get' ) !== false ) {
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode( array( 'access_token' => 'test-token' ) ),
					);
				}
				if ( strpos( $url, '/webhooks/v1/webhooks/webhook-uuid-123' ) !== false && 'DELETE' === ( $args['method'] ?? 'GET' ) ) {
					$this->assertEquals( 'Bearer test-token', $args['headers']['Authorization'] );
					return array(
						'response' => array( 'code' => 204 ),
						'body'     => '',
					);
				}
				if ( strpos( $url, '/webhooks/v1/webhooks' ) !== false ) {
					if ( 'POST' === ( $args['method'] ?? 'POST' ) && ! empty( $args['body'] ) ) {
						$registered_payload = json_decode( $args['body'], true );
						$this->assertEquals( 'Bearer test-token', $args['headers']['Authorization'] );
						return array(
							'response' => array( 'code' => 201 ),
							'body'     => wp_json_encode(
								array(
									'id'     => 'webhook-uuid-123',
									'url'    => $registered_payload['url'],
									'secret' => 'whsec_test123',
								)
							),
						);
					}

					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode(
							array(
								'webhooks' => array(
									array(
										'id'     => 'webhook-uuid-123',
										'url'    => 'https://example.com/webhook',
										'events' => array( 'epayments.payment.authorized.v1' ),
									),
								),
							)
						),
					);
				}
				return $pre;
			}
		);

		$client = new VippsClient();

		// Test list_webhooks
		$list_res = $client->list_webhooks();
		$this->assertIsArray( $list_res );
		$this->assertCount( 1, $list_res['webhooks'] );
		$this->assertEquals( 'webhook-uuid-123', $list_res['webhooks'][0]['id'] );

		// Test register_webhook
		$reg_res = $client->register_webhook( 'https://my-tunnel.example.com/wp-json/snippen/v1/vipps/webhook' );
		$this->assertIsArray( $reg_res );
		$this->assertEquals( 'webhook-uuid-123', $reg_res['id'] );
		$this->assertEquals( 'whsec_test123', $reg_res['secret'] );
		$this->assertContains( 'epayments.payment.authorized.v1', $registered_payload['events'] );

		// Test delete_webhook
		$del_res = $client->delete_webhook( 'webhook-uuid-123' );
		$this->assertIsArray( $del_res );
		$this->assertTrue( $del_res['success'] );
	}

	/**
	 * Test header masking for secure logging.
	 */
	public function test_mask_headers() {
		$headers = array(
			'Authorization'             => 'Bearer super-secret-jwt-token-12345',
			'authorization'             => 'Bearer another-token-67890',
			'Ocp-Apim-Subscription-Key' => 'd8a87b649a374b6289b4f4c7e6512bc2',
			'client_secret'             => 'my-super-secret-client-key-xyz',
			'Content-Type'              => 'application/json',
			'X-Custom-Header'           => 'custom-value',
		);

		$masked = VippsClient::mask_headers( $headers );

		$this->assertEquals( 'Bearer ***', $masked['Authorization'] );
		$this->assertEquals( 'Bearer ***', $masked['authorization'] );
		$this->assertEquals( '***bc2', $masked['Ocp-Apim-Subscription-Key'] );
		$this->assertEquals( '***xyz', $masked['client_secret'] );
		$this->assertEquals( 'application/json', $masked['Content-Type'] );
		$this->assertEquals( 'custom-value', $masked['X-Custom-Header'] );
	}

	/**
	 * Test log sanitization function.
	 */
	public function test_sanitize_for_log() {
		$raw_log = 'Error with Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9 and "access_token": "secret-jwt-999" and "client_secret": "my-secret-abc" and Ocp-Apim-Subscription-Key: sub-key-1234';

		$sanitized = VippsClient::sanitize_for_log( $raw_log );

		$this->assertStringNotContainsString( 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9', $sanitized );
		$this->assertStringNotContainsString( 'secret-jwt-999', $sanitized );
		$this->assertStringNotContainsString( 'my-secret-abc', $sanitized );
		$this->assertStringNotContainsString( 'sub-key-1234', $sanitized );

		$this->assertStringContainsString( 'Bearer ***', $sanitized );
		$this->assertStringContainsString( '"access_token": "***"', $sanitized );
		$this->assertStringContainsString( '"client_secret": "***"', $sanitized );
		$this->assertStringContainsString( 'Ocp-Apim-Subscription-Key: ***', $sanitized );
	}

	/**
	 * Test env detection and setting source identification.
	 */
	public function test_is_defined_in_env_and_get_setting_source() {
		putenv( 'TEST_SNIPPEN_VIPPS_VAR=dummy_val' );

		$this->assertTrue( VippsClient::is_defined_in_env( 'TEST_SNIPPEN_VIPPS_VAR' ) );
		$this->assertFalse( VippsClient::is_defined_in_env( 'NON_EXISTENT_VAR_12345' ) );

		$source_env = VippsClient::get_setting_source( 'non_existent_option', 'TEST_SNIPPEN_VIPPS_VAR' );
		$this->assertEquals( 'env', $source_env );

		update_option( 'test_dummy_opt', 'opt_val' );
		$source_opt = VippsClient::get_setting_source( 'test_dummy_opt', 'NON_EXISTENT_VAR_12345' );
		$this->assertEquals( 'option', $source_opt );

		delete_option( 'test_dummy_opt' );
		$source_none = VippsClient::get_setting_source( 'test_dummy_opt', 'NON_EXISTENT_VAR_12345' );
		$this->assertEquals( 'not_set', $source_none );

		putenv( 'TEST_SNIPPEN_VIPPS_VAR' );
	}

	/**
	 * Test masked getters for client secret and subscription key.
	 */
	public function test_get_masked_credentials() {
		$client = new VippsClient(
			array(
				'client_secret'    => 'verysecretstring',
				'subscription_key' => 'subkeystring123',
			)
		);

		$this->assertEquals( '***ring', $client->get_masked_client_secret() );
		$this->assertEquals( '***g123', $client->get_masked_subscription_key() );

		$empty_client = new VippsClient(
			array(
				'client_secret'    => '',
				'subscription_key' => '',
			)
		);
		$this->assertEquals( '', $empty_client->get_masked_client_secret() );
		$this->assertEquals( '', $empty_client->get_masked_subscription_key() );
	}
}

