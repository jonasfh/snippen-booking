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
		self::$db_seeded = false;
	}

	private function cleanup_setup_data() {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_booking_booking_blocks" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_booking_booking_objects" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_bookings" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_booking_blocks" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_booking_object_booking_blocks" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_booking_objects" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_pricing_rules" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_pricing_rule_booking_blocks" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_pricing_rule_booking_objects" );
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
		global $wpdb;

		$result = SetupWizard::create_starter_setup();

		$this->assertTrue( $result['success'] );
		$this->assertStringContainsString( 'success', strtolower( $result['message'] ) );

		// Verify objects were created
		$objects = $wpdb->get_results( "SELECT id FROM {$wpdb->prefix}snippen_booking_objects WHERE deleted_at IS NULL" );
		$this->assertCount( 2, $objects );
	}

	public function testCreateStarterSetupCreatesBlocks() {
		global $wpdb;

		SetupWizard::create_starter_setup();

		// Verify blocks were created (8 Mon-Fri hourly + 7 Mon-Thu hourly + 1 Day + 1 Evening = 17)
		$blocks = $wpdb->get_results( "SELECT id FROM {$wpdb->prefix}snippen_booking_blocks WHERE deleted_at IS NULL" );
		$this->assertCount( 17, $blocks );
	}

	public function testCreateStarterSetupCreatesPricing() {
		global $wpdb;

		SetupWizard::create_starter_setup();

		// Verify pricing rules were created (10)
		$pricing = $wpdb->get_results( "SELECT id FROM {$wpdb->prefix}snippen_pricing_rules WHERE deleted_at IS NULL" );
		$this->assertCount( 10, $pricing );
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
