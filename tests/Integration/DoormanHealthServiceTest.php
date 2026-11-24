<?php
/**
 * Integration tests for DoormanHealthService and Doorman offline email alerts.
 *
 * @package SnippenBooking\Tests\Integration
 */

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Api\DoorLockApi;
use SnippenBooking\Helper\Capabilities;
use SnippenBooking\Service\Door\DoormanHealthService;
use SnippenBooking\Service\Notification\NotificationManager;
use SnippenBooking\Tests\TestCase;

/**
 * Class DoormanHealthServiceTest
 */
class DoormanHealthServiceTest extends TestCase {

	/**
	 * Captured wp_mail calls.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private array $sent_emails = array();

	/**
	 * Admin user ID created for tests.
	 *
	 * @var int
	 */
	private int $admin_id = 0;

	public function setUp(): void {
		parent::setUp();
		$this->sent_emails = array();

		add_filter(
			'pre_wp_mail',
			function ( $null, $atts ) {
				$this->sent_emails[] = $atts;
				return true;
			},
			10,
			2
		);

		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		$user_id = wp_insert_user(
			array(
				'user_login' => 'doorman_admin_' . wp_generate_password( 6, false ),
				'user_pass'  => 'password',
				'user_email' => 'doorman-admin@example.com',
				'role'       => 'administrator',
			)
		);
		$this->assertIsInt( $user_id );
		$this->admin_id = $user_id;
		$user           = get_user_by( 'id', $user_id );
		if ( $user ) {
			$user->add_cap( Capabilities::MANAGE_BOOKINGS );
		}

		update_option( 'snippen_enable_doorman_api', 'yes' );
		update_option( 'snippen_email_doorman_alert_enabled', 'yes' );
		update_option( 'snippen_disable_all_emails', 'no' );
		delete_option( DoormanHealthService::OPTION_TOLERANCE_MINUTES );
		delete_option( DoormanHealthService::OPTION_ALERT_SENT_AT );
		delete_option( DoormanHealthService::OPTION_MONITOR_SINCE );
		delete_option( 'snippen_door_services_registry' );
		delete_option( 'snippen_doorman_last_received_at' );
		wp_clear_scheduled_hook( DoormanHealthService::CRON_HOOK );
	}

	public function tearDown(): void {
		wp_clear_scheduled_hook( DoormanHealthService::CRON_HOOK );
		if ( $this->admin_id > 0 ) {
			wp_delete_user( $this->admin_id );
		}
		delete_option( 'snippen_enable_doorman_api' );
		delete_option( 'snippen_email_doorman_alert_enabled' );
		delete_option( DoormanHealthService::OPTION_TOLERANCE_MINUTES );
		delete_option( DoormanHealthService::OPTION_ALERT_SENT_AT );
		delete_option( DoormanHealthService::OPTION_MONITOR_SINCE );
		delete_option( 'snippen_door_services_registry' );
		delete_option( 'snippen_doorman_last_received_at' );
		parent::tearDown();
	}

	public function test_default_tolerance_is_60_minutes(): void {
		$this->assertSame( 60, DoormanHealthService::get_tolerance_minutes() );
		$this->assertSame( 3600, DoormanHealthService::get_tolerance_seconds() );
	}

	public function test_inactive_when_doorman_api_disabled_or_tolerance_zero(): void {
		update_option( 'snippen_enable_doorman_api', 'no' );
		$res = DoormanHealthService::check_health( 1700000000, new NotificationManager() );
		$this->assertSame( 'inactive', $res['status'] );
		$this->assertFalse( $res['alert_sent'] );

		update_option( 'snippen_enable_doorman_api', 'yes' );
		update_option( DoormanHealthService::OPTION_TOLERANCE_MINUTES, 0 );
		$res = DoormanHealthService::check_health( 1700000000, new NotificationManager() );
		$this->assertSame( 'inactive', $res['status'] );
		$this->assertFalse( $res['alert_sent'] );
	}

	public function test_alerts_only_once_per_outage_and_resets_when_node_reports(): void {
		$base_time = 1700000000;
		update_option(
			'snippen_door_services_registry',
			array(
				'main-door' => array(
					'service_id' => 'main-door',
					'name'       => 'Hoveddør Snippen',
					'last_seen'  => gmdate( 'Y-m-d\TH:i:s\Z', $base_time ),
				),
			)
		);

		$manager = new NotificationManager();

		// 59 minutes elapsed: still within 60-minute tolerance.
		$ok = DoormanHealthService::check_health( $base_time + ( 59 * 60 ), $manager );
		$this->assertSame( 'ok', $ok['status'] );
		$this->assertFalse( $ok['alert_sent'] );
		$this->assertCount( 0, $this->sent_emails );

		// 61 minutes elapsed: alert sent once.
		$alert = DoormanHealthService::check_health( $base_time + ( 61 * 60 ), $manager );
		$this->assertSame( 'alert_sent', $alert['status'] );
		$this->assertTrue( $alert['alert_sent'] );
		$this->assertSame( array( 'main-door' ), $alert['alerts'] );
		$this->assertCount( 1, $this->sent_emails );
		$this->assertSame( 'doorman-admin@example.com', $this->sent_emails[0]['to'] );
		$this->assertStringContainsString( 'Hoveddør Snippen', $this->sent_emails[0]['subject'] );
		$this->assertNotNull( DoormanHealthService::get_alert_sent_at( 'main-door' ) );

		// DoorLockApi status reflects offline + alert_sent_at.
		$fleet = DoorLockApi::get_all_services_status();
		$this->assertTrue( $fleet['services']['main-door']['is_offline'] );
		$this->assertNotNull( $fleet['services']['main-door']['alert_sent_at'] );

		// Days later while still offline: must NOT send a second alert.
		$repeat = DoormanHealthService::check_health( $base_time + ( 48 * 3600 ), $manager );
		$this->assertSame( 'already_alerted', $repeat['status'] );
		$this->assertFalse( $repeat['alert_sent'] );
		$this->assertCount( 1, $this->sent_emails );

		// Node comes online briefly (e.g. phone WiFi hotspot) and reports status.
		$reconnect_time = $base_time + ( 49 * 3600 );
		update_option(
			'snippen_door_services_registry',
			array(
				'main-door' => array(
					'service_id' => 'main-door',
					'name'       => 'Hoveddør Snippen',
					'last_seen'  => gmdate( 'Y-m-d\TH:i:s\Z', $reconnect_time ),
				),
			)
		);
		DoormanHealthService::record_status( 'main-door' );
		$this->assertNull( DoormanHealthService::get_alert_sent_at( 'main-door' ) );

		// Goes offline again for 65 minutes -> alerts once again.
		$second_alert = DoormanHealthService::check_health( $reconnect_time + ( 65 * 60 ), $manager );
		$this->assertSame( 'alert_sent', $second_alert['status'] );
		$this->assertTrue( $second_alert['alert_sent'] );
		$this->assertCount( 2, $this->sent_emails );
	}

	public function test_email_toggle_disabled_skips_sending(): void {
		$base_time = 1700000000;
		update_option( 'snippen_email_doorman_alert_enabled', 'no' );
		update_option(
			'snippen_door_services_registry',
			array(
				'main-door' => array(
					'service_id' => 'main-door',
					'name'       => 'Hoveddør Snippen',
					'last_seen'  => gmdate( 'Y-m-d\TH:i:s\Z', $base_time ),
				),
			)
		);

		$res = DoormanHealthService::check_health( $base_time + ( 65 * 60 ), new NotificationManager() );
		$this->assertSame( 'alert_failed', $res['status'] );
		$this->assertFalse( $res['alert_sent'] );
		$this->assertCount( 0, $this->sent_emails );
		$this->assertNull( DoormanHealthService::get_alert_sent_at( 'main-door' ) );
	}

	public function test_sync_cron_schedule_toggles_event(): void {
		$this->assertTrue( DoormanHealthService::sync_cron_schedule() );
		$this->assertNotFalse( wp_next_scheduled( DoormanHealthService::CRON_HOOK ) );

		update_option( 'snippen_enable_doorman_api', 'no' );
		$this->assertFalse( DoormanHealthService::sync_cron_schedule() );
		$this->assertFalse( wp_next_scheduled( DoormanHealthService::CRON_HOOK ) );
	}
}
