<?php

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Admin\Pages\UserBookingsPage;
use SnippenBooking\Shortcode\BookingListShortcode;
use SnippenBooking\Database\Repository\BookingRepository;

class UserBookingsSnapshotTest extends TestCase {

	protected $requires_db        = true;
	protected $requires_seed_data = true;

	public function test_user_bookings_page_and_shortcode_render_snapshot_data() {
		global $wpdb;

		// Create user
		$user_id = wp_insert_user(
			array(
				'user_login' => 'testuser_' . uniqid(),
				'user_pass'  => 'password123',
				'user_email' => 'testuser_' . uniqid() . '@example.com',
				'role'       => 'subscriber',
			)
		);
		wp_set_current_user( $user_id );

		// Get starter object and block
		$object_id = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}snippen_booking_objects LIMIT 1" );
		$block_id  = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}snippen_booking_blocks LIMIT 1" );

		// Create booking via repository (which generates snapshot)
		$repo       = new BookingRepository();
		$booking_id = $repo->create(
			array(
				'user_id'        => $user_id,
				'booking_date'   => '2026-12-25',
				'customer_name'  => 'Test User',
				'customer_email' => 'testuser@example.com',
				'status'         => 'confirmed',
				'price'          => 1500,
			),
			array( $object_id ),
			array( $block_id )
		);

		$this->assertNotEmpty( $booking_id );

		// 1. Verify UserBookingsPage rendering
		$_GET['page'] = 'snippen-my-bookings';
		ob_start();
		$page = new UserBookingsPage();
		$page->render();
		$admin_output = ob_get_clean();

		$this->assertStringContainsString( '08:00 - 09:00', $admin_output );
		$this->assertStringContainsString( 'Festsalen', $admin_output );

		// 2. Verify BookingListShortcode rendering
		ob_start();
		$shortcode = new BookingListShortcode();
		$shortcode_output = $shortcode->render( array() );
		if ( ob_get_level() > 0 ) {
			ob_get_clean();
		}

		$this->assertStringContainsString( '08:00 - 09:00', $shortcode_output );
		$this->assertStringContainsString( 'Festsalen', $shortcode_output );
	}
}
