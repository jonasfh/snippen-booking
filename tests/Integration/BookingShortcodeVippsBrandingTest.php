<?php

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Shortcode\BookingShortcode;

/**
 * Test Vipps branding elements rendered in BookingShortcode (#319)
 */
class BookingShortcodeVippsBrandingTest extends TestCase {

	private int $user_id;

	public function setUp(): void {
		parent::setUp();
		$login         = 'test_shortcode_user_' . uniqid();
		$this->user_id = wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => 'password',
				'user_email' => $login . '@example.com',
				'role'       => 'subscriber',
			)
		);
		update_user_meta( $this->user_id, 'snippen_phone', '99887766' );
		wp_set_current_user( $this->user_id );
	}

	public function tearDown(): void {
		delete_option( 'snippen_vipps_enabled' );
		delete_option( 'snippen_vipps_client_id' );
		delete_option( 'snippen_vipps_client_secret' );
		delete_option( 'snippen_vipps_subscription_key' );
		delete_option( 'snippen_vipps_msn' );
		parent::tearDown();
	}

	/**
	 * Test that Vipps badge is rendered in the private arrangement card when Vipps is enabled
	 */
	public function test_renders_vipps_badge_when_vipps_enabled() {
		update_option( 'snippen_vipps_enabled', 'yes' );
		update_option( 'snippen_vipps_client_id', 'test_client_id' );
		update_option( 'snippen_vipps_client_secret', 'test_client_secret' );
		update_option( 'snippen_vipps_subscription_key', 'test_sub_key' );
		update_option( 'snippen_vipps_msn', '123456' );

		$output = BookingShortcode::render( array() );

		$this->assertStringContainsString( 'booking-type-title-row', $output );
		$this->assertStringContainsString( 'vipps-tag', $output );
		$this->assertStringContainsString( 'vipps-tag-logo', $output );
		$this->assertStringContainsString( 'vipps-tag-text', $output );
		$this->assertStringContainsString( 'Vipps', $output );
	}

	/**
	 * Test that Vipps badge is NOT rendered when Vipps is disabled
	 */
	public function test_does_not_render_vipps_badge_when_vipps_disabled() {
		update_option( 'snippen_vipps_enabled', 'no' );

		$output = BookingShortcode::render( array() );

		$this->assertStringContainsString( 'booking-type-title-row', $output );
		$this->assertStringNotContainsString( 'vipps-tag', $output );
		$this->assertStringNotContainsString( 'vipps-tag-logo', $output );
	}
}
