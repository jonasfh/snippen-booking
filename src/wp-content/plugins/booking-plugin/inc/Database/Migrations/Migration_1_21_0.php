<?php

namespace SnippenBooking\Database\Migrations;

/**
 * Migration for version 1.21.0
 * Refactor is_holiday to days_of_week and rename slots
 */
class Migration_1_21_0 {

	/**
	 * Run the migration
	 */
	public function up() {
		global $wpdb;

		$table_slots             = $wpdb->prefix . 'snippen_time_slots';
		$table_time_slot_objects = $wpdb->prefix . 'snippen_time_slot_booking_objects';
		$table_objects           = $wpdb->prefix . 'snippen_booking_objects';

		// 1. Rename slots that were incorrectly named during 1.20.0 migration
		$slots = $wpdb->get_results( "SELECT * FROM $table_slots WHERE deleted_at IS NULL" );

		foreach ( $slots as $slot ) {
			if ( in_array( $slot->name, array( 'Hele dagen', 'Formiddag', 'Ettermiddag' ) ) ) {
				// Find objects linked to this slot
				$object_ids = $wpdb->get_col( $wpdb->prepare( "SELECT booking_object_id FROM $table_time_slot_objects WHERE time_slot_id = %d ORDER BY booking_object_id ASC", $slot->id ) );

				if ( ! empty( $object_ids ) ) {
					// Get object names
					$in_clause    = implode( ',', array_fill( 0, count( $object_ids ), '%d' ) );
					$object_names = $wpdb->get_col( $wpdb->prepare( "SELECT name FROM $table_objects WHERE id IN ($in_clause)", ...$object_ids ) );

					$prefix = implode( ' + ', $object_names );
					if ( count( $object_names ) > 1 ) {
						$prefix = 'Hele området';
					}

					// Determine suffix
					$suffix     = '';
					$is_holiday = isset( $slot->is_holiday ) ? (int) $slot->is_holiday : 0;
					if ( $is_holiday === 1 ) {
						$suffix = ' (Helligdager og høytider)';
					} elseif ( $slot->days_of_week === '1,2,3,4' ) {
						$suffix = ' (Hverdag)';
					} elseif ( $slot->days_of_week === '5,6,0' ) {
						$suffix = ' (Helg)';
					}

					$new_name = $prefix . ' - ' . $slot->name . $suffix;

					$wpdb->update(
						$table_slots,
						array( 'name' => $new_name ),
						array( 'id' => $slot->id )
					);
				}
			}
		}

		// 2. Check if is_holiday column exists
		$column_exists = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_slots' AND COLUMN_NAME = 'is_holiday'" );
		if ( ! empty( $column_exists ) ) {
			// Update slots where is_holiday = 1 to have days_of_week = '7'
			$wpdb->query( "UPDATE $table_slots SET days_of_week = '7' WHERE is_holiday = 1" );

			// Drop the column
			$wpdb->query( "ALTER TABLE $table_slots DROP COLUMN is_holiday" );
		}
	}
}
