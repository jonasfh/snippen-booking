<?php
/**
 * SMS Gateway Health Monitoring Service
 *
 * Tracks polling activity from snippen-sms-service and alerts booking administrators
 * if polling stops beyond the configured tolerance.
 *
 * @package SnippenBooking\Service\Sms
 */

namespace SnippenBooking\Service\Sms;

use SnippenBooking\Service\Notification\NotificationManager;
use SnippenBooking\Service\Notification\SnippenSmsProvider;

/**
 * Class SmsGatewayHealthService
 */
class SmsGatewayHealthService {

	/**
	 * Option storing the Unix timestamp of the last valid gateway poll.
	 */
	const OPTION_LAST_POLL_AT = 'snippen_sms_gateway_last_poll_at';

	/**
	 * Option storing the baseline Unix timestamp when monitoring began without any prior poll.
	 */
	const OPTION_MONITOR_SINCE = 'snippen_sms_gateway_monitor_since';

	/**
	 * Option storing the Unix timestamp when an alert was sent for the current outage.
	 */
	const OPTION_ALERT_SENT_AT = 'snippen_sms_gateway_alert_sent_at';

	/**
	 * Option storing the poll tolerance in seconds.
	 */
	const OPTION_TOLERANCE_SECONDS = 'snippen_sms_gateway_poll_tolerance_seconds';

	/**
	 * Default poll tolerance in seconds (15 minutes).
	 */
	const DEFAULT_TOLERANCE_SECONDS = 900;

	/**
	 * WordPress cron hook name for gateway health checks.
	 */
	const CRON_HOOK = 'snippen_check_sms_gateway_health';

	/**
	 * Record a valid poll from snippen-sms-service and clear any active outage alert state.
	 *
	 * @param int|null $timestamp Optional Unix timestamp (defaults to current time).
	 * @return void
	 */
	public static function record_poll( ?int $timestamp = null ): void {
		$now = $timestamp ?? time();
		update_option( self::OPTION_LAST_POLL_AT, $now, false );
		delete_option( self::OPTION_ALERT_SENT_AT );
		delete_option( self::OPTION_MONITOR_SINCE );
	}

	/**
	 * Get the configured poll tolerance in seconds.
	 *
	 * @return int Tolerance in seconds (0 disables monitoring).
	 */
	public static function get_tolerance_seconds(): int {
		$raw = get_option( self::OPTION_TOLERANCE_SECONDS, self::DEFAULT_TOLERANCE_SECONDS );
		if ( '' === $raw || false === $raw ) {
			return self::DEFAULT_TOLERANCE_SECONDS;
		}
		return max( 0, (int) $raw );
	}

	/**
	 * Get the Unix timestamp of the last recorded poll, or null if never polled.
	 *
	 * @return int|null
	 */
	public static function get_last_poll_at(): ?int {
		$val = (int) get_option( self::OPTION_LAST_POLL_AT, 0 );
		return $val > 0 ? $val : null;
	}

	/**
	 * Check whether SMS gateway polling monitoring is currently active.
	 *
	 * Active when SnippenSmsProvider is the active SMS provider, is configured,
	 * and tolerance > 0.
	 *
	 * @param NotificationManager|null $notification_manager Optional manager instance.
	 * @return bool
	 */
	public static function is_monitoring_active( ?NotificationManager $notification_manager = null ): bool {
		$manager  = $notification_manager ?: new NotificationManager();
		$provider = $manager->get_active_sms_provider();

		if ( ! ( $provider instanceof SnippenSmsProvider ) || ! $provider->is_configured() ) {
			return false;
		}

		return self::get_tolerance_seconds() > 0;
	}

	/**
	 * Run the periodic health check and notify administrators if polling has stalled.
	 *
	 * @param int|null                 $now                  Optional current Unix timestamp for testing.
	 * @param NotificationManager|null $notification_manager Optional manager instance for testing.
	 * @return array{status:string,alert_sent:bool,elapsed?:int,tolerance?:int}
	 */
	public static function check_health( ?int $now = null, ?NotificationManager $notification_manager = null ): array {
		$now     = $now ?? time();
		$manager = $notification_manager ?: new NotificationManager();

		if ( ! self::is_monitoring_active( $manager ) ) {
			return array(
				'status'     => 'inactive',
				'alert_sent' => false,
			);
		}

		$tolerance    = self::get_tolerance_seconds();
		$last_poll_at = self::get_last_poll_at();

		if ( null === $last_poll_at ) {
			$monitor_since = (int) get_option( self::OPTION_MONITOR_SINCE, 0 );
			if ( $monitor_since <= 0 ) {
				update_option( self::OPTION_MONITOR_SINCE, $now, false );
				return array(
					'status'     => 'baseline_initialized',
					'alert_sent' => false,
				);
			}
			$reference_time = $monitor_since;
		} else {
			$reference_time = $last_poll_at;
		}

		$elapsed = max( 0, $now - $reference_time );

		if ( $elapsed <= $tolerance ) {
			return array(
				'status'     => 'ok',
				'elapsed'    => $elapsed,
				'tolerance'  => $tolerance,
				'alert_sent' => false,
			);
		}

		$alert_sent_at = (int) get_option( self::OPTION_ALERT_SENT_AT, 0 );
		if ( $alert_sent_at > 0 ) {
			return array(
				'status'     => 'already_alerted',
				'elapsed'    => $elapsed,
				'tolerance'  => $tolerance,
				'alert_sent' => false,
			);
		}

		$sent = $manager->send_sms_gateway_alert( $elapsed, $tolerance, $last_poll_at );
		if ( $sent ) {
			update_option( self::OPTION_ALERT_SENT_AT, $now, false );
		}

		return array(
			'status'     => $sent ? 'alert_sent' : 'alert_failed',
			'elapsed'    => $elapsed,
			'tolerance'  => $tolerance,
			'alert_sent' => $sent,
		);
	}

	/**
	 * Synchronize the WP-Cron schedule so health checks only run when snippen-sms-service is active.
	 *
	 * @param NotificationManager|null $notification_manager Optional manager instance.
	 * @return bool True if monitoring cron is active/scheduled, false if unscheduled.
	 */
	public static function sync_cron_schedule( ?NotificationManager $notification_manager = null ): bool {
		if ( self::is_monitoring_active( $notification_manager ) ) {
			if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_event( time(), 'every_five_minutes', self::CRON_HOOK );
			}
			return true;
		}

		wp_clear_scheduled_hook( self::CRON_HOOK );
		delete_option( self::OPTION_MONITOR_SINCE );
		delete_option( self::OPTION_ALERT_SENT_AT );
		return false;
	}

	/**
	 * Reset outage alert and baseline state so monitoring starts fresh.
	 *
	 * @return void
	 */
	public static function reset_alert_state(): void {
		delete_option( self::OPTION_ALERT_SENT_AT );
		delete_option( self::OPTION_MONITOR_SINCE );
	}

	/**
	 * Build a structured status summary for the admin monitoring page.
	 *
	 * @param int|null                 $now                  Optional current Unix timestamp.
	 * @param NotificationManager|null $notification_manager Optional manager instance.
	 * @return array<string,mixed>
	 */
	public static function get_status_summary( ?int $now = null, ?NotificationManager $notification_manager = null ): array {
		$now             = $now ?? time();
		$manager         = $notification_manager ?: new NotificationManager();
		$active_provider = $manager->get_active_sms_provider();
		$is_snippen_sms  = $active_provider instanceof SnippenSmsProvider;
		$is_configured   = $is_snippen_sms && $active_provider->is_configured();
		$tolerance       = self::get_tolerance_seconds();
		$monitoring      = $is_snippen_sms && $is_configured && $tolerance > 0;
		$last_poll_at    = self::get_last_poll_at();
		$monitor_since   = (int) get_option( self::OPTION_MONITOR_SINCE, 0 );
		$alert_sent_at   = (int) get_option( self::OPTION_ALERT_SENT_AT, 0 );
		$next_cron_at    = wp_next_scheduled( self::CRON_HOOK );
		$reference_time  = $last_poll_at ?? ( $monitor_since > 0 ? $monitor_since : null );
		$seconds_elapsed = null !== $reference_time ? max( 0, $now - $reference_time ) : null;
		$email_alerts_on = 'yes' === get_option( 'snippen_email_sms_gateway_alert_enabled', 'yes' );

		if ( ! $monitoring ) {
			$state = 'inactive';
		} elseif ( $alert_sent_at > 0 ) {
			$state = 'alert_active';
		} elseif ( null === $last_poll_at ) {
			$state = 'baseline';
		} elseif ( null !== $seconds_elapsed && $seconds_elapsed > $tolerance ) {
			$state = 'overdue';
		} else {
			$state = 'ok';
		}

		return array(
			'state'             => $state,
			'monitoring_active' => $monitoring,
			'provider_id'       => $active_provider ? $active_provider->get_id() : '',
			'provider_name'     => $active_provider ? $active_provider->get_name() : __( 'Ingen', 'snippen-booking' ),
			'is_snippen_sms'    => $is_snippen_sms,
			'is_configured'     => $is_configured,
			'tolerance_seconds' => $tolerance,
			'last_poll_at'      => $last_poll_at,
			'monitor_since'     => $monitor_since > 0 ? $monitor_since : null,
			'seconds_elapsed'   => $seconds_elapsed,
			'alert_sent_at'     => $alert_sent_at > 0 ? $alert_sent_at : null,
			'next_cron_at'      => $next_cron_at ? (int) $next_cron_at : null,
			'email_alerts_on'   => $email_alerts_on,
		);
	}
}
