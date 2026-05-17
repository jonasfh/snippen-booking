<?php
/**
 * Unit tests for HolidayService
 *
 * @package SnippenBooking\Tests\Unit\Service
 */

namespace SnippenBooking\Tests\Unit\Service;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\HolidayService;

/**
 * HolidayServiceTest
 */
class HolidayServiceTest extends TestCase {

	/**
	 * Test fixed holidays
	 */
	public function test_fixed_holidays() {
		$service = new HolidayService();

		// New Year's Day
		$this->assertTrue( $service->isHoliday( '2026-01-01' ) );
		// Labour Day (1. mai)
		$this->assertTrue( $service->isHoliday( '2026-05-01' ) );
		// Constitution Day (17. mai)
		$this->assertTrue( $service->isHoliday( '2026-05-17' ) );
		// Christmas Day (1. juledag)
		$this->assertTrue( $service->isHoliday( '2026-12-25' ) );
		// Boxing Day (2. juledag)
		$this->assertTrue( $service->isHoliday( '2026-12-26' ) );
	}

	/**
	 * Test moving holidays for year 2026
	 */
	public function test_moving_holidays_2026() {
		$service = new HolidayService();

		// 2026 Easter Sunday is April 5th
		$this->assertTrue( $service->isHoliday( '2026-04-02' ) ); // Skjærtorsdag
		$this->assertTrue( $service->isHoliday( '2026-04-03' ) ); // Langfredag
		$this->assertTrue( $service->isHoliday( '2026-04-05' ) ); // 1. påskedag
		$this->assertTrue( $service->isHoliday( '2026-04-06' ) ); // 2. påskedag
		$this->assertTrue( $service->isHoliday( '2026-05-14' ) ); // Kr. Himmelfart (Easter + 39 days)
		$this->assertTrue( $service->isHoliday( '2026-05-24' ) ); // 1. pinsedag (Easter + 49 days)
		$this->assertTrue( $service->isHoliday( '2026-05-25' ) ); // 2. pinsedag (Easter + 50 days)

		// Verification of some regular days (non-holidays)
		$this->assertFalse( $service->isHoliday( '2026-04-04' ) ); // Saturday between Langfredag and Easter
		$this->assertFalse( $service->isHoliday( '2026-05-23' ) ); // Saturday before 1. pinsedag
	}

	/**
	 * Test moving holidays for year 2027
	 */
	public function test_moving_holidays_2027() {
		$service = new HolidayService();

		// 2027 Easter Sunday is March 28th
		$this->assertTrue( $service->isHoliday( '2027-03-25' ) ); // Skjærtorsdag
		$this->assertTrue( $service->isHoliday( '2027-03-26' ) ); // Langfredag
		$this->assertTrue( $service->isHoliday( '2027-03-28' ) ); // 1. påskedag
		$this->assertTrue( $service->isHoliday( '2027-03-29' ) ); // 2. påskedag
		$this->assertTrue( $service->isHoliday( '2027-05-06' ) ); // Kr. Himmelfart (Easter + 39 days)
		$this->assertTrue( $service->isHoliday( '2027-05-16' ) ); // 1. pinsedag (Easter + 49 days)
		$this->assertTrue( $service->isHoliday( '2027-05-17' ) ); // 2. pinsedag (Easter + 50 days)
	}

	/**
	 * Test robustness when system TZ and PHP timezone are mismatched
	 */
	public function test_timezone_robustness() {
		$original_system_tz = getenv( 'TZ' );
		$original_php_tz    = date_default_timezone_get();

		// Mismatch setup: System timezone is Europe/Oslo, but PHP default is UTC
		putenv( 'TZ=Europe/Oslo' );
		date_default_timezone_set( 'UTC' );

		$service = new HolidayService();

		// Easter and 2. pinsedag in 2026 should still be recognized perfectly
		$this->assertTrue( $service->isHoliday( '2026-04-05' ) ); // Easter Sunday
		$this->assertTrue( $service->isHoliday( '2026-05-25' ) ); // 2. pinsedag
		$this->assertFalse( $service->isHoliday( '2026-04-04' ) ); // Non-holiday
		$this->assertFalse( $service->isHoliday( '2026-05-26' ) ); // Non-holiday

		// Restore original settings
		if ( $original_system_tz !== false ) {
			putenv( "TZ=$original_system_tz" );
		} else {
			putenv( 'TZ' );
		}
		date_default_timezone_set( $original_php_tz );
	}
}
