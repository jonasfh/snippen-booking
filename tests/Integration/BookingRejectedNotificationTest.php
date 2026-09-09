<?php
/**
 * Booking Rejected Notification Integration Tests
 *
 * @package SnippenBooking\Tests\Integration
 */

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\Notification\NotificationManager;
use SnippenBooking\Service\Notification\MessageLoggerService;
use SnippenBooking\Api\BookingActionsApi;

/**
 * Class BookingRejectedNotificationTest
 */
class BookingRejectedNotificationTest extends TestCase {

	/**
	 * Captured emails during tests.
	 *
	 * @var array
	 */
	private static $sent_mails = array();

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		self::$sent_mails = array();
		add_filter( 'pre_wp_mail', array( $this, 'catch_mail' ), 10, 2 );

		if ( ! defined( 'DOING_AJAX' ) ) {
			define( 'DOING_AJAX', true );
		}

		add_filter(
			'wp_die_ajax_handler',
			function () {
				return function ( $message, $title, $args ) {
					throw new \Exception( is_string( $message ) ? $message : wp_json_encode( $message ) );
				};
			}
		);

		// Enable rejection email by default
		update_option( 'snippen_email_booking_rejected_enabled', 'yes' );
		update_option( 'snippen_sms_booking_rejected_enabled', 'no' );
	}

	/**
	 * Tear down test environment.
	 */
	public function tearDown(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'catch_mail' ), 10 );
		parent::tearDown();
	}

	/**
	 * Catch emails.
	 */
	public function catch_mail( $send_status, $atts ) {
		self::$sent_mails[] = $atts;
		return true;
	}

	/**
	 * Test that send_booking_rejected_notification sends email with correct placeholders.
	 */
	public function test_send_booking_rejected_notification_replaces_placeholders() {
		global $wpdb;

		// 1. Create venue object
		$wpdb->insert(
			$wpdb->prefix . 'snippen_booking_objects',
			array(
				'name'       => 'Storsalen',
				'created_at' => current_time( 'mysql' ),
			)
		);
		$obj_id = $wpdb->insert_id;

		// 2. Create customer user
		$user_id = wp_create_user( 'resident_rej', 'pass12345', 'resident_rej@example.com' );
		wp_update_user(
			array(
				'ID'           => $user_id,
				'display_name' => 'Kari Nordmann',
			)
		);
		update_user_meta( $user_id, 'snippen_phone', '+4798765432' );

		// 3. Create open booking
		$uuid = wp_generate_uuid4();
		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'uuid'             => $uuid,
				'user_id'          => $user_id,
				'customer_name'    => 'Kari Nordmann',
				'customer_email'   => 'resident_rej@example.com',
				'customer_phone'   => '+4798765432',
				'booking_date'     => '2026-11-15',
				'status'           => 'pending',
				'booking_type'     => 'open',
				'price'            => 0,
				'rejection_reason' => null,
				'created_at'       => current_time( 'mysql' ),
			)
		);
		$booking_id = $wpdb->insert_id;

		// 4. Link booking to venue
		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings_booking_objects',
			array(
				'booking_id'        => $booking_id,
				'booking_object_id' => $obj_id,
			)
		);

		// 5. Send rejected notification
		$manager = new NotificationManager();
		$sent    = $manager->send_booking_rejected_notification( $booking_id, 'Lokalet er opptatt til styremøte.' );

		$this->assertTrue( $sent );
		$this->assertNotEmpty( self::$sent_mails );

		$mail = end( self::$sent_mails );
		$this->assertEquals( 'resident_rej@example.com', $mail['to'] );
		$this->assertStringContainsString( 'Storsalen', $mail['subject'] );
		$this->assertStringContainsString( 'Kari Nordmann', $mail['message'] );
		$this->assertStringContainsString( 'Storsalen', $mail['message'] );
		$this->assertStringContainsString( '2026-11-15', $mail['message'] );
		$this->assertStringContainsString( 'Lokalet er opptatt til styremøte.', $mail['message'] );
		$this->assertStringContainsString( $uuid, $mail['message'] );

		// 6. Verify logged message
		$logs = MessageLoggerService::get_messages_for_booking( $booking_id );
		$this->assertNotEmpty( $logs );
		$this->assertEquals( NotificationManager::TYPE_BOOKING_REJECTED, $logs[0]->event_type );
		$this->assertEquals( 'email', $logs[0]->channel );
		$this->assertEquals( 'sent', $logs[0]->status );
	}

	/**
	 * Test that send_booking_rejected_notification respects the disabled email option.
	 */
	public function test_send_booking_rejected_notification_respects_disabled_option() {
		global $wpdb;

		update_option( 'snippen_email_booking_rejected_enabled', 'no' );
		update_option( 'snippen_sms_booking_rejected_enabled', 'no' );

		$uuid = wp_generate_uuid4();
		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'uuid'           => $uuid,
				'user_id'        => 1,
				'customer_name'  => 'Per Beboer',
				'customer_email' => 'per@example.com',
				'customer_phone' => '+4790000000',
				'booking_date'   => '2026-11-16',
				'status'         => 'pending',
				'booking_type'   => 'open',
				'price'          => 0,
				'created_at'     => current_time( 'mysql' ),
			)
		);
		$booking_id = $wpdb->insert_id;

		$manager = new NotificationManager();
		$sent    = $manager->send_booking_rejected_notification( $booking_id, 'Avslått.' );

		$this->assertFalse( $sent );
		$this->assertEmpty( self::$sent_mails );
	}

	/**
	 * Test that BookingActionsApi status update to cancelled triggers rejection notification.
	 */
	public function test_booking_actions_api_cancellation_triggers_rejection_notification() {
		global $wpdb;

		// Set admin user
		$admin_id = wp_create_user( 'admin_action_rej', 'pass123', 'admin_action_rej@example.com' );
		$user     = new \WP_User( $admin_id );
		$user->set_role( 'administrator' );
		$user->add_cap( 'manage_snippen_bookings' );
		wp_set_current_user( $admin_id );

		// Create venue
		$wpdb->insert(
			$wpdb->prefix . 'snippen_booking_objects',
			array(
				'name'       => 'Peisestuen',
				'created_at' => current_time( 'mysql' ),
			)
		);
		$obj_id = $wpdb->insert_id;

		// Create resident user & open booking
		$res_id = wp_create_user( 'resident_action', 'pass123', 'resident_action@example.com' );
		$uuid   = wp_generate_uuid4();
		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'uuid'           => $uuid,
				'user_id'        => $res_id,
				'customer_name'  => 'Ola Resident',
				'customer_email' => 'resident_action@example.com',
				'customer_phone' => '+4791112233',
				'booking_date'   => '2026-11-20',
				'status'         => 'pending',
				'booking_type'   => 'open',
				'price'          => 0,
				'created_at'     => current_time( 'mysql' ),
			)
		);
		$booking_id = $wpdb->insert_id;

		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings_booking_objects',
			array(
				'booking_id'        => $booking_id,
				'booking_object_id' => $obj_id,
			)
		);

		$_POST['nonce']            = wp_create_nonce( 'snippen_admin_nonce' );
		$_REQUEST['nonce']         = $_POST['nonce'];
		$_POST['id']               = $booking_id;
		$_POST['status']           = 'cancelled';
		$_POST['rejection_reason'] = 'Ikke godkjent: mangler beskrivelse.';

		// Use output buffering to catch wp_send_json
		ob_start();
		$caught_json = null;
		try {
			BookingActionsApi::update_status();
		} catch ( \Throwable $e ) {
			$caught_json = $e->getMessage();
		}
		$response_raw = ob_get_clean();
		$response     = json_decode( $caught_json ?: $response_raw, true );

		$this->assertTrue( $response['success'] );
		$this->assertEquals( 'cancelled', $response['data']['new_status'] );

		// Verify that rejection notification was sent
		$this->assertNotEmpty( self::$sent_mails );
		$mail = end( self::$sent_mails );
		$this->assertEquals( 'resident_action@example.com', $mail['to'] );
		$this->assertStringContainsString( 'Ikke godkjent: mangler beskrivelse.', $mail['message'] );
	}
}
