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
}
