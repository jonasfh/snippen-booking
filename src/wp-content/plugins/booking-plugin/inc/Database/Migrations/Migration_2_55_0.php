<?php
/**
 * Migration 2.55.0
 *
 * @package SnippenBooking\Database\Migrations
 */

namespace SnippenBooking\Database\Migrations;

/**
 * Migration 2.55.0
 * Adds exclude_from_discounts column to wp_snippen_pricing_rules.
 */
class Migration_2_55_0 {

	/**
	 * Run migration
	 */
	public function up() {
		global $wpdb;

		$table_pricing_rules = $wpdb->prefix . 'snippen_pricing_rules';

		// Add exclude_from_discounts to snippen_pricing_rules if not exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$col_exists = $wpdb->get_results(
			"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_pricing_rules' AND COLUMN_NAME = 'exclude_from_discounts'"
		);
		if ( empty( $col_exists ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query(
				"ALTER TABLE $table_pricing_rules ADD COLUMN exclude_from_discounts TINYINT(1) DEFAULT 0 AFTER holiday_only"
			);
		}
	}
}
