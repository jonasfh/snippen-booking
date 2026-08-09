<?php

namespace SnippenBooking\Database\Migrations;

/**
 * Migration 2.9.0
 * Adds booking_snapshot column to wp_snippen_bookings and backfills existing historical bookings with JSON snapshot.
 */
class Migration_2_9_0 {

	public function up() {
		global $wpdb;

		$table_bookings        = $wpdb->prefix . 'snippen_bookings';
		$table_booking_blocks  = $wpdb->prefix . 'snippen_booking_booking_blocks';
		$table_blocks          = $wpdb->prefix . 'snippen_booking_blocks';
		$table_booking_objects = $wpdb->prefix . 'snippen_booking_booking_objects';
		$table_legacy_objects  = $wpdb->prefix . 'snippen_bookings_booking_objects';
		$table_objects         = $wpdb->prefix . 'snippen_booking_objects';
		$table_slots           = $wpdb->prefix . 'snippen_time_slots';

		// 1. Add booking_snapshot column if it doesn't exist
		$column_exists = $wpdb->get_results( "SHOW COLUMNS FROM `$table_bookings` LIKE 'booking_snapshot'" );
		if ( empty( $column_exists ) ) {
			$wpdb->query( "ALTER TABLE `$table_bookings` ADD COLUMN `booking_snapshot` LONGTEXT NULL AFTER `door_code`" );
		}

		// 2. Fetch all bookings that don't have a booking_snapshot
		$bookings = $wpdb->get_results( "SELECT * FROM `$table_bookings` WHERE `booking_snapshot` IS NULL OR `booking_snapshot` = ''" );

		foreach ( $bookings as $booking ) {
			// Fetch objects (check both table_booking_objects and legacy table_legacy_objects)
			$object_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DISTINCT o.id, o.name 
					 FROM `$table_objects` o
					 JOIN (
					 	SELECT booking_id, booking_object_id FROM `$table_booking_objects`
					 	UNION
					 	SELECT booking_id, booking_object_id FROM `$table_legacy_objects`
					 ) bo ON o.id = bo.booking_object_id
					 WHERE bo.booking_id = %d",
					$booking->id
				)
			);

			$objects = array();
			foreach ( $object_rows as $obj ) {
				$objects[] = array(
					'id'   => (int) $obj->id,
					'name' => $obj->name,
				);
			}

			// Fetch blocks
			$block_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT b.id, b.name, b.start_time, b.end_time
					 FROM `$table_blocks` b
					 JOIN `$table_booking_blocks` bb ON b.id = bb.booking_block_id
					 WHERE bb.booking_id = %d",
					$booking->id
				)
			);

			$blocks     = array();
			$min_start  = null;
			$max_end    = null;

			foreach ( $block_rows as $b ) {
				$blocks[] = array(
					'id'         => (int) $b->id,
					'name'       => $b->name,
					'start_time' => $b->start_time,
					'end_time'   => $b->end_time,
				);

				if ( $min_start === null || $b->start_time < $min_start ) {
					$min_start = $b->start_time;
				}
				if ( $max_end === null || $b->end_time > $max_end ) {
					$max_end = $b->end_time;
				}
			}

			// Fallback to time_slots if no booking_blocks found
			if ( empty( $blocks ) && ! empty( $booking->slot_id ) ) {
				$slot = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT id, name, start_time, end_time FROM `$table_slots` WHERE id = %d",
						$booking->slot_id
					)
				);
				if ( $slot ) {
					$min_start = $slot->start_time;
					$max_end   = $slot->end_time;
					$blocks[]  = array(
						'id'         => (int) $slot->id,
						'name'       => $slot->name,
						'start_time' => $slot->start_time,
						'end_time'   => $slot->end_time,
					);
				}
			}

			$time_range_formatted = '';
			if ( $min_start && $max_end ) {
				$time_range_formatted = date_i18n( 'H:i', strtotime( $min_start ) ) . ' - ' . date_i18n( 'H:i', strtotime( $max_end ) );
			}

			$snapshot = array(
				'start_time'           => $min_start ?: '',
				'end_time'             => $max_end ?: '',
				'time_range_formatted' => $time_range_formatted,
				'objects'              => $objects,
				'blocks'               => $blocks,
				'price'                => (float) $booking->price,
				'discount_amount'      => (float) $booking->discount_amount,
				'created_at'           => $booking->created_at,
			);

			$wpdb->update(
				$table_bookings,
				array( 'booking_snapshot' => wp_json_encode( $snapshot ) ),
				array( 'id' => $booking->id )
			);
		}
	}
}
