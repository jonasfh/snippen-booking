<?php
/**
 * Message Logger Service
 *
 * @package SnippenBooking\Service\Notification
 */

namespace SnippenBooking\Service\Notification;

use SnippenBooking\Helper\PhoneHelper;

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
	 * Check if a message of a specific event type has already been logged for a booking.
	 *
	 * @param int         $booking_id Booking ID.
	 * @param string      $event_type Event type (e.g. 'admin_booking', 'booking_confirmed').
	 * @param string|null $channel    Optional channel filter ('email', 'sms').
	 * @param string|null $status     Optional status filter ('sent', 'failed', etc.).
	 * @return bool True if a matching message exists.
	 */
	public static function has_message( int $booking_id, string $event_type, ?string $channel = null, ?string $status = null ): bool {
		global $wpdb;

		$table  = $wpdb->prefix . 'snippen_messages';
		$sql    = "SELECT COUNT(*) FROM {$table} WHERE booking_id = %d AND event_type = %s";
		$params = array( $booking_id, $event_type );

		if ( null !== $channel ) {
			$sql     .= ' AND channel = %s';
			$params[] = $channel;
		}

		if ( null !== $status ) {
			$sql     .= ' AND status = %s';
			$params[] = $status;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$count = (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );

		return $count > 0;
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
	 * Delete a single message record by ID.
	 *
	 * @param int $message_id Message record ID.
	 * @return bool True if deleted, false on failure or not found.
	 */
	public static function delete_message( int $message_id ): bool {
		global $wpdb;

		$table   = $wpdb->prefix . 'snippen_messages';
		$deleted = $wpdb->delete(
			$table,
			array( 'id' => $message_id ),
			array( '%d' )
		);

		return false !== $deleted && $deleted > 0;
	}

	/**
	 * Delete multiple message records by ID (bulk delete).
	 *
	 * @param array $message_ids List of message IDs to delete.
	 * @return int Number of deleted records.
	 */
	public static function delete_messages( array $message_ids ): int {
		global $wpdb;

		$sanitized_ids = array_filter( array_map( 'absint', $message_ids ) );
		if ( empty( $sanitized_ids ) ) {
			return 0;
		}

		$table        = $wpdb->prefix . 'snippen_messages';
		$placeholders = implode( ',', array_fill( 0, count( $sanitized_ids ), '%d' ) );
		$query        = "DELETE FROM {$table} WHERE id IN ({$placeholders})";
		$prepared     = $wpdb->prepare( $query, ...$sanitized_ids ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$deleted = $wpdb->query( $prepared ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return false !== $deleted ? (int) $deleted : 0;
	}

	/**
	 * Retrieve chronological SMS conversation history for a given phone number or booking.
	 *
	 * @param string   $phone      Phone number.
	 * @param int|null $booking_id Optional booking ID.
	 * @param int      $limit      Max number of messages.
	 * @return array List of message records ordered chronologically.
	 */
	public static function get_conversation_thread( string $phone, ?int $booking_id = null, int $limit = 50 ): array {
		global $wpdb;

		$table  = $wpdb->prefix . 'snippen_messages';
		$where  = array( "channel = 'sms'" );
		$params = array();

		$clean_digits = preg_replace( '/[^0-9]/', '', (string) $phone );
		$phone_conds  = array( 'recipient LIKE %s' );
		$phone_params = array( '%' . $wpdb->esc_like( $phone ) . '%' );

		if ( strlen( $clean_digits ) >= 8 ) {
			$last8          = substr( $clean_digits, -8 );
			$phone_conds[]  = 'recipient LIKE %s';
			$phone_params[] = '%' . $wpdb->esc_like( $last8 );
		}

		$phone_sql = '(' . implode( ' OR ', $phone_conds ) . ')';

		if ( ! empty( $booking_id ) ) {
			$where[] = "({$phone_sql} OR booking_id = %d)";
			$params  = array_merge( $phone_params, array( (int) $booking_id ) );
		} else {
			$where[] = $phone_sql;
			$params  = $phone_params;
		}

		$where_str = implode( ' AND ', $where );
		$query     = "SELECT * FROM {$table} WHERE {$where_str} ORDER BY created_at ASC, id ASC LIMIT %d";
		$params[]  = max( 1, $limit );

		$prepared = $wpdb->prepare( $query, ...$params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results  = $wpdb->get_results( $prepared ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Query inbound SMS messages with optional filters, sorting and pagination.
	 *
	 * @param array $args Filter and sorting arguments.
	 * @return array List of message records.
	 */
	public static function get_inbound_messages( array $args = array() ): array {
		global $wpdb;

		$table                      = $wpdb->prefix . 'snippen_messages';
		list( $where_sql, $params ) = self::build_inbound_where( $args );

		$limit  = isset( $args['limit'] ) ? max( 1, (int) $args['limit'] ) : 50;
		$offset = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;

		$allowed_orderby = array(
			'created_at' => 'created_at',
			'recipient'  => 'recipient',
			'status'     => 'status',
			'booking_id' => 'booking_id',
			'id'         => 'id',
		);
		$orderby         = $allowed_orderby[ $args['orderby'] ?? '' ] ?? 'created_at';
		$order           = ( isset( $args['order'] ) && 'asc' === strtolower( (string) $args['order'] ) ) ? 'ASC' : 'DESC';

		$query    = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order}, id DESC LIMIT %d OFFSET %d";
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
		global $wpdb;

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
			$clean_digits = preg_replace( '/[^0-9]/', '', (string) $args['phone'] );
			$phone_conds  = array( 'recipient LIKE %s' );
			$phone_params = array( '%' . $wpdb->esc_like( sanitize_text_field( $args['phone'] ) ) . '%' );

			if ( strlen( $clean_digits ) >= 8 ) {
				$last8          = substr( $clean_digits, -8 );
				$phone_conds[]  = 'recipient LIKE %s';
				$phone_params[] = '%' . $wpdb->esc_like( $last8 );
			}

			$where[] = '(' . implode( ' OR ', $phone_conds ) . ')';
			$params  = array_merge( $params, $phone_params );
		}

		// Connection filter: 'linked' or 'unlinked'
		if ( ! empty( $args['connection'] ) ) {
			if ( 'linked' === $args['connection'] ) {
				$where[] = '(booking_id IS NOT NULL AND booking_id > 0)';
			} elseif ( 'unlinked' === $args['connection'] ) {
				$where[] = '(booking_id IS NULL OR booking_id = 0)';
			}
		}

		// Date filter: 'today', '7days', '30days'
		if ( ! empty( $args['date_filter'] ) ) {
			if ( 'today' === $args['date_filter'] ) {
				$where[]  = 'created_at >= %s';
				$params[] = gmdate( 'Y-m-d 00:00:00' );
			} elseif ( '7days' === $args['date_filter'] ) {
				$where[]  = 'created_at >= %s';
				$params[] = gmdate( 'Y-m-d 00:00:00', strtotime( '-7 days' ) );
			} elseif ( '30days' === $args['date_filter'] ) {
				$where[]  = 'created_at >= %s';
				$params[] = gmdate( 'Y-m-d 00:00:00', strtotime( '-30 days' ) );
			}
		}

		// Comprehensive search matching: text, phone, booking ID, user name, customer name
		if ( ! empty( $args['search'] ) ) {
			$raw_search = trim( sanitize_text_field( $args['search'] ) );
			if ( '' !== $raw_search ) {
				$search_conditions = array();
				$search_params     = array();

				// 1. Text in message
				$like_search         = '%' . $wpdb->esc_like( $raw_search ) . '%';
				$search_conditions[] = 'message LIKE %s';
				$search_params[]     = $like_search;

				// 2. Direct recipient phone match
				$search_conditions[] = 'recipient LIKE %s';
				$search_params[]     = $like_search;

				// 3. Digits-only / normalized phone match
				$clean_digits = preg_replace( '/[^0-9]/', '', $raw_search );
				if ( strlen( $clean_digits ) >= 4 ) {
					$search_conditions[] = 'recipient LIKE %s';
					$search_params[]     = '%' . $wpdb->esc_like( $clean_digits ) . '%';

					$norm = PhoneHelper::normalize_phone( $raw_search );
					if ( $norm ) {
						$search_conditions[] = 'recipient = %s';
						$search_params[]     = $norm;
					}
				}

				// 4. Numeric booking ID search (e.g. "42", "#42", "booking 42")
				if ( preg_match( '/^(?:#|booking\s*)?(\d+)$/i', $raw_search, $matches ) ) {
					$search_conditions[] = 'booking_id = %d';
					$search_params[]     = (int) $matches[1];
				}

				// 5. User name or user email search
				$user_matches = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->users} WHERE display_name LIKE %s OR user_nicename LIKE %s OR user_email LIKE %s LIMIT 50", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$like_search,
						$like_search,
						$like_search
					)
				);
				if ( ! empty( $user_matches ) ) {
					$user_placeholders   = implode( ',', array_fill( 0, count( $user_matches ), '%d' ) );
					$search_conditions[] = "user_id IN ({$user_placeholders})";
					$search_params       = array_merge( $search_params, array_map( 'intval', $user_matches ) );
				}

				// 6. Booking customer name or customer email search
				$table_bookings  = $wpdb->prefix . 'snippen_bookings';
				$booking_matches = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT id FROM {$table_bookings} WHERE customer_name LIKE %s OR customer_email LIKE %s LIMIT 50", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$like_search,
						$like_search
					)
				);
				if ( ! empty( $booking_matches ) ) {
					$booking_placeholders = implode( ',', array_fill( 0, count( $booking_matches ), '%d' ) );
					$search_conditions[]  = "booking_id IN ({$booking_placeholders})";
					$search_params        = array_merge( $search_params, array_map( 'intval', $booking_matches ) );
				}

				if ( ! empty( $search_conditions ) ) {
					$where[] = '(' . implode( ' OR ', $search_conditions ) . ')';
					$params  = array_merge( $params, $search_params );
				}
			}
		}

		return array( implode( ' AND ', $where ), $params );
	}
}
