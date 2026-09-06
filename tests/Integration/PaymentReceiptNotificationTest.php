<?php
/**
 * Payment Receipt Notification Integration Tests
 *
 * @package SnippenBooking\Tests\Integration
 */

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\Notification\NotificationManager;
use SnippenBooking\Service\Notification\MessageLoggerService;

/**
 * Class PaymentReceiptNotificationTest
 */
class PaymentReceiptNotificationTest extends TestCase {

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
	 * Test sending notification with objects from junction table.
	 */
	public function test_send_payment_receipt_uploaded_notification_with_junction_objects() {
		global $wpdb;

		// 1. Create booking object
		$wpdb->insert(
			$wpdb->prefix . 'snippen_booking_objects',
			array(
				'name'       => 'Festsalen',
				'created_at' => current_time( 'mysql' ),
			)
		);
		$obj_id = $wpdb->insert_id;

		// 2. Create booking
		$uuid = wp_generate_uuid4();
		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'uuid'              => $uuid,
				'user_id'           => 10,
				'customer_name'     => 'Kari Nordmann',
				'customer_email'    => 'kari@example.com',
				'customer_phone'    => '+4790000000',
				'booking_date'      => '2026-09-10',
				'price'             => 1500,
				'status'            => 'pending',
				'payment_status_id' => 1,
				'booking_snapshot'  => wp_json_encode(
					array(
						'time_range_formatted' => '12:00 - 18:00',
					)
				),
				'created_at'        => current_time( 'mysql' ),
				'modified_at'       => current_time( 'mysql' ),
			)
		);
		$booking_id = $wpdb->insert_id;

		// 3. Link booking object
		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings_booking_objects',
			array(
				'booking_id'        => $booking_id,
				'booking_object_id' => $obj_id,
				'created_at'        => current_time( 'mysql' ),
			)
		);

		update_option( 'snippen_email_payment_receipt_uploaded_enabled', 'yes' );
		update_option( 'snippen_payment_admin_emails', 'admin1@example.com, admin2@example.com' );

		$manager = new NotificationManager();
		$result  = $manager->send_payment_receipt_uploaded_notification( $booking_id );

		$this->assertTrue( $result );
		$this->assertCount( 2, self::$sent_mails );

		$this->assertEquals( 'admin1@example.com', self::$sent_mails[0]['to'] );
		$this->assertEquals( 'admin2@example.com', self::$sent_mails[1]['to'] );
		$this->assertStringContainsString( 'Festsalen', self::$sent_mails[0]['subject'] );

		// Check message log
		$messages = MessageLoggerService::get_messages_for_booking( $booking_id );
		$this->assertNotEmpty( $messages );

		$receipt_messages = array_filter(
			$messages,
			function ( $m ) {
				return $m->event_type === 'payment_receipt_uploaded';
			}
		);
		$this->assertCount( 2, $receipt_messages );
	}

	/**
	 * Test sending notification with fallback to snapshot objects.
	 */
	public function test_send_payment_receipt_uploaded_notification_snapshot_fallback() {
		global $wpdb;

		$uuid = wp_generate_uuid4();
		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'uuid'              => $uuid,
				'user_id'           => 11,
				'customer_name'     => 'Ola Nordmann',
				'customer_email'    => 'ola@example.com',
				'customer_phone'    => '+4791111111',
				'booking_date'      => '2026-09-12',
				'price'             => 2000,
				'status'            => 'pending',
				'payment_status_id' => 1,
				'booking_snapshot'  => wp_json_encode(
					array(
						'time_range_formatted' => '10:00 - 16:00',
						'objects'              => array(
							array(
								'id'   => 1,
								'name' => 'Peisestuen',
							),
						),
					)
				),
				'created_at'        => current_time( 'mysql' ),
				'modified_at'       => current_time( 'mysql' ),
			)
		);
		$booking_id = $wpdb->insert_id;

		update_option( 'snippen_email_payment_receipt_uploaded_enabled', 'yes' );
		update_option( 'snippen_payment_admin_emails', 'admin@example.com' );

		$manager = new NotificationManager();
		$result  = $manager->send_payment_receipt_uploaded_notification( $booking_id );

		$this->assertTrue( $result );
		$this->assertCount( 1, self::$sent_mails );
		$this->assertStringContainsString( 'Peisestuen', self::$sent_mails[0]['subject'] );
	}

	/**
	 * Test when notifications are disabled.
	 */
	public function test_send_payment_receipt_uploaded_notification_disabled() {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'uuid'              => wp_generate_uuid4(),
				'user_id'           => 12,
				'customer_name'     => 'Test User',
				'customer_email'    => 'test@example.com',
				'booking_date'      => '2026-09-15',
				'price'             => 500,
				'status'            => 'pending',
				'payment_status_id' => 1,
				'created_at'        => current_time( 'mysql' ),
				'modified_at'       => current_time( 'mysql' ),
			)
		);
		$booking_id = $wpdb->insert_id;

		update_option( 'snippen_email_payment_receipt_uploaded_enabled', 'no' );
		update_option( 'snippen_sms_payment_receipt_uploaded_enabled', 'no' );

		$manager = new NotificationManager();
		$result  = $manager->send_payment_receipt_uploaded_notification( $booking_id );

		$this->assertFalse( $result );
		$this->assertEmpty( self::$sent_mails );
	}

	/**
	 * Test for nonexistent booking.
	 */
	public function test_send_payment_receipt_uploaded_notification_nonexistent_booking() {
		$manager = new NotificationManager();
		$result  = $manager->send_payment_receipt_uploaded_notification( 999999 );

		$this->assertFalse( $result );
	}
}
