<?php
/**
 * Integration tests for Vipps unpaid bookings cleanup cron routine
 *
 * @package SnippenBooking\Tests\Integration
 */

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\Vipps\VippsService;
use SnippenBooking\Database\Repository\BookingRepository;

/**
 * VippsCleanupCronTest
 */
class VippsCleanupCronTest extends TestCase {

	/**
	 * HTTP mock callback.
	 *
	 * @var callable|null
	 */
	protected $http_mock_callback = null;

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
	 * Clean up after test.
	 */
	protected function tearDown(): void {
		if ( null !== $this->http_mock_callback ) {
			remove_filter( 'pre_http_request', $this->http_mock_callback, 10 );
			$this->http_mock_callback = null;
		}
		delete_option( 'snippen_vipps_enabled' );
		delete_option( 'snippen_vipps_client_id' );
		delete_option( 'snippen_vipps_client_secret' );
		delete_option( 'snippen_vipps_subscription_key' );
		delete_option( 'snippen_vipps_msn' );
		parent::tearDown();
	}

	/**
	 * Test that expired pending_payment bookings (> 30 min) are cancelled and slots freed
	 */
	public function test_cleanup_cancels_expired_pending_payment_bookings() {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_bookings';

		$now = time();

		// Booking 1: pending_payment created 45 minutes ago -> SHOULD be cancelled
		$uuid_expired = wp_generate_uuid4();
		$created_45m  = gmdate( 'Y-m-d H:i:s', $now - ( 45 * 60 ) );
		$wpdb->insert(
			$table,
			array(
				'uuid'              => $uuid_expired,
				'user_id'           => 1,
				'booking_date'      => '2026-11-10',
				'customer_name'     => 'Expired User',
				'customer_email'    => 'expired@example.com',
				'booking_type'      => 'private',
				'price'             => 500.0,
				'payment_status_id' => 1,
				'status'            => 'pending_payment',
				'payment_notes'     => 'Vipps ref: snippen-101-1718000000-111',
				'created_at'        => $created_45m,
				'modified_at'       => $created_45m,
			)
		);
		$id_expired = (int) $wpdb->insert_id;

		// Booking 2: pending_payment created 10 minutes ago -> should NOT be cancelled
		$uuid_recent = wp_generate_uuid4();
		$created_10m = gmdate( 'Y-m-d H:i:s', $now - ( 10 * 60 ) );
		$wpdb->insert(
			$table,
			array(
				'uuid'              => $uuid_recent,
				'user_id'           => 1,
				'booking_date'      => '2026-11-11',
				'customer_name'     => 'Recent User',
				'customer_email'    => 'recent@example.com',
				'booking_type'      => 'private',
				'price'             => 500.0,
				'payment_status_id' => 1,
				'status'            => 'pending_payment',
				'payment_notes'     => 'Vipps ref: snippen-102-1718000000-222',
				'created_at'        => $created_10m,
				'modified_at'       => $created_10m,
			)
		);
		$id_recent = (int) $wpdb->insert_id;

		// Booking 3: confirmed created 45 minutes ago -> should NOT be cancelled
		$uuid_confirmed = wp_generate_uuid4();
		$wpdb->insert(
			$table,
			array(
				'uuid'              => $uuid_confirmed,
				'user_id'           => 1,
				'booking_date'      => '2026-11-12',
				'customer_name'     => 'Confirmed User',
				'customer_email'    => 'confirmed@example.com',
				'booking_type'      => 'private',
				'price'             => 500.0,
				'payment_status_id' => 2,
				'status'            => 'confirmed',
				'created_at'        => $created_45m,
				'modified_at'       => $created_45m,
			)
		);
		$id_confirmed = (int) $wpdb->insert_id;

		// Booking 4: normal pending (open/free booking) created 45 minutes ago -> should NOT be cancelled
		$uuid_pending = wp_generate_uuid4();
		$wpdb->insert(
			$table,
			array(
				'uuid'              => $uuid_pending,
				'user_id'           => 1,
				'booking_date'      => '2026-11-13',
				'customer_name'     => 'Pending User',
				'customer_email'    => 'pending@example.com',
				'booking_type'      => 'open',
				'price'             => 0.0,
				'payment_status_id' => 3,
				'status'            => 'pending',
				'created_at'        => $created_45m,
				'modified_at'       => $created_45m,
			)
		);
		$id_pending = (int) $wpdb->insert_id;

		// Mock Vipps cancel request
		$cancel_called = false;
		$this->set_http_mock(
			function ( $preempt, $parsed_args, $url ) use ( &$cancel_called ) {
				if ( strpos( $url, '/cancel' ) !== false ) {
					$cancel_called = true;
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode( new \stdClass() ),
					);
				}
				if ( strpos( $url, '/accesstoken/get' ) !== false ) {
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode(
							array(
								'access_token' => 'mock_token',
								'expires_in'   => 3600,
							)
						),
					);
				}
				return $preempt;
			}
		);

		$service         = new VippsService();
		$cancelled_count = $service->cleanup_expired_pending_bookings( 30 );

		$this->assertEquals( 1, $cancelled_count );

		// Check Booking 1: cancelled
		$row_expired = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id_expired ) );
		$this->assertEquals( 'cancelled', $row_expired->status );
		$this->assertStringContainsString( '30 minutter', $row_expired->rejection_reason );

		// Check Booking 2: still pending_payment
		$row_recent = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id_recent ) );
		$this->assertEquals( 'pending_payment', $row_recent->status );

		// Check Booking 3: still confirmed
		$row_confirmed = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id_confirmed ) );
		$this->assertEquals( 'confirmed', $row_confirmed->status );

		// Check Booking 4: still pending
		$row_pending = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id_pending ) );
		$this->assertEquals( 'pending', $row_pending->status );
	}

	/**
	 * Test Plugin cron action trigger invokes cleanup
	 */
	public function test_plugin_cron_hook_triggers_cleanup() {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_bookings';

		$created_60m = gmdate( 'Y-m-d H:i:s', time() - ( 60 * 60 ) );
		$wpdb->insert(
			$table,
			array(
				'uuid'              => wp_generate_uuid4(),
				'user_id'           => 1,
				'booking_date'      => '2026-11-20',
				'customer_name'     => 'Cron User',
				'customer_email'    => 'cron@example.com',
				'booking_type'      => 'private',
				'price'             => 500.0,
				'payment_status_id' => 1,
				'status'            => 'pending_payment',
				'created_at'        => $created_60m,
				'modified_at'       => $created_60m,
			)
		);
		$booking_id = (int) $wpdb->insert_id;

		$cancelled = \SnippenBooking\Plugin::handle_cleanup_unpaid_vipps_bookings();
		$this->assertGreaterThanOrEqual( 1, $cancelled );

		$updated = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $booking_id ) );
		$this->assertEquals( 'cancelled', $updated->status );
	}
}
