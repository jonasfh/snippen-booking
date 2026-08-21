<?php

namespace SnippenBooking\Database\Migrations;

/**
 * Migration 2.16.0
 * Add includes_wash_time column to booking_blocks and time_slots tables.
 */
class Migration_2_16_0 {

	public function up() {
		global $wpdb;

		$table_blocks = $wpdb->prefix . 'snippen_booking_blocks';
		$table_slots  = $wpdb->prefix . 'snippen_time_slots';

		// Add includes_wash_time to booking_blocks if it doesn't exist
		$column_exists_blocks = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_blocks' AND COLUMN_NAME = 'includes_wash_time'" );
		if ( empty( $column_exists_blocks ) ) {
			$wpdb->query( "ALTER TABLE $table_blocks ADD COLUMN includes_wash_time TINYINT(1) DEFAULT 0 AFTER is_active" );
		}

		// Add includes_wash_time to time_slots if it doesn't exist
		$column_exists_slots = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_slots' AND COLUMN_NAME = 'includes_wash_time'" );
		if ( empty( $column_exists_slots ) ) {
			$wpdb->query( "ALTER TABLE $table_slots ADD COLUMN includes_wash_time TINYINT(1) DEFAULT 0 AFTER is_active" );
		}
	}
}
