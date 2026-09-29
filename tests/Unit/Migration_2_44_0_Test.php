<?php
/**
 * Unit Test for Migration 2.44.0
 *
 * @package SnippenBooking\Tests\Unit
 */

namespace SnippenBooking\Tests\Unit;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Database\Migrations\Migration_2_44_0;

/**
 * Class Migration_2_44_0_Test
 */
class Migration_2_44_0_Test extends TestCase {

	/**
	 * Test migration creates table and backfills initial snapshot (revision 1) for unmigrated bookings
	 */
	public function test_migration_2_44_0_creates_table_and_backfills_snapshots() {
		global $wpdb;

		$table_bookings  = $wpdb->prefix . 'snippen_bookings';
		$table_snapshots = $wpdb->prefix . 'snippen_booking_snapshots';

		// Create a test booking without any snapshot record
		$wpdb->insert(
			$table_bookings,
			array(
				'user_id'          => 1,
				'booking_date'     => '2026-10-15',
				'customer_name'    => 'Migrasjon Test',
				'customer_email'   => 'migrasjon@example.com',
				'customer_phone'   => '99887766',
				'booking_snapshot' => wp_json_encode( array( 'start_time' => '10:00:00', 'end_time' => '14:00:00' ) ),
				'created_at'       => current_time( 'mysql' ),
				'modified_at'      => current_time( 'mysql' ),
			)
		);
		$booking_id = (int) $wpdb->insert_id;

		// Ensure no snapshots exist for this booking before migration
		$wpdb->delete( $table_snapshots, array( 'booking_id' => $booking_id ) );

		$migration = new Migration_2_44_0();
		$migration->up();

		// Verify table exists
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_snapshots'" );
		$this->assertEquals( $table_snapshots, $table_exists );

		// Verify snapshot was backfilled with revision 1
		$snapshot_row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table_snapshots WHERE booking_id = %d", $booking_id )
		);

		$this->assertNotNull( $snapshot_row );
		$this->assertEquals( 1, (int) $snapshot_row->revision );
		$this->assertNotEmpty( $snapshot_row->snapshot );
		$this->assertStringContainsString( '10:00:00', $snapshot_row->snapshot );
	}
}
