<?php

namespace SnippenBooking\Tests\Unit\Repository;

use SnippenBooking\Database\Repository\BookingRepository;
use SnippenBooking\Helper\Capabilities;
use SnippenBooking\Tests\TestCase;

class BookingRepositoryTest extends TestCase {

	public function test_regular_administrator_without_booking_capability_cannot_manage_bookings() {
		$admin_id = wp_insert_user(
			array(
				'user_login' => 'admin_cap_' . uniqid(),
				'user_email' => 'admin_cap_' . uniqid() . '@example.com',
				'user_pass'  => 'password',
				'role'       => 'administrator',
			)
		);

		wp_set_current_user( $admin_id );
		$this->assertFalse( Capabilities::can_manage_bookings() );
		wp_set_current_user( 0 );
	}

	public function test_booking_admin_capability_allows_booking_management() {
		$admin_id = wp_insert_user(
			array(
				'user_login' => 'booking_admin_' . uniqid(),
				'user_email' => 'booking_admin_' . uniqid() . '@example.com',
				'user_pass'  => 'password',
				'role'       => 'subscriber',
			)
		);

		$user = get_userdata( $admin_id );
		$user->add_cap( Capabilities::MANAGE_BOOKINGS );
		wp_set_current_user( $admin_id );

		$this->assertTrue( Capabilities::can_manage_bookings() );
		wp_set_current_user( 0 );
	}

	public function test_update_records_snapshot_history_and_updates_active_snapshot() {
		global $wpdb;
		$repository = new BookingRepository();

		$booking_id = $repository->create(
			array(
				'user_id'           => 1,
				'booking_date'      => '2026-09-30',
				'customer_name'     => 'Original User',
				'customer_email'    => 'original@example.com',
				'customer_phone'    => '+4712345678',
				'status'            => 'pending',
				'payment_status_id' => 1,
				'price'             => 100,
				'discount_amount'   => 0,
				'created_at'        => current_time( 'mysql' ),
				'modified_at'       => current_time( 'mysql' ),
			),
			array( 1 ),
			array( 1 )
		);

		$this->assertNotFalse( $booking_id );
		$this->assertTrue(
			$repository->update(
				$booking_id,
				array(
					'price'  => 150,
					'status' => 'confirmed',
				),
				array( 1 ),
				array( 1 ),
				123
			)
		);

		$booking = $repository->find( $booking_id );
		$this->assertSame( '150.00', (string) $booking->price );
		$this->assertSame( 'confirmed', $booking->status );
		$this->assertNotEmpty( $booking->booking_snapshot );

		$history_table = $wpdb->prefix . 'snippen_booking_snapshots';
		$history_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $history_table WHERE booking_id = %d", $booking_id ) );
		$this->assertGreaterThanOrEqual( 2, $history_count );
	}
}
