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
	 * Clean up after test
	 */
	protected function tearDown(): void {
		remove_all_filters( 'pre_http_request' );
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

		add_filter(
			'pre_http_request',
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
			},
			10,
			3
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
		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) {
				if ( strpos( $url, '/accesstoken/get' ) !== false ) {
					return array(
						'response' => array( 'code' => 401 ),
						'body'     => wp_json_encode( array( 'error' => 'invalid_client' ) ),
					);
				}
				return $pre;
			},
			10,
			3
		);

		$client = new VippsClient();
		$token  = $client->get_access_token( true );
		$this->assertNull( $token );
	}

	/**
	 * Test test_connection helper method
	 */
	public function test_connection_helper() {
		add_filter(
			'pre_http_request',
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
			},
			10,
			3
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

		add_filter(
			'pre_http_request',
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
			},
			10,
			3
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
		add_filter(
			'pre_http_request',
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
			},
			10,
			3
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
}
