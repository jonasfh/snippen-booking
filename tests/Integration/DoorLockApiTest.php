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

		// Clean bookings
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_bookings" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_booking_booking_blocks" );

		// Enable feature by default in tests
		update_option( 'snippen_enable_doorman_api', 'yes' );
		update_option( 'snippen_doorman_api_token', self::TEST_TOKEN );
		update_option( 'snippen_doorman_expose_hours_before', 168 );
		update_option( 'snippen_doorman_buffer_minutes_before', 30 );
		update_option( 'snippen_doorman_grace_minutes_after', 120 );
		update_option( 'snippen_doorman_booking_statuses', 'confirmed' );

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
		$this->assertCount( 1, $data['bookings'] );

		$item = $data['bookings'][0];
		$this->assertSame( $id, $item['id'] );
		$this->assertArrayNotHasKey( 'booking_ids', $item );
		$this->assertNull( $item['door_code'] );

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
		$today          = current_time( 'Y-m-d' );
		$four_hours_ago = date( 'H:i:s', time() - 4 * 3600 );
		$five_hours_ago = date( 'H:i:s', time() - 5 * 3600 );
		$this->create_booking(
			array(
				'user_id'      => 102,
				'booking_date' => $today,
				'start_time'   => $five_hours_ago,
				'end_time'     => $four_hours_ago,
				'status'       => 'confirmed',
			)
		);

		// 3. Ended 1 hour ago (within 2-hour grace period) -> Included!
		$one_hour_ago  = date( 'H:i:s', time() - 3600 );
		$two_hours_ago = date( 'H:i:s', time() - 2 * 3600 );
		$id_grace      = $this->create_booking(
			array(
				'user_id'      => 103,
				'booking_date' => $today,
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
		$this->assertArrayNotHasKey( 'booking_ids', $item );

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
		$this->assertArrayNotHasKey( 'booking_ids', $tenant_a_group );
		$this->assertSame( $day1 . 'T07:30:00Z', $tenant_a_group['start_time'] );
		$this->assertSame( $day2 . 'T22:00:00Z', $tenant_a_group['end_time'] );

		// Tenant B: single booking
		$this->assertSame( $id_b1, $tenant_b_group['id'] );
		$this->assertArrayNotHasKey( 'booking_ids', $tenant_b_group );

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
		$this->assertArrayNotHasKey( 'booking_ids', $chain );
		// Start from day1 minus 30 min = 17:30
		$this->assertSame( $day1 . 'T17:30:00Z', $chain['start_time'] );
		// End from day3 plus 120 min = 14:00
		$this->assertSame( $day3 . 'T14:00:00Z', $chain['end_time'] );
	}
}
