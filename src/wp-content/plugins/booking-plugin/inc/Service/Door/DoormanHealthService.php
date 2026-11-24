<?php
/**
 * Doorman Health Monitoring Service
 *
 * Tracks status reporting activity from snippen-doorman-service nodes and alerts
 * booking administrators once per outage when a node stops reporting beyond the
 * configured tolerance.
 *
 * @package SnippenBooking\Service\Door
 */

namespace SnippenBooking\Service\Door;

use SnippenBooking\Service\Notification\NotificationManager;

/**
 * Class DoormanHealthService
 */
class DoormanHealthService {

	/**
	 * Option storing the offline alert tolerance in minutes.
	 */
	const OPTION_TOLERANCE_MINUTES = 'snippen_doorman_offline_tolerance_minutes';

	/**
	 * Default offline alert tolerance in minutes (60 minutes / 1 hour).
	 */
	const DEFAULT_TOLERANCE_MINUTES = 60;

	/**
	 * Option storing Unix timestamps when an alert was sent per service_id.
	 */
	const OPTION_ALERT_SENT_AT = 'snippen_doorman_alert_sent_at';

	/**
	 * Option storing the baseline Unix timestamp when monitoring began without any prior status report.
	 */
	const OPTION_MONITOR_SINCE = 'snippen_doorman_monitor_since';

	/**
	 * WordPress cron hook name for Doorman health checks.
	 */
	const CRON_HOOK = 'snippen_check_doorman_health';

	/**
	 * Record a status report from a snippen-doorman-service node and clear its outage alert state.
	 *
	 * @param string $service_id Sanitized service ID.
	 * @return void
	 */
	public static function record_status( string $service_id ): void {
		$service_id = sanitize_key( $service_id );
		if ( empty( $service_id ) ) {
			$service_id = 'default';
		}

		$alerts = self::get_alert_sent_map();
		$dirty  = false;

		if ( isset( $alerts[ $service_id ] ) ) {
			unset( $alerts[ $service_id ] );
			$dirty = true;
		}
		if ( isset( $alerts['global'] ) ) {
			unset( $alerts['global'] );
			$dirty = true;
		}

		if ( $dirty ) {
			if ( empty( $alerts ) ) {
				delete_option( self::OPTION_ALERT_SENT_AT );
			} else {
				update_option( self::OPTION_ALERT_SENT_AT, $alerts, false );
			}
		}

		delete_option( self::OPTION_MONITOR_SINCE );
	}

	/**
	 * Get the configured offline tolerance in minutes.
	 *
	 * @return int Tolerance in minutes (0 disables offline email alerts).
	 */
	public static function get_tolerance_minutes(): int {
		$raw = get_option( self::OPTION_TOLERANCE_MINUTES, self::DEFAULT_TOLERANCE_MINUTES );
		if ( '' === $raw || false === $raw ) {
			return self::DEFAULT_TOLERANCE_MINUTES;
		}
		return max( 0, (int) $raw );
	}

	/**
	 * Get the configured offline tolerance in seconds.
	 *
	 * @return int Tolerance in seconds (0 disables offline email alerts).
	 */
	public static function get_tolerance_seconds(): int {
		return self::get_tolerance_minutes() * MINUTE_IN_SECONDS;
	}

	/**
	 * Get the map of service_id => alert_sent_at Unix timestamps.
	 *
	 * @return array<string,int>
	 */
	public static function get_alert_sent_map(): array {
		$raw = get_option( self::OPTION_ALERT_SENT_AT, array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$clean = array();
		foreach ( $raw as $sid => $ts ) {
			$ts_int = (int) $ts;
			if ( $ts_int > 0 ) {
				$clean[ (string) $sid ] = $ts_int;
			}
		}
		return $clean;
	}

	/**
	 * Get the Unix timestamp when an alert was sent for a given service_id, or null if none.
	 *
	 * @param string $service_id Service ID.
	 * @return int|null
	 */
	public static function get_alert_sent_at( string $service_id ): ?int {
		$alerts = self::get_alert_sent_map();
		return $alerts[ $service_id ] ?? null;
	}

	/**
	 * Check whether Doorman offline monitoring is currently active.
	 *
	 * @return bool
	 */
	public static function is_monitoring_active(): bool {
		return ( 'yes' === get_option( 'snippen_enable_doorman_api', 'no' ) ) && self::get_tolerance_minutes() > 0;
	}

	/**
	 * Run the periodic health check and notify administrators once per outage per node.
	 *
	 * @param int|null                 $now                  Optional current Unix timestamp for testing.
	 * @param NotificationManager|null $notification_manager Optional manager instance for testing.
	 * @return array{status:string,alert_sent:bool,alerts:array<int,string>}
	 */
	public static function check_health( ?int $now = null, ?NotificationManager $notification_manager = null ): array {
		$now     = $now ?? time();
		$manager = $notification_manager ?: new NotificationManager();

		if ( ! self::is_monitoring_active() ) {
			return array(
				'status'     => 'inactive',
				'alert_sent' => false,
				'alerts'     => array(),
			);
		}

		$tolerance_minutes = self::get_tolerance_minutes();
		$tolerance_seconds = $tolerance_minutes * MINUTE_IN_SECONDS;
		$alerts            = self::get_alert_sent_map();

		$registry = get_option( 'snippen_door_services_registry', array() );
		if ( ! is_array( $registry ) ) {
			$registry = array();
		}

		// Backward compatibility: fall back to global last received timestamp if registry is empty.
		if ( empty( $registry ) ) {
			$last_global_iso = get_option( 'snippen_doorman_last_received_at', '' );
			if ( ! empty( $last_global_iso ) ) {
				$registry['default'] = array(
					'service_id' => 'default',
					'name'       => __( 'Standard Doorman-tjeneste', 'snippen-booking' ),
					'last_seen'  => $last_global_iso,
				);
			}
		}

		if ( empty( $registry ) ) {
			$monitor_since = (int) get_option( self::OPTION_MONITOR_SINCE, 0 );
			if ( $monitor_since <= 0 ) {
				update_option( self::OPTION_MONITOR_SINCE, $now, false );
				return array(
					'status'     => 'baseline_initialized',
					'alert_sent' => false,
					'alerts'     => array(),
				);
			}

			$elapsed = max( 0, $now - $monitor_since );
			if ( $elapsed <= $tolerance_seconds ) {
				return array(
					'status'     => 'ok',
					'alert_sent' => false,
					'alerts'     => array(),
				);
			}

			if ( ! empty( $alerts['global'] ) ) {
				return array(
					'status'     => 'already_alerted',
					'alert_sent' => false,
					'alerts'     => array(),
				);
			}

			$sent = $manager->send_doorman_alert(
				'global',
				__( 'Doorman-tjeneste', 'snippen-booking' ),
				$elapsed,
				$tolerance_minutes,
				null
			);
			if ( $sent ) {
				$alerts['global'] = $now;
				update_option( self::OPTION_ALERT_SENT_AT, $alerts, false );
			}

			return array(
				'status'     => $sent ? 'alert_sent' : 'alert_failed',
				'alert_sent' => $sent,
				'alerts'     => $sent ? array( 'global' ) : array(),
			);
		}

		$sent_ids        = array();
		$any_failed      = false;
		$already_alerted = false;

		foreach ( $registry as $sid => $node_meta ) {
			$sid          = (string) $sid;
			$last_seen_ts = ! empty( $node_meta['last_seen'] ) ? (int) strtotime( (string) $node_meta['last_seen'] ) : 0;
			$elapsed      = $last_seen_ts > 0 ? max( 0, $now - $last_seen_ts ) : ( $tolerance_seconds + 1 );

			if ( $elapsed <= $tolerance_seconds ) {
				continue;
			}

			if ( ! empty( $alerts[ $sid ] ) ) {
				$already_alerted = true;
				continue;
			}

			$service_name = ! empty( $node_meta['name'] ) ? (string) $node_meta['name'] : $sid;
			$sent         = $manager->send_doorman_alert(
				$sid,
				$service_name,
				$elapsed,
				$tolerance_minutes,
				$last_seen_ts > 0 ? $last_seen_ts : null
			);

			if ( $sent ) {
				$alerts[ $sid ] = $now;
				$sent_ids[]     = $sid;
			} else {
				$any_failed = true;
			}
		}

		if ( ! empty( $sent_ids ) ) {
			update_option( self::OPTION_ALERT_SENT_AT, $alerts, false );
			return array(
				'status'     => 'alert_sent',
				'alert_sent' => true,
				'alerts'     => $sent_ids,
			);
		}

		if ( $any_failed ) {
			return array(
				'status'     => 'alert_failed',
				'alert_sent' => false,
				'alerts'     => array(),
			);
		}

		if ( $already_alerted ) {
			return array(
				'status'     => 'already_alerted',
				'alert_sent' => false,
				'alerts'     => array(),
			);
		}

		return array(
			'status'     => 'ok',
			'alert_sent' => false,
			'alerts'     => array(),
		);
	}

	/**
	 * Synchronize the WP-Cron schedule so health checks only run when Doorman API monitoring is active.
	 *
	 * @return bool True if monitoring cron is active/scheduled, false if unscheduled.
	 */
	public static function sync_cron_schedule(): bool {
		if ( self::is_monitoring_active() ) {
			if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_event( time(), 'every_fifteen_minutes', self::CRON_HOOK );
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
}
