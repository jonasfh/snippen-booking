<?php

namespace SnippenBooking\Api;

use SnippenBooking\Helper\Capabilities;
use SnippenBooking\Service\PaymentService;

/**
 * Handles AJAX upload of payment receipt / screenshot
 */
class UploadPaymentReceiptApi {

	/**
	 * Register AJAX hooks
	 */
	public static function register() {
		add_action( 'wp_ajax_snippen_upload_payment_receipt', array( __CLASS__, 'upload_receipt' ) );
		add_action( 'wp_ajax_nopriv_snippen_upload_payment_receipt', array( __CLASS__, 'upload_receipt' ) );
	}

	/**
	 * Handle receipt file upload
	 */
	public static function upload_receipt() {
		global $wpdb;
		$table_bookings = $wpdb->prefix . 'snippen_bookings';

		$booking_id   = isset( $_POST['booking_id'] ) ? intval( $_POST['booking_id'] ) : 0;
		$booking_uuid = isset( $_POST['booking_uuid'] ) ? sanitize_text_field( $_POST['booking_uuid'] ) : '';

		if ( ! $booking_id && empty( $booking_uuid ) ) {
			wp_send_json_error( array( 'message' => __( 'Ugyldig booking-ID.', 'snippen-booking' ) ) );
		}

		if ( ! empty( $booking_uuid ) ) {
			$booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_bookings WHERE uuid = %s AND deleted_at IS NULL", $booking_uuid ) );
		} else {
			$booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_bookings WHERE id = %d AND deleted_at IS NULL", $booking_id ) );
		}

		if ( ! $booking ) {
			wp_send_json_error( array( 'message' => __( 'Booking ble ikke funnet.', 'snippen-booking' ) ) );
		}

		// Check permission
		$authorized = false;
		if ( ! empty( $booking_uuid ) && $booking_uuid === $booking->uuid ) {
			// Authorization via valid UUID token
			$authorized = true;
		} elseif ( is_user_logged_in() ) {
			$current_user_id = get_current_user_id();
			if ( Capabilities::can_manage_bookings() || intval( $booking->user_id ) === $current_user_id ) {
				$authorized = true;
			}
		}

		if ( ! $authorized ) {
			wp_send_json_error( array( 'message' => __( 'Du har ikke tilgang til denne bookingen.', 'snippen-booking' ) ) );
		}

		// Check uploaded file
		if ( empty( $_FILES['payment_receipt'] ) || $_FILES['payment_receipt']['error'] !== UPLOAD_ERR_OK ) {
			wp_send_json_error( array( 'message' => __( 'Vennligst velg en fil som skal lastes opp.', 'snippen-booking' ) ) );
		}

		$file = $_FILES['payment_receipt'];

		// Check mime type
		$allowed_mimes = array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'webp'         => 'image/webp',
			'pdf'          => 'application/pdf',
		);

		$filetype = wp_check_filetype( basename( $file['name'] ), $allowed_mimes );
		if ( ! $filetype['type'] ) {
			wp_send_json_error( array( 'message' => __( 'Ugyldig filtype. Kun JPEG, PNG, WEBP og PDF er tillatt.', 'snippen-booking' ) ) );
		}

		// Check file size (max 8MB)
		if ( $file['size'] > 8 * 1024 * 1024 ) {
			wp_send_json_error( array( 'message' => __( 'Filen er for stor. Maksimal filstørrelse er 8MB.', 'snippen-booking' ) ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$upload_overrides = array( 'test_form' => false );

		$user_folder    = intval( $booking->user_id );
		$booking_folder = intval( $booking->id );

		$custom_upload_dir_filter = function( $uploads ) use ( $user_folder, $booking_folder ) {
			$subdir            = '/userdata/user_id_' . $user_folder . '/booking_id_' . $booking_folder;
			$uploads['subdir'] = $subdir;
			$uploads['path']   = $uploads['basedir'] . $subdir;
			$uploads['url']    = $uploads['baseurl'] . $subdir;
			return $uploads;
		};

		add_filter( 'upload_dir', $custom_upload_dir_filter );
		$uploaded_file = wp_handle_upload( $file, $upload_overrides );
		remove_filter( 'upload_dir', $custom_upload_dir_filter );

		if ( isset( $uploaded_file['error'] ) ) {
			wp_send_json_error( array( 'message' => $uploaded_file['error'] ) );
		}

		$attachment = array(
			'guid'           => $uploaded_file['url'],
			'post_mime_type' => $uploaded_file['type'],
			'post_title'     => sprintf( __( 'Betalingskvittering Booking #%d', 'snippen-booking' ), $booking->id ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $uploaded_file['file'] );
		if ( ! is_wp_error( $attachment_id ) ) {
			$attach_data = wp_generate_attachment_metadata( $attachment_id, $uploaded_file['file'] );
			wp_update_attachment_metadata( $attachment_id, $attach_data );
		}

		// Update booking with receipt attachment ID
		$wpdb->update(
			$table_bookings,
			array(
				'payment_receipt_attachment_id' => $attachment_id,
				'payment_updated_at'            => current_time( 'mysql' ),
				'modified_at'                   => current_time( 'mysql' ),
			),
			array( 'id' => $booking->id )
		);

		// Reload updated booking object for notification
		$updated_booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_bookings WHERE id = %d", $booking->id ) );
		PaymentService::notify_admin_of_receipt_upload( $updated_booking );

		wp_send_json_success(
			array(
				'message'        => __( 'Betalingsdokumentasjon ble lastet opp.', 'snippen-booking' ),
				'attachment_url' => wp_get_attachment_url( $attachment_id ),
				'status_name'    => __( 'Mangler betaling (dokumentasjon opplastet)', 'snippen-booking' ),
			)
		);
	}
}
