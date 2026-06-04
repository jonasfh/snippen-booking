<?php
/**
 * Central Notification Manager Orchestrator
 *
 * @package SnippenBooking\Service\Notification
 */

namespace SnippenBooking\Service\Notification;

use SnippenBooking\Helper\Capabilities;

/**
 * Class NotificationManager
 */
class NotificationManager {

	/**
	 * Notification type: User account activation
	 */
	const TYPE_USER_ACTIVATION = 'user_activation';

	/**
	 * Notification type: Customer booking request confirmation
	 */
	const TYPE_BOOKING_CONFIRMATION = 'booking_confirmation';

	/**
	 * Notification type: Admin booking request alert
	 */
	const TYPE_ADMIN_BOOKING = 'admin_booking';

	/**
	 * Notification type: Password Reset
	 */
	const TYPE_PASSWORD_RESET = 'password_reset';

	/**
	 * Get all registered notification providers.
	 *
	 * @return NotificationProviderInterface[]
	 */
	public function get_providers(): array {
		$providers = array(
			new EmailProvider(),
			new KeySmsProvider(),
		);

		return apply_filters( 'snippen_booking_notification_providers', $providers );
	}

	/**
	 * Get a provider by its unique ID.
	 *
	 * @param string $id Provider ID.
	 * @return NotificationProviderInterface|null
	 */
	public function get_provider( string $id ): ?NotificationProviderInterface {
		foreach ( $this->get_providers() as $provider ) {
			if ( $provider->get_id() === $id ) {
				return $provider;
			}
		}
		return null;
	}

	/**
	 * Send an account confirmation verification code to a user.
	 *
	 * @param int    $user_id User ID.
	 * @param string $code    The 6-digit confirmation code.
	 * @return bool True on success, false on failure.
	 */
	public function send_account_confirmation( int $user_id, string $code ): bool {
		$phone = get_user_meta( $user_id, 'snippen_phone', true );
		$user  = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		$message = sprintf( __( 'Din bekreftelseskode for Snippen Booking er: %s. Koden er gyldig i 15 minutter.', 'snippen-booking' ), $code );
		$sms_enabled   = 'yes' === get_option( 'snippen_sms_user_activation_enabled', 'no' );
		$email_enabled = 'yes' === get_option( 'snippen_email_user_activation_enabled', 'yes' );

		$sms_sent   = false;
		$email_sent = false;

		// 1. Process SMS transport if active and configured
		if ( $sms_enabled && ! empty( $phone ) ) {
			$provider_id = get_option( 'snippen_active_notification_provider', 'keysms' );
			$provider    = $this->get_provider( $provider_id );

			if ( $provider instanceof SmsProviderInterface && $provider->is_configured() ) {
				$sms_sent = $provider->send_sms( $phone, $message );
				if ( ! $sms_sent ) {
					error_log( sprintf( 'NotificationManager: Failed to dispatch SMS via %s.', $provider_id ) );
				}
			}
		}

		// 2. Email fallback or direct Email transport.
		if ( $email_enabled || ( $sms_enabled && ! $sms_sent ) ) {
			$email_provider = $this->get_provider( 'email' );
			if ( $email_provider instanceof EmailProviderInterface && ! empty( $user->user_email ) ) {
				$subject    = __( 'Bekreftelseskode for Snippen Booking', 'snippen-booking' );
				$email_sent = $email_provider->send_email( $user->user_email, $subject, $message );
			}
		}

		return $sms_sent || $email_sent;
	}

	/**
	 * Send booking request notifications (to admins and the customer).
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $uuid       Booking UUID.
	 * @return bool True if customer notification succeeds, false otherwise.
	 */
	public function send_booking_notifications( int $booking_id, string $uuid ): bool {
		error_log( sprintf( 'NotificationManager: Preparing notifications for booking ID %d, UUID %s', $booking_id, $uuid ) );
		global $wpdb;

		$table_bookings = $wpdb->prefix . 'snippen_bookings';
		$table_junction = $wpdb->prefix . 'snippen_bookings_booking_objects';
		$table_objects  = $wpdb->prefix . 'snippen_booking_objects';

		$booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_bookings WHERE id = %d", $booking_id ) );
		if ( ! $booking ) {
			error_log( sprintf( 'NotificationManager Error: Booking ID %d not found.', $booking_id ) );
			return false;
		}

		// Fetch associated locales/objects
		$objs         = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT o.name 
				 FROM $table_junction bo 
				 JOIN $table_objects o ON bo.booking_object_id = o.id 
				 WHERE bo.booking_id = %d",
				$booking_id
			)
		);
		$object_names = implode( ' og ', $objs );
		error_log( sprintf( 'NotificationManager: Booking objects: %s', $object_names ) );

		$sms_enabled    = 'yes' === get_option( 'snippen_sms_booking_confirmation_enabled', 'no' );
		$email_enabled  = 'yes' === get_option( 'snippen_email_booking_confirmation_enabled', 'yes' );
		$email_provider = $this->get_provider( 'email' );

		// 1. Send admin notification alerts (always email if active)
		if ( $email_enabled && $email_provider instanceof EmailProviderInterface ) {
			$admin_users = get_users(
				array(
					'capability' => Capabilities::MANAGE_BOOKINGS,
				)
			);

			$admin_emails = array();
			foreach ( $admin_users as $admin ) {
				if ( ! empty( $admin->user_email ) ) {
					$admin_emails[] = $admin->user_email;
				}
			}
			error_log( sprintf( 'NotificationManager: Active admins found for email alert: %d (%s)', count( $admin_emails ), implode( ', ', $admin_emails ) ) );

			if ( ! empty( $admin_emails ) ) {
				$subject  = sprintf( __( 'Ny Bookingforespørsel - %s', 'snippen-booking' ), $object_names );
				$message  = __( 'Ny bookingforespørsel mottatt:', 'snippen-booking' ) . "\n\n";
				$message .= __( 'Lokale:', 'snippen-booking' ) . ' ' . $object_names . "\n";
				$message .= __( 'Dato:', 'snippen-booking' ) . ' ' . $booking->booking_date . "\n";
				$message .= __( 'Navn:', 'snippen-booking' ) . ' ' . $booking->customer_name . "\n";
				$message .= __( 'Email:', 'snippen-booking' ) . ' ' . $booking->customer_email . "\n";
				$message .= __( 'Telefon:', 'snippen-booking' ) . ' ' . $booking->customer_phone . "\n";
				$message .= __( 'Beskrivelse:', 'snippen-booking' ) . ' ' . $booking->description . "\n";

				foreach ( $admin_emails as $admin_email ) {
					try {
						error_log( sprintf( 'NotificationManager: Sending admin email to %s...', $admin_email ) );
						$email_provider->send_email( $admin_email, $subject, $message );
						error_log( sprintf( 'NotificationManager: Admin email call finished for %s', $admin_email ) );
					} catch ( \Exception $e ) {
						error_log( 'NotificationManager Exception during admin email send: ' . $e->getMessage() );
					} catch ( \Throwable $t ) {
						error_log( 'NotificationManager Throwable during admin email send: ' . $t->getMessage() );
					}
				}
			}
		}

		// 2. Send customer confirmation
		$sms_link   = add_query_arg( 'booking_uuid', $uuid, home_url( '/' ) );
		$sms_sent   = false;
		$email_sent = false;

		if ( $sms_enabled && ! empty( $booking->customer_phone ) ) {
			$sms_message = sprintf(
				__( 'Takk for din bookingforespørsel for %1$s den %2$s. Se detaljer: %3$s', 'snippen-booking' ),
				$object_names,
				$booking->booking_date,
				$sms_link
			);

			$provider_id = get_option( 'snippen_active_notification_provider', 'keysms' );
			$provider    = $this->get_provider( $provider_id );
			error_log( sprintf( 'NotificationManager: Active SMS sending starting with provider %s. Configured: %s', $provider_id, $provider && $provider->is_configured() ? 'yes' : 'no' ) );

			if ( $provider instanceof SmsProviderInterface && $provider->is_configured() ) {
				error_log( sprintf( 'NotificationManager: Sending SMS to %s...', $booking->customer_phone ) );
				$sms_sent = $provider->send_sms( $booking->customer_phone, $sms_message );
				error_log( sprintf( 'NotificationManager: SMS send call returned %s', $sms_sent ? 'true' : 'false' ) );
				if ( ! $sms_sent ) {
					error_log( sprintf( 'NotificationManager: Failed to dispatch SMS via %s.', $provider_id ) );
				}
			}
		}

		// Customer Email Fallback/Direct
		if ( $email_enabled || ( $sms_enabled && ! $sms_sent ) ) {
			if ( $email_provider instanceof EmailProviderInterface ) {
				$subject      = __( 'Bekreftelse på din bookingforespørsel', 'snippen-booking' );
				$mail_message = sprintf(
					__( "Takk for din bookingforespørsel for %1\$s den %2\$s.\n\nDu kan se detaljer om din booking her: %3\$s", 'snippen-booking' ),
					$object_names,
					$booking->booking_date,
					$sms_link
				);

				$recipient = $booking->customer_email;
				if ( empty( $recipient ) ) {
					$user = get_userdata( $booking->user_id );
					if ( $user ) {
						$recipient = $user->user_email;
					}
				}
				error_log( sprintf( 'NotificationManager: Attempting customer email fallback/direct to recipient %s', $recipient ?: 'not set' ) );

				if ( ! empty( $recipient ) ) {
					try {
						error_log( sprintf( 'NotificationManager: Sending customer email fallback/direct to %s...', $recipient ) );
						$email_sent = $email_provider->send_email( $recipient, $subject, $mail_message );
						error_log( sprintf( 'NotificationManager: Customer email call finished with result: %s', $email_sent ? 'true' : 'false' ) );
					} catch ( \Exception $e ) {
						error_log( 'NotificationManager Exception during customer email fallback: ' . $e->getMessage() );
					} catch ( \Throwable $t ) {
						error_log( 'NotificationManager Throwable during customer email fallback: ' . $t->getMessage() );
					}
				}
			}
		}

		return $sms_sent || $email_sent;
	}
}
