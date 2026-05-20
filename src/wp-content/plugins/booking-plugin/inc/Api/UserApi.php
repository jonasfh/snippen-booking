<?php

namespace SnippenBooking\Api;

use SnippenBooking\Helper\Capabilities;

/**
 * Handles user-related AJAX requests
 */
class UserApi {

	/**
	 * Register AJAX handlers
	 */
	public static function register() {
		add_action( 'wp_ajax_snippen_search_users', array( __CLASS__, 'search_users' ) );

		// Account confirmation
		add_action( 'wp_ajax_snippen_request_confirmation_code', array( __CLASS__, 'request_confirmation_code' ) );
		add_action( 'wp_ajax_nopriv_snippen_request_confirmation_code', array( __CLASS__, 'request_confirmation_code' ) );
		add_action( 'wp_ajax_snippen_verify_confirmation_code', array( __CLASS__, 'verify_confirmation_code' ) );
		add_action( 'wp_ajax_nopriv_snippen_verify_confirmation_code', array( __CLASS__, 'verify_confirmation_code' ) );
	}

	/**
	 * Search users by name, login or email
	 */
	public static function search_users() {
		if ( ! Capabilities::can_manage_bookings() ) {
			wp_send_json_error( array( 'message' => 'Ingen tilgang.' ) );
		}

		$search = isset( $_GET['term'] ) ? sanitize_text_field( $_GET['term'] ) : '';

		if ( strlen( $search ) < 2 ) {
			wp_send_json_success( array() );
		}

		$users = get_users(
			array(
				'search'         => '*' . $search . '*',
				'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
				'number'         => 10,
				'fields'         => array( 'ID', 'display_name', 'user_email' ),
			)
		);

		$results = array();
		foreach ( $users as $user ) {
			$results[] = array(
				'id'    => $user->ID,
				'name'  => $user->display_name,
				'email' => $user->user_email,
				'phone' => get_user_meta( $user->ID, 'snippen_phone', true ),
			);
		}

		wp_send_json_success( $results );
	}
	/**
	 * Request a confirmation code via SMS
	 */
	public static function request_confirmation_code() {
		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '';

		if ( empty( $phone ) ) {
			wp_send_json_error( array( 'message' => 'Telefonnummer mangler.' ) );
		}

		$normalized_phone = \SnippenBooking\Helper\PhoneHelper::normalize_phone( $phone );

		if ( ! $normalized_phone ) {
			wp_send_json_error( array( 'message' => 'Ugyldig telefonnummer. Kun norske nummer er støttet.' ) );
		}

		// Find user by phone number meta
		$users = get_users(
			array(
				'meta_key'   => 'snippen_phone',
				'meta_value' => $normalized_phone,
				'number'     => 1,
			)
		);

		if ( empty( $users ) ) {
			wp_send_json_error( array( 'message' => 'Fant ingen beboer med dette telefonnummeret. Kontakt administrator.' ) );
		}

		$user = $users[0];

		if ( get_user_meta( $user->ID, 'snippen_user_deleted', true ) === 'yes' ) {
			wp_send_json_error( array( 'message' => 'Denne beboeren er slettet eller deaktivert. Kontakt administrator.' ) );
		}

		$service = new \SnippenBooking\Service\AccountConfirmationService();

		if ( $service->is_confirmed( $user->ID ) ) {
			wp_send_json_error( array( 'message' => 'Kontoen er allerede bekreftet. Vennligst logg inn.' ) );
		}

		$sms_enabled = 'yes' === get_option( 'snippen_sms_account_confirmation_enabled' );
		if ( $service->send_code( $user->ID ) ) {
			wp_send_json_success(
				array(
					'message' => $sms_enabled ? __( 'Bekreftelseskode er sendt på SMS.', 'snippen-booking' ) : __( 'Bekreftelseskode er sendt på e-post.', 'snippen-booking' ),
					'user_id' => $user->ID,
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => $sms_enabled ? __( 'Kunne ikke sende SMS. Kontakt administrator.', 'snippen-booking' ) : __( 'Kunne ikke sende e-post. Kontakt administrator.', 'snippen-booking' ),
				)
			);
		}
	}

	/**
	 * Verify confirmation code and set password
	 */
	public static function verify_confirmation_code() {
		$user_id  = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
		$code     = isset( $_POST['code'] ) ? sanitize_text_field( $_POST['code'] ) : '';
		$password = isset( $_POST['password'] ) ? $_POST['password'] : '';

		if ( ! $user_id || empty( $code ) || empty( $password ) ) {
			wp_send_json_error( array( 'message' => 'Mangler nødvendige felt.' ) );
		}

		$service = new \SnippenBooking\Service\AccountConfirmationService();

		if ( ! $service->verify_code( $user_id, $code ) ) {
			wp_send_json_error( array( 'message' => 'Ugyldig eller utløpt kode.' ) );
		}

		$result = $service->confirm_account( $user_id, $password );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => 'Din konto er nå bekreftet! Du kan nå logge inn.' ) );
	}
}
