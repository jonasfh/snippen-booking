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

		// AJAX Login endpoint
		add_action( 'wp_ajax_snippen_login', array( __CLASS__, 'login' ) );
		add_action( 'wp_ajax_nopriv_snippen_login', array( __CLASS__, 'login' ) );
	}

	/**
	 * Search users by name, login or email
	 */
	public static function search_users() {
		check_ajax_referer( 'snippen_admin_nonce', 'nonce' );

		if ( ! Capabilities::can_manage_bookings() ) {
			wp_send_json_error( array( 'message' => __( 'Ingen tilgang.', 'snippen-booking' ) ) );
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
		check_ajax_referer( 'snippen_confirmation_nonce', 'nonce' );

		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '';

		if ( empty( $phone ) ) {
			wp_send_json_error( array( 'message' => __( 'Telefonnummer mangler.', 'snippen-booking' ) ) );
		}

		$normalized_phone = \SnippenBooking\Helper\PhoneHelper::normalize_phone( $phone );

		if ( ! $normalized_phone ) {
			wp_send_json_error( array( 'message' => __( 'Ugyldig telefonnummer. Kun norske nummer er støttet.', 'snippen-booking' ) ) );
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
			wp_send_json_error( array( 'message' => __( 'Fant ingen beboer med dette telefonnummeret. Kontakt administrator.', 'snippen-booking' ) ) );
		}

		$user = $users[0];

		if ( get_user_meta( $user->ID, 'snippen_user_deleted', true ) === 'yes' ) {
			wp_send_json_error( array( 'message' => __( 'Denne beboeren er slettet eller deaktivert. Kontakt administrator.', 'snippen-booking' ) ) );
		}

		$service = new \SnippenBooking\Service\AccountConfirmationService();

		if ( $service->is_confirmed( $user->ID ) ) {
			wp_send_json_error( array( 'message' => __( 'Kontoen er allerede bekreftet. Vennligst logg inn.', 'snippen-booking' ) ) );
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
		check_ajax_referer( 'snippen_confirmation_nonce', 'nonce' );

		$user_id  = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
		$code     = isset( $_POST['code'] ) ? sanitize_text_field( $_POST['code'] ) : '';
		$password = isset( $_POST['password'] ) ? $_POST['password'] : '';

		if ( ! $user_id || empty( $code ) || empty( $password ) ) {
			wp_send_json_error( array( 'message' => __( 'Mangler nødvendige felt.', 'snippen-booking' ) ) );
		}

		$service = new \SnippenBooking\Service\AccountConfirmationService();

		if ( ! $service->verify_code( $user_id, $code ) ) {
			wp_send_json_error( array( 'message' => __( 'Ugyldig eller utløpt kode.', 'snippen-booking' ) ) );
		}

		$result = $service->confirm_account( $user_id, $password );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Din konto er nå bekreftet! Du kan nå logge inn.', 'snippen-booking' ) ) );
	}

	/**
	 * Process AJAX login
	 */
	public static function login() {
		if ( false === check_ajax_referer( 'snippen_login_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Sikkerhetssjekk feilet (ugyldig nonce). Vennligst laste siden på nytt.', 'snippen-booking' ) ) );
		}

		$log = isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotUnslashed
		$pwd         = isset( $_POST['pwd'] ) ? $_POST['pwd'] : '';
		$rememberme  = isset( $_POST['rememberme'] ) && 'forever' === $_POST['rememberme'];
		$redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : home_url( '/' );

		if ( empty( $log ) || empty( $pwd ) ) {
			wp_send_json_error( array( 'message' => __( 'Vennligst oppgi brukernavn/e-post/telefonnummer og passord.', 'snippen-booking' ) ) );
		}

		$user = wp_authenticate( $log, $pwd );

		if ( is_wp_error( $user ) ) {
			$error_message = wp_strip_all_tags( $user->get_error_message() );
			wp_send_json_error( array( 'message' => $error_message ) );
		}

		wp_set_current_user( $user->ID, $user->user_login );
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@wp_set_auth_cookie( $user->ID, $rememberme );
		do_action( 'wp_login', $user->user_login, $user );

		wp_send_json_success(
			array(
				'message'      => __( 'Innlogging vellykket! Omdirigerer...', 'snippen-booking' ),
				'redirect_url' => $redirect_to,
			)
		);
	}
}
