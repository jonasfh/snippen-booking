<?php
/**
 * Booking Edit API
 *
 * Handles admin AJAX endpoints for editing existing bookings and retrieving snapshot history.
 *
 * @package SnippenBooking\Api
 */

namespace SnippenBooking\Api;

use SnippenBooking\Helper\Capabilities;
use SnippenBooking\Database\Repository\BookingRepository;
use SnippenBooking\Service\PaymentService;
use SnippenBooking\Service\Notification\NotificationManager;

/**
 * Handles AJAX requests for editing bookings and snapshot history.
 */
class BookingEditApi {

	/**
	 * Register AJAX hooks.
	 */
	public static function register() {
		add_action( 'wp_ajax_snippen_get_booking_edit_data', array( __CLASS__, 'get_edit_data' ) );
		add_action( 'wp_ajax_snippen_edit_booking', array( __CLASS__, 'edit_booking' ) );
		add_action( 'wp_ajax_snippen_get_booking_history', array( __CLASS__, 'get_booking_history' ) );
	}

	/**
	 * Check if current user has administrator edit access.
	 *
	 * @return bool
	 */
	public static function user_can_edit() {
		return current_user_can( 'manage_options' ) || Capabilities::can_manage_bookings();
	}

	/**
	 * Fetch all data required to populate the Edit Booking modal.
	 */
	public static function get_edit_data() {
		check_ajax_referer( 'snippen_admin_nonce', 'nonce' );

		if ( ! self::user_can_edit() ) {
			wp_send_json_error( array( 'message' => __( 'Ingen tilgang.', 'snippen-booking' ) ), 403 );
		}

		$booking_id = isset( $_REQUEST['id'] ) ? (int) $_REQUEST['id'] : ( isset( $_REQUEST['booking_id'] ) ? (int) $_REQUEST['booking_id'] : 0 );
		if ( ! $booking_id ) {
			wp_send_json_error( array( 'message' => __( 'Ugyldig booking-ID.', 'snippen-booking' ) ), 400 );
		}

		$booking_repo = new BookingRepository();
		$booking      = $booking_repo->find( $booking_id );
		if ( ! $booking ) {
			wp_send_json_error( array( 'message' => __( 'Booking ble ikke funnet.', 'snippen-booking' ) ), 404 );
		}

		global $wpdb;
		$table_objects = $wpdb->prefix . 'snippen_booking_objects';
		$table_blocks  = $wpdb->prefix . 'snippen_booking_blocks';

		$objects = $wpdb->get_results(
			"SELECT id, name FROM $table_objects WHERE deleted_at IS NULL ORDER BY name ASC"
		);

		$blocks = $wpdb->get_results(
			"SELECT id, name, start_time, end_time, days_of_week\n" .
			" FROM $table_blocks\n" .
			" WHERE deleted_at IS NULL AND is_active = 1\n" .
			' ORDER BY sort_order ASC, start_time ASC'
		);

		$payment_statuses = PaymentService::get_statuses();
		$snapshots        = $booking_repo->get_snapshots( $booking_id );

		wp_send_json_success(
			array(
				'booking'             => $booking,
				'selected_object_ids' => $booking->booking_object_ids,
				'selected_block_ids'  => $booking->booking_block_ids,
				'objects'             => $objects,
				'blocks'              => $blocks,
				'payment_statuses'    => $payment_statuses,
				'snapshots'           => $snapshots,
			)
		);
	}

	/**
	 * Save edits to an existing booking.
	 */
	public static function edit_booking() {
		check_ajax_referer( 'snippen_admin_nonce', 'nonce' );

		if ( ! self::user_can_edit() ) {
			wp_send_json_error( array( 'message' => __( 'Ingen tilgang.', 'snippen-booking' ) ), 403 );
		}

		$booking_id = isset( $_POST['booking_id'] ) ? (int) $_POST['booking_id'] : 0;
		if ( ! $booking_id ) {
			wp_send_json_error( array( 'message' => __( 'Ugyldig booking-ID.', 'snippen-booking' ) ), 400 );
		}

		$booking_repo = new BookingRepository();
		$existing     = $booking_repo->find( $booking_id );
		if ( ! $existing ) {
			wp_send_json_error( array( 'message' => __( 'Booking ble ikke funnet.', 'snippen-booking' ) ), 404 );
		}

		// Validate booking date
		$booking_date = isset( $_POST['booking_date'] ) ? sanitize_text_field( wp_unslash( $_POST['booking_date'] ) ) : '';
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $booking_date ) ) {
			wp_send_json_error( array( 'message' => __( 'Ugyldig datoformat (må være ÅÅÅÅ-MM-DD).', 'snippen-booking' ) ) );
		}

		// Validate customer details
		$customer_name  = isset( $_POST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) ) : '';
		$customer_email = isset( $_POST['customer_email'] ) ? sanitize_email( wp_unslash( $_POST['customer_email'] ) ) : '';
		$customer_phone = isset( $_POST['customer_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_phone'] ) ) : '';

		if ( empty( $customer_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Kundenavn må fylles ut.', 'snippen-booking' ) ) );
		}
		if ( empty( $customer_email ) || ! is_email( $customer_email ) ) {
			wp_send_json_error( array( 'message' => __( 'Ugyldig e-postadresse.', 'snippen-booking' ) ) );
		}

		// Booking objects
		$object_ids = isset( $_POST['object_ids'] ) ? array_map( 'intval', (array) $_POST['object_ids'] ) : array();
		if ( empty( $object_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Minst ett lokale må være valgt.', 'snippen-booking' ) ) );
		}

		// Booking blocks
		$block_ids = isset( $_POST['block_ids'] ) ? array_map( 'intval', (array) $_POST['block_ids'] ) : array();
		if ( empty( $block_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Minst én tidsblokk må være valgt.', 'snippen-booking' ) ) );
		}

		// Booking type & status
		$allowed_types = array( 'private', 'open', 'cleaning' );
		$booking_type  = isset( $_POST['booking_type'] ) && in_array( $_POST['booking_type'], $allowed_types, true )
			? sanitize_text_field( wp_unslash( $_POST['booking_type'] ) )
			: 'private';

		$allowed_statuses = array( 'pending', 'pending_payment', 'confirmed', 'cancelled' );
		$status           = isset( $_POST['status'] ) && in_array( $_POST['status'], $allowed_statuses, true )
			? sanitize_text_field( wp_unslash( $_POST['status'] ) )
			: $existing->status;

		// Financial & extra fields
		$price             = isset( $_POST['price'] ) ? (float) $_POST['price'] : 0.0;
		$discount_amount   = isset( $_POST['discount_amount'] ) ? (float) $_POST['discount_amount'] : 0.0;
		$payment_status_id = isset( $_POST['payment_status_id'] ) ? (int) $_POST['payment_status_id'] : 1;
		$payment_notes     = isset( $_POST['payment_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['payment_notes'] ) ) : null;
		$door_code         = isset( $_POST['door_code'] ) ? sanitize_text_field( wp_unslash( $_POST['door_code'] ) ) : '';
		$description       = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$changes_summary   = isset( $_POST['changes_summary'] ) ? sanitize_textarea_field( wp_unslash( $_POST['changes_summary'] ) ) : '';

		$update_data = array(
			'booking_date'      => $booking_date,
			'customer_name'     => $customer_name,
			'customer_email'    => $customer_email,
			'customer_phone'    => $customer_phone,
			'booking_type'      => $booking_type,
			'status'            => $status,
			'price'             => $price,
			'discount_amount'   => $discount_amount,
			'payment_status_id' => $payment_status_id,
			'door_code'         => $door_code,
			'description'       => $description,
		);

		if ( null !== $payment_notes ) {
			$update_data['payment_notes'] = $payment_notes;
		}

		$modifier_info = array(
			'modified_by_user_id' => get_current_user_id(),
			'changes_summary'     => $changes_summary,
		);

		$result = $booking_repo->update( $booking_id, $update_data, $object_ids, $block_ids, $modifier_info );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Handle notifications if status changed
		$notification_manager = new NotificationManager();
		if ( 'confirmed' === $status && 'confirmed' !== $existing->status ) {
			$notification_manager->send_booking_confirmed_notification( $booking_id );
		}

		$new_payment_status = PaymentService::get_status_by_id( $payment_status_id );
		if ( $new_payment_status && 'PAID' === $new_payment_status->slug && (int) $existing->payment_status_id !== $payment_status_id ) {
			$notification_manager->send_payment_received_notification( $booking_id );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Bookingen er oppdatert og endring er loggført.', 'snippen-booking' ),
				'booking' => $result,
			)
		);
	}

	/**
	 * Retrieve snapshot history for a booking.
	 */
	public static function get_booking_history() {
		check_ajax_referer( 'snippen_admin_nonce', 'nonce' );

		if ( ! self::user_can_edit() ) {
			wp_send_json_error( array( 'message' => __( 'Ingen tilgang.', 'snippen-booking' ) ), 403 );
		}

		$booking_id = isset( $_REQUEST['booking_id'] ) ? (int) $_REQUEST['booking_id'] : ( isset( $_REQUEST['id'] ) ? (int) $_REQUEST['id'] : 0 );
		if ( ! $booking_id ) {
			wp_send_json_error( array( 'message' => __( 'Ugyldig booking-ID.', 'snippen-booking' ) ), 400 );
		}

		$booking_repo = new BookingRepository();
		$snapshots    = $booking_repo->get_snapshots( $booking_id );

		wp_send_json_success( array( 'snapshots' => $snapshots ) );
	}
}
