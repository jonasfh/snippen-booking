<?php

namespace SnippenBooking\Database\Migrations;

/**
 * Migration for version 1.22.0
 * Invert Price to Time Slot relationship (1 Price -> Many Time Slots)
 */
class Migration_1_22_0 {

	/**
	 * Run the migration
	 */
	public function up() {
		global $wpdb;

		$table_slots  = $wpdb->prefix . 'snippen_time_slots';
		$table_prices = $wpdb->prefix . 'snippen_prices';

		// 1. Add price_id to time_slots if it doesn't exist
		$column_exists = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_slots' AND COLUMN_NAME = 'price_id'" );
		if ( empty( $column_exists ) ) {
			$wpdb->query( "ALTER TABLE $table_slots ADD COLUMN price_id INT DEFAULT NULL" );
			$wpdb->query( "ALTER TABLE $table_slots ADD INDEX price_id (price_id)" );
		}

		// 2. Check if slot_id exists on prices to run migration
		$slot_id_exists = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_prices' AND COLUMN_NAME = 'slot_id'" );
		if ( ! empty( $slot_id_exists ) ) {

			// 3. Migrate data: Set time_slots.price_id = prices.id where prices.slot_id = time_slots.id
			// If multiple prices point to the same slot (via priority), the one with highest priority should ideally win,
			// but since it was 1-to-1 in practice, a direct update is usually fine.
			// To be safe, we order by priority so the highest priority price_id is set last and overrides.
			// MySQL UPDATE with JOIN doesn't guarantee order if multiple match, so we'll do it via PHP.

			$prices = $wpdb->get_results( "SELECT id, slot_id FROM $table_prices ORDER BY priority ASC" );
			foreach ( $prices as $price ) {
				$wpdb->update(
					$table_slots,
					array( 'price_id' => $price->id ),
					array( 'id' => $price->slot_id )
				);
			}

			// 4. Drop slot_id column from prices
			// First, try to drop foreign keys or indexes if they exist (usually just an index).
			$wpdb->query( "ALTER TABLE $table_prices DROP INDEX slot_id" );
			$wpdb->query( "ALTER TABLE $table_prices DROP COLUMN slot_id" );
		}
	}
}
