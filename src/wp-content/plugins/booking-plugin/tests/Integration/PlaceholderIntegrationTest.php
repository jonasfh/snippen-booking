<?php
/**
 * Integration Tests for All Notification Placeholders
 *
 * @package SnippenBooking\Tests\Integration
 */

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\Notification\NotificationTemplateService;
use SnippenBooking\Service\Notification\PlaceholderRegistry;
use SnippenBooking\Database\Repository\NotificationTemplateRepository;

/**
 * Class PlaceholderIntegrationTest
 */
class PlaceholderIntegrationTest extends TestCase {

	/**
	 * Template service
	 *
	 * @var NotificationTemplateService
	 */
	private $template_service;

	/**
	 * Registry
	 *
	 * @var PlaceholderRegistry
	 */
	private $registry;

	/**
	 * Set up test case
	 */
	public function setUp(): void {
		parent::setUp();
		$this->template_service = new NotificationTemplateService();
		$this->registry         = $this->template_service->get_registry();
	}

	/**
	 * Integration Test: Verify all registered placeholders can be rendered in their allowed contexts
	 */
	public function test_all_registered_placeholders_render_successfully_in_allowed_contexts() {
		$context_data = array(
			'user_name'                 => 'Ola Nordmann',
			'user_email'                => 'ola@example.com',
			'user_phone'                => '+4790000000',
			'confirmation_code'         => '987654',
			'booking_objects'           => 'Storsalen og Kjøkkenet',
			'booking_date'              => '2026-11-20',
			'booking_time'              => '12:00 - 18:00',
			'booking_description'       => 'Jubileumsfest for familien',
			'booking_url'               => 'https://example.com/booking/uuid-12345',
			'booking_price'             => '2 500',
			'bank_account'              => '1234.56.78901',
			'vipps_number'              => '54321',
			'payment_instructions'      => 'Betales senest 3 dager etter mottatt bekreftelse.',
			'door_code'                 => '144155',
			'door_code_valid_from'      => '2026-11-20 11:00',
			'door_code_valid_to'        => '2026-11-20 19:00',
			'door_code_validity_period' => '2026-11-20 11:00 - 19:00',
			'reset_link'                => 'https://example.com/reset?key=abc',
			'booking_type'              => 'Privat arrangement',
			'rejection_reason'          => 'Lokalet er opptatt til vedlikehold.',
			'payment_method'            => 'Vipps',
			'incoming_message'          => 'Hei, jeg har et spørsmål om nøkkel.',
			'message_body'              => 'Hei, jeg har et spørsmål om nøkkel.',
			'sender_phone'              => '+4790000000',
			'inbox_url'                 => 'https://example.com/wp-admin/admin.php?page=snippen-sms-inbox',
			'booking_id'                => '42',
		);

		$all_placeholders = $this->registry->get_registered_placeholders();
		$this->assertNotEmpty( $all_placeholders );

		foreach ( $all_placeholders as $name => $def ) {
			$this->assertArrayHasKey(
				$name,
				$context_data,
				"Test fixture \$context_data is missing sample value for registered placeholder '{$name}'."
			);
			$this->assertNotEmpty( $def['connected_to'], "Placeholder {$name} has no connected_to context defined." );

			foreach ( $def['connected_to'] as $connected_to ) {
				$template_text = "Testing placeholder {{{$name}}} in context {$connected_to}";

				$rendered = $this->registry->render_template( $template_text, $connected_to, $context_data, true );

				$expected_val = $context_data[ $name ];
				$this->assertStringContainsString(
					$expected_val,
					$rendered,
					"Failed to render placeholder '{$name}' in context '{$connected_to}'."
				);
				$this->assertStringNotContainsString(
					"{{{$name}}}",
					$rendered,
					"Placeholder '{{{$name}}}' was not replaced in context '{$connected_to}'."
				);
			}
		}
	}

	/**
	 * Integration Test: All default templates (SMS and Email) only use valid, allowed placeholders
	 */
	public function test_all_default_templates_validate_against_placeholder_registry() {
		$event_types = array_keys( $this->template_service->get_all_templates() );
		$this->assertNotEmpty( $event_types );

		foreach ( $event_types as $event_type ) {
			foreach ( array( 'sms', 'email' ) as $channel ) {
				$template = $this->template_service->get_default_template( $event_type, $channel );
				if ( ! empty( $template['subject'] ) ) {
					$errors = $this->registry->validate_template( $template['subject'], $event_type );
					$this->assertEmpty(
						$errors,
						sprintf(
							'Default template subject for %s (%s) failed placeholder validation: %s',
							$event_type,
							$channel,
							implode( '; ', $errors )
						)
					);
				}
				if ( ! empty( $template['body'] ) ) {
					$errors = $this->registry->validate_template( $template['body'], $event_type );
					$this->assertEmpty(
						$errors,
						sprintf(
							'Default template body for %s (%s) failed placeholder validation: %s',
							$event_type,
							$channel,
							implode( '; ', $errors )
						)
					);
				}
			}
		}
	}

	/**
	 * Integration Test: Verify validation rejects unknown placeholders
	 */
	public function test_validation_rejects_unknown_placeholders() {
		$template_text = 'Hello {{user_name}}, welcome to {{unknown_place_holder_abc}}!';
		$errors        = $this->registry->validate_template( $template_text, 'user_activation' );

		$this->assertNotEmpty( $errors );
		$this->assertStringContainsString( 'unknown_place_holder_abc', $errors[0] );
	}

	/**
	 * Integration Test: Verify validation rejects placeholders not allowed for event context
	 */
	public function test_validation_rejects_disallowed_placeholders_for_context() {
		// reset_link is allowed for password_reset, but NOT allowed for user_activation
		$template_text = 'Your code is {{confirmation_code}}. Reset link: {{reset_link}}';
		$errors        = $this->registry->validate_template( $template_text, 'user_activation' );

		$this->assertNotEmpty( $errors );
		$this->assertStringContainsString( 'reset_link', $errors[0] );
		$this->assertStringContainsString( 'user_activation', $errors[0] );
	}

	/**
	 * Integration Test: Custom template saving and rendering with registry
	 */
	public function test_custom_template_saving_and_rendering_integration() {
		$custom_subject = 'Bekreftelse for {{user_name}}';
		$custom_body    = 'Dato: {{booking_date}}, Tid: {{booking_time}}, Lokale: {{booking_objects}}, Pris: {{booking_price}} kr.';

		$saved = $this->template_service->save_template( 'booking_confirmation', 'email', $custom_subject, $custom_body );
		$this->assertTrue( $saved );

		$context = array(
			'user_name'       => 'Kari Nordmann',
			'booking_date'    => '2026-09-15',
			'booking_time'    => '10:00 - 14:00',
			'booking_objects' => 'Lillesalen',
			'booking_price'   => '1 200',
		);

		$rendered = $this->template_service->render_template( 'booking_confirmation', 'email', $context );

		$this->assertEquals( 'Bekreftelse for Kari Nordmann', $rendered['subject'] );
		$this->assertEquals( 'Dato: 2026-09-15, Tid: 10:00 - 14:00, Lokale: Lillesalen, Pris: 1 200 kr.', $rendered['body'] );
	}
}
