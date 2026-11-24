<?php
/**
 * Unit tests for BookingReminderService.
 *
 * @package SnippenBooking\Tests\Unit
 */

namespace SnippenBooking\Tests\Unit;

use SnippenBooking\Service\Notification\BookingReminderService;
use SnippenBooking\Service\Notification\MessageLoggerService;
use SnippenBooking\Service\Notification\NotificationManager;
use SnippenBooking\Tests\TestCase;

class BookingReminderServiceTest extends TestCase {

	protected function tearDown(): void {
		delete_option( 'snippen_booking_reminder_days' );
		delete_option( 'snippen_sms_booking_reminder_enabled' );
		delete_option( 'snippen_email_booking_reminder_enabled' );
		delete_option( 'snippen_active_notification_provider' );
		delete_option( 'snippen_sms_service_api_token' );
		parent::tearDown();
	}

	private function insert_booking( array $overrides = array() ): int {
		global $wpdb;

		$defaults = array(
			'uuid'           => wp_generate_uuid4(),
			'user_id'        => 1,
			'customer_name'  => 'Ola Nordmann',
			'customer_email' => 'ola@example.com',
			'customer_phone' => '+4799887766',
			'booking_date'   => '2026-12-08',
			'status'         => 'confirmed',
			'price'          => 1000,
			'created_at'     => '2026-11-01 10:00:00',
			'modified_at'    => '2026-11-01 10:00:00',
		);

		$data = array_merge( $defaults, $overrides );
		$wpdb->insert( $wpdb->prefix . 'snippen_bookings', $data );

		return (int) $wpdb->insert_id;
	}

	public function test_default_days_before_is_14() {
		$service = new BookingReminderService();
		$this->assertSame( 14, $service->get_days_before() );

		update_option( 'snippen_booking_reminder_days', 7 );
		$this->assertSame( 7, $service->get_days_before() );

		update_option( 'snippen_booking_reminder_days', 0 );
		$this->assertSame( 0, $service->get_days_before() );
	}

	public function test_only_confirmed_bookings_at_exact_target_date_are_eligible() {
		$service = new BookingReminderService();

		$confirmed_id = $this->insert_booking( array( 'booking_date' => '2026-12-08', 'status' => 'confirmed' ) );
		$this->insert_booking( array( 'booking_date' => '2026-12-08', 'status' => 'pending' ) );
		$this->insert_booking( array( 'booking_date' => '2026-12-08', 'status' => 'cancelled' ) );
		$this->insert_booking( array( 'booking_date' => '2026-12-01', 'status' => 'confirmed' ) ); // 7 days away, skipped.

		$eligible = $service->get_eligible_bookings( 14, '2026-11-24' );

		$this->assertCount( 1, $eligible );
		$this->assertSame( $confirmed_id, (int) $eligible[0]->id );
	}

	public function test_process_reminders_is_idempotent_and_logs_sms_and_email() {
		update_option( 'snippen_active_notification_provider', 'snippen_sms_service' );
		update_option( 'snippen_sms_service_api_token', 'test-token' );
		update_option( 'snippen_sms_booking_reminder_enabled', 'yes' );
		update_option( 'snippen_email_booking_reminder_enabled', 'yes' );

		$booking_id = $this->insert_booking( array( 'booking_date' => '2026-12-08', 'status' => 'confirmed' ) );

		$service = new BookingReminderService();
		$first   = $service->process_reminders( '2026-11-24' );

		$this->assertSame( 1, $first['eligible_count'] );
		$this->assertSame( 1, $first['sent_count'] );

		$messages = MessageLoggerService::get_messages_for_booking( $booking_id );
		$this->assertCount( 2, $messages );

		$channels = wp_list_pluck( $messages, 'channel' );
		$this->assertContains( 'sms', $channels );
		$this->assertContains( 'email', $channels );

		foreach ( $messages as $msg ) {
			$this->assertSame( NotificationManager::TYPE_BOOKING_REMINDER, $msg->event_type );
			$this->assertStringContainsString( 'starter om 14 dager', $msg->message );
		}

		// Second run must not send duplicate notifications.
		$second = $service->process_reminders( '2026-11-24' );
		$this->assertSame( 0, $second['eligible_count'] );
		$this->assertSame( 0, $second['sent_count'] );
	}
}
