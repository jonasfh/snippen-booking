<?php

namespace SnippenBooking\Database\Migrations;

/**
 * Migration for version 1.5.0
 * Decouples time slots from specific booking objects:
 * - Deduplicates existing time slots to keep one canonical slot per name.
 * - Updates references in bookings and prices.
 * - Deletes duplicate slots.
 * - Drops the booking_object_id column and index from the time slots table.
 */
class Migration_1_5_0 {

	/**
	 * Run the migration
	 */
	public function up() {
		global $wpdb;

		$table_slots    = $wpdb->prefix . 'snippen_time_slots';
		$table_bookings = $wpdb->prefix . 'snippen_bookings';
		$table_prices   = $wpdb->prefix . 'snippen_prices';

		// 1. Check if the booking_object_id column exists
		$column_exists = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_slots' AND COLUMN_NAME = 'booking_object_id'" );
		if ( empty( $column_exists ) ) {
			// Migration already run or column does not exist
			return;
		}

		// 2. Fetch all slots to deduplicate
		$slots = $wpdb->get_results( "SELECT id, name FROM $table_slots ORDER BY id ASC" );

		$canonical_slots = array(); // name => canonical_id
		$duplicate_ids   = array();
		$mapping         = array(); // old_id => new_id

		foreach ( $slots as $slot ) {
			$name = trim( strtolower( $slot->name ) );
			if ( ! isset( $canonical_slots[ $name ] ) ) {
				$canonical_slots[ $name ] = $slot->id;
			} else {
				$duplicate_ids[]      = $slot->id;
				$mapping[ $slot->id ] = $canonical_slots[ $name ];
			}
		}

		// 3. Update existing bookings and prices pointing to duplicate slots
		foreach ( $mapping as $old_id => $new_id ) {
			$wpdb->update( $table_bookings, array( 'slot_id' => $new_id ), array( 'slot_id' => $old_id ) );
			$wpdb->update( $table_prices, array( 'slot_id' => $new_id ), array( 'slot_id' => $old_id ) );
		}

		// 4. Delete the duplicate slots
		if ( ! empty( $duplicate_ids ) ) {
			$ids_placeholder = implode( ',', array_map( 'intval', $duplicate_ids ) );
			$wpdb->query( "DELETE FROM $table_slots WHERE id IN ($ids_placeholder)" );
		}

		// 5. Drop the index and the booking_object_id column
		$index_exists = $wpdb->get_results( "SHOW INDEX FROM $table_slots WHERE Key_name = 'booking_object_id'" );
		if ( ! empty( $index_exists ) ) {
			$wpdb->query( "ALTER TABLE $table_slots DROP INDEX booking_object_id" );
		}

		$wpdb->query( "ALTER TABLE $table_slots DROP COLUMN booking_object_id" );
	}
}
