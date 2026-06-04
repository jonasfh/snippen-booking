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

		// Hook into password reset message and title
		add_filter( 'retrieve_password_message', array( __CLASS__, 'filter_password_reset_message' ), 10, 4 );
		add_filter( 'retrieve_password_title', array( __CLASS__, 'filter_password_reset_title' ), 10, 3 );
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
	 * @param \WP_Error           $errors    WP_Error object containing any errors.
	 * @return \WP_User|false|null
	 */
	public static function reset_password_by_phone( $user_data, $errors ) {
		// If user already found, return
		if ( $user_data ) {
			return $user_data;
		}

		$user_login = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) : '';

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

	/**
	 * Filter password reset email title
	 *
	 * @param string   $title      Default title.
	 * @param string   $user_login User login.
	 * @param \WP_User $user_data  User object.
	 * @return string
	 */
	public static function filter_password_reset_title( $title, $user_login, $user_data ) {
		$template_service = new \SnippenBooking\Service\Notification\NotificationTemplateService();
		$context          = array(
			'user_name'  => $user_data->display_name ?: $user_data->user_login,
			'reset_link' => '',
		);
		$rendered         = $template_service->render_template( 'password_reset', 'email', $context );

		if ( ! empty( $rendered['subject'] ) ) {
			return $rendered['subject'];
		}

		return $title;
	}

	/**
	 * Filter password reset email message. Replaces email with SMS if requested via phone.
	 *
	 * @param string   $message    Default message.
	 * @param string   $key        Reset key.
	 * @param string   $user_login User login.
	 * @param \WP_User $user_data  User object.
	 * @return string|false
	 */
	public static function filter_password_reset_message( $message, $key, $user_login, $user_data ) {
		$template_service     = new \SnippenBooking\Service\Notification\NotificationTemplateService();
		$notification_manager = new \SnippenBooking\Service\Notification\NotificationManager();

		// $user_login here might have been redefined by WP core to the actual user_login.
		// We use network_site_url because wp-login.php uses this.
		$reset_link = network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user_login ), 'login' );

		$context = array(
			'user_name'  => $user_data->display_name ?: $user_data->user_login,
			'reset_link' => $reset_link,
		);

		// Determine if the user actually typed a phone number in the lost password form.
		$posted_login     = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) : '';
		$normalized_phone = PhoneHelper::normalize_phone( $posted_login );

		if ( $normalized_phone && $normalized_phone === get_user_meta( $user_data->ID, 'snippen_phone', true ) ) {
			// Request was made via phone.
			$sms_active = 'yes' === get_option( 'snippen_keysms_notifications_enabled', 'no' );

			if ( $sms_active ) {
				$rendered = $template_service->render_template( 'password_reset', 'sms', $context );
				$provider_id = get_option( 'snippen_active_notification_provider', 'keysms' );
				$provider    = $notification_manager->get_provider( $provider_id );

				if ( $provider instanceof \SnippenBooking\Service\Notification\SmsProviderInterface && $provider->is_configured() ) {
					$success = $provider->send_sms( $normalized_phone, $rendered['body'] );
					if ( $success ) {
						// Return false to abort WordPress sending the default email.
						return false;
					}
					error_log( 'PhoneAuth: Failed to send SMS password reset. Attempting email fallback.' );
				}
			}
		}

		// If requested via email (or SMS failed), use the email template.
		$email_rendered = $template_service->render_template( 'password_reset', 'email', $context );

		if ( ! empty( $email_rendered['body'] ) ) {
			return $email_rendered['body'];
		}

		return $message;
	}
}
