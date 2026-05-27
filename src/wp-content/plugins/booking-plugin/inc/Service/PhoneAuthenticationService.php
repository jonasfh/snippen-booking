<?php
/**
 * Phone Authentication Service
 *
 * @package SnippenBooking\Service
 */

namespace SnippenBooking\Service;

use SnippenBooking\Helper\PhoneHelper;

/**
 * Class PhoneAuthenticationService
 */
class PhoneAuthenticationService {

	/**
	 * Register hooks
	 */
	public static function register() {
		// Hook into authentication process
		add_filter( 'authenticate', array( __CLASS__, 'authenticate_by_phone' ), 20, 3 );

		// Hook into password reset user lookup
		add_action( 'lostpassword_user_data', array( __CLASS__, 'reset_password_by_phone' ), 10, 2 );

		// Modify login label
		add_filter( 'gettext', array( __CLASS__, 'modify_login_labels' ), 10, 3 );
	}

	/**
	 * Authenticate user by phone number
	 *
	 * @param \WP_User|\WP_Error|null $user     User object, WP_Error, or null.
	 * @param string                  $username Username or email (or phone).
	 * @param string                  $password Password.
	 * @return \WP_User|\WP_Error|null
	 */
	public static function authenticate_by_phone( $user, $username, $password ) {
		// If already authenticated by another method, return early
		if ( $user instanceof \WP_User ) {
			return $user;
		}

		if ( empty( $username ) || empty( $password ) ) {
			return $user;
		}

		// Check if input can be normalized as a phone number
		$normalized_phone = PhoneHelper::normalize_phone( $username );
		if ( ! $normalized_phone ) {
			return $user;
		}

		// Find user by normalized phone number
		$users = get_users(
			array(
				'meta_key'   => 'snippen_phone',
				'meta_value' => $normalized_phone,
				'number'     => 1,
			)
		);

		if ( ! empty( $users ) ) {
			$found_user = $users[0];

			// Verify password
			if ( wp_check_password( $password, $found_user->user_pass, $found_user->ID ) ) {
				return $found_user;
			} else {
				return new \WP_Error(
					'incorrect_password',
					sprintf(
						/* translators: %s: User phone number. */
						__( '<strong>Feil</strong>: Passordet du skrev inn for telefonnummeret %s er ikke riktig.', 'snippen-booking' ),
						'<strong>' . esc_html( $username ) . '</strong>'
					) . ' <a href="' . wp_lostpassword_url() . '">' . __( 'Mistet passordet ditt?', 'snippen-booking' ) . '</a>'
				);
			}
		}

		return $user;
	}

	/**
	 * Retrieve user data by phone number for password reset
	 *
	 * @param \WP_User|false|null $user_data WP_User object or false.
	 * @param string              $user_login The user login provided.
	 * @return \WP_User|false|null
	 */
	public static function reset_password_by_phone( $user_data, $user_login ) {
		// If user already found, return
		if ( $user_data ) {
			return $user_data;
		}

		$normalized_phone = PhoneHelper::normalize_phone( $user_login );
		if ( $normalized_phone ) {
			$users = get_users(
				array(
					'meta_key'   => 'snippen_phone',
					'meta_value' => $normalized_phone,
					'number'     => 1,
				)
			);

			if ( ! empty( $users ) ) {
				return $users[0];
			}
		}

		return $user_data;
	}

	/**
	 * Modify login label to include phone number
	 *
	 * @param string $translated_text Translated text.
	 * @param string $text            Original text.
	 * @param string $domain          Text domain.
	 * @return string
	 */
	public static function modify_login_labels( $translated_text, $text, $domain ) {
		if ( 'default' === $domain && in_array( $GLOBALS['pagenow'] ?? '', array( 'wp-login.php' ), true ) ) {
			if ( 'Username or Email Address' === $text || 'Brukernavn eller e-postadresse' === $text ) {
				return __( 'Brukernavn, e-post eller telefonnummer', 'snippen-booking' );
			}
		}
		return $translated_text;
	}
}
