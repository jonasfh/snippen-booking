<?php
/**
 * MessageLoggerService Test
 *
 * @package SnippenBooking\Tests\Integration
 */

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\Notification\MessageLoggerService;

/**
 * Class MessageLoggerServiceTest
 */
class MessageLoggerServiceTest extends TestCase {

	/**
	 * Test logging and retrieving messages for a booking.
	 */
	public function testLogMessageAndGetForBooking() {
		global $wpdb;

		$booking_id = 9991;
		$user_id    = 55;

		$id1 = MessageLoggerService::log_message(
			$booking_id,
			$user_id,
			'email',
			'user@example.com',
			'Booking bekreftet',
			'Din booking er bekreftet.',
			'booking_confirmation',
			'sent',
			array( 'source' => 'test' )
		);

		$this->assertNotEmpty( $id1 );

		$id2 = MessageLoggerService::log_message(
			$booking_id,
			$user_id,
			'sms',
			'+4799887766',
			null,
			'SMS varsel om booking.',
			'booking_confirmation',
			'sent'
		);

		$this->assertNotEmpty( $id2 );

		// Log a non-booking message (e.g. user activation).
		$id3 = MessageLoggerService::log_message(
			null,
			$user_id,
			'sms',
			'+4799887766',
			null,
			'Din bekreftelseskode er 123456.',
			'user_activation',
			'sent'
		);

		$this->assertNotEmpty( $id3 );

		// Retrieve messages for booking.
		$messages = MessageLoggerService::get_messages_for_booking( $booking_id );
		$this->assertCount( 2, $messages );
		$this->assertEquals( 'sms', $messages[0]->channel );
		$this->assertEquals( 'email', $messages[1]->channel );
		$this->assertEquals( $user_id, (int) $messages[0]->user_id );
		$this->assertEquals( $user_id, (int) $messages[1]->user_id );
		$this->assertEquals( 'Booking bekreftet', $messages[1]->subject );
	}
}
