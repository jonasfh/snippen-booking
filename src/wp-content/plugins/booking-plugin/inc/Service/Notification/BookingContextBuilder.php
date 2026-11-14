<?php
/**
 * Booking Notification Context Builder
 *
 * Single source of truth for building placeholder context arrays from booking records.
 *
 * @package SnippenBooking\Service\Notification
 */

namespace SnippenBooking\Service\Notification;

use SnippenBooking\Service\DoorCodeService;

/**
 * Class BookingContextBuilder
 */
class BookingContextBuilder {

	/**
	 * Build complete placeholder context array for a booking.
	 *
	 * @param object $booking Booking database record.
	 * @param array  $options Optional pre-resolved values:
	 *                        - object_names (string)
	 *                        - booking_time (string)
	 *                        - booking_url (string)
	 *                        - rejection_reason (string)
	 *                        - door_code (string).
	 * @return array Placeholder context key-value pairs.
	 */
	public static function build( $booking, array $options = array() ): array {
		if ( ! is_object( $booking ) ) {
			return array();
		}

		$booking_id   = isset( $booking->id ) ? (int) $booking->id : 0;
		$object_names = isset( $options['object_names'] )
			? (string) $options['object_names']
			: self::resolve_object_names( $booking_id, $booking );

		$booking_time = isset( $options['booking_time'] )
			? (string) $options['booking_time']
			: self::resolve_booking_time( $booking );

		$booking_url = isset( $options['booking_url'] )
			? (string) $options['booking_url']
			: ( ! empty( $booking->uuid ) ? add_query_arg( 'booking_uuid', $booking->uuid, home_url( '/' ) ) : '' );

		$default_payment_instructions = __( 'Vennligst overfør leiebeløpet innen 3 dager fra booking. Merk betalingen med ditt navn eller booking-ID.', 'snippen-booking' );

		$booking_type_raw   = $booking->booking_type ?? 'private';
		$booking_type_label = 'open' === $booking_type_raw
			? __( 'Åpent arrangement', 'snippen-booking' )
			: ( 'cleaning' === $booking_type_raw ? __( 'Utvask', 'snippen-booking' ) : __( 'Privat arrangement', 'snippen-booking' ) );

		$price                = isset( $booking->price ) ? (float) $booking->price : 0.0;
		$payment_method_label = ( $price <= 0 || 'open' === $booking_type_raw )
			? __( 'Fritatt', 'snippen-booking' )
			: ( ( ! empty( $booking->payment_notes ) && false !== stripos( (string) $booking->payment_notes, 'vipps' ) ) ? __( 'Vipps', 'snippen-booking' ) : __( 'Bankoverføring', 'snippen-booking' ) );

		$rejection_reason_opt = isset( $options['rejection_reason'] ) ? (string) $options['rejection_reason'] : '';
		$reason               = '' !== $rejection_reason_opt
			? $rejection_reason_opt
			: ( ! empty( $booking->rejection_reason ) ? (string) $booking->rejection_reason : __( 'Ingen begrunnelse oppgitt.', 'snippen-booking' ) );

		$door_code = isset( $options['door_code'] )
			? (string) $options['door_code']
			: ( ! empty( $booking->door_code ) ? (string) $booking->door_code : '' );

		$window = DoorCodeService::get_access_window( $booking );

		return array(
			'booking_id'                => (string) $booking_id,
			'user_name'                 => (string) ( $booking->customer_name ?? '' ),
			'user_email'                => (string) ( $booking->customer_email ?? '' ),
			'user_phone'                => (string) ( $booking->customer_phone ?? '' ),
			'booking_objects'           => $object_names,
			'booking_date'              => (string) ( $booking->booking_date ?? '' ),
			'booking_time'              => $booking_time,
			'booking_url'               => $booking_url,
			'booking_price'             => number_format( $price, 0, ',', ' ' ),
			'bank_account'              => (string) get_option( 'snippen_payment_bank_account', '' ),
			'vipps_number'              => (string) get_option( 'snippen_payment_vipps_number', '' ),
			'payment_instructions'      => (string) get_option( 'snippen_payment_instructions', $default_payment_instructions ),
			'booking_description'       => (string) ( $booking->description ?? '' ),
			'booking_type'              => $booking_type_label,
			'rejection_reason'          => $reason,
			'payment_method'            => $payment_method_label,
			'door_code'                 => $door_code,
			'door_code_valid_from'      => (string) ( $window['valid_from'] ?? '' ),
			'door_code_valid_to'        => (string) ( $window['valid_to'] ?? '' ),
			'door_code_validity_period' => (string) ( $window['validity_period'] ?? '' ),
			'booking'                   => $booking,
		);
	}

	/**
	 * Resolve comma/og-separated booked object names for a booking.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param object $booking    Booking record.
	 * @return string
	 */
	private static function resolve_object_names( int $booking_id, $booking ): string {
		global $wpdb;

		if ( $booking_id > 0 ) {
			$table_junction = $wpdb->prefix . 'snippen_bookings_booking_objects';
			$table_objects  = $wpdb->prefix . 'snippen_booking_objects';
			$objs           = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT o.name
					 FROM {$table_junction} bo
					 JOIN {$table_objects} o ON bo.booking_object_id = o.id
					 WHERE bo.booking_id = %d",
					$booking_id
				)
			);
			if ( ! empty( $objs ) ) {
				return implode( ' og ', $objs );
			}
		}

		if ( ! empty( $booking->booking_snapshot ) ) {
			$snapshot = json_decode( $booking->booking_snapshot, true );
			if ( is_array( $snapshot ) && ! empty( $snapshot['objects'] ) && is_array( $snapshot['objects'] ) ) {
				$names = array_filter( array_column( $snapshot['objects'], 'name' ) );
				if ( ! empty( $names ) ) {
					return implode( ' og ', $names );
				}
			}
		}

		return '';
	}

	/**
	 * Resolve formatted booking time range for a booking.
	 *
	 * @param object $booking Booking record.
	 * @return string
	 */
	private static function resolve_booking_time( $booking ): string {
		global $wpdb;

		if ( ! empty( $booking->booking_snapshot ) ) {
			$snapshot = json_decode( $booking->booking_snapshot, true );
			if ( is_array( $snapshot ) && ! empty( $snapshot['time_range_formatted'] ) ) {
				return (string) $snapshot['time_range_formatted'];
			}
		}

		if ( ! empty( $booking->slot_id ) ) {
			$table_slots = $wpdb->prefix . 'snippen_time_slots';
			$slot        = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_slots} WHERE id = %d", $booking->slot_id ) );
			if ( $slot ) {
				return sprintf( '%s - %s', date_i18n( 'H:i', strtotime( $slot->start_time ) ), date_i18n( 'H:i', strtotime( $slot->end_time ) ) );
			}
		}

		return '';
	}
}
