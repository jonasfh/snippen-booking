<?php
/**
 * Payment Reminder Service
 *
 * @package SnippenBooking\Service\Notification
 */

namespace SnippenBooking\Service\Notification;

use SnippenBooking\Service\PaymentService;

/**
 * Class PaymentReminderService
 * Manages automated payment reminder interval calculations, exemptions, sporing, and notification dispatch.
 */
class PaymentReminderService {

	/**
	 * Notification manager instance
	 *
	 * @var NotificationManager
	 */
	private $notification_manager;

	/**
	 * Constructor
	 *
	 * @param NotificationManager|null $notification_manager Optional custom notification manager.
	 */
	public function __construct( ?NotificationManager $notification_manager = null ) {
		$this->notification_manager = $notification_manager ?: new NotificationManager();
	}

	/**
	 * Get database table name for payment reminder sporing
	 *
	 * @return string
	 */
	private function get_reminders_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'snippen_booking_payment_reminders';
	}

	/**
	 * Ensure tracking database table exists
	 *
	 * @return void
	 */
	private function ensure_table_exists() {
		global $wpdb;
		$table = $this->get_reminders_table();
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
			$charset_collate = $wpdb->get_charset_collate();
			if ( file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}
			$sql = "CREATE TABLE $table (
				id BIGINT NOT NULL AUTO_INCREMENT,
				booking_id BIGINT NOT NULL,
				days_before INT NOT NULL,
				sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
				created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
				modified_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY booking_days (booking_id, days_before),
				KEY booking_id (booking_id)
			) $charset_collate;";
			dbDelta( $sql );
		}
	}

	/**
	 * Get configured reminder intervals (in days before booking date).
	 *
	 * Defaults to array(30, 21). Can be configured via option 'snippen_payment_reminder_days'
	 * or filtered via 'snippen_booking_payment_reminder_days'.
	 *
	 * @return int[] Sorted array of positive integers in descending order.
	 */
	public function get_configured_reminder_intervals(): array {
		$option_val = get_option( 'snippen_payment_reminder_days', array( 30, 21 ) );

		if ( is_string( $option_val ) ) {
			$json = json_decode( $option_val, true );
			if ( is_array( $json ) ) {
				$option_val = $json;
			} else {
				$option_val = array_map( 'trim', explode( ',', $option_val ) );
			}
		}

		$intervals = (array) $option_val;

		/**
		 * Filter payment reminder interval days
		 *
		 * @param array $intervals Configured interval days before booking.
		 */
		$intervals = apply_filters( 'snippen_booking_payment_reminder_days', $intervals );

		$clean = array();
		foreach ( (array) $intervals as $val ) {
			$int = (int) $val;
			if ( $int > 0 ) {
				$clean[] = $int;
			}
		}

		if ( empty( $clean ) ) {
			$clean = array( 30, 21 );
		}

		$clean = array_values( array_unique( $clean ) );
		rsort( $clean );

		return $clean;
	}

	/**
	 * Determine whether a booking is eligible for a payment reminder.
	 *
	 * @param object $booking     Booking DB record or object.
	 * @param int    $days_before Configured reminder interval days.
	 * @return bool
	 */
	public function is_booking_eligible( object $booking, int $days_before = 0 ): bool {
		// 1. Exclude soft-deleted or cancelled bookings
		if ( ! empty( $booking->deleted_at ) || ( isset( $booking->status ) && 'cancelled' === $booking->status ) ) {
			return false;
		}

		// 2. Exclude settled / paid bookings
		$payment_status = PaymentService::get_booking_payment_status( $booking );
		if ( $payment_status && ! empty( $payment_status->is_settled ) ) {
			return false;
		}

		// 3. Exclude bookings where a receipt has been uploaded (awaiting admin review)
		if ( ! empty( $booking->payment_receipt_attachment_id ) ) {
			return false;
		}

		$should_send = true;

		/**
		 * Filter whether a payment reminder should be sent for a booking
		 *
		 * @param bool   $should_send Whether to send payment reminder.
		 * @param object $booking     Booking record.
		 * @param int    $days_before Days before booking date interval.
		 */
		return (bool) apply_filters( 'snippen_booking_should_send_payment_reminder', $should_send, $booking, $days_before );
	}

	/**
	 * Find active, unpaid bookings that match a reminder interval and have not yet received it.
	 *
	 * @param int         $days_before    Interval step (days before booking).
	 * @param string|null $reference_date Optional Y-m-d reference date (defaults to current date).
	 * @return object[] Array of booking DB rows.
	 */
	public function get_eligible_bookings_for_interval( int $days_before, ?string $reference_date = null ): array {
		$this->ensure_table_exists();
		global $wpdb;

		$table_bookings  = $wpdb->prefix . 'snippen_bookings';
		$table_reminders = $this->get_reminders_table();
		$table_statuses  = $wpdb->prefix . 'snippen_payment_statuses';

		$ref_date            = $reference_date ? date( 'Y-m-d', strtotime( $reference_date ) ) : current_time( 'Y-m-d' );
		$target_booking_date = date( 'Y-m-d', strtotime( "$ref_date +{$days_before} days" ) );

		// Query active bookings where trigger date (booking_date - days_before) <= ref_date AND booking_date >= ref_date
		// and which have not received reminder for $days_before yet.
		$sql = $wpdb->prepare(
			"SELECT b.* 
			 FROM {$table_bookings} b
			 LEFT JOIN {$table_statuses} ps ON b.payment_status_id = ps.id
			 WHERE b.deleted_at IS NULL 
			   AND b.status != 'cancelled'
			   AND (ps.is_settled IS NULL OR ps.is_settled = 0)
			   AND b.booking_date <= %s
			   AND b.booking_date >= %s
			   AND b.id NOT IN (
				   SELECT booking_id FROM {$table_reminders} WHERE days_before = %d
			   )
			 ORDER BY b.booking_date ASC, b.id ASC",
			$target_booking_date,
			$ref_date,
			$days_before
		);

		$results = $wpdb->get_results( $sql );
		if ( empty( $results ) ) {
			return array();
		}

		$eligible = array();
		foreach ( $results as $booking ) {
			if ( $this->is_booking_eligible( $booking, $days_before ) ) {
				$eligible[] = $booking;
			}
		}

		return $eligible;
	}

	/**
	 * Process payment reminders across all configured intervals idempotently.
	 *
	 * @param string|null $reference_date Optional Y-m-d reference date for testing or backfills.
	 * @return array Processing summary array with total_sent, processed_intervals details.
	 */
	public function process_reminders( ?string $reference_date = null ): array {
		$this->ensure_table_exists();
		global $wpdb;

		$intervals = $this->get_configured_reminder_intervals();
		$table_rem = $this->get_reminders_table();
		$summary   = array(
			'total_sent'          => 0,
			'processed_intervals' => array(),
		);

		foreach ( $intervals as $days_before ) {
			$eligible_bookings                              = $this->get_eligible_bookings_for_interval( $days_before, $reference_date );
			$sent_count                                     = 0;
			$summary['processed_intervals'][ $days_before ] = array(
				'eligible_count' => count( $eligible_bookings ),
				'sent_count'     => 0,
			);

			foreach ( $eligible_bookings as $booking ) {
				$booking_id = (int) $booking->id;

				// Double-check idempotency constraint in DB before dispatching
				$already_sent = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM {$table_rem} WHERE booking_id = %d AND days_before = %d",
						$booking_id,
						$days_before
					)
				);

				if ( $already_sent ) {
					continue;
				}

				// Dispatch notification
				$this->notification_manager->send_payment_reminder( $booking_id, $days_before );

				// Record sent reminder in tracking DB table
				$wpdb->insert(
					$table_rem,
					array(
						'booking_id'  => $booking_id,
						'days_before' => $days_before,
						'sent_at'     => current_time( 'mysql' ),
						'created_at'  => current_time( 'mysql' ),
						'modified_at' => current_time( 'mysql' ),
					),
					array( '%d', '%d', '%s', '%s', '%s' )
				);

				++$sent_count;
				++$summary['total_sent'];
			}

			$summary['processed_intervals'][ $days_before ]['sent_count'] = $sent_count;
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
		error_log( sprintf( 'PaymentReminderService Cron finished. Total reminders sent: %d', $result['total_sent'] ) );
	}
}
