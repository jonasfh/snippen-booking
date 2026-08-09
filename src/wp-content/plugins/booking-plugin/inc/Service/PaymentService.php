<?php

namespace SnippenBooking\Service;

use SnippenBooking\Helper\Capabilities;

/**
 * Handles payment status management, options, and admin notifications.
 */
class PaymentService {

	/**
	 * Get all payment statuses
	 *
	 * @return array
	 */
	public static function get_statuses() {
		global $wpdb;
		$table   = $wpdb->prefix . 'snippen_payment_statuses';
		$results = $wpdb->get_results( "SELECT * FROM $table ORDER BY id ASC" );
		if ( empty( $results ) ) {
			return array(
				(object) array(
					'id'         => 1,
					'slug'       => 'UNPAID',
					'name'       => 'Mangler betaling',
					'is_settled' => 0,
				),
				(object) array(
					'id'         => 2,
					'slug'       => 'PAID',
					'name'       => 'Betalt',
					'is_settled' => 1,
				),
				(object) array(
					'id'         => 3,
					'slug'       => 'EXEMPT',
					'name'       => 'Fritatt / Gratis',
					'is_settled' => 1,
				),
			);
		}
		return $results;
	}

	/**
	 * Get payment status by ID
	 *
	 * @param int $id
	 * @return object|null
	 */
	public static function get_status_by_id( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_payment_statuses';
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $id ) );
	}

	/**
	 * Get payment status by slug
	 *
	 * @param string $slug
	 * @return object|null
	 */
	public static function get_status_by_slug( $slug ) {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_payment_statuses';
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE slug = %s", $slug ) );
	}

	/**
	 * Get payment status object for a booking
	 *
	 * @param object $booking
	 * @return object
	 */
	public static function get_booking_payment_status( $booking ) {
		$status_id = ! empty( $booking->payment_status_id ) ? (int) $booking->payment_status_id : 1;
		$status    = self::get_status_by_id( $status_id );
		if ( ! $status ) {
			return (object) array(
				'id'         => 1,
				'slug'       => 'UNPAID',
				'name'       => __( 'Mangler betaling', 'snippen-booking' ),
				'is_settled' => 0,
			);
		}
		return $status;
	}

	/**
	 * Send email notification to admin when receipt is uploaded
	 *
	 * @param object $booking
	 * @return bool
	 */
	public static function notify_admin_of_receipt_upload( $booking ) {
		if ( 'yes' !== get_option( 'snippen_payment_notify_admin', 'yes' ) ) {
			return false;
		}

		$recipients  = array();
		$admin_users = get_users(
			array(
				'capability' => \SnippenBooking\Helper\Capabilities::MANAGE_BOOKINGS,
			)
		);

		foreach ( $admin_users as $admin ) {
			if ( ! empty( $admin->user_email ) ) {
				$recipients[] = $admin->user_email;
			}
		}

		if ( empty( $recipients ) ) {
			$recipients[] = get_option( 'admin_email' );
		}

		$site_name = get_bloginfo( 'name' );
		$subject   = sprintf( __( '[%1$s] Ny betalingskvittering lastet opp - Booking #%2$d', 'snippen-booking' ), $site_name, $booking->id );

		$admin_url = admin_url( 'admin.php?page=snippen-booking&s=' . rawurlencode( $booking->customer_name ) );

		$message  = sprintf( __( "Det har blitt lastet opp ny betalingsdokumentasjon for en booking.\n\n", 'snippen-booking' ) );
		$message .= sprintf( __( "Booking-ID: #%d\n", 'snippen-booking' ), $booking->id );
		$message .= sprintf( __( "Kunde: %1\$s (%2\$s)\n", 'snippen-booking' ), $booking->customer_name, $booking->customer_email );
		$message .= sprintf( __( "Dato: %s\n", 'snippen-booking' ), $booking->booking_date );
		$message .= sprintf( __( "Beløp: %s kr\n\n", 'snippen-booking' ), number_format( $booking->price, 0, ',', ' ' ) );
		$message .= sprintf( __( "Se og behandle betalingen i admin-panelet:\n%s\n", 'snippen-booking' ), $admin_url );

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		return wp_mail( $recipients, $subject, $message, $headers );
	}
}
