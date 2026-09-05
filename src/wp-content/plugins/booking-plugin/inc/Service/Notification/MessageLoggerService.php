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

	/**
	 * Get pending outbound messages waiting to be dispatched.
	 *
	 * @param int $limit Max number of messages to fetch.
	 * @return array Array of message objects.
	 */
	public static function get_pending_outbox( int $limit = 50 ): array {
		global $wpdb;

		$table   = $wpdb->prefix . 'snippen_messages';
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE channel = 'sms' AND status = 'queued' ORDER BY id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit
			)
		);

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Get a message by ID.
	 *
	 * @param int $message_id Message ID.
	 * @return object|null Message object or null if not found.
	 */
	public static function get_message( int $message_id ): ?object {
		global $wpdb;

		$table = $wpdb->prefix . 'snippen_messages';
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$message_id
			)
		);

		return $row ?: null;
	}

	/**
	 * Update message status and merge additional metadata.
	 *
	 * @param int    $message_id     Message record ID.
	 * @param string $status         New status ('sent', 'failed', 'queued', etc.).
	 * @param array  $extra_metadata Extra metadata to merge into the JSON field.
	 * @return bool True on success, false on failure.
	 */
	public static function update_message_status( int $message_id, string $status, array $extra_metadata = array() ): bool {
		global $wpdb;

		$table   = $wpdb->prefix . 'snippen_messages';
		$message = self::get_message( $message_id );

		if ( ! $message ) {
			return false;
		}

		$current_metadata = array();
		if ( ! empty( $message->metadata ) ) {
			$decoded = json_decode( $message->metadata, true );
			if ( is_array( $decoded ) ) {
				$current_metadata = $decoded;
			}
		}

		$merged_metadata = array_merge( $current_metadata, $extra_metadata );

		$updated = $wpdb->update(
			$table,
			array(
				'status'      => sanitize_text_field( $status ),
				'metadata'    => ! empty( $merged_metadata ) ? wp_json_encode( $merged_metadata ) : null,
				'modified_at' => current_time( 'mysql' ),
			),
			array( 'id' => $message_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Assign a quarantined or unresolved message to a booking (and optional user).
	 *
	 * @param int      $message_id Message ID.
	 * @param int      $booking_id Target Booking ID.
	 * @param int|null $user_id    Target User ID (optional, derived from booking if null).
	 * @return bool True on success, false on failure.
	 */
	public static function assign_message_to_booking( int $message_id, int $booking_id, ?int $user_id = null ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'snippen_messages';

		// If user_id is null, attempt to resolve from the booking
		if ( null === $user_id ) {
			$table_bookings = $wpdb->prefix . 'snippen_bookings';
			$booking_row    = $wpdb->get_row(
				$wpdb->prepare( "SELECT user_id FROM {$table_bookings} WHERE id = %d", $booking_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);
			if ( $booking_row && ! empty( $booking_row->user_id ) ) {
				$user_id = (int) $booking_row->user_id;
			}
		}

		$data    = array(
			'booking_id'  => $booking_id,
			'status'      => 'received',
			'modified_at' => current_time( 'mysql' ),
		);
		$formats = array( '%d', '%s', '%s' );

		if ( null !== $user_id ) {
			$data['user_id'] = $user_id;
			$formats[]       = '%d';
		}

		$updated = $wpdb->update(
			$table,
			$data,
			array( 'id' => $message_id ),
			$formats,
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Query inbound SMS messages with optional filters and pagination.
	 *
	 * @param array $args Filter arguments.
	 * @return array List of message records.
	 */
	public static function get_inbound_messages( array $args = array() ): array {
		global $wpdb;

		$table                      = $wpdb->prefix . 'snippen_messages';
		list( $where_sql, $params ) = self::build_inbound_where( $args );

		$limit  = isset( $args['limit'] ) ? max( 1, (int) $args['limit'] ) : 50;
		$offset = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;

		$query    = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";
		$params[] = $limit;
		$params[] = $offset;

		$prepared = $wpdb->prepare( $query, ...$params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results  = $wpdb->get_results( $prepared ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Count inbound SMS messages matching filter arguments.
	 *
	 * @param array $args Filter arguments.
	 * @return int Number of matching messages.
	 */
	public static function count_inbound_messages( array $args = array() ): int {
		global $wpdb;

		$table                      = $wpdb->prefix . 'snippen_messages';
		list( $where_sql, $params ) = self::build_inbound_where( $args );

		$query = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		if ( ! empty( $params ) ) {
			$prepared = $wpdb->prepare( $query, ...$params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return (int) $wpdb->get_var( $prepared ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return (int) $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Build WHERE clause and parameters for inbound queries.
	 *
	 * @param array $args Filter arguments.
	 * @return array Array containing [where_sql, params].
	 */
	private static function build_inbound_where( array $args ): array {
		$where  = array( "channel = 'sms'", "event_type = 'inbound_sms'" );
		$params = array();

		if ( ! empty( $args['status'] ) && 'all' !== $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_text_field( $args['status'] );
		}

		if ( isset( $args['booking_id'] ) && '' !== $args['booking_id'] ) {
			$where[]  = 'booking_id = %d';
			$params[] = (int) $args['booking_id'];
		}

		if ( isset( $args['user_id'] ) && '' !== $args['user_id'] ) {
			$where[]  = 'user_id = %d';
			$params[] = (int) $args['user_id'];
		}

		if ( ! empty( $args['phone'] ) ) {
			$where[]  = 'recipient LIKE %s';
			$params[] = '%' . $GLOBALS['wpdb']->esc_like( sanitize_text_field( $args['phone'] ) ) . '%';
		}

		if ( ! empty( $args['search'] ) ) {
			$search   = '%' . $GLOBALS['wpdb']->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[]  = '(recipient LIKE %s OR message LIKE %s)';
			$params[] = $search;
			$params[] = $search;
		}

		return array( implode( ' AND ', $where ), $params );
	}
}
