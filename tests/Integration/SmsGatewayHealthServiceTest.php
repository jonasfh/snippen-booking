<?php
/**
 * Integration Test for SmsGatewayHealthService and SmsGatewayApi polling tracking
 *
 * @package SnippenBooking\Tests\Integration
 */

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Api\SmsGatewayApi;
use SnippenBooking\Helper\Capabilities;
use SnippenBooking\Service\Notification\NotificationManager;
use SnippenBooking\Service\Notification\SnippenSmsProvider;
use SnippenBooking\Service\Sms\SmsGatewayHealthService;
use SnippenBooking\Tests\TestCase;

/**
 * Class SmsGatewayHealthServiceTest
 */
class SmsGatewayHealthServiceTest extends TestCase {

	/**
	 * Captured emails during tests
	 *
	 * @var array
	 */
	private static $sent_mails = array();

	/**
	 * Created user IDs to clean up
	 *
	 * @var array
	 */
	private $created_user_ids = array();

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();
		self::$sent_mails = array();
		add_filter( 'pre_wp_mail', array( $this, 'catch_mail' ), 10, 2 );

		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		global $wpdb;
		$msg_table = $wpdb->prefix . 'snippen_messages';
		$wpdb->query( "TRUNCATE TABLE {$msg_table}" );

		delete_option( 'snippen_sms_provider' );
		delete_option( 'snippen_active_notification_provider' );
		delete_option( 'snippen_sms_service_api_token' );
		delete_option( SmsGatewayHealthService::OPTION_LAST_POLL_AT );
		delete_option( SmsGatewayHealthService::OPTION_MONITOR_SINCE );
		delete_option( SmsGatewayHealthService::OPTION_ALERT_SENT_AT );
		delete_option( SmsGatewayHealthService::OPTION_TOLERANCE_SECONDS );
		delete_option( 'snippen_email_sms_gateway_alert_enabled' );
	}

	/**
	 * Tear down test environment
	 */
	public function tearDown(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'catch_mail' ), 10 );

		foreach ( $this->created_user_ids as $user_id ) {
			wp_delete_user( $user_id );
		}
		$this->created_user_ids = array();

		parent::tearDown();
	}

	/**
	 * Intercept wp_mail calls
	 *
	 * @param null|bool $return Short-circuit return value.
	 * @param array     $atts   Mail attributes.
	 * @return bool
	 */
	public function catch_mail( $return, $atts ) {
		self::$sent_mails[] = $atts;
		return true;
	}

	/**
	 * Create a booking administrator user
	 *
	 * @param string $suffix Unique suffix.
	 * @return int User ID.
	 */
	private function create_booking_admin( string $suffix ): int {
		$user_id = wp_insert_user(
			array(
				'user_login' => 'gw_admin_' . $suffix,
				'user_pass'  => 'password123',
				'user_email' => 'gw_admin_' . $suffix . '@example.com',
				'role'       => 'administrator',
			)
		);
		$user    = get_userdata( $user_id );
		$user->add_cap( Capabilities::MANAGE_BOOKINGS );
		$this->created_user_ids[] = $user_id;
		return $user_id;
	}

	/**
	 * Test default tolerance is 900 seconds (15 minutes)
	 */
	public function test_default_tolerance_is_900_seconds() {
		$this->assertSame( 900, SmsGatewayHealthService::get_tolerance_seconds() );

		$provider = new SnippenSmsProvider();
		$keys     = array_column( $provider->get_settings_schema(), null, 'key' );
		$this->assertArrayHasKey( SmsGatewayHealthService::OPTION_TOLERANCE_SECONDS, $keys );
		$this->assertSame( 900, $keys[ SmsGatewayHealthService::OPTION_TOLERANCE_SECONDS ]['default'] );
	}

	/**
	 * Test monitoring is inactive when provider is keysms or unconfigured or tolerance is 0
	 */
	public function test_monitoring_inactive_when_not_using_configured_snippen_sms() {
		$this->assertFalse( SmsGatewayHealthService::is_monitoring_active() );

		update_option( 'snippen_active_notification_provider', 'snippen_sms_service' );
		$this->assertFalse( SmsGatewayHealthService::is_monitoring_active() );

		update_option( 'snippen_sms_service_api_token', 'secret-token' );
		$this->assertTrue( SmsGatewayHealthService::is_monitoring_active() );

		update_option( SmsGatewayHealthService::OPTION_TOLERANCE_SECONDS, 0 );
		$this->assertFalse( SmsGatewayHealthService::is_monitoring_active() );
	}

	/**
	 * Test SmsGatewayApi::get_outbox records poll timestamp and clears alert state
	 */
	public function test_get_outbox_records_poll_and_clears_alert_state() {
		update_option( SmsGatewayHealthService::OPTION_ALERT_SENT_AT, 1000 );
		update_option( SmsGatewayHealthService::OPTION_MONITOR_SINCE, 900 );

		$request = new \WP_REST_Request( 'GET', '/snippen/v1/sms/outbox' );
		$resp    = SmsGatewayApi::get_outbox( $request );

		$this->assertSame( 200, $resp->get_status() );
		$this->assertNotNull( SmsGatewayHealthService::get_last_poll_at() );
		$this->assertFalse( get_option( SmsGatewayHealthService::OPTION_ALERT_SENT_AT, false ) );
		$this->assertFalse( get_option( SmsGatewayHealthService::OPTION_MONITOR_SINCE, false ) );
	}

	/**
	 * Test health check initializes baseline when no poll has been recorded yet, then alerts after tolerance
	 */
	public function test_health_check_baseline_and_alert_flow() {
		$this->create_booking_admin( '1' );
		update_option( 'snippen_active_notification_provider', 'snippen_sms_service' );
		update_option( 'snippen_sms_service_api_token', 'secret-token' );
		update_option( SmsGatewayHealthService::OPTION_TOLERANCE_SECONDS, 900 );

		// First check initializes baseline without sending an alert.
		$first = SmsGatewayHealthService::check_health( 10000 );
		$this->assertSame( 'baseline_initialized', $first['status'] );
		$this->assertFalse( $first['alert_sent'] );
		$this->assertCount( 0, self::$sent_mails );

		// Within tolerance -> ok.
		$within = SmsGatewayHealthService::check_health( 10500 );
		$this->assertSame( 'ok', $within['status'] );
		$this->assertFalse( $within['alert_sent'] );
		$this->assertCount( 0, self::$sent_mails );

		// Exceeds tolerance -> sends alert email to booking admins.
		$exceeded = SmsGatewayHealthService::check_health( 11000 );
		$this->assertSame( 'alert_sent', $exceeded['status'] );
		$this->assertTrue( $exceeded['alert_sent'] );
		$this->assertCount( 1, self::$sent_mails );
		$this->assertSame( 'gw_admin_1@example.com', self::$sent_mails[0]['to'] );
		$this->assertStringContainsString( 'Snippen SMS-tjeneste', self::$sent_mails[0]['subject'] );
		$this->assertStringContainsString( '900 sekunder', self::$sent_mails[0]['message'] );

		// Subsequent check during same outage does not re-send alert.
		$repeat = SmsGatewayHealthService::check_health( 11500 );
		$this->assertSame( 'already_alerted', $repeat['status'] );
		$this->assertFalse( $repeat['alert_sent'] );
		$this->assertCount( 1, self::$sent_mails );

		// New poll resets alert state; if it later stalls again, a new alert is sent.
		SmsGatewayHealthService::record_poll( 12000 );
		$ok_again = SmsGatewayHealthService::check_health( 12300 );
		$this->assertSame( 'ok', $ok_again['status'] );

		$second_outage = SmsGatewayHealthService::check_health( 13000 );
		$this->assertSame( 'alert_sent', $second_outage['status'] );
		$this->assertTrue( $second_outage['alert_sent'] );
		$this->assertCount( 2, self::$sent_mails );
	}

	/**
	 * Test alert is logged in wp_snippen_messages with sms_gateway_alert event type
	 */
	public function test_alert_is_logged_in_messages_table() {
		$this->create_booking_admin( 'log' );
		update_option( 'snippen_active_notification_provider', 'snippen_sms_service' );
		update_option( 'snippen_sms_service_api_token', 'secret-token' );
		update_option( SmsGatewayHealthService::OPTION_TOLERANCE_SECONDS, 300 );

		SmsGatewayHealthService::record_poll( 20000 );
		$result = SmsGatewayHealthService::check_health( 20400 );

		$this->assertTrue( $result['alert_sent'] );

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}snippen_messages WHERE event_type = %s ORDER BY id DESC LIMIT 1",
				NotificationManager::TYPE_SMS_GATEWAY_ALERT
			)
		);
		$this->assertNotNull( $row );
		$this->assertSame( 'email', $row->channel );
		$this->assertSame( 'sent', $row->status );
		$this->assertSame( 'gw_admin_log@example.com', $row->recipient );
	}
}
