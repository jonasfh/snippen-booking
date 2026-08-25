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
	 * Send notification to admin when receipt is uploaded
	 *
	 * @param object $booking
	 * @return bool
	 */
	public static function notify_admin_of_receipt_upload( $booking ) {
		if ( ! $booking || empty( $booking->id ) ) {
			return false;
		}

		$manager = new \SnippenBooking\Service\Notification\NotificationManager();
		return $manager->send_payment_receipt_uploaded_notification( (int) $booking->id );
	}
}
