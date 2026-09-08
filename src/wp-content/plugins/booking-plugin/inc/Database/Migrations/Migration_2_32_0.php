<?php
/**
 * Migration 2.32.0
 *
 * @package SnippenBooking\Database\Migrations
 */

namespace SnippenBooking\Database\Migrations;

/**
 * Migration 2.32.0
 * Adds booking_type and rejection_reason to bookings, and supports_cleaning to booking blocks.
 */
class Migration_2_32_0 {

	/**
	 * Run migration
	 */
	public function up() {
		global $wpdb;

		$table_bookings = $wpdb->prefix . 'snippen_bookings';
		$table_blocks   = $wpdb->prefix . 'snippen_booking_blocks';

		// Add booking_type to snippen_bookings if not exists
		$col_booking_type = $wpdb->get_results(
			"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_bookings' AND COLUMN_NAME = 'booking_type'"
		);
		if ( empty( $col_booking_type ) ) {
			$wpdb->query( "ALTER TABLE $table_bookings ADD COLUMN booking_type VARCHAR(20) DEFAULT 'private' AFTER door_code" );
		}

		// Add rejection_reason to snippen_bookings if not exists
		$col_rejection_reason = $wpdb->get_results(
			"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_bookings' AND COLUMN_NAME = 'rejection_reason'"
		);
		if ( empty( $col_rejection_reason ) ) {
			$wpdb->query( "ALTER TABLE $table_bookings ADD COLUMN rejection_reason TEXT NULL AFTER booking_type" );
		}

		// Add supports_cleaning to snippen_booking_blocks if not exists
		$col_supports_cleaning = $wpdb->get_results(
			"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_blocks' AND COLUMN_NAME = 'supports_cleaning'"
		);
		if ( empty( $col_supports_cleaning ) ) {
			$wpdb->query( "ALTER TABLE $table_blocks ADD COLUMN supports_cleaning TINYINT(1) DEFAULT 0 AFTER custom_instructions" );
		}
	}
}
