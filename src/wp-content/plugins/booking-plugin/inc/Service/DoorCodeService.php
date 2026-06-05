<?php

namespace SnippenBooking\Service;

/**
 * Service to manage door codes for booking objects and bookings
 */
class DoorCodeService {

	/**
	 * Check if door code system is enabled
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return 'yes' === get_option( 'snippen_enable_door_code', 'no' );
	}

	/**
	 * Get configured x hours before booking start
	 */
	public static function get_hours_before() {
		return intval( get_option( 'snippen_door_code_hours_before', 24 ) );
	}

	/**
	 * Get configured y hours after booking end
	 */
	public static function get_hours_after() {
		return intval( get_option( 'snippen_door_code_hours_after', 2 ) );
	}

	/**
	 * Check if a booking is currently in the active door code window
	 *
	 * @param object $booking Booking database row with start_time and end_time
	 * @return bool
	 */
	public static function is_in_window( $booking ) {
		if ( ! self::is_enabled() ) {
			return false;
		}

		if ( ! isset( $booking->booking_date ) || ! isset( $booking->start_time ) || ! isset( $booking->end_time ) ) {
			return false;
		}

		$hours_before = self::get_hours_before();
		$hours_after  = self::get_hours_after();

		$now   = new \DateTime( current_time( 'mysql' ) );
		$start = new \DateTime( $booking->booking_date . ' ' . $booking->start_time );
		$end   = new \DateTime( $booking->booking_date . ' ' . $booking->end_time );

		if ( $end < $start ) {
			$end->modify( '+1 day' );
		}

		$start_window = clone $start;
		$start_window->modify( "-{$hours_before} hours" );

		$end_window = clone $end;
		$end_window->modify( "+{$hours_after} hours" );

		return ( $now >= $start_window && $now <= $end_window );
	}

	/**
	 * Update door code for all active approaching bookings
	 */
	public static function update_approaching_bookings_door_codes() {
		global $wpdb;
		$table_bookings = $wpdb->prefix . 'snippen_bookings';

		if ( ! self::is_enabled() ) {
			$wpdb->query( "UPDATE $table_bookings SET door_code = NULL WHERE door_code IS NOT NULL" );
			return;
		}

		$table_blocks         = $wpdb->prefix . 'snippen_booking_blocks';
		$table_booking_blocks = $wpdb->prefix . 'snippen_booking_booking_blocks';

		// Fetch all pending or confirmed bookings that are active
		$bookings = $wpdb->get_results(
			"SELECT b.*, MIN(s.start_time) as start_time, MAX(s.end_time) as end_time 
			 FROM $table_bookings b
			 JOIN $table_booking_blocks bb ON b.id = bb.booking_id
			 JOIN $table_blocks s ON bb.booking_block_id = s.id
			 WHERE b.deleted_at IS NULL AND b.status IN ('pending', 'confirmed')
			 GROUP BY b.id"
		);

		foreach ( $bookings as $booking ) {
			self::sync_booking_door_code( $booking );
		}
	}

	/**
	 * Sync a single booking's door code based on its window and object(s) door code
	 *
	 * @param object $booking Booking object
	 */
	public static function sync_booking_door_code( $booking ) {
		global $wpdb;
		$table_bookings = $wpdb->prefix . 'snippen_bookings';
		$table_junction = $wpdb->prefix . 'snippen_booking_booking_objects';
		$table_objects  = $wpdb->prefix . 'snippen_booking_objects';

		if ( ! self::is_enabled() ) {
			if ( ! empty( $booking->door_code ) ) {
				$wpdb->update(
					$table_bookings,
					array( 'door_code' => null ),
					array( 'id' => $booking->id )
				);
				$booking->door_code = null;
			}
			return;
		}

		// Hydrate start_time and end_time if they are not already set
		if ( ! isset( $booking->start_time ) || ! isset( $booking->end_time ) ) {
			$table_blocks         = $wpdb->prefix . 'snippen_booking_blocks';
			$table_booking_blocks = $wpdb->prefix . 'snippen_booking_booking_blocks';
			$times                = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT MIN(s.start_time) as start_time, MAX(s.end_time) as end_time 
					 FROM $table_booking_blocks bb
					 JOIN $table_blocks s ON bb.booking_block_id = s.id
					 WHERE bb.booking_id = %d",
					$booking->id
				)
			);
			if ( $times ) {
				$booking->start_time = $times->start_time;
				$booking->end_time   = $times->end_time;
			}
		}

		if ( self::is_in_window( $booking ) && in_array( $booking->status, array( 'pending', 'confirmed' ), true ) ) {
			// Find door codes from associated booking objects
			$door_codes = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT o.door_code 
					 FROM $table_junction bo 
					 JOIN $table_objects o ON bo.booking_object_id = o.id 
					 WHERE bo.booking_id = %d AND o.deleted_at IS NULL AND o.door_code IS NOT NULL AND o.door_code != ''",
					$booking->id
				)
			);

			$unique_door_codes = array_unique( array_filter( array_map( 'trim', $door_codes ) ) );
			$active_door_code  = ! empty( $unique_door_codes ) ? implode( ', ', $unique_door_codes ) : '';

			// If the booking's door code in the database doesn't match the active door code, update it!
			if ( $booking->door_code !== $active_door_code ) {
				$wpdb->update(
					$table_bookings,
					array( 'door_code' => $active_door_code ),
					array( 'id' => $booking->id )
				);
				$booking->door_code = $active_door_code; // Update in-memory reference too
			}
		} else {
			// If not in window or cancelled, it should be empty in the database
			if ( ! empty( $booking->door_code ) ) {
				$wpdb->update(
					$table_bookings,
					array( 'door_code' => null ),
					array( 'id' => $booking->id )
				);
				$booking->door_code = null;
			}
		}
	}

	/**
	 * When a booking object's door code is updated, update all relevant bookings immediately
	 *
	 * @param int    $booking_object_id
	 * @param string $new_door_code
	 */
	public static function handle_object_door_code_change( $booking_object_id, $new_door_code ) {
		if ( ! self::is_enabled() ) {
			return;
		}

		global $wpdb;
		$table_bookings       = $wpdb->prefix . 'snippen_bookings';
		$table_junction       = $wpdb->prefix . 'snippen_booking_booking_objects';
		$table_blocks         = $wpdb->prefix . 'snippen_booking_blocks';
		$table_booking_blocks = $wpdb->prefix . 'snippen_booking_booking_blocks';

		// Find all upcoming bookings (where booking_date >= today) associated with this booking object
		$today    = current_time( 'Y-m-d' );
		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.*, MIN(s.start_time) as start_time, MAX(s.end_time) as end_time 
				 FROM $table_bookings b
				 JOIN $table_junction bo ON b.id = bo.booking_id
				 JOIN $table_booking_blocks bb ON b.id = bb.booking_id
				 JOIN $table_blocks s ON bb.booking_block_id = s.id
				 WHERE bo.booking_object_id = %d 
				   AND b.booking_date >= %s 
				   AND b.deleted_at IS NULL 
				   AND b.status IN ('pending', 'confirmed')
				 GROUP BY b.id",
				$booking_object_id,
				$today
			)
		);

		foreach ( $bookings as $booking ) {
			// Sync this booking
			self::sync_booking_door_code( $booking );
		}
	}
}
