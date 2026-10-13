<?php

namespace SnippenBooking\Service;

/**
 * Service to manage door codes for booking objects and bookings
 */
class DoorCodeService {

	/**
	 * Check if door code system is enabled
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return 'yes' === get_option( 'snippen_enable_door_code', 'no' ) || 'yes' === get_option( 'snippen_enable_doorman_api', 'no' );
	}

	/**
	 * Get configured x hours before booking start (legacy setting)
	 */
	public static function get_hours_before() {
		return intval( get_option( 'snippen_door_code_hours_before', 24 ) );
	}

	/**
	 * Get configured y hours after booking end (legacy setting)
	 */
	public static function get_hours_after() {
		return intval( get_option( 'snippen_door_code_hours_after', 2 ) );
	}

	/**
	 * Check if a booking is currently in the active door code window.
	 * Door code is viewable by user whenever door codes are enabled and code is assigned.
	 *
	 * @param object $booking Booking database row
	 * @return bool
	 */
	public static function is_in_window( $booking ) {
		if ( ! self::is_enabled() ) {
			return false;
		}

		if ( ! empty( $booking->door_code ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Compute access window for a booking or chain of bookings according to Doorman model.
	 *
	 * @param int|object $booking_or_id
	 * @return array
	 */
	public static function get_access_window( $booking_or_id ) {
		global $wpdb;
		$table_bookings = $wpdb->prefix . 'snippen_bookings';

		if ( is_numeric( $booking_or_id ) ) {
			$booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_bookings} WHERE id = %d AND deleted_at IS NULL", (int) $booking_or_id ) );
		} else {
			$booking = $booking_or_id;
		}

		if ( ! $booking ) {
			return array(
				'calc_start'      => 0,
				'calc_end'        => 0,
				'valid_from'      => '',
				'valid_to'        => '',
				'validity_period' => '',
				'booking_ids'     => array(),
			);
		}

		$booking_ids = \SnippenBooking\Api\DoorLockApi::find_adjacent_booking_ids( (int) $booking->id );
		if ( empty( $booking_ids ) ) {
			$booking_ids = array( (int) $booking->id );
		}

		$table_bb     = $wpdb->prefix . 'snippen_booking_booking_blocks';
		$table_blocks = $wpdb->prefix . 'snippen_booking_blocks';
		$table_slots  = $wpdb->prefix . 'snippen_time_slots';

		$id_placeholders = implode( ',', array_fill( 0, count( $booking_ids ), '%d' ) );
		$rows            = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.id, b.booking_date, b.booking_snapshot,
				        COALESCE(MIN(s.start_time), ts.start_time, '00:00:00') as block_start,
				        COALESCE(MAX(s.end_time), ts.end_time, '23:59:59') as block_end
				 FROM {$table_bookings} b
				 LEFT JOIN {$table_bb} bb ON b.id = bb.booking_id
				 LEFT JOIN {$table_blocks} s ON bb.booking_block_id = s.id
				 LEFT JOIN {$table_slots} ts ON b.slot_id = ts.id
				 WHERE b.id IN ({$id_placeholders}) AND b.deleted_at IS NULL
				 GROUP BY b.id",
				...$booking_ids
			)
		);

		$min_start = null;
		$max_end   = null;

		foreach ( $rows as $r ) {
			$start_time = $r->block_start ?: '00:00:00';
			$end_time   = $r->block_end ?: '23:59:59';

			if ( ! empty( $r->booking_snapshot ) ) {
				$snap = json_decode( $r->booking_snapshot, true );
				if ( is_array( $snap ) ) {
					if ( ! empty( $snap['start_time'] ) ) {
						$start_time = $snap['start_time'];
					}
					if ( ! empty( $snap['end_time'] ) ) {
						$end_time = $snap['end_time'];
					}
				}
			}

			$start_ts = strtotime( $r->booking_date . ' ' . $start_time );
			$end_ts   = ( $end_time < $start_time )
				? strtotime( date( 'Y-m-d', strtotime( $r->booking_date . ' +1 day' ) ) . ' ' . $end_time )
				: strtotime( $r->booking_date . ' ' . $end_time );

			if ( null === $min_start || $start_ts < $min_start ) {
				$min_start = $start_ts;
			}
			if ( null === $max_end || $end_ts > $max_end ) {
				$max_end = $end_ts;
			}
		}

		if ( null === $min_start ) {
			$min_start = time();
			$max_end   = time() + 7200;
		}

		$buffer_minutes = intval( get_option( 'snippen_doorman_buffer_minutes_before', 30 ) );
		$grace_minutes  = intval( get_option( 'snippen_doorman_grace_minutes_after', 120 ) );

		$calc_start = $min_start - ( $buffer_minutes * 60 );
		$calc_end   = $max_end + ( $grace_minutes * 60 );

		$valid_from = date_i18n( 'd.m.Y \k\l. H:i', $calc_start );
		$valid_to   = date_i18n( 'd.m.Y \k\l. H:i', $calc_end );

		if ( date_i18n( 'Y-m-d', $calc_start ) === date_i18n( 'Y-m-d', $calc_end ) ) {
			$validity_period = sprintf( '%s – %s', $valid_from, date_i18n( 'H:i', $calc_end ) );
		} else {
			$validity_period = sprintf( '%s – %s', $valid_from, $valid_to );
		}

		return array(
			'calc_start'      => $calc_start,
			'calc_end'        => $calc_end,
			'valid_from'      => $valid_from,
			'valid_to'        => $valid_to,
			'validity_period' => $validity_period,
			'booking_ids'     => $booking_ids,
		);
	}

	/**
	 * Apply a door code to a booking and all adjacent bookings in its chain.
	 *
	 * @param int    $booking_id
	 * @param string $door_code
	 * @param bool   $dispatch_notification
	 * @return true|\WP_Error
	 */
	public static function apply_door_code( int $booking_id, string $door_code, bool $dispatch_notification = true ) {
		global $wpdb;
		$table_bookings = $wpdb->prefix . 'snippen_bookings';

		$booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_bookings} WHERE id = %d AND deleted_at IS NULL", $booking_id ) );
		if ( ! $booking ) {
			return new \WP_Error( 'rest_booking_not_found', __( 'Booking ble ikke funnet.', 'snippen-booking' ), array( 'status' => 404 ) );
		}

		$clean_code = trim( (string) $door_code );
		if ( '' !== $clean_code && ! preg_match( '/^[0-9]{4,6}$/', $clean_code ) ) {
			return new \WP_Error( 'rest_invalid_param', __( 'Ugyldig dørkode. Koden må bestå av 4–6 numeriske sifre.', 'snippen-booking' ), array( 'status' => 400 ) );
		}

		$old_code    = (string) $booking->door_code;
		$booking_ids = \SnippenBooking\Api\DoorLockApi::find_adjacent_booking_ids( $booking_id );
		if ( empty( $booking_ids ) ) {
			$booking_ids = array( $booking_id );
		}

		$now_mysql      = current_time( 'mysql' );
		$has_updated_at = ! empty(
			$wpdb->get_results(
				"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table_bookings}' AND COLUMN_NAME = 'door_code_updated_at'"
			)
		);

		foreach ( $booking_ids as $b_id ) {
			$data = array(
				'door_code'   => '' !== $clean_code ? $clean_code : null,
				'modified_at' => $now_mysql,
			);
			if ( $has_updated_at ) {
				$data['door_code_updated_at'] = '' !== $clean_code ? $now_mysql : null;
			}

			$wpdb->update(
				$table_bookings,
				$data,
				array( 'id' => (int) $b_id )
			);
		}

		$is_changed = ( $clean_code !== $old_code );
		if ( $dispatch_notification && $is_changed && '' !== $clean_code && 'confirmed' === $booking->status ) {
			$notification_manager = new \SnippenBooking\Service\Notification\NotificationManager();
			$notification_manager->send_door_code_updated_notification( $booking_id, $clean_code, $old_code );
		}

		return true;
	}

	/**
	 * Legacy/cron compatibility: obsolete static automatic cleanup removed.
	 */
	public static function update_approaching_bookings_door_codes() {
	}

	/**
	 * Legacy sync method: preserve assigned codes.
	 *
	 * @param object $booking Booking object
	 */
	public static function sync_booking_door_code( $booking ) {
	}

	/**
	 * Deprecated object door code propagation.
	 *
	 * @param int    $booking_object_id
	 * @param string $new_door_code
	 */
	public static function handle_object_door_code_change( $booking_object_id, $new_door_code ) {
	}
}
