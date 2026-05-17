<?php
/**
 * Service for user account confirmation via SMS
 *
 * @package SnippenBooking\Service
 */

namespace SnippenBooking\Service;

/**
 * Account Confirmation Service
 */
class AccountConfirmationService {

	/**
	 * SMS Service
	 *
	 * @var SmsServiceInterface
	 */
	private $sms_service;

	/**
	 * Constructor
	 *
	 * @param SmsServiceInterface $sms_service Optional SMS service.
	 */
	public function __construct( SmsServiceInterface $sms_service = null ) {
		$this->sms_service = $sms_service ?: new KeySmsService();
	}

	/**
	 * Generate and store a 6-digit confirmation code for a user
	 *
	 * @param int $user_id User ID.
	 * @return string The generated code.
	 */
	public function generate_code( int $user_id ): string {
		$code = sprintf( '%06d', wp_rand( 0, 999999 ) );

		update_user_meta( $user_id, 'snippen_confirmation_code', $code );
		update_user_meta( $user_id, 'snippen_confirmation_code_expiry', time() + ( 15 * MINUTE_IN_SECONDS ) );

		return $code;
	}

	/**
	 * Send confirmation code via SMS
	 *
	 * @param int $user_id User ID.
	 * @return bool True on success, false on failure.
	 */
	public function send_code( int $user_id ): bool {
		if ( 'yes' !== get_option( 'snippen_sms_account_confirmation_enabled' ) ) {
			return false;
		}

		$phone = get_user_meta( $user_id, 'snippen_phone', true );
		if ( empty( $phone ) ) {
			return false;
		}

		$code    = $this->generate_code( $user_id );
		$message = sprintf( __( 'Din bekreftelseskode for Snippen Booking er: %s. Koden er gyldig i 15 minutter.', 'snippen-booking' ), $code );

		return $this->sms_service->send( $phone, $message );
	}

	/**
	 * Verify confirmation code
	 *
	 * @param int    $user_id User ID.
	 * @param string $code    Code to verify.
	 * @return bool True if valid, false otherwise.
	 */
	public function verify_code( int $user_id, string $code ): bool {
		$stored_code = get_user_meta( $user_id, 'snippen_confirmation_code', true );
		$expiry      = get_user_meta( $user_id, 'snippen_confirmation_code_expiry', true );

		if ( empty( $stored_code ) || $stored_code !== $code ) {
			return false;
		}

		if ( time() > (int) $expiry ) {
			return false;
		}

		return true;
	}

	/**
	 * Confirm account and set password
	 *
	 * @param int    $user_id  User ID.
	 * @param string $password New password.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public function confirm_account( int $user_id, string $password ) {
		// Basic password validation
		if ( strlen( $password ) < 8 ) {
			return new \WP_Error( 'password_too_short', __( 'Passordet må være minst 8 tegn langt.', 'snippen-booking' ) );
		}

		// Update password
		wp_set_password( $password, $user_id );

		// Mark as confirmed
		update_user_meta( $user_id, 'snippen_account_confirmed', 'yes' );

		// Clear code
		delete_user_meta( $user_id, 'snippen_confirmation_code' );
		delete_user_meta( $user_id, 'snippen_confirmation_code_expiry' );

		return true;
	}

	/**
	 * Check if user account is confirmed
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function is_confirmed( int $user_id ): bool {
		return 'yes' === get_user_meta( $user_id, 'snippen_account_confirmed', true );
	}
}
