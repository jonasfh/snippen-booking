<?php
/**
 * Booking Reminder Service
 *
 * Sends automated reminders X days before a confirmed booking starts.
 *
 * @package SnippenBooking\Service\Notification
 */

namespace SnippenBooking\Service\Notification;

/**
 * Class BookingReminderService
 */
class BookingReminderService {

	/**
	 * Default days before booking start.
	 */
	const DEFAULT_DAYS_BEFORE = 14;

	/**
	 * Notification manager instance.
	 *
	 * @var NotificationManager
	 */
	private $notification_manager;

	/**
	 * Constructor.
	 *
	 * @param NotificationManager|null $notification_manager Optional custom notification manager.
	 */
	public function __construct( ?NotificationManager $notification_manager = null ) {
		$this->notification_manager = $notification_manager ?: new NotificationManager();
	}

	/**
	 * Get configured number of days before booking start.
	 *
	 * Returns 0 when disabled.
	 *
	 * @return int Non-negative integer.
	 */
	public function get_days_before(): int {
		$raw = get_option( 'snippen_booking_reminder_days', self::DEFAULT_DAYS_BEFORE );
		if ( '' === $raw || null === $raw || false === $raw ) {
			return self::DEFAULT_DAYS_BEFORE;
		}

		return max( 0, (int) $raw );
	}

	/**
	 * Find confirmed bookings starting in exactly $days_before days that have not received the reminder yet.
	 *
	 * Bookings created fewer than $days_before days before their booking_date are naturally excluded
	 * because we match booking_date = reference_date + $days_before days.
	 *
	 * @param int         $days_before    Days before booking start.
	 * @param string|null $reference_date Optional Y-m-d reference date (defaults to WP local current date).
	 * @return object[] Array of booking DB rows.
	 */
	public function get_eligible_bookings( int $days_before, ?string $reference_date = null ): array {
		if ( $days_before <= 0 ) {
			return array();
		}

		global $wpdb;

		$table_bookings = $wpdb->prefix . 'snippen_bookings';
		$table_messages = $wpdb->prefix . 'snippen_messages';

		$ref_date    = $reference_date ? gmdate( 'Y-m-d', strtotime( $reference_date ) ) : current_time( 'Y-m-d' );
		$target_date = gmdate( 'Y-m-d', strtotime( "{$ref_date} +{$days_before} days" ) );

		$sql = $wpdb->prepare(
			"SELECT b.*
			 FROM {$table_bookings} b
			 WHERE b.deleted_at IS NULL
			   AND b.status = 'confirmed'
			   AND b.booking_date = %s
			   AND NOT EXISTS (
				   SELECT 1 FROM {$table_messages} m
				   WHERE m.booking_id = b.id
				     AND m.event_type = %s
				     AND m.status != 'failed'
			   )
			 ORDER BY b.id ASC",
			$target_date,
			NotificationManager::TYPE_BOOKING_REMINDER
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $sql );
		if ( empty( $results ) ) {
			return array();
		}

		$eligible = array();
		foreach ( $results as $booking ) {
			/**
			 * Filter whether an upcoming booking reminder should be sent.
			 *
			 * @param bool   $should_send Whether to send reminder.
			 * @param object $booking     Booking record.
			 * @param int    $days_before Days before booking start.
			 */
			$should_send = (bool) apply_filters( 'snippen_booking_should_send_booking_reminder', true, $booking, $days_before );
			if ( $should_send ) {
				$eligible[] = $booking;
			}
		}

		return $eligible;
	}

	/**
	 * Process upcoming booking reminders idempotently.
	 *
	 * @param string|null $reference_date Optional Y-m-d reference date for testing.
	 * @return array{days_before: int, eligible_count: int, sent_count: int}
	 */
	public function process_reminders( ?string $reference_date = null ): array {
		$days_before = $this->get_days_before();
		$summary     = array(
			'days_before'    => $days_before,
			'eligible_count' => 0,
			'sent_count'     => 0,
		);

		if ( $days_before <= 0 ) {
			return $summary;
		}

		$eligible                  = $this->get_eligible_bookings( $days_before, $reference_date );
		$summary['eligible_count'] = count( $eligible );

		foreach ( $eligible as $booking ) {
			$booking_id = (int) $booking->id;

			if ( MessageLoggerService::has_message( $booking_id, NotificationManager::TYPE_BOOKING_REMINDER ) ) {
				continue;
			}

			if ( $this->notification_manager->send_booking_reminder( $booking_id, $days_before ) ) {
				++$summary['sent_count'];
			}
		}

		return $summary;
	}

	/**
	 * Callback handler for WP-Cron daily action.
	 *
	 * @return void
	 */
	public function run_cron() {
		$result = $this->process_reminders();
		error_log( sprintf( 'BookingReminderService Cron finished. Sent: %d / %d (days_before=%d)', $result['sent_count'], $result['eligible_count'], $result['days_before'] ) );
	}
}
