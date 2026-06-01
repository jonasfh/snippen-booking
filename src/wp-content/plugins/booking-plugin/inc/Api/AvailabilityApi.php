<?php

namespace SnippenBooking\Api;

use SnippenBooking\Service\AvailabilityService;
use SnippenBooking\Service\PricingService;
use SnippenBooking\Helper\Capabilities;

/**
 * Handles AJAX availability requests
 */
class AvailabilityApi {

	/**
	 * Register AJAX handlers
	 */
	public static function register() {
		add_action( 'wp_ajax_snippen_get_availability', array( __CLASS__, 'get_availability' ) );
		add_action( 'wp_ajax_nopriv_snippen_get_availability', array( __CLASS__, 'get_availability' ) );
	}

	/**
	 * Get availability for a given object and week
	 */
	public static function get_availability() {
		// Verify nonce for logged-in users (nopriv users don't have valid nonces)
		if ( is_user_logged_in() ) {
			check_ajax_referer( 'snippen_booking_nonce', 'nonce', false );
		}

		global $wpdb;

		$object_ids_raw = isset( $_GET['object_id'] ) ? $_GET['object_id'] : array();
		if ( ! is_array( $object_ids_raw ) ) {
			$decoded = json_decode( stripslashes( $object_ids_raw ), true );
			if ( is_array( $decoded ) ) {
				$object_ids_raw = $decoded;
			} else {
				$object_ids_raw = explode( ',', $object_ids_raw );
			}
		}

		$object_ids = array_map( 'intval', $object_ids_raw );
		$object_ids = array_filter( $object_ids );

		$start_date = sanitize_text_field( $_GET['start_date'] ?? '' ); // YYYY-MM-DD

		if ( empty( $object_ids ) || empty( $start_date ) ) {
			wp_send_json_error( array( 'message' => __( 'Manglende påkrevde parametere', 'snippen-booking' ) ) );
		}

		// Lead time (N days)
		$offset_days = 0;

		// Calculate end date (7 days)
		$end_date = date( 'Y-m-d', strtotime( $start_date . ' + 6 days' ) );

		$table_slots    = $wpdb->prefix . 'snippen_time_slots';
		$table_bookings = $wpdb->prefix . 'snippen_bookings';

		// Get slots globally
		$all_slots = $wpdb->get_results(
			"SELECT id, name, description, start_time, end_time, cleanup_hours, allow_multi_object, days_of_week, is_holiday, date_start, date_end 
             FROM $table_slots 
             WHERE deleted_at IS NULL"
		);

		$slots           = array();
		$is_multi_object = count( $object_ids ) > 1;

		foreach ( $all_slots as $slot ) {
			// RESTRICTION: For multi-object bookings, only allow slots flagged as such
			if ( $is_multi_object && ! (int) $slot->allow_multi_object ) {
				continue;
			}
			$slots[] = $slot;
		}

		// Get all bookings for the range with slot details (for UI display)
		$query_args            = array_merge( $object_ids, array( $start_date, $end_date ) );
		$in_clause             = implode( ',', array_fill( 0, count( $object_ids ), '%d' ) );
		$table_booking_objects = $wpdb->prefix . 'snippen_bookings_booking_objects';
		$table_objects         = $wpdb->prefix . 'snippen_booking_objects';

		$is_admin      = Capabilities::can_manage_bookings();
		$select_fields = 'b.booking_date, b.slot_id, s.name as slot_name, s.start_time, s.end_time, s.cleanup_hours';
		if ( $is_admin ) {
			$select_fields .= ', b.customer_name, b.customer_email, b.customer_phone, b.description as booking_description';
			$select_fields .= ", (SELECT GROUP_CONCAT(o.name SEPARATOR ', ') FROM $table_booking_objects bo JOIN $table_objects o ON bo.booking_object_id = o.id WHERE bo.booking_id = b.id) as object_names";
		}

		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT $select_fields
             FROM $table_bookings b
             JOIN $table_slots s ON b.slot_id = s.id
             WHERE b.id IN (SELECT booking_id FROM $table_booking_objects WHERE booking_object_id IN ($in_clause))
             AND b.booking_date BETWEEN %s AND %s 
             AND b.deleted_at IS NULL",
				...$query_args
			)
		);

		// Organize bookings by date
		$booked_details = array();
		foreach ( $bookings as $booking ) {
			$details = array(
				'slot_id'       => (int) $booking->slot_id,
				'slot_name'     => $booking->slot_name,
				'start_time'    => $booking->start_time,
				'end_time'      => $booking->end_time,
				'cleanup_hours' => (int) $booking->cleanup_hours,
			);

			if ( $is_admin ) {
				$details['customer_name']  = $booking->customer_name;
				$details['customer_email'] = $booking->customer_email;
				$details['customer_phone'] = $booking->customer_phone;
				$details['description']    = $booking->booking_description;
				$details['object_names']   = $booking->object_names;
			}

			$booked_details[ $booking->booking_date ][] = $details;
		}

		// Get advanced availability (blocked by cleanup)
		$availability_service = new AvailabilityService();
		$unavailable_slots    = array();

		foreach ( $object_ids as $obj_id ) {
			$unavail = $availability_service->getUnavailableSlots( $obj_id, $start_date, $end_date );
			// Merge into overall unavailable map
			foreach ( $unavail as $date => $slot_ids ) {
				if ( ! isset( $unavailable_slots[ $date ] ) ) {
					$unavailable_slots[ $date ] = array();
				}
				$unavailable_slots[ $date ] = array_merge( $unavailable_slots[ $date ], $slot_ids );
			}
		}

		// Calculate prices and applicability for each slot on each day
		$pricing_service  = new PricingService();
		$holiday_service  = new \SnippenBooking\Service\HolidayService();
		$prices_by_date   = array();
		$applicable_slots = array();

		$current = new \DateTime( $start_date );
		$last    = new \DateTime( $end_date );

		while ( $current <= $last ) {
			$date_str                      = $current->format( 'Y-m-d' );
			$prices_by_date[ $date_str ]   = array();
			$applicable_slots[ $date_str ] = array();

			$is_holiday  = $holiday_service->isHoliday( $date_str );
			$day_of_week = date( 'w', strtotime( $date_str ) );

			foreach ( $slots as $slot ) {
				// Check slot availability rules
				$match = true;
				if ( $slot->is_holiday && ! $is_holiday ) {
					$match = false;
				}
				if ( $slot->days_of_week !== null && $slot->days_of_week !== '' ) {
					$allowed_days = explode( ',', $slot->days_of_week );
					if ( ! in_array( (string) $day_of_week, $allowed_days ) ) {
						$match = false;
					}
				}
				if ( $slot->date_start && $date_str < $slot->date_start ) {
					$match = false;
				}
				if ( $slot->date_end && $date_str > $slot->date_end ) {
					$match = false;
				}

				if ( $match ) {
					$applicable_slots[ $date_str ][] = (int) $slot->id;

					$price = $pricing_service->getPrice( $object_ids, array( $slot->id ), $date_str );
					if ( $price !== null ) {
						$prices_by_date[ $date_str ][ $slot->name ] = $price;
					}
				}
			}
			$current->modify( '+1 day' );
		}

		wp_send_json_success(
			array(
				'slots'            => $slots,
				'booked'           => $booked_details,
				'unavailable'      => $unavailable_slots,
				'applicable_slots' => $applicable_slots,
				'prices'           => $prices_by_date,
				'offset_days'      => $offset_days,
			)
		);
	}
}
