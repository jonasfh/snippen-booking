<?php
/**
 * Payment Reminder Service Unit Test
 *
 * @package SnippenBooking\Tests\Unit
 */

namespace SnippenBooking\Tests\Unit;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\Notification\PaymentReminderService;
use SnippenBooking\Service\Notification\MessageLoggerService;

/**
 * Class PaymentReminderServiceTest
 */
class PaymentReminderServiceTest extends TestCase {

	/**
	 * Payment reminder service instance
	 *
	 * @var PaymentReminderService
	 */
	private $service;

	public function setUp(): void {
		parent::setUp();
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_booking_payment_reminders" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_messages" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_bookings" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_notification_templates" );
		delete_option( 'snippen_payment_reminder_days' );
		remove_all_filters( 'snippen_booking_payment_reminder_days' );
		remove_all_filters( 'snippen_booking_should_send_payment_reminder' );

		$this->service = new PaymentReminderService();
	}

	/**
	 * Test default and custom interval configuration
	 */
	public function test_get_configured_reminder_intervals() {
		// Default intervals
		$this->assertEquals( array( 30, 21 ), $this->service->get_configured_reminder_intervals() );

		// Custom option string
		update_option( 'snippen_payment_reminder_days', '14, 28, 7' );
		$this->assertEquals( array( 28, 14, 7 ), $this->service->get_configured_reminder_intervals() );

		// Custom filter
		add_filter(
			'snippen_booking_payment_reminder_days',
			function () {
				return array( 10, 5, 1 );
			}
		);
		$this->assertEquals( array( 10, 5, 1 ), $this->service->get_configured_reminder_intervals() );
	}

	/**
	 * Test eligibility checks for various booking states
	 */
	public function test_is_booking_eligible_exemption_rules() {
		// 1. Active unpaid booking with no receipt
		$unpaid_booking = (object) array(
			'id'                            => 101,
			'status'                        => 'confirmed',
			'payment_status_id'             => 1, // UNPAID
			'payment_receipt_attachment_id' => null,
			'deleted_at'                    => null,
		);
		$this->assertTrue( $this->service->is_booking_eligible( $unpaid_booking, 30 ) );

		// 2. Paid booking
		$paid_booking = (object) array(
			'id'                            => 102,
			'status'                        => 'confirmed',
			'payment_status_id'             => 2, // PAID
			'payment_receipt_attachment_id' => null,
			'deleted_at'                    => null,
		);
		$this->assertFalse( $this->service->is_booking_eligible( $paid_booking, 30 ) );

		// 3. Receipt uploaded (awaiting admin review) - EXEMPT
		$receipt_booking = (object) array(
			'id'                            => 103,
			'status'                        => 'confirmed',
			'payment_status_id'             => 1, // UNPAID
			'payment_receipt_attachment_id' => 999,
			'deleted_at'                    => null,
		);
		$this->assertFalse( $this->service->is_booking_eligible( $receipt_booking, 30 ) );

		// 4. Cancelled booking
		$cancelled_booking = (object) array(
			'id'                            => 104,
			'status'                        => 'cancelled',
			'payment_status_id'             => 1,
			'payment_receipt_attachment_id' => null,
			'deleted_at'                    => null,
		);
		$this->assertFalse( $this->service->is_booking_eligible( $cancelled_booking, 30 ) );

		// 5. Custom filter exemption
		add_filter(
			'snippen_booking_should_send_payment_reminder',
			function ( $should_send, $booking ) {
				return (int) $booking->id === 101 ? false : $should_send;
			},
			10,
			2
		);
		$this->assertFalse( $this->service->is_booking_eligible( $unpaid_booking, 30 ) );
	}

	/**
	 * Test end-to-end process_reminders workflow, idempotency, and multi-step reminders
	 */
	public function test_process_reminders_idempotency_and_multistep() {
		global $wpdb;
		$table_bookings = $wpdb->prefix . 'snippen_bookings';

		$ref_date     = '2026-09-01';
		$booking_date = '2026-10-01'; // Exactly 30 days after ref_date

		// Insert test unpaid booking
		$wpdb->insert(
			$table_bookings,
			array(
				'uuid'                          => 'uuid-test-247-1',
				'customer_name'                 => 'Kari Nordmann',
				'customer_email'                => 'kari@example.com',
				'customer_phone'                => '+4799887766',
				'booking_date'                  => $booking_date,
				'price'                         => 1200.00,
				'status'                        => 'confirmed',
				'payment_status_id'             => 1, // UNPAID
				'payment_receipt_attachment_id' => null,
			)
		);
		$booking_id = (int) $wpdb->insert_id;

		// 1. Run process_reminders on ref_date (30 days before booking)
		$summary1 = $this->service->process_reminders( $ref_date );

		$this->assertEquals( 1, $summary1['total_sent'] );
		$this->assertEquals( 1, $summary1['processed_intervals'][30]['sent_count'] );

		// Check message logger
		$messages = MessageLoggerService::get_messages_for_booking( $booking_id );
		$this->assertCount( 1, $messages );
		$this->assertEquals( 'payment_reminder', $messages[0]->event_type );
		$this->assertStringContainsString( 'kari@example.com', $messages[0]->recipient );

		// 2. Run process_reminders AGAIN on ref_date (Idempotency verification)
		$summary2 = $this->service->process_reminders( $ref_date );
		$this->assertEquals( 0, $summary2['total_sent'] );
		$this->assertEquals( 0, $summary2['processed_intervals'][30]['sent_count'] );

		$messages_after_repeat = MessageLoggerService::get_messages_for_booking( $booking_id );
		$this->assertCount( 1, $messages_after_repeat ); // Still only 1 message sent!

		// 3. Advance time to 21 days before booking (2026-09-10)
		$ref_date_21 = '2026-09-10'; // Exactly 21 days before 2026-10-01
		$summary3    = $this->service->process_reminders( $ref_date_21 );

		$this->assertEquals( 1, $summary3['total_sent'] );
		$this->assertEquals( 1, $summary3['processed_intervals'][21]['sent_count'] );

		$messages_after_21 = MessageLoggerService::get_messages_for_booking( $booking_id );
		$this->assertCount( 2, $messages_after_21 ); // Second reminder step sent successfully!
	}
}
