<?php
/**
 * SmsInboxPage Test
 *
 * @package SnippenBooking\Tests\Integration
 */

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Admin\Pages\SmsInboxPage;
use SnippenBooking\Service\Notification\MessageLoggerService;

/**
 * Class SmsInboxPageTest
 */
class SmsInboxPageTest extends TestCase {

	/**
	 * Admin user ID
	 *
	 * @var int
	 */
	private $admin_id;

	/**
	 * Set up test environment
	 */
	protected function setUp(): void {
		parent::setUp();

		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_messages" );

		$this->admin_id = wp_insert_user(
			array(
				'user_login' => 'sms_admin_tester',
				'user_pass'  => 'password123',
				'user_email' => 'admin_sms@example.com',
				'role'       => 'administrator',
			)
		);
		wp_set_current_user( $this->admin_id );

		$_GET  = array();
		$_POST = array();
	}

	/**
	 * Tear down test environment
	 */
	protected function tearDown(): void {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_messages" );

		if ( $this->admin_id ) {
			if ( ! function_exists( 'wp_delete_user' ) && defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/user.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
			}
			if ( function_exists( 'wp_delete_user' ) ) {
				wp_delete_user( $this->admin_id );
			}
		}

		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();

		parent::tearDown();
	}

	/**
	 * Test that SmsInboxPage renders header, status tabs, and message list.
	 */
	public function test_render_displays_header_tabs_and_messages() {
		$id1 = MessageLoggerService::log_message(
			10,
			$this->admin_id,
			'sms',
			'+4791234567',
			null,
			'Hei, jeg har et spørsmål om badstuen.',
			'inbound_sms',
			'received'
		);

		$id2 = MessageLoggerService::log_message(
			null,
			null,
			'sms',
			'+4799887766',
			null,
			'Ukjent henvendelse til karantene.',
			'inbound_sms',
			'quarantine'
		);

		$page = new SmsInboxPage();
		ob_start();
		$page->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'SMS Innboks &amp; Karantene', $output );
		$this->assertStringContainsString( 'Alle meldinger', $output );
		$this->assertStringContainsString( 'Karantene / Ukjent', $output );
		$this->assertStringContainsString( '+4791234567', $output );
		$this->assertStringContainsString( '+4799887766', $output );
		$this->assertStringContainsString( 'Hei, jeg har et spørsmål om badstuen.', $output );
		$this->assertStringContainsString( 'Massehandlinger', $output );
	}

	/**
	 * Test filtering messages by status and search.
	 */
	public function test_render_with_status_and_search_filter() {
		MessageLoggerService::log_message( 10, null, 'sms', '+4791234567', null, 'Hvitveis', 'inbound_sms', 'received' );
		MessageLoggerService::log_message( null, null, 'sms', '+4799887766', null, 'Blåveis', 'inbound_sms', 'quarantine' );

		// Filter for quarantine only
		$_GET['status'] = 'quarantine';
		$_REQUEST       = array_merge( $_GET, $_POST );

		$page = new SmsInboxPage();
		ob_start();
		$page->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Blåveis', $output );
		$this->assertStringNotContainsString( 'Hvitveis', $output );

		// Search for 91234567
		$_GET['status'] = '';
		$_GET['s']      = '91234567';
		$_REQUEST       = array_merge( $_GET, $_POST );

		ob_start();
		$page->render();
		$search_output = ob_get_clean();

		$this->assertStringContainsString( 'Hvitveis', $search_output );
		$this->assertStringNotContainsString( 'Blåveis', $search_output );
	}

	/**
	 * Test rendering detail modal with conversation thread.
	 */
	public function test_render_detail_modal_with_conversation_thread() {
		$id = MessageLoggerService::log_message(
			55,
			$this->admin_id,
			'sms',
			'+4790011223',
			null,
			'Forespørsel om nøkkel.',
			'inbound_sms',
			'received'
		);

		// Outbound reply in thread
		MessageLoggerService::log_message(
			55,
			$this->admin_id,
			'sms',
			'+4790011223',
			null,
			'Nøkkelen henger i nøkkelskapet.',
			'admin_sms_reply',
			'sent',
			array( 'direction' => 'outbound' )
		);

		$_GET['view_message'] = $id;
		$_REQUEST             = array_merge( $_GET, $_POST );

		$page = new SmsInboxPage();
		ob_start();
		$page->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'snippen-sms-detail-modal', $output );
		$this->assertStringContainsString( sprintf( 'SMS-detaljer #%d', $id ), $output );
		$this->assertStringContainsString( 'Samtalelogg for dette telefonnummeret', $output );
		$this->assertStringContainsString( 'Forespørsel om nøkkel.', $output );
		$this->assertStringContainsString( 'Nøkkelen henger i nøkkelskapet.', $output );
		$this->assertStringContainsString( 'Send SMS-svar', $output );
	}

	/**
	 * Test single delete action.
	 */
	public function test_handle_single_delete_action() {
		$id = MessageLoggerService::log_message(
			null,
			null,
			'sms',
			'+4799887766',
			null,
			'Slett meg',
			'inbound_sms',
			'quarantine'
		);

		$this->assertNotNull( MessageLoggerService::get_message( $id ) );

		$_POST['snippen_inbox_action'] = 'delete_single';
		$_POST['message_id']           = $id;
		$_POST['snippen_inbox_nonce']  = wp_create_nonce( 'snippen_delete_sms_message' );
		$_REQUEST                      = array_merge( $_GET, $_POST );

		$page = new SmsInboxPage();
		ob_start();
		$page->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Meldingen ble slettet.', $output );
		$this->assertNull( MessageLoggerService::get_message( $id ) );
	}

	/**
	 * Test bulk delete action.
	 */
	public function test_handle_bulk_delete_action() {
		$id1 = MessageLoggerService::log_message( null, null, 'sms', '+4790000001', null, 'Bulk 1', 'inbound_sms', 'quarantine' );
		$id2 = MessageLoggerService::log_message( null, null, 'sms', '+4790000002', null, 'Bulk 2', 'inbound_sms', 'quarantine' );
		$id3 = MessageLoggerService::log_message( null, null, 'sms', '+4790000003', null, 'Behold 3', 'inbound_sms', 'quarantine' );

		$_POST['snippen_inbox_action'] = 'bulk_action';
		$_POST['bulk_operation']       = 'delete';
		$_POST['message_ids']          = array( $id1, $id2 );
		$_POST['snippen_inbox_nonce']  = wp_create_nonce( 'snippen_bulk_sms_action' );
		$_REQUEST                      = array_merge( $_GET, $_POST );

		$page = new SmsInboxPage();
		ob_start();
		$page->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( '2 SMS-meldinger ble slettet.', $output );
		$this->assertNull( MessageLoggerService::get_message( $id1 ) );
		$this->assertNull( MessageLoggerService::get_message( $id2 ) );
		$this->assertNotNull( MessageLoggerService::get_message( $id3 ) );
	}

	/**
	 * Test sending direct reply logs outbound SMS message.
	 */
	public function test_handle_send_reply_action() {
		$id = MessageLoggerService::log_message(
			77,
			$this->admin_id,
			'sms',
			'+4791234567',
			null,
			'Hei, kan vi få svar?',
			'inbound_sms',
			'received'
		);

		// Configure SMS provider settings
		update_option( 'snippen_sms_provider', 'snippen_sms_service' );
		update_option( 'snippen_sms_service_api_token', 'test-token' );

		$_POST['snippen_inbox_action'] = 'send_reply';
		$_POST['message_id']           = $id;
		$_POST['recipient']            = '+4791234567';
		$_POST['booking_id']           = 77;
		$_POST['user_id']              = $this->admin_id;
		$_POST['reply_message']        = 'Her er direkte svar fra administrator!';
		$_POST['snippen_inbox_nonce']  = wp_create_nonce( 'snippen_reply_sms_message' );
		$_REQUEST                      = array_merge( $_GET, $_POST );

		$page = new SmsInboxPage();
		ob_start();
		$page->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'SMS-svar til +4791234567 ble lagt i utboksen', $output );

		// Verify reply is stored in snippen_messages
		$thread = MessageLoggerService::get_conversation_thread( '+4791234567', 77 );
		$this->assertCount( 2, $thread );
		$this->assertSame( 'Her er direkte svar fra administrator!', $thread[1]->message );
		$this->assertSame( 'admin_sms_reply', $thread[1]->event_type );
	}
}
