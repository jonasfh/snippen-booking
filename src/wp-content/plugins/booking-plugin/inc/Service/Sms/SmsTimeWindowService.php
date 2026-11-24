<?php
/**
 * SMS Time Window Service
 *
 * Enforces configurable send-time windows (quiet hours) for outbound SMS
 * while allowing configurable exempt event types to dispatch immediately.
 *
 * @package SnippenBooking\Service\Sms
 */

namespace SnippenBooking\Service\Sms;

/**
 * Class SmsTimeWindowService
 */
class SmsTimeWindowService {

	/**
	 * Default window start time (local HH:MM).
	 */
	const DEFAULT_START = '08:00';

	/**
	 * Default window end time (local HH:MM).
	 */
	const DEFAULT_END = '22:00';

	/**
	 * Default event types exempt from the send-time window.
	 */
	const DEFAULT_EXEMPT_EVENTS = array(
		'user_activation',
		'password_reset',
		'booking_confirmation',
		'booking_confirmed',
		'admin_sms_reply',
		'sms_disambiguation_prompt',
		'sms_disambiguation_confirmation',
	);

	/**
	 * Check whether the SMS send-time window is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return 'yes' === get_option( 'snippen_sms_time_window_enabled', 'yes' );
	}

	/**
	 * Get the configured send-time window start and end times (HH:MM).
	 *
	 * @return array{start: string, end: string}
	 */
	public static function get_window(): array {
		$start = self::sanitize_time( (string) get_option( 'snippen_sms_time_window_start', self::DEFAULT_START ), self::DEFAULT_START );
		$end   = self::sanitize_time( (string) get_option( 'snippen_sms_time_window_end', self::DEFAULT_END ), self::DEFAULT_END );

		return array(
			'start' => $start,
			'end'   => $end,
		);
	}

	/**
	 * Get event types exempt from the SMS send-time window.
	 *
	 * @return string[]
	 */
	public static function get_exempt_event_types(): array {
		$stored = get_option( 'snippen_sms_time_window_exempt_events', false );

		if ( false === $stored ) {
			$exempt = self::DEFAULT_EXEMPT_EVENTS;
		} elseif ( is_array( $stored ) ) {
			$exempt = $stored;
		} elseif ( is_string( $stored ) ) {
			$decoded = json_decode( $stored, true );
			$exempt  = is_array( $decoded ) ? $decoded : array_map( 'trim', explode( ',', $stored ) );
		} else {
			$exempt = array();
		}

		$clean = array();
		foreach ( $exempt as $event_type ) {
			$sanitized = sanitize_key( (string) $event_type );
			if ( '' !== $sanitized ) {
				$clean[] = $sanitized;
			}
		}

		$clean = array_values( array_unique( $clean ) );

		/**
		 * Filter SMS event types exempt from the send-time window.
		 *
		 * @param string[] $clean List of exempt event type slugs.
		 */
		return (array) apply_filters( 'snippen_booking_sms_window_exempt_events', $clean );
	}

	/**
	 * Check if an event type is exempt from the send-time window.
	 *
	 * @param string $event_type Notification event type.
	 * @return bool
	 */
	public static function is_exempt_event( string $event_type ): bool {
		return in_array( sanitize_key( $event_type ), self::get_exempt_event_types(), true );
	}

	/**
	 * Determine whether the given time falls within the configured SMS send-time window.
	 *
	 * @param \DateTimeInterface|null $now Optional reference datetime (defaults to WP local current_datetime()).
	 * @return bool
	 */
	public static function is_within_window( ?\DateTimeInterface $now = null ): bool {
		if ( ! self::is_enabled() ) {
			return true;
		}

		$window    = self::get_window();
		$start_min = self::time_to_minutes( $window['start'] );
		$end_min   = self::time_to_minutes( $window['end'] );

		if ( $start_min === $end_min ) {
			return true;
		}

		$now         = $now ?: current_datetime();
		$current_min = self::time_to_minutes( $now->format( 'H:i' ) );

		if ( $start_min < $end_min ) {
			return $current_min >= $start_min && $current_min < $end_min;
		}

		return $current_min >= $start_min || $current_min < $end_min;
	}

	/**
	 * Determine whether an SMS of a given event type can be dispatched right now.
	 *
	 * @param string                  $event_type Notification event type.
	 * @param \DateTimeInterface|null $now        Optional reference datetime.
	 * @return bool
	 */
	public static function can_send_now( string $event_type, ?\DateTimeInterface $now = null ): bool {
		if ( ! self::is_enabled() ) {
			return true;
		}

		if ( self::is_exempt_event( $event_type ) ) {
			return true;
		}

		return self::is_within_window( $now );
	}

	/**
	 * Sanitize an HH:MM time string, falling back to default if invalid.
	 *
	 * @param string $time     Raw time string.
	 * @param string $fallback Default HH:MM string.
	 * @return string
	 */
	public static function sanitize_time( string $time, string $fallback ): string {
		$trimmed = trim( $time );
		if ( preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', $trimmed ) ) {
			return $trimmed;
		}

		return $fallback;
	}

	/**
	 * Convert an HH:MM time string to minutes since midnight.
	 *
	 * @param string $time HH:MM string.
	 * @return int
	 */
	private static function time_to_minutes( string $time ): int {
		$parts = explode( ':', $time );
		return ( (int) $parts[0] * 60 ) + ( (int) ( $parts[1] ?? 0 ) );
	}
}
