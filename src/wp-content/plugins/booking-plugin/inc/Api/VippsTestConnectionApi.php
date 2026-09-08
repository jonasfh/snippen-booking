<?php
/**
 * AJAX endpoint for testing Vipps ePayment API connection.
 *
 * @package SnippenBooking\Api
 */

namespace SnippenBooking\Api;

use SnippenBooking\Helper\Security;
use SnippenBooking\Service\Vipps\VippsClient;

/**
 * VippsTestConnectionApi handles testing of Vipps API credentials from admin settings.
 */
class VippsTestConnectionApi {

	/**
	 * Register AJAX hooks.
	 */
	public static function register() {
		add_action( 'wp_ajax_snippen_vipps_test_connection', array( __CLASS__, 'test_connection' ) );
	}

	/**
	 * Handle AJAX test connection request.
	 */
	public static function test_connection() {
		Security::verify_ajax_nonce( 'snippen_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Ingen tilgang.', 'snippen-booking' ) ) );
		}

		$client_id        = isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : null;
		$client_secret    = isset( $_POST['client_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['client_secret'] ) ) : null;
		$subscription_key = isset( $_POST['subscription_key'] ) ? sanitize_text_field( wp_unslash( $_POST['subscription_key'] ) ) : null;
		$msn              = isset( $_POST['msn'] ) ? sanitize_text_field( wp_unslash( $_POST['msn'] ) ) : null;
		$environment      = isset( $_POST['environment'] ) ? sanitize_text_field( wp_unslash( $_POST['environment'] ) ) : null;

		$result = VippsClient::test_connection( $client_id, $client_secret, $subscription_key, $msn, $environment );

		if ( ! empty( $result['success'] ) ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}
}
