<?php

namespace SnippenBooking\Service;

/**
 * Service for calculating availability and detecting overlaps
 */
class AvailabilityService {

	/**
	 * Check if a specific slot is available for a given date and object
	 *
	 * @param int    $objectId
	 * @param string $date YYYY-MM-DD
	 * @param int    $slotId
	 * @return bool
	 */
	public function isSlotAvailable( $objectId, $date, $slotId ) {
		global $wpdb;

		$table_slots = $wpdb->prefix . 'snippen_time_slots';
		$slot        = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_slots WHERE id = %d",
				$slotId
			)
		);

		if ( ! $slot ) {
			return false;
		}

		$proposed_window = $this->calculateWindow( $date, $slot->start_time, $slot->end_time, $slot->cleanup_hours );

		// Fetch bookings that could possibly overlap (2 days before to 2 days after)
		$table_bookings = $wpdb->prefix . 'snippen_bookings';
		$buffer_start   = date( 'Y-m-d', strtotime( $date . ' - 2 days' ) );
		$buffer_end     = date( 'Y-m-d', strtotime( $date . ' + 2 days' ) );

		$table_booking_objects = $wpdb->prefix . 'snippen_bookings_booking_objects';
		$existing_bookings     = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.booking_date, s.start_time, s.end_time, s.cleanup_hours 
             FROM $table_bookings b
             JOIN $table_slots s ON b.slot_id = s.id
             JOIN $table_booking_objects bbo ON b.id = bbo.booking_id
             WHERE bbo.booking_object_id = %d 
             AND b.booking_date BETWEEN %s AND %s 
             AND b.deleted_at IS NULL
             AND b.status != 'cancelled'",
				$objectId,
				$buffer_start,
				$buffer_end
			)
		);

		foreach ( $existing_bookings as $booking ) {
			$booked_window = $this->calculateWindow( $booking->booking_date, $booking->start_time, $booking->end_time, $booking->cleanup_hours );

			$overlap = $this->isOverlapping( $proposed_window, $booked_window );
			if ( $overlap ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get a list of unavailable slots for a date range
	 * Returns an array keyed by date, containing arrays of slot IDs
	 *
	 * @param int    $objectId
	 * @param string $startDate YYYY-MM-DD
	 * @param string $endDate YYYY-MM-DD
	 * @return array
	 */
	public function getUnavailableSlots( $objectId, $startDate, $endDate ) {
		global $wpdb;
		$table_slots    = $wpdb->prefix . 'snippen_time_slots';
		$table_bookings = $wpdb->prefix . 'snippen_bookings';

		$all_slots = $wpdb->get_results(
			"SELECT * FROM $table_slots WHERE deleted_at IS NULL"
		);

		// Fetch all bookings that could affect this range (including those from before and after)
		$buffer_start = date( 'Y-m-d', strtotime( $startDate . ' - 2 days' ) );
		$buffer_end   = date( 'Y-m-d', strtotime( $endDate . ' + 2 days' ) );

		$table_booking_objects = $wpdb->prefix . 'snippen_bookings_booking_objects';
		$existing_bookings     = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.booking_date, b.slot_id, s.start_time, s.end_time, s.cleanup_hours 
             FROM $table_bookings b
             JOIN $table_slots s ON b.slot_id = s.id
             JOIN $table_booking_objects bbo ON b.id = bbo.booking_id
             WHERE bbo.booking_object_id = %d 
             AND b.booking_date BETWEEN %s AND %s 
             AND b.deleted_at IS NULL
             AND b.status != 'cancelled'",
				$objectId,
				$buffer_start,
				$buffer_end
			)
		);

		$unavailable = array();

		$current = new \DateTime( $startDate );
		$last    = new \DateTime( $endDate );

		while ( $current <= $last ) {
			$date_str                 = $current->format( 'Y-m-d' );
			$unavailable[ $date_str ] = array();

			foreach ( $all_slots as $slot ) {
				$proposed_window = $this->calculateWindow( $date_str, $slot->start_time, $slot->end_time, $slot->cleanup_hours );

				foreach ( $existing_bookings as $booking ) {
					$booked_window = $this->calculateWindow( $booking->booking_date, $booking->start_time, $booking->end_time, $booking->cleanup_hours );

					$overlap = $this->isOverlapping( $proposed_window, $booked_window );
					if ( $overlap ) {
						$unavailable[ $date_str ][] = (int) $slot->id;
						break; // Already unavailable, no need to check other bookings
					}
				}
			}
			$current->modify( '+1 day' );
		}

		return $unavailable;
	}

	/**
	 * Calculate start and end DateTime for a slot + cleanup
	 */
	private function calculateWindow( $date, $start_time, $end_time, $cleanup_hours ) {
		$start = new \DateTime( $date . ' ' . $start_time );
		$end   = new \DateTime( $date . ' ' . $end_time );

		if ( $cleanup_hours > 0 ) {
			$end->modify( '+' . (int) $cleanup_hours . ' hours' );
		}

		// Trekk fra 1 sekund for å unngå at tidsluker som går "kant i kant" overlapper
		$end->modify( '-1 second' );

		return array(
			'start' => $start,
			'end'   => $end,
		);
	}

	/**
	 * Check if two windows overlap
	 */
	private function isOverlapping( $win1, $win2 ) {
		return ( $win1['start'] < $win2['end'] ) && ( $win2['start'] < $win1['end'] );
	}

	/**
	 * Check if a time slot is applicable for a given date
	 *
	 * @param object $slot DB slot object (needs days_of_week, date_start, date_end)
	 * @param string $date_str YYYY-MM-DD
	 * @param bool   $is_holiday
	 * @return bool
	 */
	public function isSlotApplicable( $slot, $date_str, $is_holiday ) {
		$match = true;
		$day_of_week = date( 'w', strtotime( $date_str ) );

		if ( $slot->days_of_week !== null && $slot->days_of_week !== '' ) {
			$allowed_days = explode( ',', $slot->days_of_week );
			if ( $is_holiday ) {
				$is_day_match = in_array( '7', $allowed_days );
			} else {
				$is_day_match = in_array( (string) $day_of_week, $allowed_days );
			}

			if ( ! $is_day_match ) {
				$match = false;
			}
		}

		if ( !empty($slot->date_start) && $date_str < $slot->date_start ) {
			$match = false;
		}

		if ( !empty($slot->date_end) && $date_str > $slot->date_end ) {
			$match = false;
		}

		return $match;
	}
}
