<?php

namespace SnippenBooking\Api;

use SnippenBooking\Helper\Capabilities;

/**
 * Handles AJAX actions for booking management (Approve/Cancel)
 */
class BookingActionsApi {

	/**
	 * Register AJAX hooks
	 */
	public static function register() {
		add_action( 'wp_ajax_snippen_update_booking_status', array( __CLASS__, 'update_status' ) );
		add_action( 'wp_ajax_snippen_dispatch_notification_manually', array( __CLASS__, 'dispatch_notification_manually' ) );
		add_action( 'wp_ajax_snippen_get_notification_preview', array( __CLASS__, 'get_notification_preview' ) );
		add_action( 'wp_ajax_snippen_update_door_code', array( __CLASS__, 'update_door_code' ) );
	}

	/**
	 * Update booking status
	 */
	public static function update_status() {
		check_ajax_referer( 'snippen_admin_nonce', 'nonce' );

		global $wpdb;
		$table  = $wpdb->prefix . 'snippen_bookings';
		$id     = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		$status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';

		if ( ! Capabilities::can_manage_bookings() ) {
			// Only allow cancellation of own bookings for non-admins
			if ( $status !== 'cancelled' ) {
				wp_send_json_error( array( 'message' => __( 'Ingen tilgang.', 'snippen-booking' ) ) );
			}

			$booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d AND deleted_at IS NULL", $id ) );
			if ( ! $booking || intval( $booking->user_id ) !== get_current_user_id() ) {
				wp_send_json_error( array( 'message' => __( 'Ingen tilgang.', 'snippen-booking' ) ) );
			}

			// Must not be confirmed by administrator
			if ( 'confirmed' === $booking->status ) {
				wp_send_json_error( array( 'message' => __( 'Du kan ikke slette en bekreftet booking.', 'snippen-booking' ) ) );
			}

			// Must not be marked as paid
			$payment_status = \SnippenBooking\Service\PaymentService::get_booking_payment_status( $booking );
			if ( $payment_status->is_settled ) {
				wp_send_json_error( array( 'message' => __( 'Du kan ikke slette en betalt booking.', 'snippen-booking' ) ) );
			}

			// Check cancellation deadline window
			$cancellation_days = intval( get_option( 'snippen_user_cancellation_days', 14 ) );
			$today             = new \DateTime( 'today' );
			$booking_start     = new \DateTime( $booking->booking_date );
			$days_until_start  = (int) $today->diff( $booking_start )->format( '%r%a' );

			if ( $days_until_start < $cancellation_days ) {
				/* translators: %d: number of days */
				wp_send_json_error( array( 'message' => sprintf( __( 'Booking kan ikke slettes mindre enn %d dager før start.', 'snippen-booking' ), $cancellation_days ) ) );
			}
		}

		if ( ! $id || ! in_array( $status, array( 'confirmed', 'cancelled' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Ugyldig forespørsel.', 'snippen-booking' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'snippen_bookings';

		$updated = $wpdb->update(
			$table,
			array(
				'status'      => $status,
				'modified_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);

		if ( $updated !== false ) {
			wp_send_json_success(
				array(
					'message'      => __( 'Status oppdatert.', 'snippen-booking' ),
					'new_status'   => $status,
					'status_label' => self::get_status_label( $status ),
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Kunne ikke oppdatere status.', 'snippen-booking' ) ) );
		}
	}

	/**
	 * Update booking door code
	 */
	public static function update_door_code() {
		check_ajax_referer( 'snippen_admin_nonce', 'nonce' );

		if ( ! Capabilities::can_manage_bookings() ) {
			wp_send_json_error( array( 'message' => __( 'Ingen tilgang.', 'snippen-booking' ) ) );
		}

		global $wpdb;
		$id        = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		$door_code = isset( $_POST['door_code'] ) ? sanitize_text_field( $_POST['door_code'] ) : '';

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Ugyldig forespørsel.', 'snippen-booking' ) ) );
		}

		$table = $wpdb->prefix . 'snippen_bookings';

		$updated = $wpdb->update(
			$table,
			array(
				'door_code'   => $door_code,
				'modified_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);

		if ( $updated !== false ) {
			wp_send_json_success( array( 'message' => __( 'Dørkode oppdatert.', 'snippen-booking' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Kunne ikke oppdatere dørkode.', 'snippen-booking' ) ) );
		}
	}

	/**
	 * Get preview of notification message/subject before manual dispatch
	 */
	public static function get_notification_preview() {
		check_ajax_referer( 'snippen_admin_nonce', 'nonce' );

		if ( ! Capabilities::can_manage_bookings() ) {
			wp_send_json_error( array( 'message' => __( 'Ingen tilgang.', 'snippen-booking' ) ) );
		}

		global $wpdb;
		$booking_id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		$channel    = isset( $_POST['channel'] ) ? sanitize_text_field( $_POST['channel'] ) : '';

		if ( ! $booking_id || ! in_array( $channel, array( 'email_customer', 'sms_customer', 'email_admin' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Ugyldig forespørsel.', 'snippen-booking' ) ) );
		}

		$table_bookings = $wpdb->prefix . 'snippen_bookings';
		$booking        = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_bookings WHERE id = %d AND deleted_at IS NULL", $booking_id ) );

		if ( ! $booking ) {
			wp_send_json_error( array( 'message' => __( 'Booking ble ikke funnet.', 'snippen-booking' ) ) );
		}

		// Fetch associated locales/objects for message templates
		$table_junction = $wpdb->prefix . 'snippen_bookings_booking_objects';
		$table_objects  = $wpdb->prefix . 'snippen_booking_objects';
		$objs           = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT o.name 
				 FROM $table_junction bo 
				 JOIN $table_objects o ON bo.booking_object_id = o.id 
				 WHERE bo.booking_id = %d",
				$booking_id
			)
		);
		$object_names   = implode( ' og ', $objs );
		$context        = self::get_booking_context( $booking, $object_names );

		$template_service = new \SnippenBooking\Service\Notification\NotificationTemplateService();
		$raw_channel      = ( $channel === 'sms_customer' ) ? 'sms' : 'email';
		$all_templates    = $template_service->get_all_templates();
		$placeholders     = $template_service->get_all_placeholders();

		$template_options = array();
		$event_labels     = array(
			'booking_confirmation' => __( 'Booking-bekreftelse', 'snippen-booking' ),
			'admin_booking'        => __( 'Admin bookingvarsel', 'snippen-booking' ),
			'user_activation'      => __( 'Kontoaktivering', 'snippen-booking' ),
			'password_reset'       => __( 'Passordtilbakestilling', 'snippen-booking' ),
		);

		foreach ( $all_templates as $event_type => $channels ) {
			if ( isset( $channels[ $raw_channel ] ) ) {
				$tpl            = $channels[ $raw_channel ];
				$raw_subject    = $tpl['subject'] ?? '';
				$raw_body       = $tpl['body'] ?? '';
				$label          = $event_labels[ $event_type ] ?? ucfirst( str_replace( '_', ' ', $event_type ) );

				$template_options[] = array(
					'key'              => $event_type,
					'label'            => $label,
					'raw_subject'      => $raw_subject,
					'raw_body'         => $raw_body,
					'rendered_subject' => self::replace_placeholders( $raw_subject, $context ),
					'rendered_body'    => self::replace_placeholders( $raw_body, $context ),
				);
			}
		}

		$default_template_key = ( $channel === 'email_admin' ) ? 'admin_booking' : 'booking_confirmation';
		$selected_template   = $template_service->render_template( $default_template_key, $raw_channel, $context );

		$recipient = '';
		if ( $channel === 'email_admin' ) {
			$admin_users  = get_users( array( 'capability' => Capabilities::MANAGE_BOOKINGS ) );
			$admin_emails = array();
			foreach ( $admin_users as $admin ) {
				if ( ! empty( $admin->user_email ) ) {
					$admin_emails[] = $admin->user_email;
				}
			}
			$recipient = implode( ', ', $admin_emails );
		} elseif ( $channel === 'email_customer' ) {
			$recipient = $booking->customer_email;
			if ( empty( $recipient ) ) {
				$user = get_userdata( $booking->user_id );
				if ( $user ) {
					$recipient = $user->user_email;
				}
			}
		} elseif ( $channel === 'sms_customer' ) {
			$recipient = $booking->customer_phone ?: '';
		}

		wp_send_json_success(
			array(
				'recipient'            => $recipient,
				'subject'              => $selected_template['subject'],
				'message'              => $selected_template['body'],
				'default_template_key' => $default_template_key,
				'templates'            => $template_options,
				'placeholders'         => $placeholders,
			)
		);
	}

	/**
	 * Manually dispatch a notification for a booking
	 */
	public static function dispatch_notification_manually() {
		check_ajax_referer( 'snippen_admin_nonce', 'nonce' );

		if ( ! Capabilities::can_manage_bookings() ) {
			wp_send_json_error( array( 'message' => __( 'Ingen tilgang.', 'snippen-booking' ) ) );
		}

		global $wpdb;
		$booking_id     = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		$channel        = isset( $_POST['channel'] ) ? sanitize_text_field( $_POST['channel'] ) : '';
		$custom_message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : null;
		$custom_subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : null;

		if ( ! $booking_id || ! in_array( $channel, array( 'email_customer', 'sms_customer', 'email_admin' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Ugyldig forespørsel.', 'snippen-booking' ) ) );
		}

		$table_bookings = $wpdb->prefix . 'snippen_bookings';
		$booking        = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_bookings WHERE id = %d AND deleted_at IS NULL", $booking_id ) );

		if ( ! $booking ) {
			wp_send_json_error( array( 'message' => __( 'Booking ble ikke funnet.', 'snippen-booking' ) ) );
		}

		// Fetch associated locales/objects for message templates
		$table_junction = $wpdb->prefix . 'snippen_bookings_booking_objects';
		$table_objects  = $wpdb->prefix . 'snippen_booking_objects';
		$objs           = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT o.name 
				 FROM $table_junction bo 
				 JOIN $table_objects o ON bo.booking_object_id = o.id 
				 WHERE bo.booking_id = %d",
				$booking_id
			)
		);
		$object_names   = implode( ' og ', $objs );
		$context        = self::get_booking_context( $booking, $object_names );

		$notification_manager = new \SnippenBooking\Service\Notification\NotificationManager();
		$email_provider       = $notification_manager->get_provider( 'email' );

		if ( $channel === 'email_admin' ) {
			if ( ! $email_provider instanceof \SnippenBooking\Service\Notification\EmailProviderInterface ) {
				wp_send_json_error( array( 'message' => __( 'E-post tilbyder er ikke tilgjengelig.', 'snippen-booking' ) ) );
			}
			$admin_users  = get_users( array( 'capability' => Capabilities::MANAGE_BOOKINGS ) );
			$admin_emails = array();
			foreach ( $admin_users as $admin ) {
				if ( ! empty( $admin->user_email ) ) {
					$admin_emails[] = $admin->user_email;
				}
			}

			if ( empty( $admin_emails ) ) {
				wp_send_json_error( array( 'message' => __( 'Fant ingen administratorer med e-post.', 'snippen-booking' ) ) );
			}

			$subject = $custom_subject !== null && $custom_subject !== '' ? self::replace_placeholders( $custom_subject, $context ) : sprintf( __( 'Ny Bookingforespørsel - %s (Manuell sendt)', 'snippen-booking' ), $object_names );

			if ( $custom_message !== null && $custom_message !== '' ) {
				$message = self::replace_placeholders( $custom_message, $context );
			} else {
				$message  = __( 'Ny bookingforespørsel mottatt (sendt manuelt av administrator):', 'snippen-booking' ) . "\n\n";
				$message .= __( 'Lokale:', 'snippen-booking' ) . ' ' . $object_names . "\n";
				$message .= __( 'Dato:', 'snippen-booking' ) . ' ' . $booking->booking_date . "\n";
				$message .= __( 'Navn:', 'snippen-booking' ) . ' ' . $booking->customer_name . "\n";
				$message .= __( 'Email:', 'snippen-booking' ) . ' ' . $booking->customer_email . "\n";
				$message .= __( 'Telefon:', 'snippen-booking' ) . ' ' . $booking->customer_phone . "\n";
				$message .= __( 'Beskrivelse:', 'snippen-booking' ) . ' ' . $booking->description . "\n";
			}

			$success = true;
			foreach ( $admin_emails as $admin_email ) {
				if ( ! $email_provider->send_email( $admin_email, $subject, $message ) ) {
					$success = false;
				}
			}

			if ( $success ) {
				wp_send_json_success( array( 'message' => __( 'Varsel sendt til administrator(er).', 'snippen-booking' ) ) );
			} else {
				wp_send_json_error( array( 'message' => __( 'E-post sending feilet for én eller flere administratorer.', 'snippen-booking' ) ) );
			}
		}

		if ( $channel === 'email_customer' ) {
			if ( ! $email_provider instanceof \SnippenBooking\Service\Notification\EmailProviderInterface ) {
				wp_send_json_error( array( 'message' => __( 'E-post tilbyder er ikke tilgjengelig.', 'snippen-booking' ) ) );
			}

			$sms_link = add_query_arg( 'booking_uuid', $booking->uuid, home_url( '/' ) );
			$subject  = $custom_subject !== null && $custom_subject !== '' ? self::replace_placeholders( $custom_subject, $context ) : __( 'Bekreftelse på din bookingforespørsel', 'snippen-booking' );

			if ( $custom_message !== null && $custom_message !== '' ) {
				$mail_message = self::replace_placeholders( $custom_message, $context );
			} else {
				$mail_message = sprintf(
					__( "Takk for din bookingforespørsel for %1\$s den %2\$s.\n\nDu kan se detaljer om din booking her: %3\$s", 'snippen-booking' ),
					$object_names,
					$booking->booking_date,
					$sms_link
				);
			}

			$recipient = $booking->customer_email;
			if ( empty( $recipient ) ) {
				$user = get_userdata( $booking->user_id );
				if ( $user ) {
					$recipient = $user->user_email;
				}
			}

			if ( empty( $recipient ) ) {
				wp_send_json_error( array( 'message' => __( 'Kunden har ingen registrert e-postadresse.', 'snippen-booking' ) ) );
			}

			if ( $email_provider->send_email( $recipient, $subject, $mail_message ) ) {
				wp_send_json_success( array( 'message' => __( 'Bekreftelses-e-post sendt til kunden.', 'snippen-booking' ) ) );
			} else {
				wp_send_json_error( array( 'message' => __( 'Kunne ikke sende e-post til kunden.', 'snippen-booking' ) ) );
			}
		}

		if ( $channel === 'sms_customer' ) {
			if ( empty( $booking->customer_phone ) ) {
				wp_send_json_error( array( 'message' => __( 'Kunden har ikke registrert telefonnummer.', 'snippen-booking' ) ) );
			}

			$sms_link = add_query_arg( 'booking_uuid', $booking->uuid, home_url( '/' ) );

			if ( $custom_message !== null && $custom_message !== '' ) {
				$sms_message = self::replace_placeholders( $custom_message, $context );
			} else {
				$sms_message = sprintf(
					__( 'Takk for din bookingforespørsel for %1$s den %2$s. Se detaljer: %3$s', 'snippen-booking' ),
					$object_names,
					$booking->booking_date,
					$sms_link
				);
			}

			$provider_id = get_option( 'snippen_active_notification_provider', 'keysms' );
			$provider    = $notification_manager->get_provider( $provider_id );

			if ( ! $provider instanceof \SnippenBooking\Service\Notification\SmsProviderInterface || ! $provider->is_configured() ) {
				wp_send_json_error( array( 'message' => __( 'SMS tilbyder er ikke konfigurert.', 'snippen-booking' ) ) );
			}

			if ( $provider->send_sms( $booking->customer_phone, $sms_message ) ) {
				wp_send_json_success( array( 'message' => __( 'Bekreftelses-SMS sendt til kunden.', 'snippen-booking' ) ) );
			} else {
				wp_send_json_error( array( 'message' => __( 'SMS sending feilet. Sjekk logger.', 'snippen-booking' ) ) );
			}
		}

		wp_send_json_error( array( 'message' => __( 'Ugyldig handling.', 'snippen-booking' ) ) );
	}

	/**
	 * Get status label
	 */
	private static function get_status_label( $status ) {
		$labels = array(
			'pending'   => __( 'Venter', 'snippen-booking' ),
			'confirmed' => __( 'Bekreftet', 'snippen-booking' ),
			'cancelled' => __( 'Avbrutt', 'snippen-booking' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}
}
