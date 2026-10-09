<?php
/**
 * Migration 2.45.0
 *
 * @package SnippenBooking\Database\Migrations
 */

namespace SnippenBooking\Database\Migrations;

/**
 * Migration 2.45.0
 * Adds door_code_updated_at column to snippen_bookings table.
 */
class Migration_2_45_0 {

	/**
	 * Run migration
	 */
	public function up() {
		global $wpdb;

		$table_bookings = $wpdb->prefix . 'snippen_bookings';

		// Add door_code_updated_at to snippen_bookings if not exists
		$col_updated_at = $wpdb->get_results(
			"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_bookings' AND COLUMN_NAME = 'door_code_updated_at'"
		);
		if ( empty( $col_updated_at ) ) {
			$wpdb->query( "ALTER TABLE $table_bookings ADD COLUMN door_code_updated_at DATETIME NULL AFTER door_code" );
		}
	}
}
