<?php
/**
 * Unit tests for SmsInboxResolverService
 *
 * @package SnippenBooking\Tests\Unit\Service
 */

namespace SnippenBooking\Tests\Unit\Service;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\Sms\SmsInboxResolverService;
use SnippenBooking\Service\Notification\MessageLoggerService;
use SnippenBooking\Database\Repository\BookingRepository;

/**
 * Class SmsInboxResolverServiceTest
 */
class SmsInboxResolverServiceTest extends TestCase {

	/**
	 * Requires database
	 */
	protected $requires_db = true;

	/**
	 * Setup environment
	 */
	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_messages" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_bookings" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_booking_booking_objects" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_bookings_booking_objects" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_booking_objects" );

		// Seed a default booking object
		$wpdb->insert(
			$wpdb->prefix . 'snippen_booking_objects',
			array(
				'name'        => 'Badstue',
				'created_at'  => current_time( 'mysql' ),
				'modified_at' => current_time( 'mysql' ),
			)
		);

		update_option( 'snippen_sms_active_booking_past_days', 2 );
		update_option( 'snippen_sms_unpaid_booking_past_days', 0 );
		update_option( 'snippen_sms_conversation_ttl_minutes', 120 );
		update_option( 'snippen_sms_auto_disambiguate', 'yes' );
	}

	/**
	 * Helper to create a test booking
	 */
	private function create_booking( array $args = array() ): int {
		$repo = new BookingRepository();
		$defaults = array(
			'uuid'           => wp_generate_uuid4(),
			'booking_date'   => current_time( 'Y-m-d' ),
			'customer_name'  => 'Ola Nordmann',
			'customer_phone' => '+4799887766',
			'customer_email' => 'ola@example.com',
			'status'            => 'confirmed',
			'payment_status_id' => 2,
		);
		$data = array_merge( $defaults, $args );
		return $repo->create( $data, array( 1 ), array() );
	}

	/**
	 * Test parse_selection with various formats and ordinals
	 */
	public function test_parse_selection() {
		// Valid numbers
		$this->assertSame( 1, SmsInboxResolverService::parse_selection( '1', 3 ) );
		$this->assertSame( 2, SmsInboxResolverService::parse_selection( ' 2 ', 3 ) );
		$this->assertSame( 3, SmsInboxResolverService::parse_selection( '3.', 3 ) );
		$this->assertSame( 2, SmsInboxResolverService::parse_selection( '#2', 3 ) );
		$this->assertSame( 1, SmsInboxResolverService::parse_selection( 'nr 1', 3 ) );
		$this->assertSame( 2, SmsInboxResolverService::parse_selection( 'nr. 2', 3 ) );
		$this->assertSame( 3, SmsInboxResolverService::parse_selection( 'valg 3', 3 ) );
		$this->assertSame( 1, SmsInboxResolverService::parse_selection( 'booking 1', 3 ) );
		$this->assertSame( 2, SmsInboxResolverService::parse_selection( 'alternativ 2', 3 ) );

		// Norwegian ordinals
		$this->assertSame( 1, SmsInboxResolverService::parse_selection( 'første', 3 ) );
		$this->assertSame( 1, SmsInboxResolverService::parse_selection( 'den første', 3 ) );
		$this->assertSame( 2, SmsInboxResolverService::parse_selection( 'andre', 3 ) );
		$this->assertSame( 2, SmsInboxResolverService::parse_selection( 'den andre', 3 ) );
		$this->assertSame( 3, SmsInboxResolverService::parse_selection( 'tredje', 3 ) );

		// Out of range or invalid
		$this->assertNull( SmsInboxResolverService::parse_selection( '4', 3 ) );
		$this->assertNull( SmsInboxResolverService::parse_selection( '0', 3 ) );
		$this->assertNull( SmsInboxResolverService::parse_selection( 'hei', 3 ) );
		$this->assertNull( SmsInboxResolverService::parse_selection( '', 3 ) );
	}

	/**
	 * Test format_disambiguation_prompt
	 */
	public function test_format_disambiguation_prompt() {
		$b1 = (object) array(
			'resource_name' => 'Badstue',
			'booking_date'  => '2026-10-15',
			'slot_start'    => '18:00:00',
		);
		$b2 = (object) array(
			'resource_name' => 'Felleslokale',
			'booking_date'  => '2026-10-20',
			'slot_start'    => '12:00:00',
		);

		$prompt = SmsInboxResolverService::format_disambiguation_prompt( array( $b1, $b2 ) );
		$this->assertStringContainsString( 'Du har flere aktive reservasjoner', $prompt );
		$this->assertStringContainsString( '1. Badstue', $prompt );
		$this->assertStringContainsString( '2. Felleslokale', $prompt );
		$this->assertStringContainsString( 'Hvilken reservasjon gjelder henvendelsen?', $prompt );
	}

	/**
	 * Test Regel 1: Pågående samtale innenfor TTL
	 */
	public function test_rule_1_active_session() {
		$b1_id = $this->create_booking( array( 'booking_date' => '2026-10-01' ) );
		$b2_id = $this->create_booking( array( 'booking_date' => '2026-10-10' ) );

		// Previous message tied to b2_id 10 minutes ago
		MessageLoggerService::log_message(
			$b2_id,
			null,
			'sms',
			'+4799887766',
			null,
			'Forrige melding',
			'booking_confirmation',
			'sent'
		);

		$res = SmsInboxResolverService::resolve_message(
			'+4799887766',
			'Hvor er nøkkelen?'
		);

		$this->assertSame( 'active_session', $res['rule'] );
		$this->assertSame( SmsInboxResolverService::STATUS_RECEIVED, $res['status'] );
		$this->assertSame( $b2_id, $res['booking_id'] );
	}

	/**
	 * Test that active session is invalidated if a new booking is created after the session message
	 */
	public function test_active_session_invalidated_when_new_booking_created() {
		global $wpdb;

		// 1. Create booking 1
		$b1_id = $this->create_booking( array( 'booking_date' => '2026-10-01' ) );

		// 2. Simulate conversation on booking 1 (set created_at 10 minutes ago)
		$past_time = gmdate( 'Y-m-d H:i:s', time() - 600 );
		MessageLoggerService::log_message(
			$b1_id,
			null,
			'sms',
			'+4799887766',
			null,
			'Takk for bekreftelsen på booking 1',
			'inbound_sms',
			'received'
		);
		$msg_id = $wpdb->insert_id;
		$wpdb->update(
			$wpdb->prefix . 'snippen_messages',
			array( 'created_at' => $past_time ),
			array( 'id' => $msg_id )
		);

		// 3. User creates a NEW booking 2 (created now, after past_time)
		$b2_id = $this->create_booking( array( 'booking_date' => '2026-10-15' ) );

		// 4. User sends new incoming SMS
		$res = SmsInboxResolverService::resolve_message(
			'+4799887766',
			'Hei, jeg har et spørsmål'
		);

		// Active session should be invalidated and system asks for selection
		$this->assertSame( 'multiple_active_bookings', $res['rule'] );
		$this->assertSame( SmsInboxResolverService::STATUS_PENDING_SELECTION, $res['status'] );
		$this->assertTrue( $res['prompt_sent'] );
		$this->assertNull( $res['booking_id'] );
	}

	/**
	 * Test that active session is invalidated if another booking is modified after the session message
	 */
	public function test_active_session_invalidated_when_other_booking_modified() {
		global $wpdb;

		// 1. Create two bookings in the past
		$b1_id = $this->create_booking( array( 'booking_date' => '2026-10-01' ) );
		$b2_id = $this->create_booking( array( 'booking_date' => '2026-10-15' ) );

		$past_time = gmdate( 'Y-m-d H:i:s', time() - 600 );
		$wpdb->update( $wpdb->prefix . 'snippen_bookings', array( 'created_at' => $past_time, 'modified_at' => $past_time ), array( 'id' => $b1_id ) );
		$wpdb->update( $wpdb->prefix . 'snippen_bookings', array( 'created_at' => $past_time, 'modified_at' => $past_time ), array( 'id' => $b2_id ) );

		// 2. Session message on booking 1 (set created_at 5 minutes ago)
		$session_time = gmdate( 'Y-m-d H:i:s', time() - 300 );
		MessageLoggerService::log_message(
			$b1_id,
			null,
			'sms',
			'+4799887766',
			null,
			'Spørsmål om booking 1',
			'inbound_sms',
			'received'
		);
		$msg_id = $wpdb->insert_id;
		$wpdb->update(
			$wpdb->prefix . 'snippen_messages',
			array( 'created_at' => $session_time ),
			array( 'id' => $msg_id )
		);

		// 3. Admin or user modifies booking 2 (set modified_at to now, which is after session_time)
		$now = gmdate( 'Y-m-d H:i:s', time() );
		$wpdb->update(
			$wpdb->prefix . 'snippen_bookings',
			array( 'modified_at' => $now, 'description' => 'Endret tidspunkt' ),
			array( 'id' => $b2_id )
		);

		// 4. User sends new incoming SMS
		$res = SmsInboxResolverService::resolve_message(
			'+4799887766',
			'Hei igjen'
		);

		// Active session on booking 1 should be invalidated because booking 2 was modified
		$this->assertSame( 'multiple_active_bookings', $res['rule'] );
		$this->assertSame( SmsInboxResolverService::STATUS_PENDING_SELECTION, $res['status'] );
		$this->assertTrue( $res['prompt_sent'] );
	}

	/**
	 * Test that replying to a prompt reinstates active session for subsequent messages
	 */
	public function test_active_session_reinstated_after_selection_reply() {
		$b1_id = $this->create_booking( array( 'booking_date' => '2026-10-01' ) );
		$b2_id = $this->create_booking( array( 'booking_date' => '2026-10-15' ) );

		// 1. Trigger disambiguation prompt
		$prompt_res = SmsInboxResolverService::resolve_message( '+4799887766', 'Trenger hjelp' );
		$this->assertSame( 'multiple_active_bookings', $prompt_res['rule'] );

		// 2. User selects option 2 (booking 2)
		$reply_res = SmsInboxResolverService::resolve_message( '+4799887766', '2' );
		$this->assertSame( 'disambiguation_selection', $reply_res['rule'] );
		$this->assertSame( $b2_id, $reply_res['booking_id'] );

		// 3. User follows up with another question
		$followup_res = SmsInboxResolverService::resolve_message( '+4799887766', 'Flott, hvor finner jeg nøkkelen?' );
		$this->assertSame( 'active_session', $followup_res['rule'] );
		$this->assertSame( $b2_id, $followup_res['booking_id'] );
	}

	/**
	 * Test Regel 2: Svar på flervalgsforespørsel
	 */
	public function test_rule_2_disambiguation_selection() {
		$b1_id = $this->create_booking( array( 'booking_date' => '2026-10-01' ) );
		$b2_id = $this->create_booking( array( 'booking_date' => '2026-10-10' ) );

		// Simulate original incoming message in pending_selection
		$pending_msg_id = MessageLoggerService::log_message(
			null,
			null,
			'sms',
			'+4799887766',
			null,
			'Opprinnelig melding med spørsmål',
			'inbound_sms',
			'pending_selection'
		);

		// Disambiguation prompt enqueued
		MessageLoggerService::log_message(
			null,
			null,
			'sms',
			'+4799887766',
			null,
			'Velg 1 eller 2',
			SmsInboxResolverService::EVENT_DISAMBIGUATION_PROMPT,
			'sent',
			array(
				'candidate_booking_ids' => array( $b1_id, $b2_id ),
				'pending_message_id'    => $pending_msg_id,
			)
		);

		// User replies with "2"
		$res = SmsInboxResolverService::resolve_message(
			'+4799887766',
			'2'
		);

		$this->assertSame( 'disambiguation_selection', $res['rule'] );
		$this->assertSame( SmsInboxResolverService::STATUS_RECEIVED, $res['status'] );
		$this->assertSame( $b2_id, $res['booking_id'] );

		// Verify the pending message was updated to resolved booking
		$updated_pending = MessageLoggerService::get_message( $pending_msg_id );
		$this->assertSame( (string) $b2_id, (string) $updated_pending->booking_id );
		$this->assertSame( 'received', $updated_pending->status );
	}

	/**
	 * Test Regel 3: Nøyaktig 1 aktiv reservasjon
	 */
	public function test_rule_3_single_active_booking() {
		$b_id = $this->create_booking(
			array(
				'booking_date'   => gmdate( 'Y-m-d', strtotime( '+2 days' ) ),
				'customer_phone' => '+4791112233',
			)
		);

		$res = SmsInboxResolverService::resolve_message(
			'+4791112233',
			'Hei, er det wifi der?'
		);

		$this->assertSame( 'single_active_booking', $res['rule'] );
		$this->assertSame( SmsInboxResolverService::STATUS_RECEIVED, $res['status'] );
		$this->assertSame( $b_id, $res['booking_id'] );
	}

	/**
	 * Test Regel 4: Flere aktive reservasjoner triggger pending_selection og prompt
	 */
	public function test_rule_4_multiple_active_bookings() {
		$b1_id = $this->create_booking(
			array(
				'booking_date'   => gmdate( 'Y-m-d', strtotime( '+1 day' ) ),
				'customer_phone' => '+4792223344',
			)
		);
		$b2_id = $this->create_booking(
			array(
				'booking_date'   => gmdate( 'Y-m-d', strtotime( '+3 days' ) ),
				'customer_phone' => '+4792223344',
			)
		);

		$res = SmsInboxResolverService::resolve_message(
			'+4792223344',
			'Hei, vi vil gjerne ha en ekstra nøkkel.'
		);

		$this->assertSame( 'multiple_active_bookings', $res['rule'] );
		$this->assertSame( SmsInboxResolverService::STATUS_PENDING_SELECTION, $res['status'] );
		$this->assertNull( $res['booking_id'] );
		$this->assertTrue( $res['prompt_sent'] );

		// Verify prompt was enqueued to outbox
		$outbox = MessageLoggerService::get_pending_outbox();
		$this->assertCount( 1, $outbox );
		$this->assertSame( '+4792223344', $outbox[0]->recipient );
		$this->assertSame( SmsInboxResolverService::EVENT_DISAMBIGUATION_PROMPT, $outbox[0]->event_type );
		$this->assertStringContainsString( 'Du har flere aktive reservasjoner', $outbox[0]->message );
	}

	/**
	 * Test Regel 5: Registrert bruker uten aktiv reservasjon
	 */
	public function test_rule_5_registered_user_no_booking() {
		$user_id = wp_create_user( 'reguser', 'pass', 'reguser@example.com' );
		update_user_meta( $user_id, 'phone', '+4793334455' );

		$res = SmsInboxResolverService::resolve_message(
			'+4793334455',
			'Hei, jeg lurer på hvordan man leier badstuen.'
		);

		$this->assertSame( 'registered_user_no_booking', $res['rule'] );
		$this->assertSame( SmsInboxResolverService::STATUS_GENERAL_INQUIRY, $res['status'] );
		$this->assertNull( $res['booking_id'] );
		$this->assertSame( $user_id, $res['user_id'] );
	}

	/**
	 * Test Regel 6: Ukjent avsender legges i karantene
	 */
	public function test_rule_6_unknown_sender() {
		$res = SmsInboxResolverService::resolve_message(
			'+4794445566',
			'Hei, feilsendt melding eller ukjent person.'
		);

		$this->assertSame( 'unknown_sender', $res['rule'] );
		$this->assertSame( SmsInboxResolverService::STATUS_QUARANTINE, $res['status'] );
		$this->assertNull( $res['booking_id'] );
		$this->assertNull( $res['user_id'] );
	}

	/**
	 * Test active booking date filter differentiated by paid vs unpaid
	 */
	public function test_active_booking_payment_days_filter() {
		// Cancelled booking should NOT be active
		$cancelled_id = $this->create_booking(
			array(
				'customer_phone' => '+4795556677',
				'booking_date'   => gmdate( 'Y-m-d', strtotime( '+1 day' ) ),
				'status'         => 'cancelled',
			)
		);

		// Unpaid past booking 1 day ago (snippen_sms_unpaid_booking_past_days = 0, so should NOT be active)
		$unpaid_past_id = $this->create_booking(
			array(
				'customer_phone'    => '+4795556677',
				'booking_date'      => gmdate( 'Y-m-d', strtotime( '-1 day' ) ),
				'status'            => 'confirmed',
				'payment_status_id' => 1,
			)
		);

		// Paid past booking 1 day ago (snippen_sms_active_booking_past_days = 2, so SHOULD be active)
		$paid_past_id = $this->create_booking(
			array(
				'customer_phone'    => '+4795556677',
				'booking_date'      => gmdate( 'Y-m-d', strtotime( '-1 day' ) ),
				'status'            => 'confirmed',
				'payment_status_id' => 2,
			)
		);

		$search_phones = SmsInboxResolverService::get_search_phones( '+4795556677' );
		$active = SmsInboxResolverService::get_active_bookings_for_phones( $search_phones );

		$this->assertCount( 1, $active );
		$this->assertSame( (int) $paid_past_id, (int) $active[0]->id );
	}
}
