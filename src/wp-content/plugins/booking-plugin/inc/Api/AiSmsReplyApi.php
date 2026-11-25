<?php

namespace SnippenBooking\Api;

use SnippenBooking\Helper\Capabilities;
use SnippenBooking\Service\Ai\AiReplyService;

/**
 * AJAX endpoint for generating AI-assisted SMS reply drafts.
 */
class AiSmsReplyApi {

	/**
	 * Register WordPress AJAX hooks.
	 */
	public static function register() {
		add_action( 'wp_ajax_snippen_ai_suggest_sms_reply', array( __CLASS__, 'handle_suggest_reply' ) );
	}

	/**
	 * Handle AJAX request to generate an SMS reply draft via Gemini.
	 */
	public static function handle_suggest_reply() {
		check_ajax_referer( 'snippen_admin_nonce', 'nonce' );

		if ( ! Capabilities::can_manage_bookings() && ! Capabilities::can_manage_settings() ) {
			wp_send_json_error(
				array( 'message' => __( 'Du har ikke tilgang til denne funksjonen.', 'snippen-booking' ) ),
				403
			);
		}

		$message_id = isset( $_POST['message_id'] ) ? absint( $_POST['message_id'] ) : 0;
		$booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;
		$hint       = isset( $_POST['hint'] ) ? sanitize_text_field( wp_unslash( $_POST['hint'] ) ) : '';

		if ( ! $message_id && ! $booking_id ) {
			wp_send_json_error(
				array( 'message' => __( 'Mangler meldings-ID eller booking-ID.', 'snippen-booking' ) ),
				400
			);
		}

		$service = new AiReplyService();
		$result  = $service->suggest_sms_reply(
			$message_id > 0 ? $message_id : null,
			$booking_id > 0 ? $booking_id : null,
			$hint
		);

		if ( ! $result['success'] ) {
			wp_send_json_error( array( 'message' => $result['error'] ) );
		}

		wp_send_json_success(
			array(
				'draft' => $result['draft'],
			)
		);
	}
}
