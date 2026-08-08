<?php

namespace SnippenBooking\Api;

use SnippenBooking\Helper\Capabilities;
use SnippenBooking\Service\PaymentService;

/**
 * Handles admin AJAX update of payment status and payment notes.
 */
class UpdatePaymentStatusApi {

	/**
	 * Register AJAX hooks
	 */
	public static function register() {
		add_action( 'wp_ajax_snippen_update_payment_status', array( __CLASS__, 'update_payment_status' ) );
	}

	/**
	 * Handle status update
	 */
	public static function update_payment_status() {
		check_ajax_referer( 'snippen_admin_nonce', 'nonce' );

		if ( ! Capabilities::can_manage_bookings() ) {
			wp_send_json_error( array( 'message' => __( 'Ingen tilgang.', 'snippen-booking' ) ) );
		}

		global $wpdb;
		$table_bookings = $wpdb->prefix . 'snippen_bookings';

		$booking_id        = isset( $_POST['booking_id'] ) ? intval( $_POST['booking_id'] ) : 0;
		$payment_status_id = isset( $_POST['payment_status_id'] ) ? intval( $_POST['payment_status_id'] ) : 0;
		$payment_notes     = isset( $_POST['payment_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['payment_notes'] ) ) : null;

		if ( ! $booking_id || ! $payment_status_id ) {
			wp_send_json_error( array( 'message' => __( 'Ugyldige parametere.', 'snippen-booking' ) ) );
		}

		$status = PaymentService::get_status_by_id( $payment_status_id );
		if ( ! $status ) {
			wp_send_json_error( array( 'message' => __( 'Ugyldig betalingsstatus.', 'snippen-booking' ) ) );
		}

		$booking = $wpdb->get_row( $wpdb->prepare( "SELECT status FROM $table_bookings WHERE id = %d", $booking_id ) );

		$update_data = array(
			'payment_status_id'  => $payment_status_id,
			'payment_updated_at' => current_time( 'mysql' ),
			'modified_at'        => current_time( 'mysql' ),
		);

		// Automatically confirm booking if payment is set to PAID or settled and booking is currently pending
		if ( ( $status->slug === 'PAID' || (int) $status->is_settled === 1 ) && $booking && 'pending' === $booking->status ) {
			$update_data['status'] = 'confirmed';
		}

		if ( $payment_notes !== null ) {
			$update_data['payment_notes'] = $payment_notes;
		}

		$updated = $wpdb->update(
			$table_bookings,
			$update_data,
			array( 'id' => $booking_id )
		);

		if ( $updated !== false ) {
			wp_send_json_success(
				array(
					'message'     => __( 'Betalingsstatus oppdatert.', 'snippen-booking' ),
					'status_id'   => $status->id,
					'status_slug' => $status->slug,
					'status_name' => $status->name,
					'is_settled'  => (int) $status->is_settled,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Kunne ikke oppdatere betalingsstatus.', 'snippen-booking' ) ) );
		}
	}
}
