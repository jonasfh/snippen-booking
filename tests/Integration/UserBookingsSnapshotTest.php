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

	public function test_user_bookings_page_renders_type_badge_and_rejection_notice() {
		global $wpdb;

		$user_id = wp_insert_user(
			array(
				'user_login' => 'testuser_reject_' . uniqid(),
				'user_pass'  => 'password123',
				'user_email' => 'testuser_reject_' . uniqid() . '@example.com',
				'role'       => 'subscriber',
			)
		);
		wp_set_current_user( $user_id );

		$reason = 'Lokalet er opptatt til vedlikehold.';
		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'uuid'              => wp_generate_uuid4(),
				'user_id'           => $user_id,
				'booking_date'      => '2026-11-20',
				'customer_name'     => 'Rejected User',
				'customer_email'    => 'testuser_reject@example.com',
				'status'            => 'cancelled',
				'price'             => 0,
				'booking_type'      => 'open',
				'rejection_reason'  => $reason,
				'payment_status_id' => 1,
			)
		);

		$_GET['page']   = 'snippen-my-bookings';
		$_GET['status'] = 'cancelled';
		ob_start();
		$page = new UserBookingsPage();
		$page->render();
		$output = ob_get_clean();
		unset( $_GET['page'], $_GET['status'] );

		$this->assertStringContainsString( 'Åpen for sameiet', $output );
		$this->assertStringContainsString( 'Forespørsel om åpent arrangement ble avslått', $output );
		$this->assertStringContainsString( 'Begrunnelse fra styret:', $output );
		$this->assertStringContainsString( $reason, $output );
		$this->assertStringContainsString( 'Hvis du fremdeles ønsker arrangementet kan det reserveres og betales privat.', $output );
	}

	public function test_user_bookings_page_renders_vipps_info_without_upload_form() {
		global $wpdb;

		$user_id = wp_insert_user(
			array(
				'user_login' => 'testuser_vipps_' . uniqid(),
				'user_pass'  => 'password123',
				'user_email' => 'testuser_vipps_' . uniqid() . '@example.com',
				'role'       => 'subscriber',
			)
		);
		wp_set_current_user( $user_id );

		$ref = 'snippen-77-1725900000-888';
		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'uuid'              => wp_generate_uuid4(),
				'user_id'           => $user_id,
				'booking_date'      => '2026-11-21',
				'customer_name'     => 'Vipps User',
				'customer_email'    => 'testuser_vipps@example.com',
				'status'            => 'confirmed',
				'price'             => 750,
				'booking_type'      => 'private',
				'payment_status_id' => 2,
				'payment_notes'     => 'Vipps ref: ' . $ref,
			)
		);

		$_GET['page'] = 'snippen-my-bookings';
		ob_start();
		$page = new UserBookingsPage();
		$page->render();
		$output = ob_get_clean();
		unset( $_GET['page'] );

		$this->assertStringContainsString( 'Privat arrangement', $output );
		$this->assertStringContainsString( 'Betalt med Vipps', $output );
		$this->assertStringContainsString( 'Transaksjonsreferanse:', $output );
		$this->assertStringContainsString( $ref, $output );
		$this->assertStringNotContainsString( 'name="payment_receipt"', $output );
		$this->assertStringNotContainsString( 'Bankkontonr', $output );
	}
}
