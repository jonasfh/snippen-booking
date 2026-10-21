<?php
/**
 * Integration Test for Inbound SMS Relay Notifications to Administrators
 *
 * @package SnippenBooking\Tests\Integration
 */

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\Sms\SmsInboxResolverService;
use SnippenBooking\Service\Notification\NotificationManager;
use SnippenBooking\Helper\Capabilities;

/**
 * Class InboundSmsNotificationTest
 */
class InboundSmsNotificationTest extends TestCase {

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

		// Ensure tables are ready and clean
		global $wpdb;
		$msg_table = $wpdb->prefix . 'snippen_messages';
		$wpdb->query( "TRUNCATE TABLE {$msg_table}" );

		// Clean relevant options
		delete_option( 'snippen_email_inbound_sms_enabled' );
		delete_option( 'snippen_sms_inbound_sms_enabled' );
		delete_option( 'snippen_active_notification_provider' );
		delete_option( 'snippen_sms_service_api_token' );
		delete_option( 'snippen_sms_service_sender' );
	}

	/**
	 * Tear down test environment
	 */
	public function tearDown(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'catch_mail' ), 10 );

		foreach ( $this->created_user_ids as $uid ) {
			wp_delete_user( $uid );
		}
		$this->created_user_ids = array();

		parent::tearDown();
	}

	/**
	 * Intercept wp_mail calls
	 *
	 * @param bool|null $send_status Current send status.
	 * @param array     $atts        Email attributes.
	 * @return bool
	 */
	public function catch_mail( $send_status, $atts ) {
		self::$sent_mails[] = $atts;
		return true;
	}

	/**
	 * Helper to create an admin user with MANAGE_BOOKINGS
	 *
	 * @param string      $email Email address.
	 * @param string|null $phone Phone number.
	 * @return int User ID.
	 */
	private function create_admin_user( string $email, ?string $phone = null ): int {
		$login   = 'admin_' . wp_generate_password( 6, false );
		$user_id = wp_create_user( $login, 'password123', $email );
		$user    = get_user_by( 'id', $user_id );
		$user->set_role( 'administrator' );
		$user->add_cap( Capabilities::MANAGE_BOOKINGS );

		if ( ! empty( $phone ) ) {
			update_user_meta( $user_id, 'snippen_phone', $phone );
		}

		$this->created_user_ids[] = $user_id;
		return $user_id;
	}

	/**
	 * Test that inbound SMS triggers email notification to booking administrators
	 */
	public function test_inbound_sms_sends_email_to_admins() {
		$this->create_admin_user( 'admin1@example.com' );

		update_option( 'snippen_email_inbound_sms_enabled', 'yes' );
		update_option( 'snippen_sms_inbound_sms_enabled', 'no' );

		$res = SmsInboxResolverService::resolve_message(
			'+4799887766',
			'Hei, vi lurer på om det er ledig på fredag.'
		);

		$this->assertNotEmpty( $res['logged_id'] );
		$this->assertNotEmpty( self::$sent_mails );

		$recipients = wp_list_pluck( self::$sent_mails, 'to' );
		$this->assertContains( 'admin1@example.com', $recipients );

		$admin_mail = null;
		foreach ( self::$sent_mails as $m ) {
			if ( 'admin1@example.com' === $m['to'] ) {
				$admin_mail = $m;
				break;
			}
		}

		$this->assertNotNull( $admin_mail );
		$this->assertStringContainsString( '+4799887766', $admin_mail['subject'] );
		$this->assertStringContainsString( 'Hei, vi lurer på om det er ledig på fredag.', $admin_mail['message'] );
		$this->assertStringContainsString( 'view_message=' . $res['logged_id'], $admin_mail['message'] );

		// Check logger table
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_messages';
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE event_type = %s AND channel = 'email' AND recipient = %s ORDER BY id DESC LIMIT 1",
				NotificationManager::TYPE_INBOUND_SMS_RECEIVED,
				'admin1@example.com'
			)
		);

		$this->assertNotNull( $row );
		$this->assertEquals( 'sent', $row->status );
		$this->assertEquals( 'admin1@example.com', $row->recipient );
	}

	/**
	 * Test that inbound SMS triggers SMS relay to administrators with registered snippen_phone
	 */
	public function test_inbound_sms_sends_sms_to_admins() {
		$this->create_admin_user( 'admin_sms@example.com', '+4790000001' );

		update_option( 'snippen_email_inbound_sms_enabled', 'no' );
		update_option( 'snippen_sms_inbound_sms_enabled', 'yes' );
		update_option( 'snippen_active_notification_provider', 'snippen_sms_service' );
		update_option( 'snippen_sms_service_api_token', 'token-abc-123' );

		$res = SmsInboxResolverService::resolve_message(
			'+4799887766',
			'Kan dere åpne døren?'
		);

		$this->assertNotEmpty( $res['logged_id'] );
		$this->assertEmpty( self::$sent_mails );

		// Verify that relay SMS was logged for the admin
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_messages';
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE event_type = %s AND channel = 'sms' AND recipient = %s ORDER BY id DESC LIMIT 1",
				NotificationManager::TYPE_INBOUND_SMS_RECEIVED,
				'+4790000001'
			)
		);

		$this->assertNotNull( $row );
		$this->assertEquals( 'queued', $row->status );
		$this->assertStringContainsString( 'Kan dere åpne døren?', $row->message );
		$this->assertStringContainsString( '+4799887766', $row->message );
	}

	/**
	 * Test that no notifications are sent when toggles are disabled
	 */
	public function test_inbound_sms_respects_disabled_settings() {
		$this->create_admin_user( 'admin_off@example.com', '+4790000002' );

		update_option( 'snippen_email_inbound_sms_enabled', 'no' );
		update_option( 'snippen_sms_inbound_sms_enabled', 'no' );

		SmsInboxResolverService::resolve_message(
			'+4799887766',
			'En melding som ikke skal videresendes.'
		);

		$this->assertEmpty( self::$sent_mails );

		global $wpdb;
		$table = $wpdb->prefix . 'snippen_messages';
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE event_type = %s",
				NotificationManager::TYPE_INBOUND_SMS_RECEIVED
			)
		);

		$this->assertEquals( 0, (int) $count );
	}

	/**
	 * Test that opt-out (STOPP) messages do NOT trigger admin relay
	 */
	public function test_rule_filtering_ignores_opt_out() {
		$this->create_admin_user( 'admin_filter@example.com' );

		update_option( 'snippen_email_inbound_sms_enabled', 'yes' );
		update_option( 'snippen_sms_inbound_sms_enabled', 'no' );

		$res = SmsInboxResolverService::resolve_message(
			'+4799887766',
			'STOPP'
		);

		$this->assertEquals( 'opt_out', $res['rule'] );
		$this->assertEmpty( self::$sent_mails );
	}

	/**
	 * Test loop protection: SMS relay is not sent back to an administrator if they sent the SMS
	 */
	public function test_loop_protection_does_not_relay_sms_to_sender_admin() {
		// Admin 1 (the sender)
		$this->create_admin_user( 'admin_sender@example.com', '+4790001111' );
		// Admin 2 (another admin)
		$this->create_admin_user( 'admin_receiver@example.com', '+4790002222' );

		update_option( 'snippen_email_inbound_sms_enabled', 'no' );
		update_option( 'snippen_sms_inbound_sms_enabled', 'yes' );
		update_option( 'snippen_active_notification_provider', 'snippen_sms_service' );
		update_option( 'snippen_sms_service_api_token', 'token-abc-123' );

		// Inbound message arrives from Admin 1's phone
		$res = SmsInboxResolverService::resolve_message(
			'+4790001111',
			'Hei fra vakttelefon'
		);

		global $wpdb;
		$table = $wpdb->prefix . 'snippen_messages';

		// Admin 1 must NOT have received a relay SMS
		$sender_relay = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE event_type = %s AND channel = 'sms' AND recipient = %s",
				NotificationManager::TYPE_INBOUND_SMS_RECEIVED,
				'+4790001111'
			)
		);
		$this->assertNull( $sender_relay );

		// Admin 2 should have received a relay SMS
		$receiver_relay = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE event_type = %s AND channel = 'sms' AND recipient = %s",
				NotificationManager::TYPE_INBOUND_SMS_RECEIVED,
				'+4790002222'
			)
		);
		$this->assertNotNull( $receiver_relay );
	}

	/**
	 * Test loop protection: Inbound SMS from gateway's own number is ignored
	 */
	public function test_loop_protection_ignores_gateway_sender_number() {
		$this->create_admin_user( 'admin_gw@example.com', '+4790003333' );

		update_option( 'snippen_email_inbound_sms_enabled', 'yes' );
		update_option( 'snippen_sms_inbound_sms_enabled', 'yes' );
		update_option( 'snippen_active_notification_provider', 'snippen_sms_service' );
		update_option( 'snippen_sms_service_api_token', 'token-abc-123' );
		update_option( 'snippen_sms_service_sender', '4790009999' );

		// Inbound message from gateway sender number
		$res = SmsInboxResolverService::resolve_message(
			'+4790009999',
			'Echo fra modem'
		);

		$this->assertEmpty( self::$sent_mails );

		global $wpdb;
		$table = $wpdb->prefix . 'snippen_messages';
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE event_type = %s",
				NotificationManager::TYPE_INBOUND_SMS_RECEIVED
			)
		);
		$this->assertEquals( 0, (int) $count );
	}

	/**
	 * Test that booking metadata is correctly included in the notification when a booking is matched
	 */
	public function test_inbound_sms_includes_booking_metadata() {
		$this->create_admin_user( 'admin_meta@example.com' );

		update_option( 'snippen_email_inbound_sms_enabled', 'yes' );
		update_option( 'snippen_sms_inbound_sms_enabled', 'no' );

		// Create active future booking
		global $wpdb;
		$b_table = $wpdb->prefix . 'snippen_bookings';
		$wpdb->insert(
			$b_table,
			array(
				'uuid'              => wp_generate_uuid4(),
				'customer_name'     => 'Ola Nordmann',
				'customer_phone'    => '+4798765432',
				'customer_email'    => 'ola@nordmann.no',
				'booking_date'      => gmdate( 'Y-m-d', strtotime( '+5 days' ) ),
				'status'            => 'approved',
				'payment_status_id' => 1,
				'price'             => 1500,
				'created_at'        => current_time( 'mysql' ),
				'modified_at'       => current_time( 'mysql' ),
			)
		);
		$booking_id = $wpdb->insert_id;

		// Link resource
		$obj_table  = $wpdb->prefix . 'snippen_booking_objects';
		$junc_table = $wpdb->prefix . 'snippen_bookings_booking_objects';
		$wpdb->insert(
			$obj_table,
			array(
				'name'        => 'Storsalen',
				'created_at'  => current_time( 'mysql' ),
				'modified_at' => current_time( 'mysql' ),
			)
		);
		$obj_id = $wpdb->insert_id;

		$wpdb->insert(
			$junc_table,
			array(
				'booking_id'        => $booking_id,
				'booking_object_id' => $obj_id,
			)
		);

		$res = SmsInboxResolverService::resolve_message(
			'+4798765432',
			'Kan vi hente nøkkel tidligere?'
		);

		$this->assertEquals( 'single_active_booking', $res['rule'] );
		$this->assertEquals( $booking_id, $res['booking_id'] );
		$this->assertNotEmpty( self::$sent_mails );

		$mail = null;
		foreach ( self::$sent_mails as $m ) {
			if ( 'admin_meta@example.com' === $m['to'] ) {
				$mail = $m;
				break;
			}
		}

		$this->assertNotNull( $mail );
		$this->assertStringContainsString( 'Ola Nordmann', $mail['message'] );
		$this->assertStringContainsString( 'Storsalen', $mail['message'] );
		$this->assertStringContainsString( 'Kan vi hente nøkkel tidligere?', $mail['message'] );
	}
}
