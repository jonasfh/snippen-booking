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
		$user_id = wp_create_user( 'testuser_' . time(), 'password123', 'test@example.com' );
		update_user_meta( $user_id, 'snippen_phone', '+4790000000' );

		update_option( 'snippen_route_user_activation', 'email' );

		// Send confirmation
		$service = new AccountConfirmationService();
		$service->send_code( $user_id );

		// Verify email was sent with custom template
		$this->assertNotEmpty( self::$sent_mails );
		$mail = self::$sent_mails[0];

		$this->assertEquals( 'Custom Confirmation', $mail['subject'] );
		$this->assertStringContainsString( 'Custom code:', $mail['message'] );
		$this->assertStringContainsString( 'testuser_' . substr( $user_id, -4 ), $mail['message'] );

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
}
