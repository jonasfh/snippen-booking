<?php
/**
 * Integration test for demo:inbox CLI tool (bin/demo-inbox.php).
 *
 * @package SnippenBooking\Tests\Integration
 */

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\Notification\MessageLoggerService;

/**
 * Class DemoInboxTest
 */
class DemoInboxTest extends TestCase {

	/**
	 * Requires database
	 */
	protected $requires_db = true;

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();

		// Ensure completely clean state before running demo-gateway
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_messages" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_bookings" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_booking_booking_objects" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_bookings_booking_objects" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_booking_booking_blocks" );
		$wpdb->query( 'COMMIT' );

		// Run demo-gateway to ensure base settings, user test.guest and bookings exist
		$gw_output = array();
		$gw_code   = 0;
		exec( 'php ' . escapeshellarg( __DIR__ . '/../../bin/demo-gateway.php' ), $gw_output, $gw_code );
		$this->assertSame( 0, $gw_code, 'demo-gateway.php should succeed' );

		// Flush caches after external script execution
		global $wpdb;
		$wpdb->query( 'COMMIT' );
		wp_cache_flush();
		wp_load_alloptions( true );
	}

	/**
	 * Clean up test environment
	 */
	protected function tearDown(): void {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_messages" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_bookings" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_booking_booking_objects" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_bookings_booking_objects" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_booking_booking_blocks" );
		$wpdb->query( 'COMMIT' );
		parent::tearDown();
	}

	/**
	 * Helper to run demo-inbox script.
	 *
	 * @param array $args Array of CLI arguments.
	 * @return array Tuple of [int $code, array $output, string $output_str].
	 */
	private function run_inbox_cli( array $args ): array {
		$bin    = __DIR__ . '/../../bin/demo-inbox.php';
		$cmd    = 'php ' . escapeshellarg( $bin );
		foreach ( $args as $arg ) {
			$cmd .= ' ' . escapeshellarg( $arg );
		}

		$output = array();
		$code   = 0;
		exec( $cmd . ' 2>&1', $output, $code );

		return array( $code, $output, implode( "\n", $output ) );
	}

	/**
	 * Test that running without arguments displays usage and exits with error code 1.
	 */
	public function test_missing_arguments_displays_usage_and_exits_with_error() {
		list( $code, , $output_str ) = $this->run_inbox_cli( array() );

		$this->assertSame( 1, $code );
		$this->assertStringContainsString( 'Bruk:', $output_str );
		$this->assertStringContainsString( 'composer demo:inbox', $output_str );
	}

	/**
	 * Test that --help flag displays usage and exits with 0.
	 */
	public function test_help_flag_displays_usage_and_exits_with_zero() {
		list( $code, , $output_str ) = $this->run_inbox_cli( array( '--help' ) );

		$this->assertSame( 0, $code );
		$this->assertStringContainsString( 'Bruk:', $output_str );
		$this->assertStringContainsString( 'Opsjoner:', $output_str );
	}

	/**
	 * Test that an invalid phone number displays an error and exits with code 1.
	 */
	public function test_invalid_phone_number_exits_with_error() {
		list( $code, , $output_str ) = $this->run_inbox_cli( array( '123', 'Hei' ) );

		$this->assertSame( 1, $code );
		$this->assertStringContainsString( 'Ugyldig telefonnummer', $output_str );
	}

	/**
	 * Test incoming SMS for existing resident with active booking resolves and logs message.
	 */
	public function test_incoming_sms_single_active_booking_resolves_and_logs() {
		list( $code, , $output_str ) = $this->run_inbox_cli(
			array( '99887766', 'Hei, jeg har et spørsmål om vask av lokalet.' )
		);

		$this->assertSame( 0, $code, 'CLI command should exit with 0' );
		$this->assertStringContainsString( '+4799887766', $output_str );
		$this->assertStringContainsString( '200 OK', $output_str );
		$this->assertStringContainsString( 'received', $output_str );
		$this->assertStringContainsString( 'Ola Nordmann', $output_str );

		// Verify database log entry
		global $wpdb;
		$wpdb->query( 'COMMIT' );
		$table_msgs = $wpdb->prefix . 'snippen_messages';
		$row        = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_msgs} WHERE recipient = %s AND event_type = %s ORDER BY id DESC LIMIT 1",
				'+4799887766',
				'inbound_sms'
			)
		);

		$this->assertNotNull( $row, 'Inbound message should be recorded in snippen_messages' );
		$this->assertSame( 'received', $row->status );
		$this->assertSame( 'Hei, jeg har et spørsmål om vask av lokalet.', $row->message );
	}

	/**
	 * Test --raw flag outputs valid JSON.
	 */
	public function test_raw_flag_returns_valid_json() {
		list( $code, , $output_str ) = $this->run_inbox_cli(
			array( '+4799887766', 'Test melding for rå output', '--raw' )
		);

		$this->assertSame( 0, $code );
		$decoded = json_decode( $output_str, true );
		$this->assertIsArray( $decoded, 'Output should be valid JSON' );
		$this->assertTrue( $decoded['success'] );
		$this->assertNotEmpty( $decoded['results'] );
		$this->assertSame( 'received', $decoded['results'][0]['status'] );
	}

	/**
	 * Test token override with invalid token results in 401 error.
	 */
	public function test_token_override_invalid_token_returns_error() {
		list( $code, , $output_str ) = $this->run_inbox_cli(
			array( '99887766', 'Uautorisert test', '--token=ugyldig-hemmelig-token' )
		);

		$this->assertSame( 1, $code );
		$this->assertStringContainsString( '401 Feil', $output_str );
		$this->assertStringContainsString( 'rest_forbidden', $output_str );
	}

	/**
	 * Test unknown sender is routed to quarantine.
	 */
	public function test_unknown_sender_routed_to_quarantine() {
		list( $code, , $output_str ) = $this->run_inbox_cli(
			array( '91112233', 'Ukjent avsender henvendelse' )
		);

		$this->assertSame( 0, $code );
		$this->assertStringContainsString( 'quarantine', $output_str );
		$this->assertStringContainsString( 'unknown_sender', $output_str );
		$this->assertStringContainsString( 'Ingen', $output_str );
	}

	/**
	 * Test registered user with no active bookings is categorized as general_inquiry.
	 */
	public function test_registered_user_no_active_booking_routed_to_general_inquiry() {
		// Create a resident user without bookings
		$user_id = wp_create_user( 'resident.nobooking', 'password123', 'resident.nobooking@example.no' );
		update_user_meta( $user_id, 'snippen_phone', '+4791999999' );

		global $wpdb;
		$wpdb->query( 'COMMIT' );

		list( $code, , $output_str ) = $this->run_inbox_cli(
			array( '91999999', 'Hei Snippen, hvordan leier man lokale?' )
		);

		$this->assertSame( 0, $code );
		$this->assertStringContainsString( 'general_inquiry', $output_str );
		$this->assertStringContainsString( 'registered_user_no_booking', $output_str );
		$this->assertStringContainsString( "Bruker #{$user_id}", $output_str );
	}

	/**
	 * Test multiple active bookings triggers disambiguation prompt and numeric choice resolves.
	 */
	public function test_multiple_active_bookings_disambiguation_and_numeric_choice() {
		global $wpdb;

		// Clear previous messages for this user/phone to ensure clean session state
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}snippen_messages WHERE recipient = %s", '+4799887766' ) );

		// Create a second future booking for test.guest (+4799887766)
		$user           = get_user_by( 'login', 'test.guest' );
		$table_bookings = $wpdb->prefix . 'snippen_bookings';
		$wpdb->insert(
			$table_bookings,
			array(
				'uuid'           => wp_generate_uuid4(),
				'user_id'        => $user->ID,
				'slot_id'        => 1,
				'booking_date'   => gmdate( 'Y-m-d', strtotime( '+5 days' ) ),
				'customer_name'  => $user->display_name,
				'customer_email' => $user->user_email,
				'customer_phone' => '+4799887766',
				'status'         => 'confirmed',
				'price'          => 500,
				'created_at'     => current_time( 'mysql' ),
				'modified_at'    => current_time( 'mysql' ),
			)
		);

		$wpdb->query( 'COMMIT' );
		wp_cache_flush();

		// Step 1: Inbound message from user with multiple bookings triggers disambiguation prompt
		list( $code1, , $output1 ) = $this->run_inbox_cli(
			array( '99887766', 'Hei, jeg lurer på nøkkelen til lokalet' )
		);

		$this->assertSame( 0, $code1 );
		$this->assertStringContainsString( 'pending_selection', $output1 );
		$this->assertStringContainsString( 'multiple_active_bookings', $output1 );
		$this->assertStringContainsString( 'Valg-SMS ble generert', $output1 );

		// Step 2: User replies with numeric choice "1"
		list( $code2, , $output2 ) = $this->run_inbox_cli(
			array( '99887766', '1' )
		);

		$this->assertSame( 0, $code2 );
		$this->assertStringContainsString( 'received', $output2 );
		$this->assertStringContainsString( 'disambiguation_selection', $output2 );
	}

	/**
	 * Test user feedback scenario:
	 * 1. User has 1 booking
	 * 2. User sends SMS -> active session on booking 1
	 * 3. User creates a new booking (booking 2)
	 * 4. User sends SMS -> active session is invalidated, system asks for selection
	 * 5. User replies with choice -> resolved to chosen booking
	 * 6. User sends follow-up SMS -> active session reinstated on chosen booking
	 */
	public function test_active_session_interrupted_when_new_booking_created() {
		global $wpdb;

		// Clear previous messages for clean test run
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}snippen_messages WHERE recipient = %s", '+4799887766' ) );
		$wpdb->query( 'COMMIT' );

		// Step 1 & 2: User test.guest already has 1 booking from demo-gateway (Booking 1). Send SMS.
		list( $code1, , $output1 ) = $this->run_inbox_cli(
			array( '99887766', 'Første henvendelse om booking 1' )
		);
		$this->assertSame( 0, $code1 );
		$this->assertStringContainsString( 'received', $output1 );

		// Age the first message slightly (5 seconds ago) to guarantee timestamp order
		$past_time = gmdate( 'Y-m-d H:i:s', time() - 5 );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}snippen_messages SET created_at = %s WHERE recipient = %s",
				$past_time,
				'+4799887766'
			)
		);

		// Step 3: User creates a NEW booking (Booking 2) created now (after past_time)
		$user           = get_user_by( 'login', 'test.guest' );
		$table_bookings = $wpdb->prefix . 'snippen_bookings';
		$wpdb->insert(
			$table_bookings,
			array(
				'uuid'           => wp_generate_uuid4(),
				'user_id'        => $user->ID,
				'slot_id'        => 1,
				'booking_date'   => gmdate( 'Y-m-d', strtotime( '+6 days' ) ),
				'customer_name'  => $user->display_name,
				'customer_email' => $user->user_email,
				'customer_phone' => '+4799887766',
				'status'         => 'confirmed',
				'price'          => 600,
				'created_at'     => current_time( 'mysql' ),
				'modified_at'    => current_time( 'mysql' ),
			)
		);
		$booking2_id = (int) $wpdb->insert_id;

		$wpdb->query( 'COMMIT' );
		wp_cache_flush();

		// Step 4: User sends new SMS. Active session should be interrupted!
		list( $code2, , $output2 ) = $this->run_inbox_cli(
			array( '99887766', 'Andre henvendelse etter at ny booking ble opprettet' )
		);
		$this->assertSame( 0, $code2 );
		$this->assertStringContainsString( 'pending_selection', $output2 );
		$this->assertStringContainsString( 'multiple_active_bookings', $output2 );
		$this->assertStringContainsString( 'Valg-SMS ble generert', $output2 );

		// Step 5: User responds with "2"
		list( $code3, , $output3 ) = $this->run_inbox_cli(
			array( '99887766', '2' )
		);
		$this->assertSame( 0, $code3 );
		$this->assertStringContainsString( 'received', $output3 );
		$this->assertStringContainsString( 'disambiguation_selection', $output3 );
		$this->assertStringContainsString( "Booking #{$booking2_id}", $output3 );

		// Step 6: Next user SMS continues on booking 2 with active_session
		list( $code4, , $output4 ) = $this->run_inbox_cli(
			array( '99887766', 'Flott, tusen takk for hjelpen!' )
		);
		$this->assertSame( 0, $code4 );
		$this->assertStringContainsString( 'received', $output4 );
		$this->assertStringContainsString( 'active_session', $output4 );
		$this->assertStringContainsString( "Booking #{$booking2_id}", $output4 );
	}
}

