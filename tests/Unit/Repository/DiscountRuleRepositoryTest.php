<?php

namespace SnippenBooking\Tests\Unit\Repository;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Database\Repository\DiscountRuleRepository;
use SnippenBooking\Database\Install;

/**
 * Unit tests for DiscountRuleRepository
 */
class DiscountRuleRepositoryTest extends TestCase {

	private $repo;

	protected function setUp(): void {
		parent::setUp();
		Install::activate();
		$this->repo = new DiscountRuleRepository();
		
		// Clear existing rules
		global $wpdb;
		$wpdb->query("DELETE FROM {$wpdb->prefix}snippen_discount_rules");
		$wpdb->query("DELETE FROM {$wpdb->prefix}snippen_discount_rule_booking_objects");
	}

	public function test_save_and_find() {
		$data = array(
			'name' => 'Test Rule',
			'discount_type' => 'percentage',
			'discount_value' => 15.0,
			'priority' => 10
		);

		$id = $this->repo->save($data);
		$this->assertGreaterThan(0, $id);

		$rule = $this->repo->find($id);
		$this->assertEquals('Test Rule', $rule->name);
		$this->assertEquals('percentage', $rule->discount_type);
		$this->assertEquals(15.0, $rule->discount_value);
	}

	public function test_sync_booking_objects() {
		$id = $this->repo->save(array(
			'name' => 'Sync Test',
			'discount_type' => 'fixed_amount',
			'discount_value' => 100.0,
			'priority' => 10
		));

		$this->repo->sync_booking_objects($id, array(1, 2));

		$objects = $this->repo->get_rule_objects($id);
		$this->assertCount(2, $objects);
		$this->assertContains('1', $objects);
		$this->assertContains('2', $objects);

		// Test replacement
		$this->repo->sync_booking_objects($id, array(3));
		$objects = $this->repo->get_rule_objects($id);
		$this->assertCount(1, $objects);
		$this->assertContains('3', $objects);
	}

	public function test_find_applicable_rule_duration_filter() {
		$rule1 = $this->repo->save(array(
			'name' => 'Short Duration',
			'discount_type' => 'percentage',
			'discount_value' => 10.0,
			'min_duration_hours' => null,
			'max_duration_hours' => 2.0,
			'priority' => 10
		));
		$this->repo->sync_booking_objects($rule1, array(1));

		$rule2 = $this->repo->save(array(
			'name' => 'Long Duration',
			'discount_type' => 'percentage',
			'discount_value' => 20.0,
			'min_duration_hours' => 3.0,
			'max_duration_hours' => null,
			'priority' => 20
		));
		$this->repo->sync_booking_objects($rule2, array(1));

		// Test 1 hour (should match rule 1)
		$matched = $this->repo->find_applicable_rule(array(1), 1.0);
		$this->assertNotNull($matched);
		$this->assertEquals($rule1, $matched->id);

		// Test 4 hours (should match rule 2)
		$matched = $this->repo->find_applicable_rule(array(1), 4.0);
		$this->assertNotNull($matched);
		$this->assertEquals($rule2, $matched->id);

		// Test 2.5 hours (should match neither)
		$matched = $this->repo->find_applicable_rule(array(1), 2.5);
		$this->assertNull($matched);
	}

	public function test_find_applicable_rule_date_filters() {
		// Rule that only applies on Mondays (1)
		$rule_monday = $this->repo->save(array(
			'name' => 'Monday Rule',
			'discount_type' => 'percentage',
			'discount_value' => 10.0,
			'days_of_week' => '1',
			'priority' => 10
		));
		$this->repo->sync_booking_objects($rule_monday, array(1));

		// Rule that only applies on Holidays
		$rule_holiday = $this->repo->save(array(
			'name' => 'Holiday Rule',
			'discount_type' => 'fixed_amount',
			'discount_value' => 500.0,
			'holiday_only' => 1,
			'priority' => 20
		));
		$this->repo->sync_booking_objects($rule_holiday, array(1));

		// Test with a Monday: 2026-06-01 is a Monday
		$matched = $this->repo->find_applicable_rule(array(1), 1.0, '2026-06-01');
		$this->assertNotNull($matched);
		$this->assertEquals($rule_monday, $matched->id);

		// Test with a Tuesday: 2026-06-02 is a Tuesday, not a holiday
		$matched = $this->repo->find_applicable_rule(array(1), 1.0, '2026-06-02');
		$this->assertNull($matched);

		// Test with a Holiday (May 17th 2026)
		$matched = $this->repo->find_applicable_rule(array(1), 1.0, '2026-05-17');
		$this->assertNotNull($matched);
		$this->assertEquals($rule_holiday, $matched->id);
	}
}
