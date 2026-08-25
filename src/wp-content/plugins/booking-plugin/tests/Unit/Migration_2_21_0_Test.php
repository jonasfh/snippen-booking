<?php
/**
 * Migration 2.21.0 Unit Test
 *
 * @package SnippenBooking\Tests\Unit
 */

namespace SnippenBooking\Tests\Unit;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Database\Migrations\Migration_2_21_0;
use SnippenBooking\Database\Repository\NotificationTemplateRepository;

/**
 * Class Migration_2_21_0_Test
 */
class Migration_2_21_0_Test extends TestCase {

	/**
	 * Test up method creates table and seeds default payment reminder templates
	 */
	public function test_migration_up_creates_table_and_seeds_defaults() {
		global $wpdb;

		$migration = new Migration_2_21_0();
		$migration->up();

		$table = $wpdb->prefix . 'snippen_booking_payment_reminders';
		$this->assertEquals( $table, $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) );

		$repository = new NotificationTemplateRepository();
		$email_tpl  = $repository->find_by_connected_and_type( 'payment-reminder', 'email' );
		$sms_tpl    = $repository->find_by_connected_and_type( 'payment-reminder', 'sms' );

		$this->assertNotNull( $email_tpl );
		$this->assertNotNull( $sms_tpl );
		$this->assertEquals( 'email', $email_tpl->type );
		$this->assertEquals( 'sms', $sms_tpl->type );
	}
}
