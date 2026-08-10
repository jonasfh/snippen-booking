<?php

namespace SnippenBooking\Api;

use SnippenBooking\Service\AvailabilityService;
use SnippenBooking\Service\PricingService;
use SnippenBooking\Service\DiscountService;
use SnippenBooking\Helper\Capabilities;
use SnippenBooking\Database\Repository\BookingRepository;

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

		$block_ids_raw = isset( $_POST['block_ids'] ) ? $_POST['block_ids'] : '';
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

		$slot_id              = isset( $_POST['slot_id'] ) ? intval( $_POST['slot_id'] ) : 0;
		$availability_service = new AvailabilityService();

		if ( empty( $block_ids ) && $slot_id > 0 ) {
			if ( empty( $booking_object_ids ) || empty( $booking_date ) ) {
				wp_send_json_error( array( 'message' => __( 'Mangler nødvendige felt.', 'snippen-booking' ) ) );
			}

			$terms_url = get_option( 'snippen_terms_url', '' );
			if ( ! empty( $terms_url ) ) {
				$accept_terms = isset( $_POST['accept_terms'] ) ? $_POST['accept_terms'] : '';
				if ( $accept_terms !== 'on' && $accept_terms !== 'true' && $accept_terms !== '1' ) {
					wp_send_json_error( array( 'message' => __( 'Du må akseptere vilkårene for å kunne booke.', 'snippen-booking' ) ) );
				}
			}

			// Perform legacy slot checks
			$table_slots = $wpdb->prefix . 'snippen_time_slots';
			$slot        = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_slots WHERE id = %d AND deleted_at IS NULL", $slot_id ) );
			if ( ! $slot ) {
				wp_send_json_error( array( 'message' => __( 'Tidsluken finnes ikke.', 'snippen-booking' ) ) );
			}

			foreach ( $booking_object_ids as $obj_id ) {
				if ( ! $availability_service->isSlotAvailable( $obj_id, $booking_date, $slot_id ) ) {
					wp_send_json_error( array( 'message' => __( 'En eller flere tidsluker er ikke lenger tilgjengelig.', 'snippen-booking' ) ) );
				}
			}

			$table_time_slot_objects = $wpdb->prefix . 'snippen_time_slot_booking_objects';
			$required_objects        = $wpdb->get_col( $wpdb->prepare( "SELECT booking_object_id FROM $table_time_slot_objects WHERE time_slot_id = %d", $slot_id ) );
			$required_objects        = array_map( 'intval', $required_objects );
			sort( $required_objects );
			sort( $booking_object_ids );
			if ( $required_objects !== $booking_object_ids ) {
				wp_send_json_error( array( 'message' => __( 'Tidsluken stemmer ikke overens med de valgte lokalene. Vennligst last inn siden på nytt.', 'snippen-booking' ) ) );
			}

			// Process user details
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
			$customer_phone = get_user_meta( $booking_user_id, 'snippen_phone', true );
			if ( empty( $customer_phone ) ) {
				wp_send_json_error( array( 'message' => __( 'Brukeren mangler telefonnummer på sin profil. Vennligst kontakt administrator.', 'snippen-booking' ) ) );
			}

			$customer_name  = sanitize_text_field( $_POST['name'] ?? '' );
			$customer_email = sanitize_email( $_POST['email'] ?? '' );
			$description    = sanitize_textarea_field( $_POST['description'] ?? '' );

			$pricing_service = new PricingService();
			$base_price      = $pricing_service->getPrice( $booking_object_ids, array( $slot_id ), $booking_date );
			if ( $base_price === null ) {
				$base_price = 0.0;
			}

			// For legacy slots, we can calculate duration from slot start and end
			$duration = 0;
			if ( $slot ) {
				$start = strtotime( $slot->start_time );
				$end   = strtotime( $slot->end_time );
				if ( $end <= $start ) {
					$end += 24 * 3600;
				}
				$duration = round( ( $end - $start ) / 3600, 2 );
			}

			$discount_service = new DiscountService();
			$discount_repo    = new \SnippenBooking\Database\Repository\DiscountRuleRepository();
			$rule             = $discount_repo->find_applicable_rule( $booking_object_ids, $duration, $booking_date );

			$discount_amount  = 0.0;
			$final_price      = $base_price;
			$discount_rule_id = null;

			if ( $rule ) {
				if ( $rule->discount_type === 'percentage' ) {
					$discount_amount = $base_price * ( (float) $rule->discount_value / 100 );
				} elseif ( $rule->discount_type === 'fixed_amount' ) {
					$discount_amount = (float) $rule->discount_value;
				}
				if ( $discount_amount > $base_price ) {
					$discount_amount = $base_price;
				}
				$final_price      = $base_price - $discount_amount;
				$discount_rule_id = $rule->id;
			}

			$uuid = wp_generate_uuid4();

			$booking_data = array(
				'uuid'             => $uuid,
				'booking_date'     => $booking_date,
				'user_id'          => $booking_user_id,
				'slot_id'          => $slot_id,
				'customer_name'    => $customer_name,
				'customer_email'   => $customer_email,
				'customer_phone'   => $customer_phone,
				'description'      => $description,
				'price'            => $final_price,
				'discount_amount'  => $discount_amount,
				'discount_rule_id' => $discount_rule_id,
				'status'           => 'pending',
				'created_at'       => current_time( 'mysql' ),
				'modified_at'      => current_time( 'mysql' ),
			);

			$booking_repository = new BookingRepository();
			$booking_id         = $booking_repository->create( $booking_data, $booking_object_ids, array() );

			if ( $booking_id ) {
				$dispatch_method = get_option( 'snippen_notification_dispatch_method', 'async' );
				if ( 'sync' === $dispatch_method ) {
					$notification_manager = new \SnippenBooking\Service\Notification\NotificationManager();
					$notification_manager->send_booking_notifications( $booking_id, $uuid );
				} elseif ( ! wp_next_scheduled( 'snippen_booking_send_notifications', array( $booking_id, $uuid ) ) ) {
						wp_schedule_single_event( time(), 'snippen_booking_send_notifications', array( $booking_id, $uuid ) );
				}
				wp_send_json_success( array( 'message' => __( 'Bookingforespørsel sendt! Vi kontakter deg snart.', 'snippen-booking' ) ) );
			} else {
				wp_send_json_error( array( 'message' => __( 'Kunne ikke lagre booking. Vennligst prøv igjen.', 'snippen-booking' ) ) );
			}
		}

		if ( empty( $booking_object_ids ) || empty( $booking_date ) || empty( $block_ids ) ) {
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

		// Check if blocks are available for each of the selected objects
		$availability_service = new AvailabilityService();
		foreach ( $booking_object_ids as $obj_id ) {
			if ( ! $availability_service->areBlocksAvailable( $obj_id, $booking_date, $block_ids ) ) {
				wp_send_json_error( array( 'message' => __( 'Én eller flere av de valgte blokkene er ikke lenger ledig.', 'snippen-booking' ) ) );
			}
		}

		// Process user ID
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

		$customer_phone = get_user_meta( $booking_user_id, 'snippen_phone', true );
		if ( empty( $customer_phone ) ) {
			wp_send_json_error( array( 'message' => __( 'Brukeren mangler telefonnummer på sin profil. Vennligst kontakt administrator.', 'snippen-booking' ) ) );
		}

		$customer_name  = sanitize_text_field( $_POST['name'] ?? '' );
		$customer_email = sanitize_email( $_POST['email'] ?? '' );
		$description    = sanitize_textarea_field( $_POST['description'] ?? '' );

		// Calculate total price
		$pricing_service = new PricingService();
		$base_price      = $pricing_service->getPrice( $booking_object_ids, $block_ids, $booking_date );
		if ( $base_price === null ) {
			$base_price = 0.0;
		}

		$discount_service = new DiscountService();
		$discount_info    = $discount_service->applyDiscount( $base_price, $booking_object_ids, $block_ids, $booking_date );

		$uuid = wp_generate_uuid4();

		$booking_data = array(
			'uuid'             => $uuid,
			'booking_date'     => $booking_date,
			'user_id'          => $booking_user_id,
			'customer_name'    => $customer_name,
			'customer_email'   => $customer_email,
			'customer_phone'   => $customer_phone,
			'description'      => $description,
			'price'            => $discount_info['final_price'],
			'discount_amount'  => $discount_info['discount_amount'],
			'discount_rule_id' => $discount_info['discount_rule'] ? $discount_info['discount_rule']->id : null,
			'status'           => 'pending',
			'created_at'       => current_time( 'mysql' ),
			'modified_at'      => current_time( 'mysql' ),
		);

		$booking_repository = new BookingRepository();
		$booking_id         = $booking_repository->create( $booking_data, $booking_object_ids, $block_ids );

		if ( $booking_id ) {
			$dispatch_method = get_option( 'snippen_notification_dispatch_method', 'async' );

			if ( 'sync' === $dispatch_method ) {
				error_log( 'Booking API: Booking opprettet. Sender varsler synkront (direkte) for booking ID ' . $booking_id );
				$notification_manager = new \SnippenBooking\Service\Notification\NotificationManager();
				$notification_manager->send_booking_notifications( $booking_id, $uuid );
			} else {
				error_log( 'Booking API: Booking opprettet. Planlegger asynkron utsendelse av varsler for booking ID ' . $booking_id );
				if ( ! wp_next_scheduled( 'snippen_booking_send_notifications', array( $booking_id, $uuid ) ) ) {
					wp_schedule_single_event( time(), 'snippen_booking_send_notifications', array( $booking_id, $uuid ) );
				}
			}

			wp_send_json_success(
				array(
					'message' => __( 'Bookingforespørsel sendt! Vi kontakter deg snart.', 'snippen-booking' ),
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Kunne ikke lagre booking. Vennligst prøv igjen.', 'snippen-booking' ) ) );
		}
	}
}
