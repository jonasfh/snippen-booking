<?php

namespace SnippenBooking\Database\Migrations;

/**
 * Migration 2.10.0
 * Add is_active column to time slots and discount rules tables.
 */
class Migration_2_10_0 {

	public function up() {
		global $wpdb;

		$table_slots          = $wpdb->prefix . 'snippen_time_slots';
		$table_blocks         = $wpdb->prefix . 'snippen_booking_blocks';
		$table_discount_rules = $wpdb->prefix . 'snippen_discount_rules';

		// Add is_active to time_slots if it doesn't exist
		$column_exists = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_slots' AND COLUMN_NAME = 'is_active'" );
		if ( empty( $column_exists ) ) {
			$wpdb->query( "ALTER TABLE $table_slots ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER price_id" );
		}

		// Add is_active to booking_blocks if it doesn't exist
		$column_exists_blocks = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_blocks' AND COLUMN_NAME = 'is_active'" );
		if ( empty( $column_exists_blocks ) ) {
			$wpdb->query( "ALTER TABLE $table_blocks ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER sort_order" );
		}

		// Add is_active to discount_rules if it doesn't exist
		$column_exists_discount = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_discount_rules' AND COLUMN_NAME = 'is_active'" );
		if ( empty( $column_exists_discount ) ) {
			$wpdb->query( "ALTER TABLE $table_discount_rules ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER priority" );
		}
	}
}
