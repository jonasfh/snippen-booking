<?php

namespace SnippenBooking\Tests\Unit;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Admin\SetupWizard;

class SetupWizardTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		SetupWizard::reset();
		// Clean up any existing data
		$this->cleanup_setup_data();
	}

	protected function tearDown(): void {
		parent::tearDown();
		// Clean up options after each test
		SetupWizard::reset();
		$this->cleanup_setup_data();
	}

	private function cleanup_setup_data() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}snippen_prices" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}snippen_price_booking_objects" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}snippen_bookings_booking_objects" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}snippen_bookings" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}snippen_time_slots" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}snippen_booking_objects" );
	}

	public function testIsCompletedReturnsFalseByDefault() {
		$this->assertFalse( SetupWizard::is_completed() );
	}

	public function testMarkCompletedSetsOption() {
		SetupWizard::mark_completed();
		$this->assertTrue( SetupWizard::is_completed() );
	}

	public function testResetClearsCompletion() {
		SetupWizard::mark_completed();
		SetupWizard::reset();
		$this->assertFalse( SetupWizard::is_completed() );
	}

	public function testCreateStarterSetupCreatesObjects() {
		// Mock database tables
		global $wpdb;

		$result = SetupWizard::create_starter_setup();

		$this->assertTrue( $result['success'] );
		$this->assertStringContainsString( 'success', strtolower( $result['message'] ) );

		// Verify objects were created
		$objects = $wpdb->get_results( "SELECT id FROM {$wpdb->prefix}snippen_booking_objects WHERE deleted_at IS NULL" );
		$this->assertCount( 2, $objects );
	}

	public function testCreateStarterSetupCreatesSlots() {
		global $wpdb;

		SetupWizard::create_starter_setup();

		// Verify time slots were created
		$slots = $wpdb->get_results( "SELECT id FROM {$wpdb->prefix}snippen_time_slots WHERE deleted_at IS NULL" );
		$this->assertCount( 3, $slots );
	}

	public function testCreateStarterSetupCreatesPricing() {
		global $wpdb;

		SetupWizard::create_starter_setup();

		// Verify pricing was created
		$pricing = $wpdb->get_results( "SELECT id FROM {$wpdb->prefix}snippen_prices" );
		$this->assertGreaterThan( 0, count( $pricing ) );
	}

	public function testCreateStarterSetupIsIdempotent() {
		// Create setup once
		$result1 = SetupWizard::create_starter_setup();
		$this->assertTrue( $result1['success'] );

		global $wpdb;
		$count1 = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}snippen_booking_objects WHERE deleted_at IS NULL" );

		// Try to create again
		$result2 = SetupWizard::create_starter_setup();
		$this->assertFalse( $result2['success'] );

		// Verify no duplicate objects were created
		$count2 = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}snippen_booking_objects WHERE deleted_at IS NULL" );
		$this->assertEquals( $count1, $count2 );
	}
}
