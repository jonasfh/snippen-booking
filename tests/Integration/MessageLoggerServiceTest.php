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

	/**
	 * Test deleting a single message.
	 */
	public function testDeleteMessage() {
		$id = MessageLoggerService::log_message(
			null,
			null,
			'sms',
			'+4799881122',
			null,
			'Melding som skal slettes',
			'inbound_sms',
			'quarantine'
		);

		$this->assertNotEmpty( $id );
		$this->assertNotNull( MessageLoggerService::get_message( $id ) );

		$deleted = MessageLoggerService::delete_message( $id );
		$this->assertTrue( $deleted );
		$this->assertNull( MessageLoggerService::get_message( $id ) );

		// Deleting non-existent should return false
		$this->assertFalse( MessageLoggerService::delete_message( 999999 ) );
	}

	/**
	 * Test bulk deleting multiple messages.
	 */
	public function testDeleteMessages() {
		$id1 = MessageLoggerService::log_message( null, null, 'sms', '+4799881122', null, 'Bulk 1', 'inbound_sms', 'quarantine' );
		$id2 = MessageLoggerService::log_message( null, null, 'sms', '+4799881122', null, 'Bulk 2', 'inbound_sms', 'quarantine' );
		$id3 = MessageLoggerService::log_message( null, null, 'sms', '+4799881122', null, 'Bulk 3', 'inbound_sms', 'quarantine' );

		$this->assertSame( 2, MessageLoggerService::delete_messages( array( $id1, $id2 ) ) );

		$this->assertNull( MessageLoggerService::get_message( $id1 ) );
		$this->assertNull( MessageLoggerService::get_message( $id2 ) );
		$this->assertNotNull( MessageLoggerService::get_message( $id3 ) );

		// Empty list should return 0
		$this->assertSame( 0, MessageLoggerService::delete_messages( array() ) );
	}

	/**
	 * Test querying inbound messages with status, connection filters and column sorting.
	 */
	public function testInboundMessagesFiltersAndSorting() {
		global $wpdb;

		// Clean messages table for precise assertions
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_messages" );

		// Insert 3 test inbound messages
		$id1 = MessageLoggerService::log_message( 10, 1, 'sms', '+4790000001', null, 'Melding A', 'inbound_sms', 'received' );
		$id2 = MessageLoggerService::log_message( null, null, 'sms', '+4790000002', null, 'Melding B', 'inbound_sms', 'quarantine' );
		$id3 = MessageLoggerService::log_message( 20, 2, 'sms', '+4790000003', null, 'Melding C', 'inbound_sms', 'pending_selection' );

		// Filter by status
		$quarantine = MessageLoggerService::get_inbound_messages( array( 'status' => 'quarantine' ) );
		$this->assertCount( 1, $quarantine );
		$this->assertSame( (int) $id2, (int) $quarantine[0]->id );

		// Filter by connection: linked
		$linked = MessageLoggerService::get_inbound_messages( array( 'connection' => 'linked' ) );
		$this->assertCount( 2, $linked );

		// Filter by connection: unlinked
		$unlinked = MessageLoggerService::get_inbound_messages( array( 'connection' => 'unlinked' ) );
		$this->assertCount( 1, $unlinked );
		$this->assertSame( (int) $id2, (int) $unlinked[0]->id );

		// Sort by recipient ASC
		$sorted_asc = MessageLoggerService::get_inbound_messages( array( 'orderby' => 'recipient', 'order' => 'asc' ) );
		$this->assertSame( '+4790000001', $sorted_asc[0]->recipient );
		$this->assertSame( '+4790000003', $sorted_asc[2]->recipient );

		// Sort by recipient DESC
		$sorted_desc = MessageLoggerService::get_inbound_messages( array( 'orderby' => 'recipient', 'order' => 'desc' ) );
		$this->assertSame( '+4790000003', $sorted_desc[0]->recipient );
		$this->assertSame( '+4790000001', $sorted_desc[2]->recipient );
	}

	/**
	 * Test comprehensive search matching by phone (formatted/unformatted), text, and booking ID.
	 */
	public function testInboundMessagesSearch() {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_messages" );

		$id1 = MessageLoggerService::log_message( 42, 1, 'sms', '+4791122334', null, 'Kan vi få ekstra nøkkel?', 'inbound_sms', 'received' );
		$id2 = MessageLoggerService::log_message( null, null, 'sms', '+4799887766', null, 'Hei fra karantene', 'inbound_sms', 'quarantine' );

		// 1. Search by 8-digit phone without country code
		$results = MessageLoggerService::get_inbound_messages( array( 'search' => '91122334' ) );
		$this->assertCount( 1, $results );
		$this->assertSame( (int) $id1, (int) $results[0]->id );

		// 2. Search by phone with spaces
		$results_spaced = MessageLoggerService::get_inbound_messages( array( 'search' => '99 88 77 66' ) );
		$this->assertCount( 1, $results_spaced );
		$this->assertSame( (int) $id2, (int) $results_spaced[0]->id );

		// 3. Search by message snippet
		$results_text = MessageLoggerService::get_inbound_messages( array( 'search' => 'ekstra nøkkel' ) );
		$this->assertCount( 1, $results_text );
		$this->assertSame( (int) $id1, (int) $results_text[0]->id );

		// 4. Search by booking ID `#42`
		$results_booking = MessageLoggerService::get_inbound_messages( array( 'search' => '#42' ) );
		$this->assertCount( 1, $results_booking );
		$this->assertSame( (int) $id1, (int) $results_booking[0]->id );
	}

	/**
	 * Test retrieving chronological SMS conversation thread for a phone / booking.
	 */
	public function testGetConversationThread() {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_messages" );

		$phone = '+4791122334';

		// Inbound message 1
		MessageLoggerService::log_message( 50, 1, 'sms', $phone, null, 'Hei, har dere ledig tid?', 'inbound_sms', 'received' );
		// Outbound reply
		MessageLoggerService::log_message( 50, 1, 'sms', $phone, null, 'Ja, det er ledig på fredag!', 'admin_sms_reply', 'sent', array( 'direction' => 'outbound' ) );
		// Inbound follow-up
		MessageLoggerService::log_message( 50, 1, 'sms', $phone, null, 'Flott, da booker jeg nå.', 'inbound_sms', 'received' );

		$thread = MessageLoggerService::get_conversation_thread( '91122334', 50 );
		$this->assertCount( 3, $thread );
		$this->assertSame( 'Hei, har dere ledig tid?', $thread[0]->message );
		$this->assertSame( 'Ja, det er ledig på fredag!', $thread[1]->message );
		$this->assertSame( 'Flott, da booker jeg nå.', $thread[2]->message );
	}
}
