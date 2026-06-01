<?php

namespace SnippenBooking\Database\Migrations;

/**
 * Migration for version 1.19.0
 * Refactor availability rules from prices to time slots
 */
class Migration_1_19_0 {

	/**
	 * Run the migration
	 */
	public function up() {
		global $wpdb;

		$table_slots    = $wpdb->prefix . 'snippen_time_slots';
		$table_prices   = $wpdb->prefix . 'snippen_prices';
		$table_bookings = $wpdb->prefix . 'snippen_bookings';

		// 1. Check if columns exist in time slots table
		$column_exists = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_slots' AND COLUMN_NAME = 'days_of_week'" );
		if ( empty( $column_exists ) ) {
			$wpdb->query( "ALTER TABLE $table_slots ADD COLUMN days_of_week VARCHAR(20) DEFAULT NULL" );
			$wpdb->query( "ALTER TABLE $table_slots ADD COLUMN is_holiday TINYINT(1) DEFAULT 0" );
			$wpdb->query( "ALTER TABLE $table_slots ADD COLUMN date_start DATE DEFAULT NULL" );
			$wpdb->query( "ALTER TABLE $table_slots ADD COLUMN date_end DATE DEFAULT NULL" );
		}

		// 2. Migrate existing rules from prices
		// Find all slots
		$slots = $wpdb->get_results( "SELECT * FROM $table_slots WHERE deleted_at IS NULL" );

		// Instantiate holiday service
		$holiday_service = null;
		if ( class_exists( '\\SnippenBooking\\Service\\HolidayService' ) ) {
			$holiday_service = new \SnippenBooking\Service\HolidayService();
		}

		foreach ( $slots as $slot ) {
			// Get prices for this slot, ordered by priority desc so higher priority rules (like holiday) are processed later or earlier?
			// Processing order doesn't matter much for creating slots, but for matching bookings we might want to match highest priority rules first.
			$prices = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_prices WHERE slot_id = %d ORDER BY priority DESC, id ASC", $slot->id ) );

			if ( empty( $prices ) ) {
				continue;
			}

			// Group prices by unique availability rules
			$rule_groups = array();
			foreach ( $prices as $price ) {
				// We check if the price table still has these columns (in case migration is re-run)
				if ( ! isset( $price->days_of_week ) ) {
					continue; // Already migrated
				}

				$rule_hash = md5(
					serialize(
						array(
							'days_of_week' => $price->days_of_week,
							'is_holiday'   => $price->is_holiday,
							'date_start'   => $price->date_start,
							'date_end'     => $price->date_end,
						)
					)
				);

				if ( ! isset( $rule_groups[ $rule_hash ] ) ) {
					$rule_groups[ $rule_hash ] = array(
						'rules'  => array(
							'days_of_week' => $price->days_of_week,
							'is_holiday'   => $price->is_holiday,
							'date_start'   => $price->date_start,
							'date_end'     => $price->date_end,
						),
						'prices' => array(),
					);
				}
				$rule_groups[ $rule_hash ]['prices'][] = $price;
			}

			if ( empty( $rule_groups ) ) {
				continue;
			}

			$is_first = true;
			foreach ( $rule_groups as $hash => $group ) {
				$rules = $group['rules'];
				if ( $is_first ) {
					// Use the existing slot
					$new_slot_id = $slot->id;

					// Update the existing slot with these rules
					$wpdb->update(
						$table_slots,
						$rules,
						array( 'id' => $new_slot_id )
					);
					$is_first = false;
				} else {
					// Duplicate the slot
					$wpdb->insert(
						$table_slots,
						array(
							'name'               => $slot->name,
							'description'        => $slot->description,
							'start_time'         => $slot->start_time,
							'end_time'           => $slot->end_time,
							'cleanup_hours'      => $slot->cleanup_hours,
							'allow_multi_object' => $slot->allow_multi_object,
							'days_of_week'       => $rules['days_of_week'],
							'is_holiday'         => $rules['is_holiday'],
							'date_start'         => $rules['date_start'],
							'date_end'           => $rules['date_end'],
							'created_at'         => current_time( 'mysql' ),
							'modified_at'        => current_time( 'mysql' ),
						)
					);
					$new_slot_id = $wpdb->insert_id;

					// Update prices pointing to this group to point to the new slot
					$price_ids = array_map(
						function ( $p ) {
							return (int) $p->id;
						},
						$group['prices']
					);
					if ( ! empty( $price_ids ) ) {
						$price_ids_str = implode( ',', $price_ids );
						$wpdb->query( $wpdb->prepare( "UPDATE $table_prices SET slot_id = %d WHERE id IN ($price_ids_str)", $new_slot_id ) );
					}

					// Update bookings for this old slot that match the new rules
					$bookings = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_bookings WHERE slot_id = %d", $slot->id ) );

					foreach ( $bookings as $booking ) {
						$date        = $booking->booking_date;
						$is_holiday  = $holiday_service ? $holiday_service->isHoliday( $date ) : 0;
						$day_of_week = date( 'w', strtotime( $date ) );

						// Does the date match the new rules?
						$match = true;
						if ( $rules['is_holiday'] && ! $is_holiday ) {
							$match = false;
						}
						if ( $rules['days_of_week'] !== null && $rules['days_of_week'] !== '' ) {
							$allowed_days = explode( ',', $rules['days_of_week'] );
							if ( ! in_array( (string) $day_of_week, $allowed_days ) ) {
								$match = false;
							}
						}
						if ( $rules['date_start'] && $date < $rules['date_start'] ) {
							$match = false;
						}
						if ( $rules['date_end'] && $date > $rules['date_end'] ) {
							$match = false;
						}

						// Note: Bookings that matched the first rule group were left on the original slot.
						// Here, we reassign them if they match the higher priority (or subsequent) rule group.
						// This mimics the price priority since we grouped and ordered by priority.
						if ( $match ) {
							$wpdb->update(
								$table_bookings,
								array( 'slot_id' => $new_slot_id ),
								array( 'id' => $booking->id )
							);
						}
					}
				}
			}
		}

		// 3. Drop columns from prices
		$column_exists = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_prices' AND COLUMN_NAME = 'days_of_week'" );
		if ( ! empty( $column_exists ) ) {
			$wpdb->query( "ALTER TABLE $table_prices DROP COLUMN days_of_week" );
			$wpdb->query( "ALTER TABLE $table_prices DROP COLUMN is_holiday" );
			$wpdb->query( "ALTER TABLE $table_prices DROP COLUMN date_start" );
			$wpdb->query( "ALTER TABLE $table_prices DROP COLUMN date_end" );
		}
	}
}
