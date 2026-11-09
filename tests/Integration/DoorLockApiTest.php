<?php
/**
 * Integration tests for DoorLockApi
 *
 * @package SnippenBooking\Tests\Integration
 */

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Api\DoorLockApi;

/**
 * Class DoorLockApiTest
 */
class DoorLockApiTest extends TestCase {

	/**
	 * Requires database
	 */
	protected $requires_db = true;

	/**
	 * Test token
	 */
	const TEST_TOKEN = 'test-doorman-token-abc123xyz';

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		global $wpdb;

		// Clean bookings and locks
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_bookings" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_booking_booking_blocks" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_booking_booking_objects" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_door_locks" );

		// Enable feature by default in tests
		update_option( 'snippen_enable_doorman_api', 'yes' );
		update_option( 'snippen_doorman_api_token', self::TEST_TOKEN );
		update_option( 'snippen_doorman_expose_hours_before', 168 );
		update_option( 'snippen_doorman_buffer_minutes_before', 30 );
		update_option( 'snippen_doorman_grace_minutes_after', 120 );
		update_option( 'snippen_doorman_booking_statuses', 'confirmed' );
		delete_option( 'snippen_door_services_registry' );
		delete_option( 'snippen_doorman_last_status' );
		delete_option( 'snippen_doorman_last_received_at' );

		DoorLockApi::register();
		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down test options
	 */
	protected function tearDown(): void {
		delete_option( 'snippen_enable_doorman_api' );
		delete_option( 'snippen_doorman_api_token' );
		delete_option( 'snippen_doorman_expose_hours_before' );
		delete_option( 'snippen_doorman_buffer_minutes_before' );
		delete_option( 'snippen_doorman_grace_minutes_after' );
		delete_option( 'snippen_doorman_booking_statuses' );
		delete_option( 'snippen_door_services_registry' );
		delete_option( 'snippen_doorman_last_status' );
		delete_option( 'snippen_doorman_last_received_at' );
		parent::tearDown();
	}

	/**
	 * Helper to create an authorized request
	 *
	 * @param string $method HTTP method.
	 * @param string $path REST route.
	 * @return \WP_REST_Request
	 */
	private function create_auth_request( $method, $path ) {
		$request = new \WP_REST_Request( $method, $path );
		$request->add_header( 'Authorization', 'Bearer ' . self::TEST_TOKEN );
		return $request;
	}

	/**
	 * Helper to insert a booking for testing
	 *
	 * @param array $args Custom booking fields.
	 * @return int Booking ID.
	 */
	private function create_booking( array $args = array() ) {
		global $wpdb;

		$defaults = array(
			'uuid'           => wp_generate_uuid4(),
			'user_id'        => 1,
			'slot_id'        => 0,
			'booking_date'   => current_time( 'Y-m-d' ),
			'customer_name'  => 'Ola Nordmann',
			'customer_email' => 'ola@example.com',
			'customer_phone' => '+4799887766',
			'price'          => 1000.0,
			'status'         => 'confirmed',
			'door_code'      => null,
			'created_at'     => current_time( 'mysql' ),
			'modified_at'    => current_time( 'mysql' ),
		);

		$data = array_merge( $defaults, $args );

		$start_time = $data['start_time'] ?? '12:00:00';
		$end_time   = $data['end_time'] ?? '18:00:00';
		unset( $data['start_time'], $data['end_time'] );

		$wpdb->insert( $wpdb->prefix . 'snippen_bookings', $data );
		$booking_id = (int) $wpdb->insert_id;

		// Create block and link
		$wpdb->insert(
			$wpdb->prefix . 'snippen_booking_blocks',
			array(
				'name'       => 'Test Block',
				'start_time' => $start_time,
				'end_time'   => $end_time,
				'is_active'  => 1,
			)
		);
		$block_id = (int) $wpdb->insert_id;

		$wpdb->insert(
			$wpdb->prefix . 'snippen_booking_booking_blocks',
			array(
				'booking_id'       => $booking_id,
				'booking_block_id' => $block_id,
			)
		);

		return $booking_id;
	}

	/**
	 * Test feature disabled
	 */
	public function test_feature_disabled_by_default() {
		update_option( 'snippen_enable_doorman_api', 'no' );

		$request = new \WP_REST_Request( 'GET', '/snippen/v1/door/bookings' );
		$request->add_header( 'Authorization', 'Bearer ' . self::TEST_TOKEN );

		$response = rest_do_request( $request );
		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Test verify_token when API token is unconfigured
	 */
	public function test_verify_token_unconfigured() {
		delete_option( 'snippen_doorman_api_token' );

		$request = $this->create_auth_request( 'GET', '/snippen/v1/door/bookings' );
		$result  = DoorLockApi::verify_token( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 401, $result->get_error_data()['status'] );
	}

	/**
	 * Test verify_token with invalid or missing headers
	 */
	public function test_verify_token_invalid_or_missing() {
		// Missing header
		$req1   = new \WP_REST_Request( 'GET', '/snippen/v1/door/bookings' );
		$result = DoorLockApi::verify_token( $req1 );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 401, $result->get_error_data()['status'] );

		// Wrong Bearer token
		$req2 = new \WP_REST_Request( 'GET', '/snippen/v1/door/bookings' );
		$req2->add_header( 'Authorization', 'Bearer wrong-token' );
		$result = DoorLockApi::verify_token( $req2 );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 401, $result->get_error_data()['status'] );

		// Wrong X-API-Key token
		$req3 = new \WP_REST_Request( 'GET', '/snippen/v1/door/bookings' );
		$req3->add_header( 'X-API-Key', 'wrong-key' );
		$result = DoorLockApi::verify_token( $req3 );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 401, $result->get_error_data()['status'] );
	}

	/**
	 * Test verify_token with valid credentials
	 */
	public function test_verify_token_valid() {
		// Bearer header
		$req1 = new \WP_REST_Request( 'GET', '/snippen/v1/door/bookings' );
		$req1->add_header( 'Authorization', 'Bearer ' . self::TEST_TOKEN );
		$this->assertTrue( DoorLockApi::verify_token( $req1 ) );

		// X-API-Key header
		$req2 = new \WP_REST_Request( 'GET', '/snippen/v1/door/bookings' );
		$req2->add_header( 'X-API-Key', self::TEST_TOKEN );
		$this->assertTrue( DoorLockApi::verify_token( $req2 ) );
	}

	/**
	 * Test verify_token with multiple configured API keys (Issue #380).
	 */
	public function test_verify_token_with_multiple_keys() {
		delete_option( 'snippen_doorman_api_token' );
		update_option(
			'snippen_doorman_api_keys',
			array(
				array(
					'id'    => 'node_main',
					'name'  => 'Hovedhus Node',
					'token' => 'token-main-node-123',
				),
				array(
					'id'    => 'node_annex',
					'name'  => 'Utleiedel Node',
					'token' => 'token-annex-node-456',
				),
			)
		);

		// Valid Node 1 via Bearer
		$req1 = new \WP_REST_Request( 'GET', '/snippen/v1/door/bookings' );
		$req1->add_header( 'Authorization', 'Bearer token-main-node-123' );
		$this->assertTrue( DoorLockApi::verify_token( $req1 ) );

		// Valid Node 2 via X-API-Key
		$req2 = new \WP_REST_Request( 'GET', '/snippen/v1/door/bookings' );
		$req2->add_header( 'X-API-Key', 'token-annex-node-456' );
		$this->assertTrue( DoorLockApi::verify_token( $req2 ) );

		// Invalid token
		$req3 = new \WP_REST_Request( 'GET', '/snippen/v1/door/bookings' );
		$req3->add_header( 'Authorization', 'Bearer unauthorized-token' );
		$res3 = DoorLockApi::verify_token( $req3 );
		$this->assertInstanceOf( \WP_Error::class, $res3 );
		$this->assertSame( 401, $res3->get_error_data()['status'] );

		// Legacy token is also accepted alongside multiple keys
		update_option( 'snippen_doorman_api_token', 'token-legacy-789' );
		$req4 = new \WP_REST_Request( 'GET', '/snippen/v1/door/bookings' );
		$req4->add_header( 'Authorization', 'Bearer token-legacy-789' );
		$this->assertTrue( DoorLockApi::verify_token( $req4 ) );

		// Cleanup
		delete_option( 'snippen_doorman_api_keys' );
		update_option( 'snippen_doorman_api_token', self::TEST_TOKEN );
	}

	/**
	 * Test GET /door/bookings when database is empty
	 */
	public function test_get_bookings_empty() {
		$request  = $this->create_auth_request( 'GET', '/snippen/v1/door/bookings' );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'bookings', $data );
		$this->assertEmpty( $data['bookings'] );
	}

	/**
	 * Test GET /door/bookings with buffer and grace calculations
	 */
	public function test_get_bookings_single_with_buffer_and_grace() {
		// Tomorrow 12:00 to 18:00
		$tomorrow = date( 'Y-m-d', strtotime( '+1 day' ) );
		$id       = $this->create_booking(
			array(
				'booking_date' => $tomorrow,
				'start_time'   => '12:00:00',
				'end_time'     => '18:00:00',
				'status'       => 'confirmed',
				'door_code'    => null,
			)
		);

		$request  = $this->create_auth_request( 'GET', '/snippen/v1/door/bookings' );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'server_time', $data );
		$this->assertCount( 1, $data['bookings'] );

		$item = $data['bookings'][0];
		$this->assertSame( $id, $item['id'] );
		$this->assertSame( array( $id ), $item['booking_ids'] );
		$this->assertNull( $item['door_code'] );
		$this->assertNull( $item['door_code_updated_at'] );

		// 12:00 minus 30 min buffer = 11:30:00Z
		$this->assertSame( $tomorrow . 'T11:30:00Z', $item['start_time'] );
		// 18:00 plus 120 min grace = 20:00:00Z
		$this->assertSame( $tomorrow . 'T20:00:00Z', $item['end_time'] );

		// GDPR: Assert no personal info is present in the response
		$this->assertArrayNotHasKey( 'customer_name', $item );
		$this->assertArrayNotHasKey( 'customer_email', $item );
		$this->assertArrayNotHasKey( 'customer_phone', $item );
	}

	/**
	 * Test GET /door/bookings filtering: horizon window, grace period, and status
	 */
	public function test_get_bookings_horizon_and_expiration() {
		// 1. In 10 days (beyond 7-day horizon) -> Excluded
		$in_10_days = date( 'Y-m-d', strtotime( '+10 days' ) );
		$this->create_booking(
			array(
				'user_id'      => 101,
				'booking_date' => $in_10_days,
				'start_time'   => '10:00:00',
				'end_time'     => '14:00:00',
				'status'       => 'confirmed',
			)
		);

		// 2. Ended 4 hours ago (beyond 2-hour grace period) -> Excluded
		$time_4h_ago    = time() - 4 * 3600;
		$date_4h_ago    = date( 'Y-m-d', $time_4h_ago );
		$four_hours_ago = date( 'H:i:s', $time_4h_ago );
		$five_hours_ago = date( 'H:i:s', time() - 5 * 3600 );
		$this->create_booking(
			array(
				'user_id'      => 102,
				'booking_date' => $date_4h_ago,
				'start_time'   => $five_hours_ago,
				'end_time'     => $four_hours_ago,
				'status'       => 'confirmed',
			)
		);

		// 3. Ended 1 hour ago (within 2-hour grace period) -> Included!
		$time_1h_ago   = time() - 3600;
		$date_1h_ago   = date( 'Y-m-d', $time_1h_ago );
		$one_hour_ago  = date( 'H:i:s', $time_1h_ago );
		$two_hours_ago = date( 'H:i:s', time() - 2 * 3600 );
		$id_grace      = $this->create_booking(
			array(
				'user_id'      => 103,
				'booking_date' => $date_1h_ago,
				'start_time'   => $two_hours_ago,
				'end_time'     => $one_hour_ago,
				'status'       => 'confirmed',
			)
		);

		// 4. In 2 days but status 'pending' -> Excluded
		$in_2_days = date( 'Y-m-d', strtotime( '+2 days' ) );
		$this->create_booking(
			array(
				'user_id'      => 104,
				'booking_date' => $in_2_days,
				'start_time'   => '12:00:00',
				'end_time'     => '16:00:00',
				'status'       => 'pending',
			)
		);

		$request  = $this->create_auth_request( 'GET', '/snippen/v1/door/bookings' );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertCount( 1, $data['bookings'] );
		$this->assertSame( $id_grace, $data['bookings'][0]['id'] );
	}

	/**
	 * Test GET /door/bookings chains adjacent bookings for same tenant
	 */
	public function test_get_bookings_adjacent_chaining() {
		$day1 = date( 'Y-m-d', strtotime( '+2 days' ) );
		$day2 = date( 'Y-m-d', strtotime( '+3 days' ) );

		// User 42 has party on Saturday 18:00 - 23:59
		$id1 = $this->create_booking(
			array(
				'user_id'      => 42,
				'booking_date' => $day1,
				'start_time'   => '18:00:00',
				'end_time'     => '23:59:00',
				'status'       => 'confirmed',
			)
		);

		// User 42 has cleaning on Sunday 09:00 - 11:00
		$id2 = $this->create_booking(
			array(
				'user_id'      => 42,
				'booking_date' => $day2,
				'start_time'   => '09:00:00',
				'end_time'     => '11:00:00',
				'booking_type' => 'cleaning',
				'status'       => 'confirmed',
			)
		);

		$request  = $this->create_auth_request( 'GET', '/snippen/v1/door/bookings' );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();

		// Should be merged into exactly 1 combined item
		$this->assertCount( 1, $data['bookings'] );
		$item = $data['bookings'][0];

		$this->assertSame( $id1, $item['id'] );
		$this->assertSame( array( $id1, $id2 ), $item['booking_ids'] );

		// Start: Day 1 at 18:00 minus 30 min buffer = Day 1 17:30:00Z
		$this->assertSame( $day1 . 'T17:30:00Z', $item['start_time'] );
		// End: Day 2 at 11:00 plus 120 min grace = Day 2 13:00:00Z
		$this->assertSame( $day2 . 'T13:00:00Z', $item['end_time'] );
	}

	/**
	 * Test adjacent chaining when bookings are on consecutive days with large gap and interleaved bookings from another tenant.
	 */
	public function test_get_bookings_adjacent_chaining_with_interleaved_other_tenant_and_gap() {
		$day1 = date( 'Y-m-d', strtotime( '+1 day' ) );
		$day2 = date( 'Y-m-d', strtotime( '+2 days' ) );

		// Tenant A has booking on Day 1 08:00 - 10:00
		$id_a1 = $this->create_booking(
			array(
				'user_id'      => 88,
				'booking_date' => $day1,
				'start_time'   => '08:00:00',
				'end_time'     => '10:00:00',
				'status'       => 'confirmed',
			)
		);

		// Tenant B has booking on Day 1 14:00 - 16:00 (in between)
		$id_b1 = $this->create_booking(
			array(
				'user_id'      => 99,
				'booking_date' => $day1,
				'start_time'   => '14:00:00',
				'end_time'     => '16:00:00',
				'status'       => 'confirmed',
			)
		);

		// Tenant A has booking on Day 2 18:00 - 20:00 (32 hour gap from Tenant A's first booking)
		$id_a2 = $this->create_booking(
			array(
				'user_id'      => 88,
				'booking_date' => $day2,
				'start_time'   => '18:00:00',
				'end_time'     => '20:00:00',
				'status'       => 'confirmed',
			)
		);

		$request  = $this->create_auth_request( 'GET', '/snippen/v1/door/bookings' );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();

		// Should have 2 groups: one for Tenant A (chained), one for Tenant B
		$this->assertCount( 2, $data['bookings'] );

		// Find Tenant A's group and Tenant B's group
		$tenant_a_group = null;
		$tenant_b_group = null;
		foreach ( $data['bookings'] as $grp ) {
			if ( $grp['id'] === $id_a1 ) {
				$tenant_a_group = $grp;
			} elseif ( $grp['id'] === $id_b1 ) {
				$tenant_b_group = $grp;
			}
		}

		$this->assertNotNull( $tenant_a_group );
		$this->assertNotNull( $tenant_b_group );

		// Tenant A: chained across day 1 and day 2
		$this->assertSame( $id_a1, $tenant_a_group['id'] );
		$this->assertSame( array( $id_a1, $id_a2 ), $tenant_a_group['booking_ids'] );
		$this->assertSame( $day1 . 'T07:30:00Z', $tenant_a_group['start_time'] );
		$this->assertSame( $day2 . 'T22:00:00Z', $tenant_a_group['end_time'] );

		// Tenant B: single booking
		$this->assertSame( $id_b1, $tenant_b_group['id'] );
		$this->assertSame( array( $id_b1 ), $tenant_b_group['booking_ids'] );

		// Test PATCHing Tenant A's second booking propagates to both Tenant A bookings, but not Tenant B
		$patch_req = $this->create_auth_request( 'PATCH', '/snippen/v1/door/bookings/' . $id_a2 . '/code' );
		$patch_req->set_header( 'Content-Type', 'application/json' );
		$patch_req->set_body( wp_json_encode( array( 'door_code' => '778899' ) ) );
		$patch_res = rest_do_request( $patch_req );
		$this->assertSame( 200, $patch_res->get_status() );

		global $wpdb;
		$table   = $wpdb->prefix . 'snippen_bookings';
		$code_a1 = $wpdb->get_var( $wpdb->prepare( "SELECT door_code FROM {$table} WHERE id = %d", $id_a1 ) );
		$code_a2 = $wpdb->get_var( $wpdb->prepare( "SELECT door_code FROM {$table} WHERE id = %d", $id_a2 ) );
		$code_b1 = $wpdb->get_var( $wpdb->prepare( "SELECT door_code FROM {$table} WHERE id = %d", $id_b1 ) );

		$this->assertSame( '778899', $code_a1 );
		$this->assertSame( '778899', $code_a2 );
		$this->assertNull( $code_b1 );
	}

	/**
	 * Test PATCH /door/bookings/{id}/code with valid PIN codes
	 */
	public function test_patch_door_code_valid() {
		global $wpdb;

		$id1 = $this->create_booking(
			array(
				'user_id'      => 7,
				'booking_date' => date( 'Y-m-d', strtotime( '+1 day' ) ),
				'start_time'   => '12:00:00',
				'end_time'     => '18:00:00',
			)
		);
		$id2 = $this->create_booking(
			array(
				'user_id'      => 7,
				'booking_date' => date( 'Y-m-d', strtotime( '+2 days' ) ),
				'start_time'   => '09:00:00',
				'end_time'     => '11:00:00',
			)
		);

		// Patch 6-digit code
		$request = $this->create_auth_request( 'PATCH', '/snippen/v1/door/bookings/' . $id1 . '/code' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'door_code' => '482910' ) ) );

		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertSame( $id1, $data['id'] );

		// Verify database values for both bookings
		$table = $wpdb->prefix . 'snippen_bookings';
		$row1  = $wpdb->get_row( $wpdb->prepare( "SELECT door_code, door_code_updated_at FROM {$table} WHERE id = %d", $id1 ) );
		$row2  = $wpdb->get_row( $wpdb->prepare( "SELECT door_code, door_code_updated_at FROM {$table} WHERE id = %d", $id2 ) );

		$this->assertSame( '482910', $row1->door_code );
		$this->assertSame( '482910', $row2->door_code );
		$this->assertNotEmpty( $row1->door_code_updated_at );
		$this->assertNotEmpty( $row2->door_code_updated_at );

		// Also test 4-digit code works
		$req_4 = $this->create_auth_request( 'PATCH', '/snippen/v1/door/bookings/' . $id1 . '/code' );
		$req_4->set_header( 'Content-Type', 'application/json' );
		$req_4->set_body( wp_json_encode( array( 'door_code' => '1234' ) ) );

		$res_4 = rest_do_request( $req_4 );
		$this->assertSame( 200, $res_4->get_status() );

		$code_after = $wpdb->get_var( $wpdb->prepare( "SELECT door_code FROM {$table} WHERE id = %d", $id1 ) );
		$this->assertSame( '1234', $code_after );
	}

	/**
	 * Test PATCH /door/bookings/{id}/code rejects invalid codes and null
	 */
	public function test_patch_door_code_invalid() {
		$id = $this->create_booking();

		// Too short (3 digits)
		$req1 = $this->create_auth_request( 'PATCH', '/snippen/v1/door/bookings/' . $id . '/code' );
		$req1->set_header( 'Content-Type', 'application/json' );
		$req1->set_body( wp_json_encode( array( 'door_code' => '123' ) ) );
		$res1 = rest_do_request( $req1 );
		$this->assertSame( 400, $res1->get_status() );

		// Too long (7 digits)
		$req2 = $this->create_auth_request( 'PATCH', '/snippen/v1/door/bookings/' . $id . '/code' );
		$req2->set_header( 'Content-Type', 'application/json' );
		$req2->set_body( wp_json_encode( array( 'door_code' => '1234567' ) ) );
		$res2 = rest_do_request( $req2 );
		$this->assertSame( 400, $res2->get_status() );

		// Letters/non-numeric
		$req3 = $this->create_auth_request( 'PATCH', '/snippen/v1/door/bookings/' . $id . '/code' );
		$req3->set_header( 'Content-Type', 'application/json' );
		$req3->set_body( wp_json_encode( array( 'door_code' => 'abcd' ) ) );
		$res3 = rest_do_request( $req3 );
		$this->assertSame( 400, $res3->get_status() );

		// Null is explicitly rejected (Doorman does not reset codes via PATCH)
		$req4 = $this->create_auth_request( 'PATCH', '/snippen/v1/door/bookings/' . $id . '/code' );
		$req4->set_header( 'Content-Type', 'application/json' );
		$req4->set_body( wp_json_encode( array( 'door_code' => null ) ) );
		$res4 = rest_do_request( $req4 );
		$this->assertSame( 400, $res4->get_status() );
	}

	/**
	 * Test PATCH /door/bookings/{id}/code with non-existent booking ID
	 */
	public function test_patch_door_code_not_found() {
		$request = $this->create_auth_request( 'PATCH', '/snippen/v1/door/bookings/99999/code' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'door_code' => '482910' ) ) );

		$response = rest_do_request( $request );
		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * Test PATCH on secondary booking ID in an adjacent chain updates the entire chain
	 */
	public function test_patch_via_secondary_adjacent_id() {
		global $wpdb;

		$id1 = $this->create_booking(
			array(
				'user_id'      => 88,
				'booking_date' => date( 'Y-m-d', strtotime( '+1 day' ) ),
				'start_time'   => '18:00:00',
				'end_time'     => '23:00:00',
			)
		);
		$id2 = $this->create_booking(
			array(
				'user_id'      => 88,
				'booking_date' => date( 'Y-m-d', strtotime( '+2 days' ) ),
				'start_time'   => '09:00:00',
				'end_time'     => '12:00:00',
			)
		);

		// Patch using id2 instead of id1
		$request = $this->create_auth_request( 'PATCH', '/snippen/v1/door/bookings/' . $id2 . '/code' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'door_code' => '778899' ) ) );

		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );

		$table = $wpdb->prefix . 'snippen_bookings';
		$code1 = $wpdb->get_var( $wpdb->prepare( "SELECT door_code FROM {$table} WHERE id = %d", $id1 ) );
		$code2 = $wpdb->get_var( $wpdb->prepare( "SELECT door_code FROM {$table} WHERE id = %d", $id2 ) );

		$this->assertSame( '778899', $code1 );
		$this->assertSame( '778899', $code2 );
	}

	/**
	 * Test that three consecutive days chain together for the same user
	 */
	public function test_adjacent_bookings_consecutive_days_chain() {
		$day1 = date( 'Y-m-d', strtotime( '+1 day' ) );
		$day2 = date( 'Y-m-d', strtotime( '+2 days' ) );
		$day3 = date( 'Y-m-d', strtotime( '+3 days' ) );

		$id1 = $this->create_booking(
			array(
				'user_id'      => 99,
				'booking_date' => $day1,
				'start_time'   => '18:00:00',
				'end_time'     => '22:00:00',
			)
		);
		$id2 = $this->create_booking(
			array(
				'user_id'      => 99,
				'booking_date' => $day2,
				'start_time'   => '10:00:00',
				'end_time'     => '16:00:00',
			)
		);
		$id3 = $this->create_booking(
			array(
				'user_id'      => 99,
				'booking_date' => $day3,
				'start_time'   => '09:00:00',
				'end_time'     => '12:00:00',
			)
		);

		$request  = $this->create_auth_request( 'GET', '/snippen/v1/door/bookings' );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertCount( 1, $data['bookings'] );
		$chain = $data['bookings'][0];

		$this->assertSame( $id1, $chain['id'] );
		$this->assertSame( array( $id1, $id2, $id3 ), $chain['booking_ids'] );
		// Start from day1 minus 30 min = 17:30
		$this->assertSame( $day1 . 'T17:30:00Z', $chain['start_time'] );
		// End from day3 plus 120 min = 14:00
		$this->assertSame( $day3 . 'T14:00:00Z', $chain['end_time'] );
	}

	/**
	 * Helper to create a venue / booking object
	 *
	 * @param string   $name Name of venue.
	 * @param int|null $lock_id Associated lock ID.
	 * @return int Venue ID.
	 */
	private function create_venue( string $name, ?int $lock_id = null ): int {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'snippen_booking_objects',
			array(
				'name'         => $name,
				'door_lock_id' => $lock_id,
				'created_at'   => current_time( 'mysql' ),
				'modified_at'  => current_time( 'mysql' ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Helper to link a booking to a venue
	 *
	 * @param int $booking_id Booking ID.
	 * @param int $venue_id Venue ID.
	 */
	private function link_booking_venue( int $booking_id, int $venue_id ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'snippen_booking_booking_objects',
			array(
				'booking_id'        => $booking_id,
				'booking_object_id' => $venue_id,
			)
		);
	}

	/**
	 * Test GET /door/locks returns all active locks with handles_objects
	 */
	public function test_get_locks_endpoint() {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_door_locks" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_booking_objects" );

		$lock_repo = new \SnippenBooking\Database\Repository\DoorLockRepository();
		$lock1_id  = $lock_repo->create( array( 'name' => 'Hovedinngang' ) );
		$lock2_id  = $lock_repo->create( array( 'name' => 'Badstuedør' ) );

		$venue1 = $this->create_venue( 'Peisestuen', $lock1_id );
		$venue2 = $this->create_venue( 'Festsalen', $lock1_id );
		$venue3 = $this->create_venue( 'Badstue', $lock2_id );

		$request  = $this->create_auth_request( 'GET', '/snippen/v1/door/locks' );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertArrayHasKey( 'locks', $data );
		$this->assertCount( 2, $data['locks'] );

		// Check Lock 1
		$this->assertSame( $lock1_id, $data['locks'][0]['id'] );
		$this->assertSame( 'Hovedinngang', $data['locks'][0]['name'] );
		$this->assertCount( 2, $data['locks'][0]['handles_objects'] );
		$handles1_names = array_column( $data['locks'][0]['handles_objects'], 'name' );
		$this->assertContains( 'Peisestuen', $handles1_names );
		$this->assertContains( 'Festsalen', $handles1_names );

		// Check Lock 2
		$this->assertSame( $lock2_id, $data['locks'][1]['id'] );
		$this->assertSame( 'Badstuedør', $data['locks'][1]['name'] );
		$this->assertCount( 1, $data['locks'][1]['handles_objects'] );
		$this->assertSame( 'Badstue', $data['locks'][1]['handles_objects'][0]['name'] );
	}

	/**
	 * Test GET /door/bookings with multiple locks requires lock_id (returns 400)
	 */
	public function test_get_bookings_multiple_locks_missing_lock_id_returns_400() {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_door_locks" );

		$lock_repo = new \SnippenBooking\Database\Repository\DoorLockRepository();
		$lock_repo->create( array( 'name' => 'Hovedinngang' ) );
		$lock_repo->create( array( 'name' => 'Badstuedør' ) );

		$request  = $this->create_auth_request( 'GET', '/snippen/v1/door/bookings' );
		$response = rest_do_request( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'missing_lock_id', $response->as_error()->get_error_code() );
	}

	/**
	 * Test GET /door/bookings with non-existent lock_id returns 404
	 */
	public function test_get_bookings_invalid_lock_id_returns_404() {
		$request = $this->create_auth_request( 'GET', '/snippen/v1/door/bookings' );
		$request->set_param( 'lock_id', 9999 );
		$response = rest_do_request( $request );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'lock_not_found', $response->as_error()->get_error_code() );
	}

	/**
	 * Test GET /door/bookings?lock_id=X correctly filters bookings by associated lock
	 */
	public function test_get_bookings_filtered_by_lock_id() {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_door_locks" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_booking_objects" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_booking_booking_objects" );

		$lock_repo = new \SnippenBooking\Database\Repository\DoorLockRepository();
		$lock1_id  = $lock_repo->create( array( 'name' => 'Hovedinngang' ) );
		$lock2_id  = $lock_repo->create( array( 'name' => 'Badstuedør' ) );

		$venue_main  = $this->create_venue( 'Hovedsal', $lock1_id );
		$venue_sauna = $this->create_venue( 'Badstue', $lock2_id );

		$date = date( 'Y-m-d', strtotime( '+1 day' ) );

		// Booking 1 for main hall (Lock 1)
		$booking1_id = $this->create_booking(
			array(
				'user_id'      => 101,
				'booking_date' => $date,
				'start_time'   => '10:00:00',
				'end_time'     => '14:00:00',
			)
		);
		$this->link_booking_venue( $booking1_id, $venue_main );

		// Booking 2 for sauna (Lock 2)
		$booking2_id = $this->create_booking(
			array(
				'user_id'      => 102,
				'booking_date' => $date,
				'start_time'   => '15:00:00',
				'end_time'     => '18:00:00',
			)
		);
		$this->link_booking_venue( $booking2_id, $venue_sauna );

		// Request for Lock 1 -> should only contain booking1
		$req1 = $this->create_auth_request( 'GET', '/snippen/v1/door/bookings' );
		$req1->set_param( 'lock_id', $lock1_id );
		$res1  = rest_do_request( $req1 );
		$data1 = $res1->get_data();

		$this->assertSame( 200, $res1->get_status() );
		$this->assertCount( 1, $data1['bookings'] );
		$this->assertSame( $booking1_id, $data1['bookings'][0]['id'] );

		// Request for Lock 2 -> should only contain booking2
		$req2 = $this->create_auth_request( 'GET', '/snippen/v1/door/bookings' );
		$req2->set_param( 'lock_id', $lock2_id );
		$res2  = rest_do_request( $req2 );
		$data2 = $res2->get_data();

		$this->assertSame( 200, $res2->get_status() );
		$this->assertCount( 1, $data2['bookings'] );
		$this->assertSame( $booking2_id, $data2['bookings'][0]['id'] );
	}

	/**
	 * Test GET /door/bookings JSON contract includes objects, lock_ids, and ISO timestamps (Issue #368).
	 */
	public function test_get_bookings_contract_with_objects_and_locks() {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_door_locks" );

		$lock_repo = new \SnippenBooking\Database\Repository\DoorLockRepository();
		$lock_id   = $lock_repo->create( array( 'name' => 'Inngangsdør 1' ) );
		$venue_id  = $this->create_venue( 'Peisestuen', $lock_id );

		$tomorrow = date( 'Y-m-d', strtotime( '+1 day' ) );
		$b_id     = $this->create_booking(
			array(
				'booking_date' => $tomorrow,
				'start_time'   => '15:00:00',
				'end_time'     => '18:00:00',
				'status'       => 'confirmed',
				'door_code'    => '123456',
			)
		);
		$this->link_booking_venue( $b_id, $venue_id );

		// Set explicit door_code_updated_at
		$now_mysql = '2026-11-07 12:30:00';
		$wpdb->update(
			$wpdb->prefix . 'snippen_bookings',
			array( 'door_code_updated_at' => $now_mysql ),
			array( 'id' => $b_id )
		);

		$req      = $this->create_auth_request( 'GET', '/snippen/v1/door/bookings' );
		$res      = rest_do_request( $req );
		$data     = $res->get_data();

		$this->assertSame( 200, $res->get_status() );
		$this->assertArrayHasKey( 'server_time', $data );
		$this->assertCount( 1, $data['bookings'] );

		$booking = $data['bookings'][0];
		$this->assertSame( $b_id, $booking['id'] );
		$this->assertSame( array( $b_id ), $booking['booking_ids'] );
		$this->assertSame( '123456', $booking['door_code'] );
		$this->assertSame( '2026-11-07T12:30:00Z', $booking['door_code_updated_at'] );
		$this->assertSame( array( $lock_id ), $booking['lock_ids'] );
		$this->assertCount( 1, $booking['booking_objects'] );
		$this->assertSame( $venue_id, $booking['booking_objects'][0]['id'] );
		$this->assertSame( 'Peisestuen', $booking['booking_objects'][0]['name'] );
	}

	/**
	 * Test that when adjacent bookings in a chain have codes, the newest door_code_updated_at wins (Issue #368).
	 */
	public function test_get_bookings_chain_newest_door_code_updated_at_wins() {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_door_locks" );

		$lock_repo = new \SnippenBooking\Database\Repository\DoorLockRepository();
		$lock_id   = $lock_repo->create( array( 'name' => 'Felleslås' ) );
		$venue_id  = $this->create_venue( 'Peisestuen', $lock_id );

		$day1 = date( 'Y-m-d', strtotime( '+1 day' ) );
		$day2 = date( 'Y-m-d', strtotime( '+2 days' ) );

		// Booking 1: older code
		$b1_id = $this->create_booking(
			array(
				'user_id'      => 55,
				'booking_date' => $day1,
				'start_time'   => '10:00:00',
				'end_time'     => '14:00:00',
				'status'       => 'confirmed',
				'door_code'    => '111111',
			)
		);
		$this->link_booking_venue( $b1_id, $venue_id );

		// Booking 2: newer code
		$b2_id = $this->create_booking(
			array(
				'user_id'      => 55,
				'booking_date' => $day2,
				'start_time'   => '10:00:00',
				'end_time'     => '14:00:00',
				'status'       => 'confirmed',
				'door_code'    => '222222',
			)
		);
		$this->link_booking_venue( $b2_id, $venue_id );

		$wpdb->update(
			$wpdb->prefix . 'snippen_bookings',
			array( 'door_code_updated_at' => '2026-11-06 10:00:00' ),
			array( 'id' => $b1_id )
		);
		$wpdb->update(
			$wpdb->prefix . 'snippen_bookings',
			array( 'door_code_updated_at' => '2026-11-07 14:00:00' ),
			array( 'id' => $b2_id )
		);

		$req  = $this->create_auth_request( 'GET', '/snippen/v1/door/bookings' );
		$res  = rest_do_request( $req );
		$data = $res->get_data();

		$this->assertSame( 200, $res->get_status() );
		$this->assertCount( 1, $data['bookings'] );
		$chain = $data['bookings'][0];

		$this->assertSame( array( $b1_id, $b2_id ), $chain['booking_ids'] );
		$this->assertSame( '222222', $chain['door_code'], 'Newer code on booking 2 should win the merged chain' );
		$this->assertSame( '2026-11-07T14:00:00Z', $chain['door_code_updated_at'] );
	}

	/**
	 * Test include_expired_days query parameter returns recently expired bookings (Issue #368).
	 */
	public function test_get_bookings_include_expired_days() {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_door_locks" );

		$past_3_days  = date( 'Y-m-d', strtotime( '-3 days' ) );
		$past_10_days = date( 'Y-m-d', strtotime( '-10 days' ) );

		// Booking expired 3 days ago
		$b_expired_3d = $this->create_booking(
			array(
				'user_id'      => 60,
				'booking_date' => $past_3_days,
				'start_time'   => '10:00:00',
				'end_time'     => '12:00:00',
				'status'       => 'confirmed',
				'door_code'    => '333333',
			)
		);

		// Booking expired 10 days ago
		$b_expired_10d = $this->create_booking(
			array(
				'user_id'      => 61,
				'booking_date' => $past_10_days,
				'start_time'   => '10:00:00',
				'end_time'     => '12:00:00',
				'status'       => 'confirmed',
				'door_code'    => '444444',
			)
		);

		// 1. Without include_expired_days -> both should be excluded (already expired)
		$req1  = $this->create_auth_request( 'GET', '/snippen/v1/door/bookings' );
		$res1  = rest_do_request( $req1 );
		$data1 = $res1->get_data();
		$this->assertSame( 200, $res1->get_status() );
		$this->assertCount( 0, $data1['bookings'] );

		// 2. With include_expired_days=7 -> b_expired_3d included, b_expired_10d excluded
		$req2 = $this->create_auth_request( 'GET', '/snippen/v1/door/bookings' );
		$req2->set_param( 'include_expired_days', 7 );
		$res2  = rest_do_request( $req2 );
		$data2 = $res2->get_data();

		$this->assertSame( 200, $res2->get_status() );
		$this->assertCount( 1, $data2['bookings'] );
		$this->assertSame( $b_expired_3d, $data2['bookings'][0]['id'] );
		$this->assertSame( '333333', $data2['bookings'][0]['door_code'] );
	}

	/**
	 * Test PATCH /door/bookings/{id}/code returns expected contract structure (Issue #368).
	 */
	public function test_patch_door_code_response_contract() {
		$day1 = date( 'Y-m-d', strtotime( '+1 day' ) );
		$day2 = date( 'Y-m-d', strtotime( '+2 days' ) );

		$id1 = $this->create_booking(
			array(
				'user_id'      => 77,
				'booking_date' => $day1,
				'start_time'   => '10:00:00',
				'end_time'     => '12:00:00',
				'status'       => 'confirmed',
			)
		);

		$id2 = $this->create_booking(
			array(
				'user_id'      => 77,
				'booking_date' => $day2,
				'start_time'   => '10:00:00',
				'end_time'     => '12:00:00',
				'status'       => 'confirmed',
			)
		);

		$req = $this->create_auth_request( 'PATCH', '/snippen/v1/door/bookings/' . $id1 . '/code' );
		$req->set_header( 'Content-Type', 'application/json' );
		$req->set_body( wp_json_encode( array( 'door_code' => '654321' ) ) );

		$res  = rest_do_request( $req );
		$data = $res->get_data();

		$this->assertSame( 200, $res->get_status() );
		$this->assertTrue( $data['success'] );
		$this->assertSame( $id1, $data['id'] );
		$this->assertSame( array( $id1, $id2 ), $data['updated_booking_ids'] );
		$this->assertSame( '654321', $data['door_code'] );
		$this->assertNotEmpty( $data['door_code_updated_at'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $data['door_code_updated_at'] );
	}

	/**
	 * Test find_adjacent_booking_ids excludes cancelled bookings and uses booking_snapshot times (Issue #368).
	 */
	public function test_find_adjacent_booking_ids_excludes_cancelled_and_uses_snapshot_times() {
		$day1 = date( 'Y-m-d', strtotime( '+1 day' ) );
		$day2 = date( 'Y-m-d', strtotime( '+2 days' ) );

		$b1_id = $this->create_booking(
			array(
				'user_id'          => 88,
				'booking_date'     => $day1,
				'status'           => 'confirmed',
				'booking_snapshot' => wp_json_encode(
					array(
						'start_time' => '12:00:00',
						'end_time'   => '18:00:00',
					)
				),
			)
		);

		// Adjacent day, but cancelled -> should NOT be chained
		$b2_cancelled_id = $this->create_booking(
			array(
				'user_id'          => 88,
				'booking_date'     => $day2,
				'status'           => 'cancelled',
				'booking_snapshot' => wp_json_encode(
					array(
						'start_time' => '12:00:00',
						'end_time'   => '18:00:00',
					)
				),
			)
		);

		$adjacent = DoorLockApi::find_adjacent_booking_ids( $b1_id );
		$this->assertSame( array( $b1_id ), $adjacent );

		$adjacent_cancelled = DoorLockApi::find_adjacent_booking_ids( $b2_cancelled_id );
		$this->assertSame( array( $b2_cancelled_id ), $adjacent_cancelled );
	}

	/**
	 * Test POST /door/status saves snapshot and returns 200 (Issue #369).
	 */
	public function test_post_door_status_endpoint_success() {
		$payload = array(
			'generated_at'        => '2026-11-07T13:15:10+00:00',
			'expired_window_days' => 7,
			'locks'               => array(
				array(
					'name'        => 'Inngangsdør 1 (Hoveddør)',
					'address'     => '98:1B:B5:0B:BB:35',
					'key_slot'    => 1,
					'lock_id'     => '1',
					'battery'     => 85,
					'lock_status' => 'LOCKED',
					'door_status' => 'CLOSED',
					'last_seen'   => '2026-11-07T13:15:08+00:00',
					'slots'       => array(
						'3' => '101',
						'4' => null,
						'5' => null,
						'6' => null,
						'7' => null,
						'8' => null,
						'9' => null,
					),
				),
			),
			'reservations_in_db'  => array(),
			'provisioned_on_locks' => array(),
			'recently_expired'    => array(),
		);

		$req = $this->create_auth_request( 'POST', '/snippen/v1/door/status' );
		$req->set_header( 'Content-Type', 'application/json' );
		$req->set_body( wp_json_encode( $payload ) );

		$res  = rest_do_request( $req );
		$data = $res->get_data();

		$this->assertSame( 200, $res->get_status() );
		$this->assertTrue( $data['success'] );
		$this->assertNotEmpty( $data['received_at'] );

		$saved = get_option( 'snippen_doorman_last_status' );
		$this->assertIsArray( $saved );
		$this->assertSame( '2026-11-07T13:15:10+00:00', $saved['generated_at'] );
	}

	/**
	 * Test POST /door/status rejects unauthorized requests and invalid JSON (Issue #369).
	 */
	public function test_post_door_status_unauthorized_and_invalid_json() {
		// Unauthorized
		$unauth_req = new \WP_REST_Request( 'POST', '/snippen/v1/door/status' );
		$res1       = rest_do_request( $unauth_req );
		$this->assertSame( 401, $res1->get_status() );

		// Invalid JSON
		$req2 = $this->create_auth_request( 'POST', '/snippen/v1/door/status' );
		$res2 = rest_do_request( $req2 );
		$this->assertSame( 400, $res2->get_status() );
	}

	/**
	 * Test GET /door/status returns null snapshot when empty and enriched data when populated (Issue #369).
	 */
	public function test_get_door_status_enriched_with_metadata() {
		delete_option( 'snippen_doorman_last_status' );
		delete_option( 'snippen_doorman_last_received_at' );
		delete_option( 'snippen_door_services_registry' );
		delete_option( 'snippen_door_status_default' );

		// 1. When empty
		$req1  = $this->create_auth_request( 'GET', '/snippen/v1/door/status' );
		$res1  = rest_do_request( $req1 );
		$data1 = $res1->get_data();

		$this->assertSame( 200, $res1->get_status() );
		$this->assertArrayHasKey( 'server_time', $data1 );
		$this->assertNull( $data1['doorman_last_received_at'] );
		$this->assertNull( $data1['doorman_status'] );

		// 2. Insert booking with venue in DB
		$lock_repo = new \SnippenBooking\Database\Repository\DoorLockRepository();
		$lock_id   = $lock_repo->create( array( 'name' => 'Inngangsdør 1' ) );
		$venue_id  = $this->create_venue( 'Peisestuen', $lock_id );

		$b_id = $this->create_booking(
			array(
				'customer_name'  => 'Status Test Leietaker',
				'customer_phone' => '+4799001122',
				'customer_email' => 'status@test.com',
				'status'         => 'confirmed',
				'door_code'      => '123456',
			)
		);
		$this->link_booking_venue( $b_id, $venue_id );

		$payload = array(
			'generated_at'        => '2026-11-07T13:15:10+00:00',
			'expired_window_days' => 7,
			'locks'               => array(
				array(
					'lock_id' => (string) $lock_id,
					'name'    => 'Inngangsdør 1',
					'battery' => 90,
				),
			),
			'reservations_in_db'  => array(
				array(
					'booking_id' => (string) $b_id,
					'pin'        => '123456',
					'status'     => 'PROVISIONED',
				),
			),
			'provisioned_on_locks' => array(
				array(
					'booking_id' => (string) $b_id,
					'slot'       => 3,
					'pin'        => '123456',
				),
			),
			'recently_expired'    => array(),
		);

		update_option( 'snippen_doorman_last_status', $payload );
		update_option( 'snippen_doorman_last_received_at', '2026-11-07T13:15:11Z' );

		// 3. GET /door/status should be enriched
		$req2  = $this->create_auth_request( 'GET', '/snippen/v1/door/status' );
		$res2  = rest_do_request( $req2 );
		$data2 = $res2->get_data();

		$this->assertSame( 200, $res2->get_status() );
		$this->assertSame( '2026-11-07T13:15:11Z', $data2['doorman_last_received_at'] );

		$res_item = $data2['doorman_status']['reservations_in_db'][0];
		$this->assertSame( 'Status Test Leietaker', $res_item['customer_name'] );
		$this->assertSame( '+4799001122', $res_item['customer_phone'] );
		$this->assertCount( 1, $res_item['booking_objects'] );
		$this->assertSame( 'Peisestuen', $res_item['booking_objects'][0]['name'] );

		$prov_item = $data2['doorman_status']['provisioned_on_locks'][0];
		$this->assertSame( 'Status Test Leietaker', $prov_item['customer_name'] );
	}

	/**
	 * Test POST /door/status supports multiple independent Doorman services (Issue #372).
	 */
	public function test_post_status_multi_service_support() {
		// 1. Post from Node 1 (Grendehuset)
		$payload1 = array(
			'service_id'          => 'grendehus-node',
			'service'             => array(
				'id'          => 'grendehus-node',
				'name'        => 'Grendehuset (Hovedbygg)',
				'version'     => '0.19.0',
				'hostname'    => 'snippen-pi-grendehus',
				'ip_address'  => '192.168.1.120',
				'mac_address' => 'd8:3a:dd:12:34:56',
				'lock_ids'    => array( '1' ),
			),
			'generated_at'        => '2026-11-07T14:30:00Z',
			'expired_window_days' => 7,
			'locks'               => array(
				array(
					'lock_id'     => '1',
					'name'        => 'Hoveddør Grendehuset',
					'address'     => '98:1B:B5:0B:BB:35',
					'battery'     => 85,
					'lock_status' => 'LOCKED',
					'door_status' => 'CLOSED',
				),
			),
			'reservations_in_db'  => array(),
			'provisioned_on_locks' => array(),
			'recently_expired'    => array(),
		);

		$req1 = $this->create_auth_request( 'POST', '/snippen/v1/door/status' );
		$req1->set_header( 'Content-Type', 'application/json' );
		$req1->set_body( wp_json_encode( $payload1 ) );

		$res1  = rest_do_request( $req1 );
		$data1 = $res1->get_data();

		$this->assertSame( 200, $res1->get_status() );
		$this->assertTrue( $data1['success'] );
		$this->assertSame( 'grendehus-node', $data1['service_id'] );

		// Verify option saved for node 1
		$saved_node1 = get_option( 'snippen_door_status_grendehus-node' );
		$this->assertIsArray( $saved_node1 );
		$this->assertSame( 'Grendehuset (Hovedbygg)', $saved_node1['service']['name'] );

		// 2. Post from Node 2 (Hybel)
		$payload2 = array(
			'service_id'          => 'hybel-node',
			'service'             => array(
				'id'          => 'hybel-node',
				'name'        => 'Utleieleilighet (Hybel)',
				'version'     => '0.19.1',
				'hostname'    => 'snippen-pi-hybel',
				'ip_address'  => '192.168.1.125',
				'mac_address' => 'd8:3a:dd:98:76:54',
				'lock_ids'    => array( '2' ),
			),
			'generated_at'        => '2026-11-07T14:35:00Z',
			'expired_window_days' => 7,
			'locks'               => array(
				array(
					'lock_id'     => '2',
					'name'        => 'Inngangsdør Hybel',
					'address'     => '98:1B:B5:0B:CC:99',
					'battery'     => 92,
					'lock_status' => 'LOCKED',
					'door_status' => 'CLOSED',
				),
			),
			'reservations_in_db'  => array(),
			'provisioned_on_locks' => array(),
			'recently_expired'    => array(),
		);

		$req2 = $this->create_auth_request( 'POST', '/snippen/v1/door/status' );
		$req2->set_header( 'Content-Type', 'application/json' );
		$req2->set_body( wp_json_encode( $payload2 ) );

		$res2  = rest_do_request( $req2 );
		$data2 = $res2->get_data();

		$this->assertSame( 200, $res2->get_status() );
		$this->assertSame( 'hybel-node', $data2['service_id'] );

		// Node 1 data must NOT be overwritten
		$node1_check = get_option( 'snippen_door_status_grendehus-node' );
		$this->assertSame( 'Grendehuset (Hovedbygg)', $node1_check['service']['name'] );

		// Node 2 data must exist independently
		$node2_check = get_option( 'snippen_door_status_hybel-node' );
		$this->assertSame( 'Utleieleilighet (Hybel)', $node2_check['service']['name'] );

		// Registry must contain both nodes
		$registry = get_option( 'snippen_door_services_registry' );
		$this->assertArrayHasKey( 'grendehus-node', $registry );
		$this->assertArrayHasKey( 'hybel-node', $registry );
		$this->assertSame( '192.168.1.120', $registry['grendehus-node']['ip_address'] );
		$this->assertSame( '192.168.1.125', $registry['hybel-node']['ip_address'] );
		$this->assertSame( 85, $registry['grendehus-node']['min_battery'] );
		$this->assertSame( 92, $registry['hybel-node']['min_battery'] );
	}

	/**
	 * Test GET /door/status returns aggregated overview without params and filtered by service_id (Issue #372).
	 */
	public function test_get_status_multi_service_aggregated_and_filtered() {
		// Seed 2 services
		$this->test_post_status_multi_service_support();

		// 1. GET /door/status without params -> Aggregated + Services
		$req_all = $this->create_auth_request( 'GET', '/snippen/v1/door/status' );
		$res_all = rest_do_request( $req_all );
		$data_all = $res_all->get_data();

		$this->assertSame( 200, $res_all->get_status() );
		$this->assertArrayHasKey( 'services', $data_all );
		$this->assertArrayHasKey( 'services_status', $data_all );
		$this->assertArrayHasKey( 'aggregated', $data_all );
		$this->assertCount( 2, $data_all['services'] );
		$this->assertCount( 2, $data_all['services_status'] );
		$this->assertCount( 2, $data_all['aggregated']['locks'] );

		// 2. GET /door/status?service_id=grendehus-node -> Specific node
		$req_node1 = $this->create_auth_request( 'GET', '/snippen/v1/door/status' );
		$req_node1->set_param( 'service_id', 'grendehus-node' );
		$res_node1  = rest_do_request( $req_node1 );
		$data_node1 = $res_node1->get_data();

		$this->assertSame( 200, $res_node1->get_status() );
		$this->assertSame( 'grendehus-node', $data_node1['service_id'] );
		$this->assertCount( 1, $data_node1['doorman_status']['locks'] );
		$this->assertSame( 'Hoveddør Grendehuset', $data_node1['doorman_status']['locks'][0]['name'] );

		// 3. GET /door/status?service_id=nonexistent -> 404
		$req_notfound = $this->create_auth_request( 'GET', '/snippen/v1/door/status' );
		$req_notfound->set_param( 'service_id', 'nonexistent-node' );
		$res_notfound = rest_do_request( $req_notfound );

		$this->assertSame( 404, $res_notfound->get_status() );
	}

	/**
	 * Test offline detection when last_seen is older than 15 minutes (Issue #372).
	 */
	public function test_multi_service_offline_detection() {
		$now = time();
		$registry = array(
			'online-node' => array(
				'service_id'  => 'online-node',
				'name'        => 'Aktiv Node',
				'last_seen'   => gmdate( 'Y-m-d\TH:i:s\Z', $now - 300 ), // 5 min ago
				'lock_ids'    => array( '1' ),
				'min_battery' => 80,
			),
			'offline-node' => array(
				'service_id'  => 'offline-node',
				'name'        => 'Død Node',
				'last_seen'   => gmdate( 'Y-m-d\TH:i:s\Z', $now - 1200 ), // 20 min ago (> 15m)
				'lock_ids'    => array( '2' ),
				'min_battery' => 15,
			),
		);
		update_option( 'snippen_door_services_registry', $registry );

		$status = DoorLockApi::get_all_services_status();
		$services = $status['services'];

		$this->assertFalse( $services['online-node']['is_offline'] );
		$this->assertSame( 'online', $services['online-node']['status'] );

		$this->assertTrue( $services['offline-node']['is_offline'] );
		$this->assertSame( 'offline', $services['offline-node']['status'] );
	}
}
