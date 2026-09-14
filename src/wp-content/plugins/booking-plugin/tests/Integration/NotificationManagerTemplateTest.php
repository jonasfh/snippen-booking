<?php
/**
 * Notification Manager Integration Tests with Templates
 *
 * @package SnippenBooking\Tests\Integration
 */

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\Notification\NotificationManager;
use SnippenBooking\Service\Notification\NotificationTemplateService;
use SnippenBooking\Service\AccountConfirmationService;

/**
 * Integration tests for notification manager with templates
 */
class NotificationManagerTemplateTest extends TestCase {

	/**
	 * Captured emails during tests
	 *
	 * @var array
	 */
	private static $sent_mails = array();

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

		add_filter(
			'wp_die_ajax_handler',
			function() {
				return function( $message ) {
					throw new \Exception( is_string( $message ) ? $message : wp_json_encode( $message ) );
				};
			}
		);
	}

	/**
	 * Tear down test environment
	 */
	public function tearDown(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'catch_mail' ), 10 );
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
	 * Test account confirmation uses custom template
	 */
	public function test_account_confirmation_uses_custom_template() {
		$template_service = new NotificationTemplateService();
		$custom_template  = 'Custom code: {{confirmation_code}} for {{user_name}}';

		$template_service->save_template( 'user_activation', 'email', 'Custom Confirmation', $custom_template );

		// Create test user
		$username = 'testuser_' . time();
		$user_id  = wp_create_user( $username, 'password123', 'test@example.com' );
		update_user_meta( $user_id, 'snippen_phone', '+4790000000' );

		update_option( 'snippen_email_user_activation_enabled', 'yes' );
		update_option( 'snippen_route_user_activation', 'email' );

		// Send confirmation
		$service = new AccountConfirmationService();
		$service->send_code( $user_id );

		// Verify email was sent with custom template
		$this->assertNotEmpty( self::$sent_mails );
		$mail = self::$sent_mails[0];

		$this->assertEquals( 'Custom Confirmation', $mail['subject'] );
		$this->assertStringContainsString( 'Custom code:', $mail['message'] );
		$this->assertStringContainsString( $username, $mail['message'] );

		// Cleanup
		wp_delete_user( $user_id );
	}

	/**
	 * Test booking confirmation uses custom template
	 */
	public function test_booking_confirmation_uses_custom_template() {
		global $wpdb;

		$template_service = new NotificationTemplateService();
		$custom_template  = 'Thank you {{user_name}} for booking {{booking_objects}} on {{booking_date}}';

		$template_service->save_template( 'booking_confirmation', 'email', 'Thanks for Booking!', $custom_template );

		update_option( 'snippen_email_booking_confirmation_enabled', 'yes' );
		update_option( 'snippen_route_booking_confirmation', 'email' );

		// Create test booking object
		$wpdb->insert(
			$wpdb->prefix . 'snippen_booking_objects',
			array(
				'name'       => 'Test Room',
				'created_at' => current_time( 'mysql' ),
			)
		);
		$object_id = $wpdb->insert_id;

		// Create test booking
		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'uuid'           => wp_generate_uuid4(),
				'booking_date'   => '2026-07-21',
				'user_id'        => 1,
				'slot_id'        => 1,
				'customer_name'  => 'John Doe',
				'customer_email' => 'john@example.com',
				'customer_phone' => '+4790000000',
				'price'          => 200,
				'status'         => 'pending',
				'created_at'     => current_time( 'mysql' ),
			)
		);
		$booking_id = $wpdb->insert_id;
		$uuid       = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT uuid FROM {$wpdb->prefix}snippen_bookings WHERE id = %d",
				$booking_id
			)
		);

		// Link booking to object
		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings_booking_objects',
			array(
				'booking_id'        => $booking_id,
				'booking_object_id' => $object_id,
				'created_at'        => current_time( 'mysql' ),
			)
		);

		// Send booking notifications
		$manager = new NotificationManager();
		$manager->send_booking_notifications( $booking_id, $uuid );

		// Verify custom template was used
		$customer_mail = null;
		foreach ( self::$sent_mails as $mail ) {
			if ( 'john@example.com' === $mail['to'] ) {
				$customer_mail = $mail;
				break;
			}
		}

		$this->assertNotNull( $customer_mail );
		$this->assertEquals( 'Thanks for Booking!', $customer_mail['subject'] );
		$this->assertStringContainsString( 'Thank you John Doe', $customer_mail['message'] );
		$this->assertStringContainsString( 'Test Room', $customer_mail['message'] );
		$this->assertStringContainsString( '2026-07-21', $customer_mail['message'] );
	}

	/**
	 * Test default template is used when no custom template exists
	 */
	public function test_default_template_when_no_custom_exists() {
		// Ensure no custom template
		delete_option( 'snippen_template_user_activation_email' );

		$user_id = wp_create_user( 'testuser_' . time(), 'password123', 'test2@example.com' );
		update_user_meta( $user_id, 'snippen_phone', '+4791111111' );

		update_option( 'snippen_email_user_activation_enabled', 'yes' );
		update_option( 'snippen_route_user_activation', 'email' );

		$service = new AccountConfirmationService();
		$service->send_code( $user_id );

		// Verify email was sent with default template
		$this->assertNotEmpty( self::$sent_mails );
		$mail = self::$sent_mails[0];

		// Default template should have Norwegian text
		$this->assertStringContainsString( 'Bekreftelseskode', $mail['subject'] );

		// Cleanup
		wp_delete_user( $user_id );
	}

	/**
	 * Test admin notification uses custom template
	 */
	public function test_admin_notification_uses_custom_template() {
		global $wpdb;

		$template_service = new NotificationTemplateService();
		$custom_template  = 'Admin Alert: {{user_name}} ({{user_phone}}) booked {{booking_objects}}';

		$template_service->save_template( 'admin_booking', 'email', 'New Booking!', $custom_template );

		// Create admin user
		$admin_id = wp_create_user( 'admin_' . time(), 'password123', 'admin@example.com' );
		grant_super_admin( $admin_id );

		// Create test booking object
		$wpdb->insert(
			$wpdb->prefix . 'snippen_booking_objects',
			array(
				'name'       => 'Admin Test Room',
				'created_at' => current_time( 'mysql' ),
			)
		);
		$object_id = $wpdb->insert_id;

		// Create booking
		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'uuid'           => wp_generate_uuid4(),
				'booking_date'   => '2026-08-01',
				'user_id'        => 1,
				'slot_id'        => 1,
				'customer_name'  => 'Jane Smith',
				'customer_email' => 'jane@example.com',
				'customer_phone' => '+4792222222',
				'price'          => 300,
				'status'         => 'pending',
				'created_at'     => current_time( 'mysql' ),
			)
		);
		$booking_id = $wpdb->insert_id;

		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings_booking_objects',
			array(
				'booking_id'        => $booking_id,
				'booking_object_id' => $object_id,
				'created_at'        => current_time( 'mysql' ),
			)
		);

		$uuid = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT uuid FROM {$wpdb->prefix}snippen_bookings WHERE id = %d",
				$booking_id
			)
		);

		update_option( 'snippen_email_admin_booking_enabled', 'yes' );
		update_option( 'snippen_route_admin_booking', 'email' );

		$manager = new NotificationManager();
		$manager->send_booking_notifications( $booking_id, $uuid );

		// Verify admin received custom notification
		$admin_mail = null;
		foreach ( self::$sent_mails as $mail ) {
			if ( 'admin@example.com' === $mail['to'] ) {
				$admin_mail = $mail;
				break;
			}
		}

		$this->assertNotNull( $admin_mail );
		$this->assertEquals( 'New Booking!', $admin_mail['subject'] );
		$this->assertStringContainsString( 'Admin Alert: Jane Smith', $admin_mail['message'] );
		$this->assertStringContainsString( '+4792222222', $admin_mail['message'] );
	}

	/**
	 * Test get_notification_preview returns templates and placeholders
	 */
	public function test_get_notification_preview_templates_and_placeholders() {
		global $wpdb;

		// Authenticate as admin
		$user_id = wp_create_user( 'testadmin_' . time(), 'password123', 'adminpreview@example.com' );
		$user    = get_user_by( 'id', $user_id );
		$user->add_cap( 'manage_snippen_bookings' );
		wp_set_current_user( $user_id );

		// Create test booking
		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'uuid'           => wp_generate_uuid4(),
				'booking_date'   => '2026-09-01',
				'user_id'        => 1,
				'slot_id'        => 1,
				'customer_name'  => 'Alice Admin',
				'customer_email' => 'alice@example.com',
				'customer_phone' => '+4793333333',
				'price'          => 500,
				'status'         => 'confirmed',
				'created_at'     => current_time( 'mysql' ),
			)
		);
		$booking_id = $wpdb->insert_id;

		$_POST['nonce']    = wp_create_nonce( 'snippen_admin_nonce' );
		$_REQUEST['nonce'] = $_POST['nonce'];
		$_POST['id']       = $booking_id;
		$_POST['channel']  = 'email_customer';

		ob_start();
		try {
			\SnippenBooking\Api\BookingActionsApi::get_notification_preview();
		} catch ( \Throwable $e ) {
			// Expected AJAX completion
		}
		$response_json = ob_get_clean();
		$data          = json_decode( $response_json, true );

		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'templates', $data['data'] );
		$this->assertArrayHasKey( 'placeholders', $data['data'] );
		$this->assertEquals( 'alice@example.com', $data['data']['recipient'] );
		$this->assertEquals( 'booking_confirmation', $data['data']['default_template_key'] );
	}

	/**
	 * Test dispatch_notification_manually replaces placeholders in custom message
	 */
	public function test_dispatch_notification_manually_replaces_placeholders() {
		global $wpdb;

		$user_id = wp_create_user( 'testadmin2_' . time(), 'password123', 'admindispatch@example.com' );
		$user    = get_user_by( 'id', $user_id );
		$user->add_cap( 'manage_snippen_bookings' );
		wp_set_current_user( $user_id );

		// Create test booking
		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'uuid'           => wp_generate_uuid4(),
				'booking_date'   => '2026-09-10',
				'user_id'        => 1,
				'slot_id'        => 1,
				'customer_name'  => 'Bob Builder',
				'customer_email' => 'bob@example.com',
				'customer_phone' => '+4794444444',
				'price'          => 450,
				'status'         => 'confirmed',
				'created_at'     => current_time( 'mysql' ),
			)
		);
		$booking_id = $wpdb->insert_id;

		$_POST['nonce']    = wp_create_nonce( 'snippen_admin_nonce' );
		$_REQUEST['nonce'] = $_POST['nonce'];
		$_POST['id']       = $booking_id;
		$_POST['channel']  = 'email_customer';
		$_POST['subject']  = 'Hei {{user_name}}';
		$_POST['message']  = 'Din booking er bekreftet for {{booking_date}}. Totalpris: {{booking_price}} kr.';

		ob_start();
		try {
			\SnippenBooking\Api\BookingActionsApi::dispatch_notification_manually();
		} catch ( \Throwable $e ) {
			// Expected AJAX completion
		}
		$response_json = ob_get_clean();
		$data          = json_decode( $response_json, true );

		$this->assertTrue( $data['success'] );

		$sent_mail = null;
		foreach ( self::$sent_mails as $mail ) {
			if ( 'bob@example.com' === $mail['to'] ) {
				$sent_mail = $mail;
				break;
			}
		}

		$this->assertNotNull( $sent_mail );
		$this->assertEquals( 'Hei Bob Builder', $sent_mail['subject'] );
		$this->assertStringContainsString( 'Din booking er bekreftet for 2026-09-10.', $sent_mail['message'] );
		$this->assertStringContainsString( 'Totalpris: 450 kr.', $sent_mail['message'] );
	}

	/**
	 * Test send_admin_booking_notification dispatches admin email and is idempotent
	 */
	public function test_send_admin_booking_notification_dispatches_email_and_is_idempotent() {
		global $wpdb;

		update_option( 'snippen_email_admin_booking_enabled', 'yes' );
		update_option( 'snippen_sms_admin_booking_enabled', 'no' );

		// Create admin user
		$admin_id = wp_create_user( 'admin_notif_test_' . time(), 'pass123', 'admin_notif@example.com' );
		$user     = get_user_by( 'id', $admin_id );
		$user->add_cap( 'manage_snippen_bookings' );

		$uuid = wp_generate_uuid4();
		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'uuid'           => $uuid,
				'booking_date'   => '2026-11-20',
				'user_id'        => 1,
				'customer_name'  => 'Admin Target User',
				'customer_email' => 'target@example.com',
				'customer_phone' => '+4791111111',
				'price'          => 1200,
				'status'         => 'confirmed',
				'created_at'     => current_time( 'mysql' ),
			)
		);
		$booking_id = $wpdb->insert_id;

		self::$sent_mails = array();

		$manager = new NotificationManager();
		$result  = $manager->send_admin_booking_notification( $booking_id, $uuid );
		$this->assertTrue( $result );

		// Verify admin received email
		$admin_mail = null;
		foreach ( self::$sent_mails as $mail ) {
			if ( 'admin_notif@example.com' === $mail['to'] ) {
				$admin_mail = $mail;
				break;
			}
		}
		$this->assertNotNull( $admin_mail );
		$this->assertTrue( \SnippenBooking\Service\Notification\MessageLoggerService::has_message( $booking_id, NotificationManager::TYPE_ADMIN_BOOKING ) );

		// Clear sent mails and call again - idempotency check
		self::$sent_mails = array();
		$second_call      = $manager->send_admin_booking_notification( $booking_id, $uuid );
		$this->assertTrue( $second_call );
		$this->assertEmpty( self::$sent_mails, 'Should not send duplicate email on second call' );
	}

	/**
	 * Test MessageLoggerService::has_message helper
	 */
	public function test_message_logger_service_has_message_filters() {
		$booking_id = 9999;
		$this->assertFalse( \SnippenBooking\Service\Notification\MessageLoggerService::has_message( $booking_id, 'test_event' ) );

		\SnippenBooking\Service\Notification\MessageLoggerService::log_message(
			$booking_id,
			1,
			'sms',
			'+4790000000',
			null,
			'Test body',
			'test_event',
			'sent'
		);

		$this->assertTrue( \SnippenBooking\Service\Notification\MessageLoggerService::has_message( $booking_id, 'test_event' ) );
		$this->assertTrue( \SnippenBooking\Service\Notification\MessageLoggerService::has_message( $booking_id, 'test_event', 'sms' ) );
		$this->assertFalse( \SnippenBooking\Service\Notification\MessageLoggerService::has_message( $booking_id, 'test_event', 'email' ) );
		$this->assertTrue( \SnippenBooking\Service\Notification\MessageLoggerService::has_message( $booking_id, 'test_event', 'sms', 'sent' ) );
		$this->assertFalse( \SnippenBooking\Service\Notification\MessageLoggerService::has_message( $booking_id, 'test_event', 'sms', 'failed' ) );
	}
}
