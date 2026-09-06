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
	 * Notification type: Payment Reminder
	 */
	const TYPE_PAYMENT_REMINDER = 'payment_reminder';

	/**
	 * Notification type: Payment Receipt Uploaded Alert
	 */
	const TYPE_PAYMENT_RECEIPT_UPLOADED = 'payment_receipt_uploaded';

	/**
	 * Notification type: Booking confirmed (approved by admin)
	 */
	const TYPE_BOOKING_CONFIRMED = 'booking_confirmed';

	/**
	 * Notification type: Payment received (marked as PAID)
	 */
	const TYPE_PAYMENT_RECEIVED = 'payment_received';

	/**
	 * Get all registered notification providers.
	 *
	 * @return NotificationProviderInterface[]
	 */
	public function get_providers(): array {
		$providers = array(
			new EmailProvider(),
			new KeySmsProvider(),
			new SnippenSmsProvider(),
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
	 * Determine initial logged status for an SMS dispatch attempt.
	 *
	 * @param bool   $success     Whether the provider accepted/sent the SMS.
	 * @param string $provider_id ID of the active provider.
	 * @return string 'queued', 'sent', or 'failed'.
	 */
	private function get_sms_initial_status( bool $success, string $provider_id ): string {
		if ( ! $success ) {
			return 'failed';
		}
		if ( 'snippen_sms_service' === $provider_id ) {
			return 'queued';
		}
		return 'sent';
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

		$template_service = new NotificationTemplateService();
		$context          = array(
			'user_name'         => $user->display_name ?: $user->user_login,
			'confirmation_code' => $code,
		);
		$rendered_sms     = $template_service->render_template( 'user_activation', 'sms', $context );
		$rendered_email   = $template_service->render_template( 'user_activation', 'email', $context );

		$sms_enabled   = 'yes' === get_option( 'snippen_sms_user_activation_enabled', 'no' );
		$email_enabled = 'yes' === get_option( 'snippen_email_user_activation_enabled', 'yes' );

		$sms_sent   = false;
		$email_sent = false;

		// 1. Process SMS transport if active and configured
		if ( $sms_enabled && ! empty( $phone ) ) {
			$provider_id = get_option( 'snippen_active_notification_provider', 'keysms' );
			$provider    = $this->get_provider( $provider_id );

			if ( $provider instanceof SmsProviderInterface && $provider->is_configured() ) {
				$sms_sent = $provider->send_sms( $phone, $rendered_sms['body'] );
				MessageLoggerService::log_message(
					null,
					$user_id,
					'sms',
					$phone,
					null,
					$rendered_sms['body'],
					self::TYPE_USER_ACTIVATION,
					$this->get_sms_initial_status( $sms_sent, $provider_id ),
					array( 'provider' => $provider_id )
				);
				if ( ! $sms_sent ) {
					error_log( sprintf( 'NotificationManager: Failed to dispatch SMS via %s.', $provider_id ) );
				}
			}
		}

		// 2. Email fallback or direct Email transport.
		if ( $email_enabled || ( $sms_enabled && ! $sms_sent ) ) {
			$email_provider = $this->get_provider( 'email' );
			if ( $email_provider instanceof EmailProviderInterface && ! empty( $user->user_email ) ) {
				$subject    = ! empty( $rendered_email['subject'] ) ? $rendered_email['subject'] : __( 'Bekreftelseskode for Snippen Booking', 'snippen-booking' );
				$email_sent = $email_provider->send_email( $user->user_email, $subject, $rendered_email['body'] );
				MessageLoggerService::log_message(
					null,
					$user_id,
					'email',
					$user->user_email,
					$subject,
					$rendered_email['body'],
					self::TYPE_USER_ACTIVATION,
					$email_sent ? 'sent' : 'failed'
				);
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

		$sms_enabled       = 'yes' === get_option( 'snippen_sms_booking_confirmation_enabled', 'no' );
		$email_enabled     = 'yes' === get_option( 'snippen_email_booking_confirmation_enabled', 'yes' );
		$email_admin       = 'yes' === get_option( 'snippen_email_admin_booking_enabled', 'yes' );
		$sms_admin_enabled = 'yes' === get_option( 'snippen_sms_admin_booking_enabled', 'no' );

		$email_provider   = $this->get_provider( 'email' );
		$template_service = new NotificationTemplateService();

		// Fetch booking time string from snapshot or slot
		$booking_time = '';
		if ( ! empty( $booking->booking_snapshot ) ) {
			$snapshot = json_decode( $booking->booking_snapshot, true );
			if ( is_array( $snapshot ) && ! empty( $snapshot['time_range_formatted'] ) ) {
				$booking_time = $snapshot['time_range_formatted'];
			}
		}
		if ( empty( $booking_time ) && ! empty( $booking->slot_id ) ) {
			$table_slots = $wpdb->prefix . 'snippen_time_slots';
			$slot        = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_slots WHERE id = %d", $booking->slot_id ) );
			if ( $slot ) {
				$booking_time = sprintf( '%s - %s', date_i18n( 'H:i', strtotime( $slot->start_time ) ), date_i18n( 'H:i', strtotime( $slot->end_time ) ) );
			}
		}

		$admin_context        = array(
			'user_name'           => $booking->customer_name,
			'user_email'          => $booking->customer_email,
			'user_phone'          => $booking->customer_phone,
			'booking_objects'     => $object_names,
			'booking_date'        => $booking->booking_date,
			'booking_time'        => $booking_time,
			'booking_description' => $booking->description,
		);
		$rendered_admin_email = $template_service->render_template( 'admin_booking', 'email', $admin_context );
		$rendered_admin_sms   = $template_service->render_template( 'admin_booking', 'sms', $admin_context );

		// Retrieve all admin users
		$admin_users = get_users(
			array(
				'capability' => Capabilities::MANAGE_BOOKINGS,
			)
		);

		// 1. Send admin notification email alerts
		if ( $email_admin && $email_provider instanceof EmailProviderInterface && ! empty( $admin_users ) ) {
			$admin_emails = array();
			foreach ( $admin_users as $admin ) {
				if ( ! empty( $admin->user_email ) ) {
					$admin_emails[] = $admin->user_email;
				}
			}
			error_log( sprintf( 'NotificationManager: Active admins found for email alert: %d (%s)', count( $admin_emails ), implode( ', ', $admin_emails ) ) );

			if ( ! empty( $admin_emails ) ) {
				$subject = ! empty( $rendered_admin_email['subject'] ) ? $rendered_admin_email['subject'] : sprintf( __( 'Ny Bookingforespørsel - %s', 'snippen-booking' ), $object_names );
				$message = $rendered_admin_email['body'];

				foreach ( $admin_emails as $admin_email ) {
					try {
						error_log( sprintf( 'NotificationManager: Sending admin email to %s...', $admin_email ) );
						$sent = $email_provider->send_email( $admin_email, $subject, $message );
						MessageLoggerService::log_message(
							$booking_id,
							$booking->user_id ? (int) $booking->user_id : null,
							'email',
							$admin_email,
							$subject,
							$message,
							self::TYPE_ADMIN_BOOKING,
							$sent ? 'sent' : 'failed'
						);
						error_log( sprintf( 'NotificationManager: Admin email call finished for %s', $admin_email ) );
					} catch ( \Exception $e ) {
						error_log( 'NotificationManager Exception during admin email send: ' . $e->getMessage() );
					} catch ( \Throwable $t ) {
						error_log( 'NotificationManager Throwable during admin email send: ' . $t->getMessage() );
					}
				}
			}
		}

		// 2. Send admin notification SMS alerts
		if ( $sms_admin_enabled && ! empty( $admin_users ) ) {
			$provider_id  = get_option( 'snippen_active_notification_provider', 'keysms' );
			$sms_provider = $this->get_provider( $provider_id );

			if ( $sms_provider instanceof SmsProviderInterface && $sms_provider->is_configured() ) {
				$admin_phones = array();
				foreach ( $admin_users as $admin ) {
					$admin_phone = get_user_meta( $admin->ID, 'snippen_phone', true );
					if ( ! empty( $admin_phone ) ) {
						$admin_phones[] = $admin_phone;
					}
				}
				error_log( sprintf( 'NotificationManager: Active admins found for SMS alert: %d (%s)', count( $admin_phones ), implode( ', ', $admin_phones ) ) );

				if ( ! empty( $admin_phones ) ) {
					$admin_sms_message = $rendered_admin_sms['body'];

					foreach ( $admin_phones as $admin_phone ) {
						try {
							error_log( sprintf( 'NotificationManager: Sending admin SMS to %s...', $admin_phone ) );
							$sms_res = $sms_provider->send_sms( $admin_phone, $admin_sms_message );
							MessageLoggerService::log_message(
								$booking_id,
								$booking->user_id ? (int) $booking->user_id : null,
								'sms',
								$admin_phone,
								null,
								$admin_sms_message,
								self::TYPE_ADMIN_BOOKING,
								$this->get_sms_initial_status( $sms_res, $provider_id ),
								array( 'provider' => $provider_id )
							);
						} catch ( \Exception $e ) {
							error_log( 'NotificationManager Exception during admin SMS send: ' . $e->getMessage() );
						} catch ( \Throwable $t ) {
							error_log( 'NotificationManager Throwable during admin SMS send: ' . $t->getMessage() );
						}
					}
				}
			}
		}

		// 2. Send customer confirmation using booking_confirmation NotificationTemplateService
		$sms_link   = add_query_arg( 'booking_uuid', $uuid, home_url( '/' ) );
		$sms_sent   = false;
		$email_sent = false;

		$template_service             = new NotificationTemplateService();
		$default_payment_instructions = __( 'Vennligst overfør leiebeløpet innen 3 dager fra booking. Merk betalingen med ditt navn eller booking-ID.', 'snippen-booking' );
		$context                      = array(
			'user_name'            => $booking->customer_name,
			'booking_objects'      => $object_names,
			'booking_date'         => $booking->booking_date,
			'booking_time'         => $booking_time,
			'booking_url'          => $sms_link,
			'booking_price'        => number_format( $booking->price, 0, ',', ' ' ),
			'bank_account'         => get_option( 'snippen_payment_bank_account', '' ),
			'vipps_number'         => get_option( 'snippen_payment_vipps_number', '' ),
			'payment_instructions' => get_option( 'snippen_payment_instructions', $default_payment_instructions ),
		);

		$rendered_sms   = $template_service->render_template( 'booking_confirmation', 'sms', $context );
		$rendered_email = $template_service->render_template( 'booking_confirmation', 'email', $context );

		if ( $sms_enabled && ! empty( $booking->customer_phone ) ) {
			$sms_message = $rendered_sms['body'];

			$provider_id = get_option( 'snippen_active_notification_provider', 'keysms' );
			$provider    = $this->get_provider( $provider_id );
			error_log( sprintf( 'NotificationManager: Active SMS sending starting with provider %s. Configured: %s', $provider_id, $provider && $provider->is_configured() ? 'yes' : 'no' ) );

			if ( $provider instanceof SmsProviderInterface && $provider->is_configured() ) {
				error_log( sprintf( 'NotificationManager: Sending SMS to %s...', $booking->customer_phone ) );
				$sms_sent = $provider->send_sms( $booking->customer_phone, $sms_message );
				MessageLoggerService::log_message(
					$booking_id,
					$booking->user_id ? (int) $booking->user_id : null,
					'sms',
					$booking->customer_phone,
					null,
					$sms_message,
					self::TYPE_BOOKING_CONFIRMATION,
					$this->get_sms_initial_status( $sms_sent, $provider_id ),
					array( 'provider' => $provider_id )
				);
				error_log( sprintf( 'NotificationManager: SMS send call returned %s', $sms_sent ? 'true' : 'false' ) );
				if ( ! $sms_sent ) {
					error_log( sprintf( 'NotificationManager: Failed to dispatch SMS via %s.', $provider_id ) );
				}
			}
		}

		// Customer Email Fallback/Direct
		if ( $email_enabled || ( $sms_enabled && ! $sms_sent ) ) {
			if ( $email_provider instanceof EmailProviderInterface ) {
				$subject      = ! empty( $rendered_email['subject'] ) ? $rendered_email['subject'] : __( 'Bekreftelse på din bookingforespørsel', 'snippen-booking' );
				$mail_message = $rendered_email['body'];

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
						MessageLoggerService::log_message(
							$booking_id,
							$booking->user_id ? (int) $booking->user_id : null,
							'email',
							$recipient,
							$subject,
							$mail_message,
							self::TYPE_BOOKING_CONFIRMATION,
							$email_sent ? 'sent' : 'failed'
						);
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

	/**
	 * Send payment reminder notification to customer for a booking.
	 *
	 * @param int $booking_id  Booking ID.
	 * @param int $days_before Interval days before booking date (optional).
	 * @return bool True if SMS or email was sent successfully, false otherwise.
	 */
	public function send_payment_reminder( int $booking_id, int $days_before = 0 ): bool {
		error_log( sprintf( 'NotificationManager: Preparing payment reminder for booking ID %d (%d days before)', $booking_id, $days_before ) );
		global $wpdb;

		$table_bookings = $wpdb->prefix . 'snippen_bookings';
		$table_junction = $wpdb->prefix . 'snippen_bookings_booking_objects';
		$table_objects  = $wpdb->prefix . 'snippen_booking_objects';

		$booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_bookings} WHERE id = %d", $booking_id ) );
		if ( ! $booking ) {
			error_log( sprintf( 'NotificationManager Error: Booking ID %d not found for payment reminder.', $booking_id ) );
			return false;
		}

		// Fetch associated locales/objects
		$objs         = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT o.name 
				 FROM {$table_junction} bo 
				 JOIN {$table_objects} o ON bo.booking_object_id = o.id 
				 WHERE bo.booking_id = %d",
				$booking_id
			)
		);
		$object_names = implode( ' og ', $objs );

		$sms_enabled   = 'yes' === get_option( 'snippen_sms_payment_reminder_enabled', 'no' );
		$email_enabled = 'yes' === get_option( 'snippen_email_payment_reminder_enabled', 'yes' );

		$email_provider   = $this->get_provider( 'email' );
		$template_service = new NotificationTemplateService();

		// Fetch booking time string
		$booking_time = '';
		if ( ! empty( $booking->booking_snapshot ) ) {
			$snapshot = json_decode( $booking->booking_snapshot, true );
			if ( is_array( $snapshot ) && ! empty( $snapshot['time_range_formatted'] ) ) {
				$booking_time = $snapshot['time_range_formatted'];
			}
		}
		if ( empty( $booking_time ) && ! empty( $booking->slot_id ) ) {
			$table_slots = $wpdb->prefix . 'snippen_time_slots';
			$slot        = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_slots} WHERE id = %d", $booking->slot_id ) );
			if ( $slot ) {
				$booking_time = sprintf( '%s - %s', date_i18n( 'H:i', strtotime( $slot->start_time ) ), date_i18n( 'H:i', strtotime( $slot->end_time ) ) );
			}
		}

		$booking_url                  = add_query_arg( 'booking_uuid', $booking->uuid, home_url( '/' ) );
		$default_payment_instructions = __( 'Vennligst overfør leiebeløpet innen kort tid. Merk betalingen med ditt navn eller booking-ID.', 'snippen-booking' );

		$context = array(
			'user_name'            => $booking->customer_name,
			'user_email'           => $booking->customer_email,
			'user_phone'           => $booking->customer_phone,
			'booking_objects'      => $object_names,
			'booking_date'         => $booking->booking_date,
			'booking_time'         => $booking_time,
			'booking_url'          => $booking_url,
			'booking_price'        => number_format( (float) $booking->price, 0, ',', ' ' ),
			'bank_account'         => get_option( 'snippen_payment_bank_account', '' ),
			'vipps_number'         => get_option( 'snippen_payment_vipps_number', '' ),
			'payment_instructions' => get_option( 'snippen_payment_instructions', $default_payment_instructions ),
		);

		$rendered_sms   = $template_service->render_template( 'payment_reminder', 'sms', $context );
		$rendered_email = $template_service->render_template( 'payment_reminder', 'email', $context );

		$sms_sent   = false;
		$email_sent = false;

		// 1. Send SMS reminder
		if ( $sms_enabled && ! empty( $booking->customer_phone ) ) {
			$provider_id  = get_option( 'snippen_active_notification_provider', 'keysms' );
			$sms_provider = $this->get_provider( $provider_id );

			if ( $sms_provider instanceof SmsProviderInterface && $sms_provider->is_configured() ) {
				$sms_sent = $sms_provider->send_sms( $booking->customer_phone, $rendered_sms['body'] );
				MessageLoggerService::log_message(
					$booking_id,
					$booking->user_id ? (int) $booking->user_id : null,
					'sms',
					$booking->customer_phone,
					null,
					$rendered_sms['body'],
					self::TYPE_PAYMENT_REMINDER,
					$this->get_sms_initial_status( $sms_sent, $provider_id ),
					array(
						'provider'    => $provider_id,
						'days_before' => $days_before,
					)
				);
			}
		}

		// 2. Send Email reminder
		if ( $email_enabled || ( $sms_enabled && ! $sms_sent ) ) {
			if ( $email_provider instanceof EmailProviderInterface ) {
				$recipient = $booking->customer_email;
				if ( empty( $recipient ) && ! empty( $booking->user_id ) ) {
					$user = get_userdata( $booking->user_id );
					if ( $user ) {
						$recipient = $user->user_email;
					}
				}

				if ( ! empty( $recipient ) ) {
					$subject    = ! empty( $rendered_email['subject'] ) ? $rendered_email['subject'] : __( 'Betalingspåminnelse for din booking', 'snippen-booking' );
					$email_sent = $email_provider->send_email( $recipient, $subject, $rendered_email['body'] );
					MessageLoggerService::log_message(
						$booking_id,
						$booking->user_id ? (int) $booking->user_id : null,
						'email',
						$recipient,
						$subject,
						$rendered_email['body'],
						self::TYPE_PAYMENT_REMINDER,
						$email_sent ? 'sent' : 'failed',
						array( 'days_before' => $days_before )
					);
				}
			}
		}

		return $sms_sent || $email_sent;
	}

	/**
	 * Send notification to admins when a user uploads payment receipt
	 *
	 * @param int $booking_id Booking ID.
	 * @return bool True if at least one notification sent successfully.
	 */
	public function send_payment_receipt_uploaded_notification( int $booking_id ): bool {
		$booking_repository = new \SnippenBooking\Database\Repository\BookingRepository();
		$booking            = $booking_repository->find( $booking_id );

		if ( ! $booking ) {
			return false;
		}

		$email_enabled = get_option( 'snippen_email_payment_receipt_uploaded_enabled', 'yes' ) === 'yes';
		$sms_enabled   = get_option( 'snippen_sms_payment_receipt_uploaded_enabled', 'no' ) === 'yes';

		if ( ! $email_enabled && ! $sms_enabled ) {
			return false;
		}

		$object_repository = new \SnippenBooking\Database\Repository\BookingObjectRepository();
		$objects           = $object_repository->find_by_ids( $booking->object_ids );
		$object_names      = implode( ', ', array_column( $objects, 'name' ) );

		$booking_time = $booking->start_time . ' - ' . $booking->end_time;
		if ( ! empty( $booking->is_all_day ) ) {
			$booking_time = __( 'Hele dagen', 'snippen-booking' );
		}

		$booking_url = add_query_arg( 'booking_uuid', $booking->uuid, home_url( '/' ) );

		$context = array(
			'user_name'       => $booking->customer_name,
			'user_email'      => $booking->customer_email,
			'user_phone'      => $booking->customer_phone,
			'booking_objects' => $object_names,
			'booking_date'    => $booking->booking_date,
			'booking_time'    => $booking_time,
			'booking_url'     => $booking_url,
			'booking_price'   => number_format( (float) $booking->price, 0, ',', ' ' ),
		);

		$template_service = new NotificationTemplateService();
		$rendered_sms     = $template_service->render_template( 'payment_receipt_uploaded', 'sms', $context );
		$rendered_email   = $template_service->render_template( 'payment_receipt_uploaded', 'email', $context );

		$admin_emails = get_option( 'snippen_payment_admin_emails', '' );
		if ( empty( $admin_emails ) ) {
			$admin_emails = get_option( 'admin_email' );
		}

		$admin_email_list = array_filter( array_map( 'trim', explode( ',', (string) $admin_emails ) ) );

		$sms_sent   = false;
		$email_sent = false;

		// 1. Email notification
		if ( $email_enabled && ! empty( $admin_email_list ) ) {
			$email_provider = $this->get_provider( 'email' );
			if ( $email_provider instanceof EmailProviderInterface ) {
				$subject = ! empty( $rendered_email['subject'] ) ? $rendered_email['subject'] : __( 'Ny betalingskvittering lastet opp', 'snippen-booking' );
				foreach ( $admin_email_list as $admin_email ) {
					$sent = $email_provider->send_email( $admin_email, $subject, $rendered_email['body'] );
					MessageLoggerService::log_message(
						$booking_id,
						$booking->user_id ? (int) $booking->user_id : null,
						'email',
						$admin_email,
						$subject,
						$rendered_email['body'],
						self::TYPE_PAYMENT_RECEIPT_UPLOADED,
						$sent ? 'sent' : 'failed'
					);
					if ( $sent ) {
						$email_sent = true;
					}
				}
			}
		}

		// 2. SMS notification
		if ( $sms_enabled ) {
			$admin_users  = get_users( array( 'role' => 'administrator' ) );
			$admin_phones = array();
			foreach ( $admin_users as $admin ) {
				$phone = get_user_meta( $admin->ID, 'snippen_phone', true );
				if ( ! empty( $phone ) ) {
					$admin_phones[] = $phone;
				}
			}

			if ( ! empty( $admin_phones ) ) {
				$provider_id  = get_option( 'snippen_active_notification_provider', 'keysms' );
				$sms_provider = $this->get_provider( $provider_id );
				if ( $sms_provider instanceof SmsProviderInterface && $sms_provider->is_configured() ) {
					foreach ( $admin_phones as $admin_phone ) {
						$sent = $sms_provider->send_sms( $admin_phone, $rendered_sms['body'] );
						MessageLoggerService::log_message(
							$booking_id,
							$booking->user_id ? (int) $booking->user_id : null,
							'sms',
							$admin_phone,
							null,
							$rendered_sms['body'],
							self::TYPE_PAYMENT_RECEIPT_UPLOADED,
							$this->get_sms_initial_status( $sent, $provider_id ),
							array( 'provider' => $provider_id )
						);
						if ( $sent ) {
							$sms_sent = true;
						}
					}
				}
			}
		}

		return $email_sent || $sms_sent;
	}

	/**
	 * Send notification to customer when booking is confirmed/approved by admin.
	 *
	 * @param int $booking_id Booking ID.
	 * @return bool True if at least one notification was sent successfully.
	 */
	public function send_booking_confirmed_notification( int $booking_id ): bool {
		global $wpdb;

		$table_bookings = $wpdb->prefix . 'snippen_bookings';
		$table_junction = $wpdb->prefix . 'snippen_bookings_booking_objects';
		$table_objects  = $wpdb->prefix . 'snippen_booking_objects';

		$booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_bookings} WHERE id = %d AND deleted_at IS NULL", $booking_id ) );
		if ( ! $booking ) {
			return false;
		}

		$sms_enabled   = 'yes' === get_option( 'snippen_sms_booking_confirmed_enabled', 'no' );
		$email_enabled = 'yes' === get_option( 'snippen_email_booking_confirmed_enabled', 'yes' );

		if ( ! $sms_enabled && ! $email_enabled ) {
			return false;
		}

		// Fetch associated locales/objects
		$objs         = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT o.name 
				 FROM {$table_junction} bo 
				 JOIN {$table_objects} o ON bo.booking_object_id = o.id 
				 WHERE bo.booking_id = %d",
				$booking_id
			)
		);
		$object_names = implode( ' og ', $objs );

		// Fetch booking time string
		$booking_time = '';
		if ( ! empty( $booking->booking_snapshot ) ) {
			$snapshot = json_decode( $booking->booking_snapshot, true );
			if ( is_array( $snapshot ) && ! empty( $snapshot['time_range_formatted'] ) ) {
				$booking_time = $snapshot['time_range_formatted'];
			}
		}
		if ( empty( $booking_time ) && ! empty( $booking->slot_id ) ) {
			$table_slots = $wpdb->prefix . 'snippen_time_slots';
			$slot        = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_slots} WHERE id = %d", $booking->slot_id ) );
			if ( $slot ) {
				$booking_time = sprintf( '%s - %s', date_i18n( 'H:i', strtotime( $slot->start_time ) ), date_i18n( 'H:i', strtotime( $slot->end_time ) ) );
			}
		}

		$booking_url                  = add_query_arg( 'booking_uuid', $booking->uuid, home_url( '/' ) );
		$default_payment_instructions = __( 'Vennligst overfør leiebeløpet innen 3 dager fra booking. Merk betalingen med ditt navn eller booking-ID.', 'snippen-booking' );

		$context = array(
			'user_name'            => $booking->customer_name,
			'user_email'           => $booking->customer_email,
			'user_phone'           => $booking->customer_phone,
			'booking_objects'      => $object_names,
			'booking_date'         => $booking->booking_date,
			'booking_time'         => $booking_time,
			'booking_url'          => $booking_url,
			'booking_price'        => number_format( (float) $booking->price, 0, ',', ' ' ),
			'bank_account'         => get_option( 'snippen_payment_bank_account', '' ),
			'vipps_number'         => get_option( 'snippen_payment_vipps_number', '' ),
			'payment_instructions' => get_option( 'snippen_payment_instructions', $default_payment_instructions ),
		);

		$template_service = new NotificationTemplateService();
		$rendered_sms     = $template_service->render_template( 'booking_confirmed', 'sms', $context );
		$rendered_email   = $template_service->render_template( 'booking_confirmed', 'email', $context );

		$sms_sent   = false;
		$email_sent = false;

		$phone = ! empty( $booking->customer_phone ) ? $booking->customer_phone : '';
		if ( empty( $phone ) && ! empty( $booking->user_id ) ) {
			$phone = (string) get_user_meta( $booking->user_id, 'snippen_phone', true );
		}

		if ( $sms_enabled && ! empty( $phone ) ) {
			$provider_id  = get_option( 'snippen_active_notification_provider', 'keysms' );
			$sms_provider = $this->get_provider( $provider_id );
			if ( $sms_provider instanceof SmsProviderInterface && $sms_provider->is_configured() ) {
				$sms_sent = $sms_provider->send_sms( $phone, $rendered_sms['body'] );
				MessageLoggerService::log_message(
					$booking_id,
					$booking->user_id ? (int) $booking->user_id : null,
					'sms',
					$phone,
					null,
					$rendered_sms['body'],
					self::TYPE_BOOKING_CONFIRMED,
					$this->get_sms_initial_status( $sms_sent, $provider_id ),
					array( 'provider' => $provider_id )
				);
			}
		}

		$recipient = ! empty( $booking->customer_email ) ? $booking->customer_email : '';
		if ( empty( $recipient ) && ! empty( $booking->user_id ) ) {
			$user = get_userdata( $booking->user_id );
			if ( $user ) {
				$recipient = $user->user_email;
			}
		}

		if ( $email_enabled && ! empty( $recipient ) ) {
			$email_provider = $this->get_provider( 'email' );
			if ( $email_provider instanceof EmailProviderInterface ) {
				$subject    = ! empty( $rendered_email['subject'] ) ? $rendered_email['subject'] : __( 'Din reservasjon er godkjent og bekreftet', 'snippen-booking' );
				$email_sent = $email_provider->send_email( $recipient, $subject, $rendered_email['body'] );
				MessageLoggerService::log_message(
					$booking_id,
					$booking->user_id ? (int) $booking->user_id : null,
					'email',
					$recipient,
					$subject,
					$rendered_email['body'],
					self::TYPE_BOOKING_CONFIRMED,
					$email_sent ? 'sent' : 'failed'
				);
			}
		}

		return $sms_sent || $email_sent;
	}

	/**
	 * Send notification to customer when payment is registered as PAID.
	 *
	 * @param int $booking_id Booking ID.
	 * @return bool True if at least one notification was sent successfully.
	 */
	public function send_payment_received_notification( int $booking_id ): bool {
		global $wpdb;

		$table_bookings = $wpdb->prefix . 'snippen_bookings';
		$table_junction = $wpdb->prefix . 'snippen_bookings_booking_objects';
		$table_objects  = $wpdb->prefix . 'snippen_booking_objects';

		$booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_bookings} WHERE id = %d AND deleted_at IS NULL", $booking_id ) );
		if ( ! $booking ) {
			return false;
		}

		$sms_enabled   = 'yes' === get_option( 'snippen_sms_payment_received_enabled', 'no' );
		$email_enabled = 'yes' === get_option( 'snippen_email_payment_received_enabled', 'yes' );

		if ( ! $sms_enabled && ! $email_enabled ) {
			return false;
		}

		// Fetch associated locales/objects
		$objs         = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT o.name 
				 FROM {$table_junction} bo 
				 JOIN {$table_objects} o ON bo.booking_object_id = o.id 
				 WHERE bo.booking_id = %d",
				$booking_id
			)
		);
		$object_names = implode( ' og ', $objs );

		// Fetch booking time string
		$booking_time = '';
		if ( ! empty( $booking->booking_snapshot ) ) {
			$snapshot = json_decode( $booking->booking_snapshot, true );
			if ( is_array( $snapshot ) && ! empty( $snapshot['time_range_formatted'] ) ) {
				$booking_time = $snapshot['time_range_formatted'];
			}
		}
		if ( empty( $booking_time ) && ! empty( $booking->slot_id ) ) {
			$table_slots = $wpdb->prefix . 'snippen_time_slots';
			$slot        = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_slots} WHERE id = %d", $booking->slot_id ) );
			if ( $slot ) {
				$booking_time = sprintf( '%s - %s', date_i18n( 'H:i', strtotime( $slot->start_time ) ), date_i18n( 'H:i', strtotime( $slot->end_time ) ) );
			}
		}

		$booking_url = add_query_arg( 'booking_uuid', $booking->uuid, home_url( '/' ) );

		$context = array(
			'user_name'       => $booking->customer_name,
			'user_email'      => $booking->customer_email,
			'user_phone'      => $booking->customer_phone,
			'booking_objects' => $object_names,
			'booking_date'    => $booking->booking_date,
			'booking_time'    => $booking_time,
			'booking_url'     => $booking_url,
			'booking_price'   => number_format( (float) $booking->price, 0, ',', ' ' ),
		);

		$template_service = new NotificationTemplateService();
		$rendered_sms     = $template_service->render_template( 'payment_received', 'sms', $context );
		$rendered_email   = $template_service->render_template( 'payment_received', 'email', $context );

		$sms_sent   = false;
		$email_sent = false;

		$phone = ! empty( $booking->customer_phone ) ? $booking->customer_phone : '';
		if ( empty( $phone ) && ! empty( $booking->user_id ) ) {
			$phone = (string) get_user_meta( $booking->user_id, 'snippen_phone', true );
		}

		if ( $sms_enabled && ! empty( $phone ) ) {
			$provider_id  = get_option( 'snippen_active_notification_provider', 'keysms' );
			$sms_provider = $this->get_provider( $provider_id );
			if ( $sms_provider instanceof SmsProviderInterface && $sms_provider->is_configured() ) {
				$sms_sent = $sms_provider->send_sms( $phone, $rendered_sms['body'] );
				MessageLoggerService::log_message(
					$booking_id,
					$booking->user_id ? (int) $booking->user_id : null,
					'sms',
					$phone,
					null,
					$rendered_sms['body'],
					self::TYPE_PAYMENT_RECEIVED,
					$this->get_sms_initial_status( $sms_sent, $provider_id ),
					array( 'provider' => $provider_id )
				);
			}
		}

		$recipient = ! empty( $booking->customer_email ) ? $booking->customer_email : '';
		if ( empty( $recipient ) && ! empty( $booking->user_id ) ) {
			$user = get_userdata( $booking->user_id );
			if ( $user ) {
				$recipient = $user->user_email;
			}
		}

		if ( $email_enabled && ! empty( $recipient ) ) {
			$email_provider = $this->get_provider( 'email' );
			if ( $email_provider instanceof EmailProviderInterface ) {
				$subject    = ! empty( $rendered_email['subject'] ) ? $rendered_email['subject'] : __( 'Betaling bekreftet', 'snippen-booking' );
				$email_sent = $email_provider->send_email( $recipient, $subject, $rendered_email['body'] );
				MessageLoggerService::log_message(
					$booking_id,
					$booking->user_id ? (int) $booking->user_id : null,
					'email',
					$recipient,
					$subject,
					$rendered_email['body'],
					self::TYPE_PAYMENT_RECEIVED,
					$email_sent ? 'sent' : 'failed'
				);
			}
		}

		return $sms_sent || $email_sent;
	}
}
