<?php

namespace SnippenBooking\Tests\Unit\Service;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\PricingService;
use SnippenBooking\Database\Install;

/**
 * Unit tests for PricingService logic using blocks and pricing rules
 */
class PricingServiceTest extends TestCase {

	private $service;
	private $objectId = 1;

	/**
	 * Set up the test environment
	 */
	protected function setUp(): void {
		parent::setUp();
		// Ensure tables and seed data exist
		Install::activate();
		$this->service = new PricingService();
	}

	private function getBlockId( $name ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}snippen_booking_blocks WHERE name = %s LIMIT 1", $name ) );
	}

	/**
	 * Test pricing for weekday hour block
	 */
	public function test_weekday_hour_pricing() {
		$date = '2026-06-01'; // Monday
		$block_08_09 = $this->getBlockId('08-09');

		$price = $this->service->getPrice( array( $this->objectId ), array( $block_08_09 ), $date );

		// Weekday hourly blocks should price at 100.00
		$this->assertEquals( 100.00, $price );
	}

	/**
	 * Test pricing for multiple weekday blocks
	 */
	public function test_multiple_weekday_blocks_pricing() {
		$date = '2026-06-01'; // Monday
		$block_08_09 = $this->getBlockId('08-09');
		$block_09_10 = $this->getBlockId('09-10');

		$price = $this->service->getPrice( array( $this->objectId ), array( $block_08_09, $block_09_10 ), $date );

		// Two hours should price at 200.00
		$this->assertEquals( 200.00, $price );
	}

	/**
	 * Test weekend pricing
	 */
	public function test_weekend_day_pricing() {
		$date = '2026-06-06'; // Saturday
		$block_day = $this->getBlockId('Day');

		$price = $this->service->getPrice( array( $this->objectId ), array( $block_day ), $date );

		// Weekend Day block should price at 1000.00
		$this->assertEquals( 1000.00, $price );
	}

	/**
	 * Test holiday pricing takes highest priority
	 */
	public function test_holiday_pricing_takes_priority() {
		// Christmas Eve 2026 is Thursday but HolidayService treats it as holiday or we can mock/assert
		// Let's use a date we know is a holiday or check HolidayService.
		// Wait, let's verify if HolidayService handles holidays: May 17th is Norway constitution day
		$date = '2026-05-17'; // May 17th (Sunday, also a holiday)
		$block_day = $this->getBlockId('Day');

		$price = $this->service->getPrice( array( $this->objectId ), array( $block_day ), $date );

		// Holiday Day block should price at 2500.00 instead of weekend day (1000) because holiday rule priority is 100
		$this->assertEquals( 2500.00, $price );
	}
}
