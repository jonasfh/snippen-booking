<?php
/**
 * Unit Test for Migration 2.42.0
 *
 * @package SnippenBooking\Tests\Unit
 */

namespace SnippenBooking\Tests\Unit;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Database\Migrations\Migration_2_42_0;

/**
 * Class Migration_2_42_0_Test
 */
class Migration_2_42_0_Test extends TestCase {

	/**
	 * Test migration enables snippen_sms_booking_confirmed_enabled when confirmation is enabled
	 */
	public function test_migration_2_42_0_enables_confirmed_toggle_when_confirmation_enabled() {
		update_option( 'snippen_sms_booking_confirmation_enabled', 'yes' );
		update_option( 'snippen_sms_booking_confirmed_enabled', 'no' );

		$migration = new Migration_2_42_0();
		$migration->up();

		$this->assertEquals( 'yes', get_option( 'snippen_sms_booking_confirmed_enabled' ) );
	}

	/**
	 * Test migration leaves confirmed toggle unchanged when confirmation is disabled
	 */
	public function test_migration_2_42_0_leaves_confirmed_toggle_when_confirmation_disabled() {
		update_option( 'snippen_sms_booking_confirmation_enabled', 'no' );
		update_option( 'snippen_sms_booking_confirmed_enabled', 'no' );

		$migration = new Migration_2_42_0();
		$migration->up();

		$this->assertEquals( 'no', get_option( 'snippen_sms_booking_confirmed_enabled' ) );
	}
}
