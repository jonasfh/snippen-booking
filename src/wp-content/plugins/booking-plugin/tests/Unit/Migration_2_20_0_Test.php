<?php
/**
 * Unit Test for Migration 2.20.0
 *
 * @package SnippenBooking\Tests\Unit
 */

namespace SnippenBooking\Tests\Unit;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Database\Migrations\Migration_2_20_0;
use SnippenBooking\Database\Repository\NotificationTemplateRepository;

/**
 * Class Migration_2_20_0_Test
 */
class Migration_2_20_0_Test extends TestCase {

	/**
	 * Test migration execution and legacy option migration
	 */
	public function test_migration_2_20_0_executes_and_migrates_legacy_options() {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_notification_templates';
		$wpdb->query( "TRUNCATE TABLE {$table}" );

		// Set legacy options
		update_option( 'snippen_template_user_activation_sms', 'Custom SMS Legacy' );
		update_option( 'snippen_template_booking_confirmation_email', 'Custom Email Legacy' );

		$migration = new Migration_2_20_0();
		$migration->up();

		$repository = new NotificationTemplateRepository();
		$templates  = $repository->get_all();

		$this->assertCount( 8, $templates );

		$user_sms    = $repository->find_by_connected_and_type( 'user_activation', 'sms' );
		$booking_em  = $repository->find_by_connected_and_type( 'booking_confirmation', 'email' );

		$this->assertNotNull( $user_sms );
		$this->assertEquals( 'Custom SMS Legacy', $user_sms->message );

		$this->assertNotNull( $booking_em );
		$this->assertEquals( 'Custom Email Legacy', $booking_em->message );

		$this->assertFalse( get_option( 'snippen_template_user_activation_sms' ) );
		$this->assertFalse( get_option( 'snippen_template_booking_confirmation_email' ) );
	}
}
