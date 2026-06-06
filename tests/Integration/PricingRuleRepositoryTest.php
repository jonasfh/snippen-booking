<?php

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Database\Repository\PricingRuleRepository;

class PricingRuleRepositoryTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		\SnippenBooking\Database\MigrationManager::run();
		// Clean up tables
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_pricing_rules" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_pricing_rule_booking_objects" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_pricing_rule_booking_blocks" );
	}

	public function test_find_matching_rule_returns_null_when_no_rules() {
		$repo = new PricingRuleRepository();
		$rule = $repo->find_matching_rule( '2025-05-15', 1, 1 );
		$this->assertNull( $rule );
	}

	public function test_find_matching_rule_returns_highest_priority() {
		$repo = new PricingRuleRepository();

		// Rule 1: Priority 10
		$id1 = $repo->save( array( 'name' => 'Rule 1', 'price' => 100, 'priority' => 10, 'is_active' => 1 ) );
		$repo->sync_booking_objects( $id1, array( 1 ) );
		$repo->sync_booking_blocks( $id1, array( 1 ) );

		// Rule 2: Priority 50
		$id2 = $repo->save( array( 'name' => 'Rule 2', 'price' => 200, 'priority' => 50, 'is_active' => 1 ) );
		$repo->sync_booking_objects( $id2, array( 1 ) );
		$repo->sync_booking_blocks( $id2, array( 1 ) );

		$rule = $repo->find_matching_rule( '2025-05-15', 1, 1 );
		
		$this->assertNotNull( $rule );
		$this->assertEquals( 'Rule 2', $rule->name );
		$this->assertEquals( 200, $rule->price );
	}

	public function test_find_matching_rule_respects_is_active() {
		$repo = new PricingRuleRepository();

		// Rule 1: Priority 50, Inactive
		$id1 = $repo->save( array( 'name' => 'Rule 1', 'price' => 200, 'priority' => 50, 'is_active' => 0 ) );
		$repo->sync_booking_objects( $id1, array( 1 ) );
		$repo->sync_booking_blocks( $id1, array( 1 ) );

		// Rule 2: Priority 10, Active
		$id2 = $repo->save( array( 'name' => 'Rule 2', 'price' => 100, 'priority' => 10, 'is_active' => 1 ) );
		$repo->sync_booking_objects( $id2, array( 1 ) );
		$repo->sync_booking_blocks( $id2, array( 1 ) );

		$rule = $repo->find_matching_rule( '2025-05-15', 1, 1 );
		
		$this->assertNotNull( $rule );
		$this->assertEquals( 'Rule 2', $rule->name );
	}

	public function test_find_matching_rule_respects_day_of_week() {
		$repo = new PricingRuleRepository();

		// Friday rule
		$id1 = $repo->save( array( 'name' => 'Friday', 'price' => 500, 'priority' => 10, 'is_active' => 1, 'days_of_week' => '5' ) );
		$repo->sync_booking_objects( $id1, array( 1 ) );
		$repo->sync_booking_blocks( $id1, array( 1 ) );

		// Saturday rule
		$id2 = $repo->save( array( 'name' => 'Saturday', 'price' => 600, 'priority' => 10, 'is_active' => 1, 'days_of_week' => '6' ) );
		$repo->sync_booking_objects( $id2, array( 1 ) );
		$repo->sync_booking_blocks( $id2, array( 1 ) );

		// 2025-05-16 is a Friday
		$rule = $repo->find_matching_rule( '2025-05-16', 1, 1 );
		
		$this->assertNotNull( $rule );
		$this->assertEquals( 'Friday', $rule->name );
	}

	public function test_find_matching_rule_respects_date_constraints() {
		$repo = new PricingRuleRepository();

		// Summer rule
		$id1 = $repo->save( array( 'name' => 'Summer', 'price' => 500, 'priority' => 50, 'is_active' => 1, 'date_start' => '2025-06-01', 'date_end' => '2025-08-31' ) );
		$repo->sync_booking_objects( $id1, array( 1 ) );
		$repo->sync_booking_blocks( $id1, array( 1 ) );

		// Normal rule
		$id2 = $repo->save( array( 'name' => 'Normal', 'price' => 200, 'priority' => 10, 'is_active' => 1 ) );
		$repo->sync_booking_objects( $id2, array( 1 ) );
		$repo->sync_booking_blocks( $id2, array( 1 ) );

		// Inside summer
		$rule_summer = $repo->find_matching_rule( '2025-07-15', 1, 1 );
		$this->assertNotNull( $rule_summer );
		$this->assertEquals( 'Summer', $rule_summer->name );

		// Outside summer
		$rule_normal = $repo->find_matching_rule( '2025-05-15', 1, 1 );
		$this->assertNotNull( $rule_normal );
		$this->assertEquals( 'Normal', $rule_normal->name );
	}
}
