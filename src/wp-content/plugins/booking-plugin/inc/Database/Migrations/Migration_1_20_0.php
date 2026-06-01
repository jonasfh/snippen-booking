<?php

namespace SnippenBooking\Database\Migrations;

/**
 * Migration for version 1.20.0
 * Replace allow_multi_object with time_slot_booking_objects junction table
 */
class Migration_1_20_0 {

	/**
	 * Run the migration
	 */
	public function up() {
		global $wpdb;

		$table_slots             = $wpdb->prefix . 'snippen_time_slots';
		$table_prices            = $wpdb->prefix . 'snippen_prices';
		$table_price_objects     = $wpdb->prefix . 'snippen_price_booking_objects';
		$table_time_slot_objects = $wpdb->prefix . 'snippen_time_slot_booking_objects';
		$table_bookings          = $wpdb->prefix . 'snippen_bookings';

		// 1. Create the new junction table if it doesn't exist (Install.php might have created it, but migrations run after or on update)
		$charset_collate       = $wpdb->get_charset_collate();
		$sql_time_slot_objects = "CREATE TABLE IF NOT EXISTS $table_time_slot_objects (
            id INT NOT NULL AUTO_INCREMENT,
            time_slot_id INT NOT NULL,
            booking_object_id INT NOT NULL,
            PRIMARY KEY  (id),
            KEY time_slot_id (time_slot_id),
            KEY booking_object_id (booking_object_id),
            UNIQUE KEY unique_time_slot_object (time_slot_id, booking_object_id)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_time_slot_objects );

		// 2. Check if we need to migrate data.
		// If price_booking_objects table exists, we migrate from it.
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_price_objects'" ) === $table_price_objects;

		if ( $table_exists ) {
			// Get all slots
			$slots = $wpdb->get_results( "SELECT * FROM $table_slots WHERE deleted_at IS NULL" );

			foreach ( $slots as $slot ) {
				// Get prices for this slot
				$prices = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_prices WHERE slot_id = %d", $slot->id ) );

				if ( empty( $prices ) ) {
					continue;
				}

				// Group by unique combinations of booking objects
				$combinations = array();

				foreach ( $prices as $price ) {
					$object_ids = $wpdb->get_col( $wpdb->prepare( "SELECT booking_object_id FROM $table_price_objects WHERE price_id = %d ORDER BY booking_object_id ASC", $price->id ) );

					if ( empty( $object_ids ) ) {
						continue;
					}

					$hash = implode( ',', $object_ids );
					if ( ! isset( $combinations[ $hash ] ) ) {
						$combinations[ $hash ] = array(
							'object_ids' => $object_ids,
							'prices'     => array(),
						);
					}
					$combinations[ $hash ]['prices'][] = $price;
				}

				if ( empty( $combinations ) ) {
					continue;
				}

				$is_first = true;
				foreach ( $combinations as $hash => $group ) {
					$object_ids   = $group['object_ids'];
					$prices_group = $group['prices'];

					if ( $is_first ) {
						$new_slot_id = $slot->id;
						$is_first    = false;
					} else {
						// Create a new slot for this combination
						$wpdb->insert(
							$table_slots,
							array(
								'name'          => $slot->name,
								'description'   => $slot->description,
								'start_time'    => $slot->start_time,
								'end_time'      => $slot->end_time,
								'cleanup_hours' => $slot->cleanup_hours,
								'days_of_week'  => isset( $slot->days_of_week ) ? $slot->days_of_week : null,
								'is_holiday'    => isset( $slot->is_holiday ) ? $slot->is_holiday : 0,
								'date_start'    => isset( $slot->date_start ) ? $slot->date_start : null,
								'date_end'      => isset( $slot->date_end ) ? $slot->date_end : null,
								'created_at'    => current_time( 'mysql' ),
								'modified_at'   => current_time( 'mysql' ),
							)
						);
						$new_slot_id = $wpdb->insert_id;

						// Update these prices to point to the new slot
						$price_ids = array_map(
							function ( $p ) {
								return (int) $p->id;
							},
							$prices_group
						);
						if ( ! empty( $price_ids ) ) {
							$price_ids_str = implode( ',', $price_ids );
							$wpdb->query( $wpdb->prepare( "UPDATE $table_prices SET slot_id = %d WHERE id IN ($price_ids_str)", $new_slot_id ) );
						}

						$table_booking_objects = $wpdb->prefix . 'snippen_bookings_booking_objects';

						// Get all bookings using the old slot
						$bookings = $wpdb->get_results( $wpdb->prepare( "SELECT id FROM $table_bookings WHERE slot_id = %d", $slot->id ) );

						foreach ( $bookings as $booking ) {
							$b_object_ids = $wpdb->get_col( $wpdb->prepare( "SELECT booking_object_id FROM $table_booking_objects WHERE booking_id = %d ORDER BY booking_object_id ASC", $booking->id ) );
							$b_hash       = implode( ',', $b_object_ids );

							if ( $b_hash === $hash ) {
								$wpdb->update(
									$table_bookings,
									array( 'slot_id' => $new_slot_id ),
									array( 'id' => $booking->id )
								);
							}
						}
					}

					// Insert into the new time_slot_booking_objects table
					foreach ( $object_ids as $obj_id ) {
						$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_time_slot_objects WHERE time_slot_id = %d AND booking_object_id = %d", $new_slot_id, $obj_id ) );
						if ( ! $exists ) {
							$wpdb->insert(
								$table_time_slot_objects,
								array(
									'time_slot_id'      => $new_slot_id,
									'booking_object_id' => $obj_id,
								)
							);
						}
					}
				}
			}

			// Drop the old table
			$wpdb->query( "DROP TABLE IF EXISTS $table_price_objects" );
		}

		// 3. Drop allow_multi_object from time_slots
		$column_exists = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_slots' AND COLUMN_NAME = 'allow_multi_object'" );
		if ( ! empty( $column_exists ) ) {
			$wpdb->query( "ALTER TABLE $table_slots DROP COLUMN allow_multi_object" );
		}
	}
}
