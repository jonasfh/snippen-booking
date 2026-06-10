<?php

namespace SnippenBooking\Api;

use SnippenBooking\Database\Repository\BookingBlockRepository;
use SnippenBooking\Database\Repository\BookingRepository;
use SnippenBooking\Service\AvailabilityService;
use SnippenBooking\Service\PricingService;
use SnippenBooking\Service\DiscountService;
use SnippenBooking\Helper\Capabilities;
use SnippenBooking\Service\HolidayService;

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
		add_action( 'wp_ajax_snippen_get_objects_availability', array( __CLASS__, 'get_objects_availability' ) );
		add_action( 'wp_ajax_nopriv_snippen_get_objects_availability', array( __CLASS__, 'get_objects_availability' ) );
	}

	/**
	 * Get availability for a given object list and week
	 */
	public static function get_availability() {
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

		$end_date = date( 'Y-m-d', strtotime( $start_date . ' + 6 days' ) );

		$block_repository     = new BookingBlockRepository();
		$booking_repository   = new BookingRepository();
		$availability_service = new AvailabilityService();
		$pricing_service      = new PricingService();
		$discount_service     = new DiscountService();
		$holiday_service      = new HolidayService();

		$all_blocks = $block_repository->find_all();
		$is_admin   = Capabilities::can_manage_bookings();

		$today = new \DateTime( current_time( 'Y-m-d' ) );

		// Fetch bookings for the date range
		$bookings_by_object = array();
		foreach ( $object_ids as $obj_id ) {
			$bookings_by_object[ $obj_id ] = $booking_repository->find_by_object_and_date_range( $obj_id, $start_date, $end_date );
		}

		$days = array();
		$current = new \DateTime( $start_date );
		$last    = new \DateTime( $end_date );

		$weekdays = array(
			__( 'Søndag', 'snippen-booking' ),
			__( 'Mandag', 'snippen-booking' ),
			__( 'Tirsdag', 'snippen-booking' ),
			__( 'Onsdag', 'snippen-booking' ),
			__( 'Torsdag', 'snippen-booking' ),
			__( 'Fredag', 'snippen-booking' ),
			__( 'Lørdag', 'snippen-booking' ),
		);

		while ( $current <= $last ) {
			$date_str   = $current->format( 'Y-m-d' );
			$is_holiday = $holiday_service->isHoliday( $date_str );
			$day_val    = (int) $current->format( 'w' );
			$is_past    = $current < $today;

			$day_blocks = array();
			foreach ( $all_blocks as $block ) {
				if ( ! $availability_service->isBlockApplicable( $block, $date_str, $is_holiday ) ) {
					continue;
				}

				// Check availability
				$total_capacity        = count( $object_ids );
				$available_capacity    = 0;
				$occupied_object_names = array();
				$booked_by             = null;
				$booking_info          = null;

				foreach ( $object_ids as $obj_id ) {
					if ( $availability_service->isBlockAvailable( $obj_id, $date_str, $block->id ) ) {
						$available_capacity++;
					} else {
						// Fetch object name
						$obj_name = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM {$wpdb->prefix}snippen_booking_objects WHERE id = %d", $obj_id ) );
						if ( $obj_name ) {
							$occupied_object_names[] = $obj_name;
						}

						// Find who booked it for admin
						if ( $is_admin && ! $booking_info ) {
							foreach ( $bookings_by_object[ $obj_id ] as $booking ) {
								if ( $booking->booking_date === $date_str && in_array( (int) $block->id, $booking->booking_block_ids, true ) ) {
									$booked_by    = $booking->customer_name;
									$booking_info = array(
										'customer_name'  => $booking->customer_name,
										'customer_email' => $booking->customer_email,
										'customer_phone' => $booking->customer_phone,
										'description'    => $booking->description,
										'object_names'   => implode( ', ', array_map( function($id) use ($wpdb) {
											return $wpdb->get_var( $wpdb->prepare( "SELECT name FROM {$wpdb->prefix}snippen_booking_objects WHERE id = %d", $id ) );
										}, $booking->booking_object_ids ) ),
										'start_time'     => $block->start_time,
										'end_time'       => $block->end_time,
										'slot_name'      => $block->name,
									);
									break;
								}
							}
						}
					}
				}

				$is_available = $available_capacity > 0;

				$base_price = $pricing_service->getPrice( $object_ids, array( $block->id ), $date_str );
				$discount_info = $discount_service->applyDiscount( $base_price, $object_ids, array( $block->id ), $date_str );

				$day_blocks[] = array(
					'id'                    => (int) $block->id,
					'name'                  => $block->name,
					'start_time'            => $block->start_time,
					'end_time'              => $block->end_time,
					'is_available'          => $is_available,
					'available_capacity'    => $available_capacity,
					'total_capacity'        => $total_capacity,
					'occupied_object_names' => $occupied_object_names,
					'price'                 => $discount_info['final_price'],
					'base_price'            => $base_price,
					'discount_amount'       => $discount_info['discount_amount'],
					'booked_by'             => $booked_by,
					'booking_info'          => $booking_info,
				);
			}

			$days[] = array(
				'date'               => $date_str,
				'day_name'           => $weekdays[ $day_val ],
				'day_date_formatted' => $current->format( 'j.n' ),
				'is_past'            => $is_past,
				'is_holiday'         => $is_holiday,
				'blocks'             => $day_blocks,
			);

			$current->modify( '+1 day' );
		}

		wp_send_json_success(
			array(
				'days'        => $days,
				'offset_days' => 0,
			)
		);
	}

	/**
	 * Get availability and pricing for booking objects on a specific date and blocks
	 */
	public static function get_objects_availability() {
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

		$selected_objects_raw = isset( $_GET['selected_object_ids'] ) ? $_GET['selected_object_ids'] : array();
		if ( ! is_array( $selected_objects_raw ) ) {
			$decoded = json_decode( stripslashes( $selected_objects_raw ), true );
			if ( is_array( $decoded ) ) {
				$selected_objects_raw = $decoded;
			} else {
				$selected_objects_raw = explode( ',', $selected_objects_raw );
			}
		}
		$selected_object_ids = array_map( 'intval', $selected_objects_raw );
		$selected_object_ids = array_filter( $selected_object_ids );

		$event_date = sanitize_text_field( $_GET['event_date'] ?? '' );

		$block_ids_raw = isset( $_GET['block_ids'] ) ? $_GET['block_ids'] : array();
		if ( ! is_array( $block_ids_raw ) ) {
			$decoded = json_decode( stripslashes( $block_ids_raw ), true );
			if ( is_array( $decoded ) ) {
				$block_ids_raw = $decoded;
			} else {
				$block_ids_raw = explode( ',', $block_ids_raw );
			}
		}
		$block_ids = array_map( 'intval', $block_ids_raw );
		$block_ids = array_filter( $block_ids );

		if ( empty( $object_ids ) || empty( $event_date ) || empty( $block_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Manglende påkrevde parametere.', 'snippen-booking' ) ) );
		}

		$availability_service = new AvailabilityService();
		$pricing_service      = new PricingService();

		$table_objects = $wpdb->prefix . 'snippen_booking_objects';
		$objects_data  = array();

		foreach ( $object_ids as $obj_id ) {
			$obj_name = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM $table_objects WHERE id = %d", $obj_id ) );
			$is_avail = $availability_service->areBlocksAvailable( $obj_id, $event_date, $block_ids );

			$objects_data[] = array(
				'id'           => $obj_id,
				'name'         => $obj_name,
				'is_available' => $is_avail,
			);
		}

		$base_price = 0;
		$final_price = 0;
		$discount_amount = 0;
		$discount_name = null;

		if ( ! empty( $selected_object_ids ) ) {
			$base_price = $pricing_service->getPrice( $selected_object_ids, $block_ids, $event_date );
			$discount_service = new DiscountService();
			$discount_info = $discount_service->applyDiscount( $base_price, $selected_object_ids, $block_ids, $event_date );
			$final_price = $discount_info['final_price'];
			$discount_amount = $discount_info['discount_amount'];
			if ( $discount_info['discount_rule'] ) {
				$discount_name = $discount_info['discount_rule']->name;
				if ( $discount_info['discount_rule']->discount_type === 'percentage' ) {
					$discount_name .= ' (' . floatval( $discount_info['discount_rule']->discount_value ) . '%)';
				}
			}
		}

		wp_send_json_success(
			array(
				'objects' => $objects_data,
				'price'   => $final_price,
				'base_price' => $base_price,
				'discount_amount' => $discount_amount,
				'discount_name' => $discount_name,
			)
		);
	}
}
