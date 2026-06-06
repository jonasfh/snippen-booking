<?php

namespace SnippenBooking\Database\Migrations;

/**
 * Migration for version 1.25.0
 * Add is_active column to pricing rules table.
 */
class Migration_1_25_0 {

	/**
	 * Run the migration
	 */
	public function up() {
		global $wpdb;

		$table_pricing_rules = $wpdb->prefix . 'snippen_pricing_rules';

		// Add is_active to pricing rules if it doesn't exist
		$column_exists = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_pricing_rules' AND COLUMN_NAME = 'is_active'" );
		if ( empty( $column_exists ) ) {
			$wpdb->query( "ALTER TABLE $table_pricing_rules ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER priority" );
		}
	}
}
