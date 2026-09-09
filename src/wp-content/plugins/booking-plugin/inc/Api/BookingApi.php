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

			$booking_type = isset( $_POST['booking_type'] ) ? sanitize_text_field( $_POST['booking_type'] ) : 'private';
			if ( ! in_array( $booking_type, array( 'private', 'open', 'cleaning' ), true ) ) {
				$booking_type = 'private';
			}

			if ( 'open' === $booking_type || 'cleaning' === $booking_type ) {
				$final_price       = 0.0;
				$discount_amount   = 0.0;
				$discount_rule_id  = null;
				$payment_status_id = 3; // EXEMPT
			} else {
				$payment_status_id = 1; // UNPAID
			}

			$is_vipps_enabled = ( new \SnippenBooking\Service\Vipps\VippsService() )->is_enabled();
			$status           = ( $is_vipps_enabled && 'private' === $booking_type && $final_price > 0 ) ? 'pending_payment' : 'pending';
			$uuid             = wp_generate_uuid4();

			$booking_data = array(
				'uuid'              => $uuid,
				'booking_date'      => $booking_date,
				'user_id'           => $booking_user_id,
				'slot_id'           => $slot_id,
				'customer_name'     => $customer_name,
				'customer_email'    => $customer_email,
				'customer_phone'    => $customer_phone,
				'description'       => $description,
				'booking_type'      => $booking_type,
				'price'             => $final_price,
				'discount_amount'   => $discount_amount,
				'discount_rule_id'  => $discount_rule_id,
				'payment_status_id' => $payment_status_id,
				'status'            => $status,
				'created_at'        => current_time( 'mysql' ),
				'modified_at'       => current_time( 'mysql' ),
			);

			$booking_repository = new BookingRepository();
			$booking_id         = $booking_repository->create( $booking_data, $booking_object_ids, array() );

			if ( $booking_id ) {
				self::handle_vipps_checkout_or_notifications( $booking_id, $uuid, $booking_type, $final_price, $customer_phone );
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

		$booking_type = isset( $_POST['booking_type'] ) ? sanitize_text_field( $_POST['booking_type'] ) : 'private';
		if ( ! in_array( $booking_type, array( 'private', 'open', 'cleaning' ), true ) ) {
			$booking_type = 'private';
		}

		if ( 'open' === $booking_type || 'cleaning' === $booking_type ) {
			$final_price       = 0.0;
			$discount_amount   = 0.0;
			$discount_rule_id  = null;
			$payment_status_id = 3; // EXEMPT
		} else {
			// Calculate total price
			$pricing_service = new PricingService();
			$base_price      = $pricing_service->getPrice( $booking_object_ids, $block_ids, $booking_date );
			if ( $base_price === null ) {
				$base_price = 0.0;
			}

			$discount_service  = new DiscountService();
			$discount_info     = $discount_service->applyDiscount( $base_price, $booking_object_ids, $block_ids, $booking_date );
			$final_price       = $discount_info['final_price'];
			$discount_amount   = $discount_info['discount_amount'];
			$discount_rule_id  = $discount_info['discount_rule'] ? $discount_info['discount_rule']->id : null;
			$payment_status_id = 1; // UNPAID
		}

		$uuid = wp_generate_uuid4();

		$is_vipps_enabled = ( new \SnippenBooking\Service\Vipps\VippsService() )->is_enabled();
		$status           = ( $is_vipps_enabled && 'private' === $booking_type && $final_price > 0 ) ? 'pending_payment' : 'pending';

		$booking_data = array(
			'uuid'              => $uuid,
			'booking_date'      => $booking_date,
			'user_id'           => $booking_user_id,
			'customer_name'     => $customer_name,
			'customer_email'    => $customer_email,
			'customer_phone'    => $customer_phone,
			'description'       => $description,
			'booking_type'      => $booking_type,
			'price'             => $final_price,
			'discount_amount'   => $discount_amount,
			'discount_rule_id'  => $discount_rule_id,
			'payment_status_id' => $payment_status_id,
			'status'            => $status,
			'created_at'        => current_time( 'mysql' ),
			'modified_at'       => current_time( 'mysql' ),
		);

		$booking_repository = new BookingRepository();
		$booking_id         = $booking_repository->create( $booking_data, $booking_object_ids, $block_ids );

		if ( $booking_id ) {
			// Handle optional included cleaning for next day
			$include_cleaning = ! empty( $_POST['include_cleaning'] );
			if ( $include_cleaning && 'cleaning' !== $booking_type ) {
				$next_date  = date( 'Y-m-d', strtotime( $booking_date . ' + 1 day' ) );
				$block_repo = new \SnippenBooking\Database\Repository\BookingBlockRepository();
				$blocks     = $block_repo->find_by_ids( $block_ids );

				$has_supports_cleaning = false;
				foreach ( $blocks as $b ) {
					if ( ! empty( $b->supports_cleaning ) ) {
						$has_supports_cleaning = true;
						break;
					}
				}

				if ( $has_supports_cleaning ) {
					$cleaning_block_ids = $availability_service->getCleaningBlockIds( $next_date );

					if ( ! empty( $cleaning_block_ids ) ) {
						$cleaning_avail = true;
						foreach ( $booking_object_ids as $so_id ) {
							if ( ! $availability_service->areBlocksAvailable( $so_id, $next_date, $cleaning_block_ids ) ) {
								$cleaning_avail = false;
								break;
							}
						}

						if ( $cleaning_avail ) {
							$cleaning_data = array(
								'uuid'              => wp_generate_uuid4(),
								'booking_date'      => $next_date,
								'user_id'           => $booking_user_id,
								'customer_name'     => $customer_name,
								'customer_email'    => $customer_email,
								'customer_phone'    => $customer_phone,
								/* translators: %d: booking ID */
								'description'       => sprintf( __( 'Utvask etter booking #%d', 'snippen-booking' ), $booking_id ),
								'booking_type'      => 'cleaning',
								'price'             => 0.0,
								'discount_amount'   => 0.0,
								'discount_rule_id'  => null,
								'payment_status_id' => 3, // EXEMPT
								'status'            => 'pending',
								'created_at'        => current_time( 'mysql' ),
								'modified_at'       => current_time( 'mysql' ),
							);
							$booking_repository->create( $cleaning_data, $booking_object_ids, $cleaning_block_ids );
						}
					}
				}
			}

			self::handle_vipps_checkout_or_notifications( $booking_id, $uuid, $booking_type, $final_price, $customer_phone );
		} else {
			wp_send_json_error( array( 'message' => __( 'Kunne ikke lagre booking. Vennligst prøv igjen.', 'snippen-booking' ) ) );
		}
	}

	/**
	 * Handle Vipps checkout flow or regular booking notifications.
	 *
	 * @param int    $booking_id     Booking ID.
	 * @param string $uuid           Booking UUID.
	 * @param string $booking_type   Booking type ('private', 'open', 'cleaning').
	 * @param float  $final_price    Final booking price.
	 * @param string $customer_phone Customer phone number.
	 */
	private static function handle_vipps_checkout_or_notifications( $booking_id, $uuid, $booking_type, $final_price, $customer_phone ) {
		global $wpdb;

		$vipps_service = new \SnippenBooking\Service\Vipps\VippsService();
		$is_vipps      = $vipps_service->is_enabled() && 'private' === $booking_type && $final_price > 0;

		if ( $is_vipps ) {
			$booking_obj = (object) array(
				'id'   => $booking_id,
				'uuid' => $uuid,
			);
			$return_url  = ! empty( $_POST['return_url'] ) ? sanitize_url( wp_unslash( $_POST['return_url'] ) ) : wp_get_referer();
			$payment_res = $vipps_service->create_booking_payment( $booking_obj, $final_price, $customer_phone, null, $return_url );

			if ( is_wp_error( $payment_res ) ) {
				error_log( 'Vipps create_booking_payment failed: ' . $payment_res->get_error_message() );
				$wpdb->update(
					$wpdb->prefix . 'snippen_bookings',
					array(
						'status'           => 'cancelled',
						'rejection_reason' => sprintf(
							/* translators: %s: Vipps error message */
							__( 'Vipps-betaling kunne ikke opprettes: %s', 'snippen-booking' ),
							$payment_res->get_error_message()
						),
						'modified_at'      => current_time( 'mysql' ),
					),
					array( 'id' => $booking_id )
				);
				wp_send_json_error(
					array(
						'message' => sprintf(
							/* translators: %s: Vipps error message */
							__( 'Kunne ikke opprette Vipps-betaling: %s', 'snippen-booking' ),
							$payment_res->get_error_message()
						),
					)
				);
			}

			$reference = ! empty( $payment_res['reference'] ) ? $payment_res['reference'] : '';
			if ( $reference ) {
				$wpdb->update(
					$wpdb->prefix . 'snippen_bookings',
					array(
						'payment_notes' => 'Vipps ref: ' . $reference,
						'modified_at'   => current_time( 'mysql' ),
					),
					array( 'id' => $booking_id )
				);
			}

			wp_send_json_success(
				array(
					'message'      => __( 'Videresender til Vipps...', 'snippen-booking' ),
					'redirect_url' => $payment_res['redirectUrl'],
					'reference'    => $reference,
				)
			);
		}

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
	}
}
