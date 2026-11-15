<?php

namespace SnippenBooking\Tests\Integration\Service;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\DiscountService;
use SnippenBooking\Service\PricingService;
use SnippenBooking\Database\Install;
use SnippenBooking\Database\Repository\BookingBlockRepository;
use SnippenBooking\Database\Repository\DiscountRuleRepository;
use SnippenBooking\Database\Repository\PricingRuleRepository;

/**
 * Unit tests for DiscountService
 */
class DiscountServiceTest extends TestCase {

	private $service;
	private $repo;
	private $objectId = 1;

	protected function setUp(): void {
		parent::setUp();
		Install::activate();
		$this->service = new DiscountService();
		$this->repo = new DiscountRuleRepository();
		
		// Clear existing discount rules from database
		global $wpdb;
		$wpdb->query("DELETE FROM {$wpdb->prefix}snippen_discount_rules");
		$wpdb->query("DELETE FROM {$wpdb->prefix}snippen_discount_rule_booking_objects");
	}

	private function getBlockId( $name ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}snippen_booking_blocks WHERE name = %s LIMIT 1", $name ) );
	}

	public function test_no_discount_applied() {
		$block_08_09 = $this->getBlockId('08-09');
		$base_price = 100.00;

		$discount_info = $this->service->applyDiscount( $base_price, array( $this->objectId ), array( $block_08_09 ) );

		$this->assertEquals( 100.00, $discount_info['final_price'] );
		$this->assertEquals( 0.0, $discount_info['discount_amount'] );
		$this->assertNull( $discount_info['discount_rule'] );
	}

	public function test_percentage_discount_applied() {
		// Create a rule: 10% discount for >= 2 hours
		$rule_id = $this->repo->save( array(
			'name' => '10% over 2 hours',
			'discount_type' => 'percentage',
			'discount_value' => 10.0,
			'min_duration_hours' => 2.0,
			'max_duration_hours' => null,
			'priority' => 10
		) );
		$this->repo->sync_booking_objects( $rule_id, array( $this->objectId ) );

		$block_08_09 = $this->getBlockId('08-09');
		$block_09_10 = $this->getBlockId('09-10');
		$base_price = 200.00; // 2 hours

		$discount_info = $this->service->applyDiscount( $base_price, array( $this->objectId ), array( $block_08_09, $block_09_10 ) );

		$this->assertEquals( 180.00, $discount_info['final_price'] );
		$this->assertEquals( 20.00, $discount_info['discount_amount'] );
		$this->assertEquals( $rule_id, $discount_info['discount_rule']->id );
	}

	public function test_fixed_amount_discount_applied() {
		// Create a rule: 50 NOK discount for >= 2 hours
		$rule_id = $this->repo->save( array(
			'name' => '50 NOK off',
			'discount_type' => 'fixed_amount',
			'discount_value' => 50.0,
			'min_duration_hours' => 2.0,
			'max_duration_hours' => null,
			'priority' => 10
		) );
		$this->repo->sync_booking_objects( $rule_id, array( $this->objectId ) );

		$block_08_09 = $this->getBlockId('08-09');
		$block_09_10 = $this->getBlockId('09-10');
		$base_price = 200.00;

		$discount_info = $this->service->applyDiscount( $base_price, array( $this->objectId ), array( $block_08_09, $block_09_10 ) );

		$this->assertEquals( 150.00, $discount_info['final_price'] );
		$this->assertEquals( 50.00, $discount_info['discount_amount'] );
		$this->assertEquals( $rule_id, $discount_info['discount_rule']->id );
	}

	public function test_discount_cannot_exceed_base_price() {
		$rule_id = $this->repo->save( array(
			'name' => '1000 NOK off',
			'discount_type' => 'fixed_amount',
			'discount_value' => 1000.0,
			'min_duration_hours' => null,
			'max_duration_hours' => null,
			'priority' => 10
		) );
		$this->repo->sync_booking_objects( $rule_id, array( $this->objectId ) );

		$block_08_09 = $this->getBlockId('08-09');
		$base_price = 100.00;

		$discount_info = $this->service->applyDiscount( $base_price, array( $this->objectId ), array( $block_08_09 ) );

		$this->assertEquals( 0.00, $discount_info['final_price'] );
		$this->assertEquals( 100.00, $discount_info['discount_amount'] );
	}

	public function test_priority_wins() {
		// Create a rule: 10% discount (priority 10)
		$rule_1 = $this->repo->save( array(
			'name' => '10% off',
			'discount_type' => 'percentage',
			'discount_value' => 10.0,
			'min_duration_hours' => null,
			'max_duration_hours' => null,
			'priority' => 10
		) );
		$this->repo->sync_booking_objects( $rule_1, array( $this->objectId ) );

		// Create a rule: 20% discount (priority 20)
		$rule_2 = $this->repo->save( array(
			'name' => '20% off',
			'discount_type' => 'percentage',
			'discount_value' => 20.0,
			'min_duration_hours' => null,
			'max_duration_hours' => null,
			'priority' => 20
		) );
		$this->repo->sync_booking_objects( $rule_2, array( $this->objectId ) );

		$block_08_09 = $this->getBlockId('08-09');
		$base_price = 100.00;

		$discount_info = $this->service->applyDiscount( $base_price, array( $this->objectId ), array( $block_08_09 ) );

		$this->assertEquals( 80.00, $discount_info['final_price'] );
		$this->assertEquals( 20.00, $discount_info['discount_amount'] );
		$this->assertEquals( $rule_2, $discount_info['discount_rule']->id );
	}

	public function test_fixed_price_discount_applied() {
		// Create a rule: Fixed price of 400 NOK for >= 2 hours
		$rule_id = $this->repo->save( array(
			'name' => 'Fixed price 400 NOK',
			'discount_type' => 'fixed_price',
			'discount_value' => 400.0,
			'min_duration_hours' => 2.0,
			'max_duration_hours' => null,
			'priority' => 10
		) );
		$this->repo->sync_booking_objects( $rule_id, array( $this->objectId ) );

		$block_08_09 = $this->getBlockId('08-09');
		$block_09_10 = $this->getBlockId('09-10');
		$base_price = 1000.00;

		$discount_info = $this->service->applyDiscount( $base_price, array( $this->objectId ), array( $block_08_09, $block_09_10 ) );

		$this->assertEquals( 400.00, $discount_info['final_price'] );
		$this->assertEquals( 600.00, $discount_info['discount_amount'] );
		$this->assertEquals( $rule_id, $discount_info['discount_rule']->id );
	}

	/**
	 * Test that a pricing rule marked exclude_from_discounts is not overridden by a duration discount.
	 */
	public function test_excluded_pricing_rule_ignores_duration_discount() {
		$block_repo        = new BookingBlockRepository();
		$pricing_rule_repo = new PricingRuleRepository();
		$pricing_service   = new PricingService();

		// Weekday half-day discount: 4-8 hours -> fixed_price 500 NOK (Mon-Fri: 1,2,3,4,5)
		$discount_rule_id = $this->repo->save(
			array(
				'name'               => 'Hverdag halvdag',
				'discount_type'      => 'fixed_price',
				'discount_value'     => 500.0,
				'min_duration_hours' => 4.0,
				'max_duration_hours' => 8.0,
				'days_of_week'       => '1,2,3,4,5',
				'priority'           => 10,
			)
		);
		$this->repo->sync_booking_objects( $discount_rule_id, array( $this->objectId ) );

		// Evening block (7 hours) with fixed weekend price excluded from discounts
		$evening_block_id = $block_repo->save(
			array(
				'name'       => 'Kveld 16-23',
				'start_time' => '16:00:00',
				'end_time'   => '23:00:00',
				'is_active'  => 1,
			)
		);
		$evening_rule_id  = $pricing_rule_repo->save(
			array(
				'name'                   => 'Halv dag helg',
				'price'                  => 1000.00,
				'priority'               => 50,
				'is_active'              => 1,
				'days_of_week'           => '5,6,0',
				'exclude_from_discounts' => 1,
			)
		);
		$pricing_rule_repo->sync_booking_objects( $evening_rule_id, array( $this->objectId ) );
		$pricing_rule_repo->sync_booking_blocks( $evening_rule_id, array( $evening_block_id ) );

		$friday        = '2026-06-05'; // Friday
		$breakdown     = $pricing_service->getPriceBreakdown( array( $this->objectId ), array( $evening_block_id ), $friday );
		$discount_info = $this->service->applyDiscountWithBreakdown( $breakdown, array( $this->objectId ), $friday );

		$this->assertEquals( 1000.00, $discount_info['final_price'] );
		$this->assertEquals( 0.0, $discount_info['discount_amount'] );
		$this->assertNull( $discount_info['discount_rule'] );
	}

	/**
	 * Test partial calculation when combining discountable daytime blocks with an excluded evening block.
	 */
	public function test_combined_discountable_day_and_excluded_evening_uses_partial_calculation() {
		$block_repo        = new BookingBlockRepository();
		$pricing_rule_repo = new PricingRuleRepository();
		$pricing_service   = new PricingService();

		// Weekday half-day discount: 4-8 hours -> fixed_price 500 NOK (Mon-Fri: 1,2,3,4,5)
		$discount_rule_id = $this->repo->save(
			array(
				'name'               => 'Hverdag halvdag',
				'discount_type'      => 'fixed_price',
				'discount_value'     => 500.0,
				'min_duration_hours' => 4.0,
				'max_duration_hours' => 8.0,
				'days_of_week'       => '1,2,3,4,5',
				'priority'           => 10,
			)
		);
		$this->repo->sync_booking_objects( $discount_rule_id, array( $this->objectId ) );

		// Daytime 4-hour block priced at 600 NOK (discountable)
		$day_block_id = $block_repo->save(
			array(
				'name'       => 'Formiddag 10-14',
				'start_time' => '10:00:00',
				'end_time'   => '14:00:00',
				'is_active'  => 1,
			)
		);
		$day_rule_id  = $pricing_rule_repo->save(
			array(
				'name'                   => 'Formiddag hverdag',
				'price'                  => 600.00,
				'priority'               => 50,
				'is_active'              => 1,
				'days_of_week'           => '1,2,3,4,5',
				'exclude_from_discounts' => 0,
			)
		);
		$pricing_rule_repo->sync_booking_objects( $day_rule_id, array( $this->objectId ) );
		$pricing_rule_repo->sync_booking_blocks( $day_rule_id, array( $day_block_id ) );

		// Evening 7-hour block priced at 1000 NOK (excluded from discounts)
		$evening_block_id = $block_repo->save(
			array(
				'name'       => 'Kveld 16-23',
				'start_time' => '16:00:00',
				'end_time'   => '23:00:00',
				'is_active'  => 1,
			)
		);
		$evening_rule_id  = $pricing_rule_repo->save(
			array(
				'name'                   => 'Halv dag helg',
				'price'                  => 1000.00,
				'priority'               => 50,
				'is_active'              => 1,
				'days_of_week'           => '5,6,0',
				'exclude_from_discounts' => 1,
			)
		);
		$pricing_rule_repo->sync_booking_objects( $evening_rule_id, array( $this->objectId ) );
		$pricing_rule_repo->sync_booking_blocks( $evening_rule_id, array( $evening_block_id ) );

		$friday        = '2026-06-05'; // Friday
		$breakdown     = $pricing_service->getPriceBreakdown(
			array( $this->objectId ),
			array( $day_block_id, $evening_block_id ),
			$friday
		);
		$discount_info = $this->service->applyDiscountWithBreakdown( $breakdown, array( $this->objectId ), $friday );

		// Daytime 600 -> discounted to 500 (100 NOK discount); Evening stays 1000 -> total 1500
		$this->assertEquals( 1500.00, $discount_info['final_price'] );
		$this->assertEquals( 100.00, $discount_info['discount_amount'] );
		$this->assertEquals( $discount_rule_id, $discount_info['discount_rule']->id );
	}
}
