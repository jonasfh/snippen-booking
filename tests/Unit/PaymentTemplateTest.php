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
}
