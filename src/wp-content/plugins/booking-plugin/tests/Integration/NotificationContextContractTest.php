<?php
/**
 * Contract Tests for Notification Context Builders and Placeholder Rendering
 *
 * Ensures every context builder (NotificationManager, BookingActionsApi, etc.)
 * provides all placeholders declared as allowed by PlaceholderRegistry for each context,
 * and that no unreplaced {{placeholder}} tokens ever leak into SMS or email output.
 *
 * @package SnippenBooking\Tests\Integration
 */

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\Notification\NotificationManager;
use SnippenBooking\Service\Notification\NotificationTemplateService;
use SnippenBooking\Service\Notification\PlaceholderRegistry;
use SnippenBooking\Api\BookingActionsApi;

/**
 * Class NotificationContextContractTest
 */
class NotificationContextContractTest extends TestCase {

	/**
	 * Captured emails during tests
	 *
	 * @var array
	 */
	private static $sent_mails = array();

	/**
	 * Registry instance
	 *
	 * @var PlaceholderRegistry
	 */
	private $registry;

	/**
	 * Template service instance
	 *
	 * @var NotificationTemplateService
	 */
	private $template_service;

	/**
	 * Test booking ID
	 *
	 * @var int
	 */
	private $booking_id;

	/**
	 * Test booking object ID
	 *
	 * @var int
	 */
	private $object_id;

	/**
	 * Test time slot ID
	 *
	 * @var int
	 */
	private $slot_id;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();

		self::$sent_mails       = array();
		$this->template_service = new NotificationTemplateService();
		$this->registry         = $this->template_service->get_registry();

		add_filter( 'pre_wp_mail', array( $this, 'catch_mail' ), 10, 2 );

		update_option( 'snippen_payment_bank_account', '1234.56.78901' );
		update_option( 'snippen_payment_vipps_number', '98765' );
		update_option( 'snippen_payment_instructions', 'Betales innen 3 dager.' );

		$this->seed_test_booking();
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
	 * Create a realistic booking, venue object, and time slot in the database
	 */
	private function seed_test_booking(): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'snippen_booking_objects',
			array(
				'name'        => 'Peisestuen',
				'description' => 'Testlokale',
			)
		);
		$this->object_id = (int) $wpdb->insert_id;

		$wpdb->insert(
			$wpdb->prefix . 'snippen_time_slots',
			array(
				'name'       => 'Dagtid',
				'start_time' => '10:00:00',
				'end_time'   => '18:00:00',
			)
		);
		$this->slot_id = (int) $wpdb->insert_id;

		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'uuid'             => 'ddea42ed-dd8e-44ed-af0d-1e73c4a713f8',
				'customer_name'    => 'Nina Arnfinnsdottir',
				'customer_email'   => 'nina@example.com',
				'customer_phone'   => '+4790001122',
				'booking_date'     => '2026-11-14',
				'slot_id'          => $this->slot_id,
				'price'            => 1500,
				'description'      => 'Bursdagsfeiring',
				'booking_type'     => 'private',
				'status'           => 'confirmed',
				'door_code'        => '166366',
				'rejection_reason' => 'Ikke aktuelt',
				'booking_snapshot' => wp_json_encode(
					array(
						'start_time'           => '10:00:00',
						'end_time'             => '18:00:00',
						'time_range_formatted' => '10:00 - 18:00',
					)
				),
			)
		);
		$this->booking_id = (int) $wpdb->insert_id;

		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings_booking_objects',
			array(
				'booking_id'        => $this->booking_id,
				'booking_object_id' => $this->object_id,
			)
		);
	}

	/**
	 * Build a synthetic template string containing every placeholder allowed for a given context
	 *
	 * @param string $context_name Event context name.
	 * @return string
	 */
	private function build_all_placeholders_template( string $context_name ): string {
		$allowed = $this->registry->get_placeholders_for_context( $context_name );
		$parts   = array();
		foreach ( array_keys( $allowed ) as $name ) {
			$parts[] = "{$name}={{{$name}}}";
		}
		return implode( ' | ', $parts );
	}

	/**
	 * Booking event contexts that must all be supported by NotificationManager's booking context builder
	 *
	 * @return array
	 */
	public function booking_context_provider(): array {
		return array(
			'booking_confirmation'     => array( 'booking_confirmation' ),
			'booking_confirmed'        => array( 'booking_confirmed' ),
			'booking_rejected'         => array( 'booking_rejected' ),
			'admin_booking'            => array( 'admin_booking' ),
			'payment_reminder'         => array( 'payment_reminder' ),
			'payment_receipt_uploaded' => array( 'payment_receipt_uploaded' ),
			'payment_received'         => array( 'payment_received' ),
			'door_code_updated'        => array( 'door_code_updated' ),
		);
	}

	/**
	 * Contract Test: NotificationManager booking context provides every placeholder allowed by PlaceholderRegistry
	 *
	 * @dataProvider booking_context_provider
	 * @param string $context_name Event context name.
	 */
	public function test_notification_manager_booking_context_satisfies_all_allowed_placeholders( string $context_name ) {
		global $wpdb;
		$booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}snippen_bookings WHERE id = %d", $this->booking_id ) );

		$manager = new NotificationManager();
		$ref     = new \ReflectionClass( $manager );
		$method  = $ref->getMethod( 'build_booking_context' );
		$method->setAccessible( true );

		$context = $method->invoke(
			$manager,
			$booking,
			'Peisestuen',
			'10:00 - 18:00',
			'https://vestreholmensameie.no/?booking_uuid=' . $booking->uuid,
			'Avslått pga vedlikehold'
		);

		$template_text = $this->build_all_placeholders_template( $context_name );
		$rendered      = $this->registry->render_template( $template_text, $context_name, $context, true );

		$this->assertDoesNotMatchRegularExpression(
			'/\{\{[^}]+\}\}/',
			$rendered,
			"Unreplaced placeholder left in NotificationManager context '{$context_name}': {$rendered}"
		);
	}

	/**
	 * Contract Test: BookingActionsApi manual message preview replaces all placeholders including door_code_valid_from/to
	 */
	public function test_booking_actions_api_preview_replaces_all_door_code_placeholders() {
		$admin_id = wp_create_user( 'contract_admin_' . wp_rand( 1000, 9999 ), 'password123', 'contract_admin_' . wp_rand( 1000, 9999 ) . '@example.com' );
		$user     = new \WP_User( $admin_id );
		$user->set_role( 'administrator' );
		$user->add_cap( \SnippenBooking\Helper\Capabilities::MANAGE_BOOKINGS );
		wp_set_current_user( $admin_id );

		$_POST['nonce']    = wp_create_nonce( 'snippen_admin_nonce' );
		$_REQUEST['nonce'] = $_POST['nonce'];
		$_POST['id']       = $this->booking_id;
		$_POST['channel']  = 'sms_customer';

		$response = null;
		add_filter(
			'wp_die_ajax_handler',
			function () {
				return function ( $message ) {
					throw new \Exception( is_string( $message ) ? $message : (string) $message );
				};
			}
		);

		ob_start();
		try {
			BookingActionsApi::get_notification_preview();
		} catch ( \Exception $e ) {
			// Expected wp_die from wp_send_json_success.
		}
		$json     = ob_get_clean();
		$response = json_decode( $json, true );

		$this->assertIsArray( $response );
		$this->assertTrue( $response['success'], 'get_message_templates did not return success: ' . $json );

		$templates_by_key = array();
		foreach ( $response['data']['templates'] as $tpl ) {
			$templates_by_key[ $tpl['key'] ] = $tpl;
		}

		$this->assertArrayHasKey( 'door_code_updated', $templates_by_key, 'door_code_updated template missing from BookingActionsApi options.' );

		$door_code_tpl = $templates_by_key['door_code_updated'];
		$this->assertDoesNotMatchRegularExpression(
			'/\{\{[^}]+\}\}/',
			$door_code_tpl['rendered_body'],
			'Unreplaced placeholder in BookingActionsApi door_code_updated preview: ' . $door_code_tpl['rendered_body']
		);
		$this->assertStringContainsString( '166366', $door_code_tpl['rendered_body'] );
	}

	/**
	 * Contract Test: Empty optional field present in context should render as empty string in non-strict mode, never leak {{token}}
	 */
	public function test_empty_context_value_renders_as_empty_string_in_non_strict_mode() {
		$context = array(
			'user_name'           => 'Test Bruker',
			'booking_description' => '',
			'door_code'           => '',
		);

		$rendered = $this->registry->render_template(
			'Hei {{user_name}}! Kode: {{door_code}}.',
			'booking_confirmed',
			$context,
			false
		);

		$this->assertStringNotContainsString( '{{door_code}}', $rendered );
		$this->assertEquals( 'Hei Test Bruker! Kode: .', $rendered );
	}

	/**
	 * Contract Test: End-to-end NotificationManager email dispatch with all allowed placeholders for booking_confirmed
	 */
	public function test_notification_manager_email_dispatch_renders_all_allowed_placeholders() {
		$all_tokens_body = $this->build_all_placeholders_template( 'booking_confirmed' );
		$this->template_service->save_template(
			'booking_confirmed',
			'email',
			'Bekreftet for {{user_name}} - {{booking_objects}}',
			$all_tokens_body
		);

		update_option( 'snippen_email_booking_confirmed_enabled', 'yes' );
		update_option( 'snippen_sms_booking_confirmed_enabled', 'no' );

		$manager = new NotificationManager();
		$manager->send_booking_confirmed_notification( $this->booking_id );

		$this->assertNotEmpty( self::$sent_mails, 'Expected booking_confirmed email to be sent.' );
		$mail = self::$sent_mails[0];

		$this->assertDoesNotMatchRegularExpression( '/\{\{[^}]+\}\}/', $mail['subject'] );
		$this->assertDoesNotMatchRegularExpression( '/\{\{[^}]+\}\}/', $mail['message'] );
		$this->assertStringContainsString( 'Nina Arnfinnsdottir', $mail['message'] );
		$this->assertStringContainsString( '166366', $mail['message'] );
	}

	/**
	 * Contract Test: Non-booking contexts (user_activation, password_reset, inbound_sms_received) render all allowed placeholders
	 */
	public function test_non_booking_contexts_satisfy_all_allowed_placeholders() {
		$non_booking_contexts = array(
			'user_activation'      => array(
				'user_name'         => 'Kari Nordmann',
				'confirmation_code' => '123456',
			),
			'password_reset'       => array(
				'user_name'  => 'Kari Nordmann',
				'reset_link' => 'https://vestreholmensameie.no/reset?key=xyz',
			),
			'inbound_sms_received' => array(
				'user_name'        => 'Nina Arnfinnsdottir',
				'user_email'       => 'nina@example.com',
				'user_phone'       => '+4790001122',
				'booking_objects'  => 'Peisestuen',
				'booking_date'     => '2026-11-14',
				'booking_time'     => '10:00 - 18:00',
				'booking_url'      => 'https://vestreholmensameie.no/?booking_uuid=ddea42ed-dd8e-44ed-af0d-1e73c4a713f8',
				'incoming_message' => 'Hei, når er koden aktiv?',
				'message_body'     => 'Hei, når er koden aktiv?',
				'sender_phone'     => '+4790001122',
				'inbox_url'        => 'https://vestreholmensameie.no/wp-admin/admin.php?page=snippen-sms-inbox',
				'booking_id'       => (string) $this->booking_id,
			),
		);

		foreach ( $non_booking_contexts as $context_name => $context_data ) {
			$template_text = $this->build_all_placeholders_template( $context_name );
			$rendered      = $this->registry->render_template( $template_text, $context_name, $context_data, true );

			$this->assertDoesNotMatchRegularExpression(
				'/\{\{[^}]+\}\}/',
				$rendered,
				"Unreplaced placeholder left in non-booking context '{$context_name}': {$rendered}"
			);
		}
	}
}
