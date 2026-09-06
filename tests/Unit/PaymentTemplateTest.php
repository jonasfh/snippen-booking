<?php
/**
 * Payment Template Unit Test
 *
 * @package SnippenBooking\Tests\Unit
 */

namespace SnippenBooking\Tests\Unit;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\Notification\NotificationTemplateService;

/**
 * Unit tests for booking_confirmation template placeholders.
 */
class PaymentTemplateTest extends TestCase {

	public function setUp(): void {
		parent::setUp();
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_notification_templates" );
		delete_option( 'snippen_template_booking_confirmation_email' );
		delete_option( 'snippen_template_booking_confirmation_sms' );
	}

	/**
	 * Test that booking_confirmation default template and placeholders render correctly.
	 */
	public function test_booking_confirmation_template_rendering_with_payment_placeholders() {
		$template_service = new NotificationTemplateService();

		// Verify event type exists in placeholders
		$placeholders = $template_service->get_available_placeholders( 'booking_confirmation' );
		$this->assertArrayHasKey( 'bank_account', $placeholders );
		$this->assertArrayHasKey( 'vipps_number', $placeholders );
		$this->assertArrayHasKey( 'booking_price', $placeholders );
		$this->assertArrayHasKey( 'payment_instructions', $placeholders );

		// Render template with context
		$context = array(
			'user_name'            => 'Ola Nordmann',
			'booking_objects'      => 'Festsalen',
			'booking_date'         => '2026-09-01',
			'booking_url'          => 'https://example.com/?booking_uuid=123',
			'booking_price'        => '1 500',
			'bank_account'         => '1234.56.78901',
			'vipps_number'         => '#12345',
			'payment_instructions' => 'Vennligst overfør leiebeløpet innen 3 dager.',
		);

		$rendered = $template_service->render_template( 'booking_confirmation', 'email', $context );

		$this->assertNotEmpty( $rendered['subject'] );
		$this->assertStringContainsString( 'Ola Nordmann', $rendered['body'] );
		$this->assertStringContainsString( 'Festsalen', $rendered['body'] );
		$this->assertStringContainsString( '1234.56.78901', $rendered['body'] );
		$this->assertStringContainsString( '#12345', $rendered['body'] );
		$this->assertStringContainsString( '1 500', $rendered['body'] );
		$this->assertStringContainsString( 'Vennligst overfør leiebeløpet innen 3 dager.', $rendered['body'] );
	}

	/**
	 * Test that payment_receipt_uploaded default template and placeholders render correctly.
	 */
	public function test_payment_receipt_uploaded_template_rendering() {
		$template_service = new NotificationTemplateService();

		$placeholders = $template_service->get_available_placeholders( 'payment_receipt_uploaded' );
		$this->assertArrayHasKey( 'user_name', $placeholders );
		$this->assertArrayHasKey( 'user_email', $placeholders );
		$this->assertArrayHasKey( 'booking_objects', $placeholders );
		$this->assertArrayHasKey( 'booking_date', $placeholders );
		$this->assertArrayHasKey( 'booking_price', $placeholders );
		$this->assertArrayHasKey( 'booking_url', $placeholders );

		$context = array(
			'user_name'       => 'Kari Nordmann',
			'user_email'      => 'kari@example.com',
			'booking_objects' => 'Peisestuen',
			'booking_date'    => '2026-10-15',
			'booking_price'   => '2 000',
			'booking_url'     => 'https://example.com/?booking_uuid=456',
		);

		$rendered = $template_service->render_template( 'payment_receipt_uploaded', 'email', $context );

		$this->assertStringContainsString( 'Peisestuen', $rendered['subject'] );
		$this->assertStringContainsString( 'Kari Nordmann', $rendered['body'] );
		$this->assertStringContainsString( 'kari@example.com', $rendered['body'] );
		$this->assertStringContainsString( 'Peisestuen', $rendered['body'] );
		$this->assertStringContainsString( '2026-10-15', $rendered['body'] );
		$this->assertStringContainsString( '2 000', $rendered['body'] );
		$this->assertStringContainsString( 'https://example.com/?booking_uuid=456', $rendered['body'] );
	}

	/**
	 * Test that booking_confirmed default template and placeholders render correctly.
	 */
	public function test_booking_confirmed_template_rendering() {
		$template_service = new NotificationTemplateService();

		$placeholders = $template_service->get_available_placeholders( 'booking_confirmed' );
		$this->assertArrayHasKey( 'user_name', $placeholders );
		$this->assertArrayHasKey( 'booking_objects', $placeholders );
		$this->assertArrayHasKey( 'booking_date', $placeholders );
		$this->assertArrayHasKey( 'booking_time', $placeholders );
		$this->assertArrayHasKey( 'booking_url', $placeholders );

		$context = array(
			'user_name'       => 'Ola Nordmann',
			'booking_objects' => 'Festsalen',
			'booking_date'    => '2026-09-01',
			'booking_time'    => '10:00 - 14:00',
			'booking_url'     => 'https://example.com/?booking_uuid=123',
		);

		$rendered_email = $template_service->render_template( 'booking_confirmed', 'email', $context );
		$this->assertStringContainsString( 'Festsalen', $rendered_email['subject'] );
		$this->assertStringContainsString( 'godkjent og bekreftet', $rendered_email['subject'] );
		$this->assertStringContainsString( 'Ola Nordmann', $rendered_email['body'] );
		$this->assertStringContainsString( '2026-09-01', $rendered_email['body'] );
		$this->assertStringContainsString( '10:00 - 14:00', $rendered_email['body'] );
		$this->assertStringContainsString( 'https://example.com/?booking_uuid=123', $rendered_email['body'] );

		$rendered_sms = $template_service->render_template( 'booking_confirmed', 'sms', $context );
		$this->assertStringContainsString( 'Festsalen', $rendered_sms['body'] );
		$this->assertStringContainsString( '2026-09-01', $rendered_sms['body'] );
		$this->assertStringContainsString( '10:00 - 14:00', $rendered_sms['body'] );
		$this->assertStringContainsString( 'godkjent og bekreftet', $rendered_sms['body'] );
		$this->assertStringContainsString( 'https://example.com/?booking_uuid=123', $rendered_sms['body'] );
	}

	/**
	 * Test that payment_received default template and placeholders render correctly.
	 */
	public function test_payment_received_template_rendering() {
		$template_service = new NotificationTemplateService();

		$placeholders = $template_service->get_available_placeholders( 'payment_received' );
		$this->assertArrayHasKey( 'user_name', $placeholders );
		$this->assertArrayHasKey( 'booking_objects', $placeholders );
		$this->assertArrayHasKey( 'booking_date', $placeholders );
		$this->assertArrayHasKey( 'booking_time', $placeholders );
		$this->assertArrayHasKey( 'booking_price', $placeholders );
		$this->assertArrayHasKey( 'booking_url', $placeholders );

		$context = array(
			'user_name'       => 'Ola Nordmann',
			'booking_objects' => 'Festsalen',
			'booking_date'    => '2026-09-01',
			'booking_time'    => '10:00 - 14:00',
			'booking_price'   => '1 500',
			'booking_url'     => 'https://example.com/?booking_uuid=123',
		);

		$rendered_email = $template_service->render_template( 'payment_received', 'email', $context );
		$this->assertStringContainsString( 'Festsalen', $rendered_email['subject'] );
		$this->assertStringContainsString( 'Tusen takk for mottatt betaling for reservasjon', $rendered_email['body'] );
		$this->assertStringContainsString( '2026-09-01', $rendered_email['body'] );
		$this->assertStringContainsString( '10:00 - 14:00', $rendered_email['body'] );
		$this->assertStringContainsString( '1 500', $rendered_email['body'] );
		$this->assertStringContainsString( 'https://example.com/?booking_uuid=123', $rendered_email['body'] );

		$rendered_sms = $template_service->render_template( 'payment_received', 'sms', $context );
		$this->assertStringContainsString( 'Tusen takk for mottatt betaling for reservasjon', $rendered_sms['body'] );
		$this->assertStringContainsString( 'Festsalen', $rendered_sms['body'] );
		$this->assertStringContainsString( '2026-09-01', $rendered_sms['body'] );
		$this->assertStringContainsString( '10:00 - 14:00', $rendered_sms['body'] );
		$this->assertStringContainsString( 'https://example.com/?booking_uuid=123', $rendered_sms['body'] );
	}
}
