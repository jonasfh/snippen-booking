<?php

namespace SnippenBooking\Api;

use SnippenBooking\Service\AvailabilityService;
use SnippenBooking\Service\PricingService;
use SnippenBooking\Helper\Capabilities;

/**
 * Handles booking submission AJAX requests
 */
class BookingApi {

	/**
	 * Register AJAX handlers
	 */
	public static function register() {
		add_action( 'wp_ajax_snippen_booking_submit', array( __CLASS__, 'submit_booking' ) );
		add_action( 'wp_ajax_nopriv_snippen_booking_submit', array( __CLASS__, 'submit_booking' ) );
	}

	/**
	 * Handle booking submission
	 */
	public static function submit_booking() {
		global $wpdb;

		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'snippen_booking_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed', 'snippen-booking' ) ) );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Du må være innlogget for å booke.', 'snippen-booking' ) ) );
		}

		$object_ids_raw = isset( $_POST['booking_object_id'] ) ? $_POST['booking_object_id'] : array();
		if ( ! is_array( $object_ids_raw ) ) {
			$decoded = json_decode( stripslashes( $object_ids_raw ), true );
			if ( is_array( $decoded ) ) {
				$object_ids_raw = $decoded;
			} else {
				$object_ids_raw = explode( ',', $object_ids_raw );
			}
		}

		$booking_object_ids = array_map( 'intval', $object_ids_raw );
		$booking_object_ids = array_filter( $booking_object_ids );

		$booking_date = sanitize_text_field( $_POST['event_date'] ?? '' );

		$slot_ids_raw = isset( $_POST['slot_id'] ) ? $_POST['slot_id'] : '';
		$slot_ids     = array_map( 'intval', explode( ',', $slot_ids_raw ) );
		$slot_ids     = array_filter( $slot_ids );

		if ( empty( $booking_object_ids ) || empty( $booking_date ) || empty( $slot_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Mangler nødvendige felt.', 'snippen-booking' ) ) );
		}

		$terms_url = get_option( 'snippen_terms_url', '' );
		if ( ! empty( $terms_url ) ) {
			$accept_terms = isset( $_POST['accept_terms'] ) ? $_POST['accept_terms'] : '';
			if ( $accept_terms !== 'on' && $accept_terms !== 'true' && $accept_terms !== '1' ) {
				wp_send_json_error( array( 'message' => __( 'Du må akseptere vilkårene for å kunne booke.', 'snippen-booking' ) ) );
			}
		}

		error_log( 'Booking API: Validering fullført. Sjekker tilgjengelighet for dato ' . $booking_date );

		// Check if available (using advanced overlap detection) for all requested slots
		$availability_service = new \SnippenBooking\Service\AvailabilityService();
		$slots_to_book        = array();

		// Fetch the objects linked to the requested slots from the database
		$table_time_slot_objects = $wpdb->prefix . 'snippen_time_slot_booking_objects';
		
		// Match slot IDs to their respective booking objects
		foreach ( $booking_object_ids as $obj_id ) {
			$matched_slot_id = 0;
			// Find which of the submitted slot_ids belongs to this object
			foreach ( $slot_ids as $sid ) {
				$slot_check = $wpdb->get_var( 
					$wpdb->prepare( 
						"SELECT time_slot_id FROM $table_time_slot_objects WHERE time_slot_id = %d AND booking_object_id = %d", 
						$sid, $obj_id 
					) 
				);
				if ( $slot_check ) {
					$matched_slot_id = $sid;
					break;
				}
			}

			if ( ! $matched_slot_id || ! $availability_service->isSlotAvailable( $obj_id, $booking_date, $matched_slot_id ) ) {
				wp_send_json_error( array( 'message' => __( 'En eller flere tidsluker er ikke lenger tilgjengelig.', 'snippen-booking' ) ) );
			}
			$slots_to_book[ $obj_id ] = $matched_slot_id;
		}

		// Strictly validate that the requested booking_object_ids exactly match the required objects for the chosen slots.
		// We cannot allow a multi-object slot (e.g. "Hele området") to be booked for only one of its objects.
		$unique_slots = array_unique( array_values( $slots_to_book ) );
		foreach ( $unique_slots as $sid ) {
			$required_objects = $wpdb->get_col(
				$wpdb->prepare( "SELECT booking_object_id FROM $table_time_slot_objects WHERE time_slot_id = %d", $sid )
			);
			$required_objects = array_map( 'intval', $required_objects );
			
			// Find which of our requested objects are assigned to this slot
			$requested_for_this_slot = array_keys( $slots_to_book, $sid );
			
			// Compare arrays regardless of order
			sort($required_objects);
			sort($requested_for_this_slot);
			
			if ( $required_objects !== $requested_for_this_slot ) {
				wp_send_json_error( array( 'message' => __( 'Tidsluken stemmer ikke overens med de valgte lokalene. Vennligst last inn siden på nytt.', 'snippen-booking' ) ) );
			}
		}

		// Process booking data
		$table_bookings        = $wpdb->prefix . 'snippen_bookings';
		$table_booking_objects = $wpdb->prefix . 'snippen_bookings_booking_objects';
		$customer_name         = sanitize_text_field( $_POST['name'] ?? '' );
		$customer_email        = sanitize_email( $_POST['email'] ?? '' );

		// Get current user or admin override
		$current_user_id = get_current_user_id();
		$booking_user_id = $current_user_id;

		if ( Capabilities::can_manage_bookings() && ! empty( $_POST['user_id'] ) ) {
			$booking_user_id = intval( $_POST['user_id'] );
		}

		if ( ! $booking_user_id ) {
			wp_send_json_error( array( 'message' => __( 'Ugyldig bruker.', 'snippen-booking' ) ) );
		}

		if ( get_user_meta( $booking_user_id, 'snippen_user_deleted', true ) === 'yes' ) {
			wp_send_json_error( array( 'message' => __( 'Kontoen din er slettet eller deaktivert. Kontakt administrator.', 'snippen-booking' ) ) );
		}

		error_log( 'Booking API: Tilgjengelighet og bruker verifisert for bruker ID ' . $booking_user_id );

		// Fetch phone from user meta securely (cannot be changed by user in form)
		$customer_phone = get_user_meta( $booking_user_id, 'snippen_phone', true );

		if ( empty( $customer_phone ) ) {
			wp_send_json_error( array( 'message' => __( 'Brukeren mangler telefonnummer på sin profil. Vennligst kontakt administrator.', 'snippen-booking' ) ) );
		}
		$description = sanitize_textarea_field( $_POST['description'] ?? '' );

		// Get slot info for price lookup and restrictions
		$table_slots   = $wpdb->prefix . 'snippen_time_slots';
		$first_slot_id = reset( $slots_to_book );
		$slot_info     = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT name FROM $table_slots WHERE id = %d",
				$first_slot_id
			)
		);
		$slot_name     = $slot_info ? $slot_info->name : '';

		// Calculate price
		$pricing_service = new PricingService();
		$price           = $pricing_service->getPrice( array_keys( $slots_to_book ), array_values( $slots_to_book ), $booking_date );

		if ( $price === null ) {
			// Fallback if no price defined, but in a real scenario we might want to block this
			$price = 0;
		}

		$uuid = wp_generate_uuid4();

		// Insert single booking record
		$booking_data = array(
			'uuid'           => $uuid,
			'booking_date'   => $booking_date,
			'user_id'        => $booking_user_id,
			'slot_id'        => $first_slot_id,
			'customer_name'  => $customer_name,
			'customer_email' => $customer_email,
			'customer_phone' => $customer_phone,
			'description'    => $description,
			'price'          => $price,
			'status'         => 'pending',
			'created_at'     => current_time( 'mysql' ),
		);

		$booking_inserted = $wpdb->insert( $table_bookings, $booking_data );

		if ( ! $booking_inserted ) {
			error_log( 'Booking API: Kunne ikke sette inn booking i databasen for uuid ' . $uuid );
			wp_send_json_error( array( 'message' => __( 'Kunne ikke lagre booking. Vennligst prøv igjen.', 'snippen-booking' ) ) );
		}

		$booking_id    = $wpdb->insert_id;
		error_log( 'Booking API: Booking lagret i databasen med ID ' . $booking_id );
		$success_count = 0;

		// Insert relationships in junction table
		foreach ( $slots_to_book as $obj_id => $sid ) {
			$junction_data = array(
				'booking_id'        => $booking_id,
				'booking_object_id' => $obj_id,
				'created_at'        => current_time( 'mysql' ),
			);

			$result = $wpdb->insert( $table_booking_objects, $junction_data );
			if ( $result ) {
				++$success_count;
			}
		}

		if ( $success_count == count( $booking_object_ids ) ) {
			$dispatch_method = get_option( 'snippen_notification_dispatch_method', 'async' );

			if ( 'sync' === $dispatch_method ) {
				error_log( 'Booking API: Relasjoner opprettet. Sender varsler synkront (direkte) for booking ID ' . $booking_id );
				$notification_manager = new \SnippenBooking\Service\Notification\NotificationManager();
				$notification_manager->send_booking_notifications( $booking_id, $uuid );
				error_log( 'Booking API: Synkron utsendelse fullført for ID ' . $booking_id );
			} else {
				error_log( 'Booking API: Relasjoner opprettet. Planlegger asynkron utsendelse av varsler for booking ID ' . $booking_id );
				// Dispatch notifications asynchronously to prevent timeouts
				if ( ! wp_next_scheduled( 'snippen_booking_send_notifications', array( $booking_id, $uuid ) ) ) {
					wp_schedule_single_event( time(), 'snippen_booking_send_notifications', array( $booking_id, $uuid ) );
				}
				error_log( 'Booking API: Varsler planlagt. Booking fullført for ID ' . $booking_id );
			}

			wp_send_json_success(
				array(
					'message' => __( 'Bookingforespørsel sendt! Vi kontakter deg snart.', 'snippen-booking' ),
				)
			);
		} else {
			// Clean up the booking if junction inserts failed
			$wpdb->delete( $table_bookings, array( 'id' => $booking_id ) );
			wp_send_json_error( array( 'message' => __( 'Kunne ikke lagre alle bookinger. Vennligst prøv igjen.', 'snippen-booking' ) ) );
		}
	}
}
