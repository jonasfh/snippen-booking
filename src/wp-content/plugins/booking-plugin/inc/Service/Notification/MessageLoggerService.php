<?php
/**
 * Message Logger Service
 *
 * @package SnippenBooking\Service\Notification
 */

namespace SnippenBooking\Service\Notification;

/**
 * Class MessageLoggerService
 * Handles logging and retrieving sent user communications.
 */
class MessageLoggerService {

	/**
	 * Log a communication message in the database.
	 *
	 * @param int|null    $booking_id Booking ID, if associated with a booking.
	 * @param int|null    $user_id    User ID, if associated with a WordPress user.
	 * @param string      $channel    Channel used (e.g. 'email', 'sms').
	 * @param string      $recipient  Recipient (email or phone number).
	 * @param string|null $subject    Email subject, if applicable.
	 * @param string      $message    Content of the message sent.
	 * @param string      $event_type Type of event (e.g. 'booking_confirmation', 'user_activation', 'admin_booking', 'manual_dispatch').
	 * @param string      $status     Status of the dispatch ('sent' or 'failed').
	 * @param array       $metadata   Optional metadata/context array.
	 * @return int|false  Inserted record ID or false on error.
	 */
	public static function log_message(
		?int $booking_id,
		?int $user_id,
		string $channel,
		string $recipient,
		?string $subject,
		string $message,
		string $event_type,
		string $status = 'sent',
		array $metadata = array()
	) {
		global $wpdb;

		$table = $wpdb->prefix . 'snippen_messages';

		$data = array(
			'booking_id'  => $booking_id,
			'user_id'     => $user_id,
			'channel'     => sanitize_text_field( $channel ),
			'recipient'   => sanitize_text_field( $recipient ),
			'subject'     => null !== $subject ? sanitize_text_field( $subject ) : null,
			'message'     => $message,
			'event_type'  => sanitize_text_field( $event_type ),
			'status'      => sanitize_text_field( $status ),
			'metadata'    => ! empty( $metadata ) ? wp_json_encode( $metadata ) : null,
			'created_at'  => current_time( 'mysql' ),
			'modified_at' => current_time( 'mysql' ),
		);

		$format = array(
			'%d',
			'%d',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
		);

		$inserted = $wpdb->insert( $table, $data, $format );

		if ( false !== $inserted ) {
			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Get all logged messages for a specific booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array Array of message objects ordered newest first.
	 */
	public static function get_messages_for_booking( int $booking_id ): array {
		global $wpdb;

		$table   = $wpdb->prefix . 'snippen_messages';
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE booking_id = %d ORDER BY created_at DESC, id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$booking_id
			)
		);

		return is_array( $results ) ? $results : array();
	}
}
