<?php

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Database\Install;
use SnippenBooking\Shortcode\AccountConfirmationShortcode;

class AccountConfirmationShortcodeTest extends TestCase {

	/**
	 * Set up the test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		Install::activate();
		AccountConfirmationShortcode::register();
	}

	/**
	 * Test that when a guest user is logged in, the shortcode returns an empty string.
	 */
	public function test_returns_empty_when_user_logged_in() {
		// Mock logged in user
		$user_id = wp_insert_user(
			array(
				'user_login' => 'test_logged_in_user_' . uniqid(),
				'user_pass'  => 'password',
				'role'       => 'subscriber',
			)
		);
		$this->assertNotInstanceOf( \WP_Error::class, $user_id, 'User creation failed: ' . ( is_wp_error( $user_id ) ? $user_id->get_error_message() : '' ) );
		wp_set_current_user( $user_id );

		$output = do_shortcode( '[snippen_account_confirmation]' );
		$this->assertEquals( '', $output );

		// Clean up
		wp_set_current_user( 0 );
	}

	/**
	 * Test that when a guest user is NOT logged in, the shortcode renders the confirmation form.
	 */
	public function test_renders_form_when_user_not_logged_in() {
		// Mock NOT logged in user
		wp_set_current_user( 0 );

		$output = do_shortcode( '[snippen_account_confirmation]' );
		$this->assertStringContainsString( 'class="snippen-confirmation-container"', $output );
		$this->assertStringContainsString( 'id="confirmation-step-1"', $output );
		$this->assertStringContainsString( 'id="snippen-request-code"', $output );
	}
}
