<?php
/**
 * Unit tests for SmsTimeWindowService and outbox time-window filtering.
 *
 * @package SnippenBooking\Tests\Unit
 */

namespace SnippenBooking\Tests\Unit;

use SnippenBooking\Service\Notification\MessageLoggerService;
use SnippenBooking\Service\Sms\SmsTimeWindowService;
use SnippenBooking\Tests\TestCase;

class SmsTimeWindowServiceTest extends TestCase {

	protected function tearDown(): void {
		delete_option( 'snippen_sms_time_window_enabled' );
		delete_option( 'snippen_sms_time_window_start' );
		delete_option( 'snippen_sms_time_window_end' );
		delete_option( 'snippen_sms_time_window_exempt_events' );
		parent::tearDown();
	}

	public function test_default_window_boundaries() {
		$tz = new \DateTimeZone( 'Europe/Oslo' );

		$this->assertFalse( SmsTimeWindowService::is_within_window( new \DateTimeImmutable( '2026-11-24 07:59:00', $tz ) ) );
		$this->assertTrue( SmsTimeWindowService::is_within_window( new \DateTimeImmutable( '2026-11-24 08:00:00', $tz ) ) );
		$this->assertTrue( SmsTimeWindowService::is_within_window( new \DateTimeImmutable( '2026-11-24 14:30:00', $tz ) ) );
		$this->assertTrue( SmsTimeWindowService::is_within_window( new \DateTimeImmutable( '2026-11-24 21:59:00', $tz ) ) );
		$this->assertFalse( SmsTimeWindowService::is_within_window( new \DateTimeImmutable( '2026-11-24 22:00:00', $tz ) ) );
		$this->assertFalse( SmsTimeWindowService::is_within_window( new \DateTimeImmutable( '2026-11-24 23:30:00', $tz ) ) );
	}

	public function test_disabled_window_allows_any_time() {
		update_option( 'snippen_sms_time_window_enabled', 'no' );
		$tz = new \DateTimeZone( 'Europe/Oslo' );

		$this->assertTrue( SmsTimeWindowService::is_within_window( new \DateTimeImmutable( '2026-11-24 03:00:00', $tz ) ) );
		$this->assertTrue( SmsTimeWindowService::can_send_now( 'booking_reminder', new \DateTimeImmutable( '2026-11-24 03:00:00', $tz ) ) );
	}

	public function test_exempt_events_can_send_outside_window() {
		$night = new \DateTimeImmutable( '2026-11-24 23:15:00', new \DateTimeZone( 'Europe/Oslo' ) );

		$exempt = array(
			'user_activation',
			'password_reset',
			'booking_confirmation',
			'booking_confirmed',
			'admin_sms_reply',
			'sms_disambiguation_prompt',
			'sms_disambiguation_confirmation',
		);

		foreach ( $exempt as $event ) {
			$this->assertTrue( SmsTimeWindowService::can_send_now( $event, $night ), "Expected {$event} to be exempt" );
		}

		$non_exempt = array(
			'door_code_updated',
			'booking_reminder',
			'payment_reminder',
			'rental_feedback',
		);

		foreach ( $non_exempt as $event ) {
			$this->assertFalse( SmsTimeWindowService::can_send_now( $event, $night ), "Expected {$event} NOT to be exempt" );
		}
	}

	public function test_custom_window_and_custom_exempt_list() {
		update_option( 'snippen_sms_time_window_start', '09:30' );
		update_option( 'snippen_sms_time_window_end', '20:00' );
		update_option( 'snippen_sms_time_window_exempt_events', array( 'user_activation' ) );

		$tz = new \DateTimeZone( 'Europe/Oslo' );

		$this->assertFalse( SmsTimeWindowService::can_send_now( 'booking_reminder', new \DateTimeImmutable( '2026-11-24 09:00:00', $tz ) ) );
		$this->assertTrue( SmsTimeWindowService::can_send_now( 'booking_reminder', new \DateTimeImmutable( '2026-11-24 09:30:00', $tz ) ) );
		$this->assertFalse( SmsTimeWindowService::can_send_now( 'booking_reminder', new \DateTimeImmutable( '2026-11-24 20:00:00', $tz ) ) );
		$this->assertTrue( SmsTimeWindowService::can_send_now( 'user_activation', new \DateTimeImmutable( '2026-11-24 23:00:00', $tz ) ) );
		$this->assertFalse( SmsTimeWindowService::can_send_now( 'booking_confirmed', new \DateTimeImmutable( '2026-11-24 23:00:00', $tz ) ) );
	}

	public function test_get_pending_outbox_filters_non_exempt_outside_window() {
		MessageLoggerService::log_message( 1, null, 'sms', '+4799887766', null, 'Aktivering', 'user_activation', 'queued' );
		MessageLoggerService::log_message( 2, null, 'sms', '+4799887766', null, 'Påminnelse', 'booking_reminder', 'queued' );
		MessageLoggerService::log_message( 3, null, 'sms', '+4799887766', null, 'Dørkode', 'door_code_updated', 'queued' );

		$outside = MessageLoggerService::get_pending_outbox( 50, false );
		$this->assertCount( 1, $outside );
		$this->assertSame( 'user_activation', $outside[0]->event_type );

		$inside = MessageLoggerService::get_pending_outbox( 50, true );
		$this->assertCount( 3, $inside );
	}
}
