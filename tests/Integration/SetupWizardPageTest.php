<?php

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Admin\SetupWizard;

class SetupWizardPageTest extends TestCase {

	protected $requires_seed_data = false;

	protected function setUp(): void {
		parent::setUp();
		// Ensure we're in admin
		wp_set_current_user( 1 );
	}

	protected function tearDown(): void {
		parent::tearDown();
		SetupWizard::reset();
	}

	public function testSetupWizardPageRenders() {
		// Simulate admin user
		$this->assertTrue( current_user_can( 'manage_options' ) );

		// The page should render without errors
		ob_start();
		\SnippenBooking\Admin\SetupWizardPage::render_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Setup Wizard', $output );
		$this->assertStringContainsString( 'Installer Standard Oppsett', $output );
	}

	public function testSetupWizardFormSubmissionCreatesData() {
		// Set up POST data
		$_POST = array(
			'action'   => 'create_starter_setup',
			'_wpnonce' => wp_create_nonce( 'snippen_booking_wizard' ),
		);

		global $wpdb;

		// Before setup
		$objects_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}snippen_booking_objects WHERE deleted_at IS NULL" );

		// Render page (this processes the form)
		ob_start();
		\SnippenBooking\Admin\SetupWizardPage::render_page();
		$output = ob_get_clean();

		// After setup
		$objects_after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}snippen_booking_objects WHERE deleted_at IS NULL" );

		// Verify objects were created
		$this->assertGreaterThan( $objects_before, $objects_after );
		$this->assertStringContainsString( 'success', strtolower( $output ) );

		// Clean up
		unset( $_POST );
	}

	public function testSetupWizardSkipAction() {
		// Set up POST data for skip action
		$_POST = array(
			'action'   => 'skip_wizard',
			'_wpnonce' => wp_create_nonce( 'snippen_booking_wizard' ),
		);

		// Render page
		ob_start();
		\SnippenBooking\Admin\SetupWizardPage::render_page();
		$output = ob_get_clean();

		// Verify wizard is marked as completed
		$this->assertTrue( SetupWizard::is_completed() );
		$this->assertStringContainsString( 'skipped', strtolower( $output ) );

		// Clean up
		unset( $_POST );
	}
}
