<?php
/**
 * Pluggable Notification Integration Tests
 *
 * @package SnippenBooking\Tests\Integration
 */

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\Notification\NotificationManager;
use SnippenBooking\Service\Notification\SmsProviderInterface;

/**
 * Class NotificationPluggableTest
 */
class NotificationPluggableTest extends TestCase {

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
		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}
		self::$sent_mails = array();
		add_filter( 'pre_wp_mail', array( $this, 'catch_mail' ), 10, 2 );

		// Reset captured mock sms
		MockSmsProvider::$last_sms = null;
		
		// Register Mock SMS Provider
		add_filter( 'snippen_booking_notification_providers', array( $this, 'register_mock_provider' ) );
	}

	/**
	 * Tear down test environment.
	 */
	public function tearDown(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'catch_mail' ), 10 );
		remove_filter( 'snippen_booking_notification_providers', array( $this, 'register_mock_provider' ) );
		parent::tearDown();
	}

	/**
	 * Catch emails.
	 */
	public function catch_mail( $send_status, $atts ) {
		self::$sent_mails[] = $atts;
		return true; // prevent actual sending
	}

	/**
	 * Filter hook to register mock provider.
	 */
	public function register_mock_provider( $providers ) {
		$providers[] = new MockSmsProvider();
		return $providers;
	}

	/**
	 * Test third-party provider dynamic registration and delivery.
	 */
	public function testThirdPartySmsDelivery() {
		global $wpdb;

		// 1. Configure settings to use Mock SMS
		update_option( 'snippen_active_notification_provider', 'mock_sms' );
		update_option( 'snippen_sms_booking_confirmation_enabled', 'yes' );

		// 2. Setup mock booking
		$wpdb->insert( $wpdb->prefix . 'snippen_booking_objects', array( 'name' => 'Room A' ) );
		$obj_id = $wpdb->insert_id;

		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'uuid'           => wp_generate_uuid4(),
				'booking_date'   => '2026-07-20',
				'user_id'        => 1,
				'slot_id'        => 1,
				'customer_name'  => 'Integrator User',
				'customer_email' => 'integrator@example.com',
				'customer_phone' => '+4740004000',
				'price'          => 100,
				'status'         => 'pending',
				'created_at'     => current_time( 'mysql' ),
			)
		);
		$booking_id = $wpdb->insert_id;

		// Insert junction entry
		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings_booking_objects',
			array(
				'booking_id'        => $booking_id,
				'booking_object_id' => $obj_id,
				'created_at'        => current_time( 'mysql' ),
			)
		);

		// 3. Dispatch notifications
		$manager = new NotificationManager();
		$this->assertNotNull( $manager->get_provider( 'mock_sms' ) );

		$result = $manager->send_booking_notifications( $booking_id, 'dummy-uuid' );
		$this->assertTrue( $result );

		// 4. Assert SMS was sent through Mock SMS Provider!
		$this->assertNotNull( MockSmsProvider::$last_sms );
		$this->assertEquals( '+4740004000', MockSmsProvider::$last_sms['to'] );
		$this->assertStringContainsString( 'Room A', MockSmsProvider::$last_sms['message'] );
		$this->assertStringContainsString( 'dummy-uuid', MockSmsProvider::$last_sms['message'] );
	}
}

/**
 * Mock SMS Provider Class for Integration Testing
 */
class MockSmsProvider implements SmsProviderInterface {

	/**
	 * Track last SMS payload.
	 */
	public static $last_sms = null;

	public function get_id(): string {
		return 'mock_sms';
	}

	public function get_name(): string {
		return 'Mock SMS Provider';
	}

	public function get_settings_schema(): array {
		return array();
	}

	public function is_configured(): bool {
		return true;
	}

	public function send_sms( string $to, string $message ): bool {
		self::$last_sms = array(
			'to'      => $to,
			'message' => $message,
		);
		return true;
	}
}
