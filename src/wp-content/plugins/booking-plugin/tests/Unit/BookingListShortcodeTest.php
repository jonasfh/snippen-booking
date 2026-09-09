<?php
/**
 * Booking List Shortcode Unit / Integration Tests
 *
 * @package SnippenBooking\Tests\Unit
 */

namespace SnippenBooking\Tests\Unit;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Shortcode\BookingListShortcode;

/**
 * Tests for BookingListShortcode
 */
class BookingListShortcodeTest extends TestCase {

	/**
	 * Test shortcode returns empty string when not logged in and login-form is 0
	 */
	public function test_render_logged_out_no_form() {
		wp_set_current_user( 0 );
		$output = BookingListShortcode::render( array( 'login-form' => 0 ) );
		$this->assertSame( '', $output );
	}

	/**
	 * Test shortcode renders login form when user is logged out and login-form is 1
	 */
	public function test_render_logged_out_with_form() {
		wp_set_current_user( 0 );
		$output = BookingListShortcode::render( array( 'login-form' => 1 ) );
		$this->assertStringContainsString( 'snippen-booking-login-card', $output );
		$this->assertStringContainsString( 'Logg inn', $output );
	}

	/**
	 * Test shortcode renders compact list and toggle controls when logged in
	 */
	public function test_render_logged_in_shows_compact_list_and_toggle() {
		$user_id = wp_create_user( 'testbookinguser', 'password123', 'testbookinguser@example.com' );
		wp_set_current_user( $user_id );

		$output = BookingListShortcode::render( array() );
		$this->assertStringContainsString( 'snippen-booking-list-container', $output );
		$this->assertStringContainsString( 'booking-view-toggle', $output );
		$this->assertStringContainsString( 'Kommende bookinger', $output );
		$this->assertStringContainsString( 'Arkiv', $output );
		$this->assertStringContainsString( 'Du har ingen kommende bookinger.', $output );
	}

	/**
	 * Test shortcode archive view renders archive empty message when archive tab selected
	 */
	public function test_render_archive_view_empty_message() {
		$user_id = wp_create_user( 'testarchiveuser', 'password123', 'testarchiveuser@example.com' );
		wp_set_current_user( $user_id );

		$_GET['booking_view'] = 'archive';
		$output = BookingListShortcode::render( array() );
		unset( $_GET['booking_view'] );

		$this->assertStringContainsString( 'Du har ingen tidligere bookinger i arkivet.', $output );
	}

	/**
	 * Test that open booking displays 'Åpen for sameiet' badge
	 */
	public function test_render_displays_open_booking_type_badge() {
		global $wpdb;
		$user_id = wp_create_user( 'testopenuser', 'password123', 'testopenuser@example.com' );
		wp_set_current_user( $user_id );

		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'uuid'              => wp_generate_uuid4(),
				'user_id'           => $user_id,
				'booking_date'      => gmdate( 'Y-m-d', strtotime( '+2 days' ) ),
				'customer_name'     => 'Open User',
				'customer_email'    => 'testopenuser@example.com',
				'status'            => 'confirmed',
				'price'             => 0.00,
				'booking_type'      => 'open',
				'payment_status_id' => 1,
			)
		);

		$output = BookingListShortcode::render( array() );
		$this->assertStringContainsString( 'snippen-type-open', $output );
		$this->assertStringContainsString( 'Åpen for sameiet', $output );
	}

	/**
	 * Test that private booking displays 'Privat arrangement' badge
	 */
	public function test_render_displays_private_booking_type_badge() {
		global $wpdb;
		$user_id = wp_create_user( 'testprivateuser', 'password123', 'testprivateuser@example.com' );
		wp_set_current_user( $user_id );

		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'uuid'              => wp_generate_uuid4(),
				'user_id'           => $user_id,
				'booking_date'      => gmdate( 'Y-m-d', strtotime( '+3 days' ) ),
				'customer_name'     => 'Private User',
				'customer_email'    => 'testprivateuser@example.com',
				'status'            => 'pending',
				'price'             => 500.00,
				'booking_type'      => 'private',
				'payment_status_id' => 1,
			)
		);

		$output = BookingListShortcode::render( array() );
		$this->assertStringContainsString( 'snippen-type-private', $output );
		$this->assertStringContainsString( 'Privat arrangement', $output );
	}

	/**
	 * Test that Vipps-paid booking displays Vipps status and reference without manual receipt upload form
	 */
	public function test_render_vipps_booking_displays_reference_without_manual_form() {
		global $wpdb;
		$user_id = wp_create_user( 'testvippsuser', 'password123', 'testvippsuser@example.com' );
		wp_set_current_user( $user_id );

		$ref = 'snippen-99-1725900000-123';
		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'uuid'              => wp_generate_uuid4(),
				'user_id'           => $user_id,
				'booking_date'      => gmdate( 'Y-m-d', strtotime( '+4 days' ) ),
				'customer_name'     => 'Vipps User',
				'customer_email'    => 'testvippsuser@example.com',
				'status'            => 'confirmed',
				'price'             => 600.00,
				'booking_type'      => 'private',
				'payment_status_id' => 2,
				'payment_notes'     => 'Vipps ref: ' . $ref,
			)
		);

		$output = BookingListShortcode::render( array() );
		$this->assertStringContainsString( 'Betalt med Vipps', $output );
		$this->assertStringContainsString( 'Transaksjonsreferanse:', $output );
		$this->assertStringContainsString( $ref, $output );
		$this->assertStringNotContainsString( 'name="payment_receipt"', $output );
		$this->assertStringNotContainsString( 'Bankkontonr:', $output );
	}

	/**
	 * Test that free booking displays free notice without manual upload form
	 */
	public function test_render_free_booking_displays_notice_without_upload_form() {
		global $wpdb;
		$user_id = wp_create_user( 'testfreeuser', 'password123', 'testfreeuser@example.com' );
		wp_set_current_user( $user_id );

		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'uuid'              => wp_generate_uuid4(),
				'user_id'           => $user_id,
				'booking_date'      => gmdate( 'Y-m-d', strtotime( '+5 days' ) ),
				'customer_name'     => 'Free User',
				'customer_email'    => 'testfreeuser@example.com',
				'status'            => 'confirmed',
				'price'             => 0.00,
				'booking_type'      => 'open',
				'payment_status_id' => 1,
			)
		);

		$output = BookingListShortcode::render( array() );
		$this->assertStringContainsString( 'Gratis arrangement – ingen betaling kreves.', $output );
		$this->assertStringNotContainsString( 'name="payment_receipt"', $output );
	}

	/**
	 * Test that cancelled open booking displays rejection notice, administrator reason, and private re-booking info
	 */
	public function test_render_cancelled_open_booking_displays_rejection_notice() {
		global $wpdb;
		$user_id = wp_create_user( 'testrejectuser', 'password123', 'testrejectuser@example.com' );
		wp_set_current_user( $user_id );

		$reason = 'Lokalet er reservert for sameiets generalforsamling denne kvelden.';
		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'uuid'              => wp_generate_uuid4(),
				'user_id'           => $user_id,
				'booking_date'      => gmdate( 'Y-m-d', strtotime( '+6 days' ) ),
				'customer_name'     => 'Reject User',
				'customer_email'    => 'testrejectuser@example.com',
				'status'            => 'cancelled',
				'price'             => 0.00,
				'booking_type'      => 'open',
				'rejection_reason'  => $reason,
				'payment_status_id' => 1,
			)
		);

		$output = BookingListShortcode::render( array() );
		$this->assertStringContainsString( 'snippen-rejection-notice', $output );
		$this->assertStringContainsString( 'Forespørsel om åpent arrangement ble avslått', $output );
		$this->assertStringContainsString( 'Begrunnelse fra styret:', $output );
		$this->assertStringContainsString( $reason, $output );
		$this->assertStringContainsString( 'Hvis du fremdeles ønsker arrangementet kan det reserveres og betales privat.', $output );
	}
}
