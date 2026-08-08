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
 * Unit tests for payment_instructions template.
 */
class PaymentTemplateTest extends TestCase {

	/**
	 * Test that payment_instructions default template and placeholders render correctly.
	 */
	public function test_payment_instructions_template_rendering() {
		$template_service = new NotificationTemplateService();

		// Verify event type exists in placeholders
		$placeholders = $template_service->get_available_placeholders( 'payment_instructions' );
		$this->assertArrayHasKey( 'bank_account', $placeholders );
		$this->assertArrayHasKey( 'vipps_number', $placeholders );

		// Render template with context
		$context = array(
			'user_name'       => 'Ola Nordmann',
			'booking_objects' => 'Festsalen',
			'booking_date'    => '2026-09-01',
			'booking_price'   => '1 500',
			'bank_account'    => '1234.56.78901',
			'vipps_number'    => '#12345',
		);

		$rendered = $template_service->render_template( 'payment_instructions', 'email', $context );

		$this->assertStringContainsString( 'Betalingsinstruksjoner', $rendered['subject'] );
		$this->assertStringContainsString( 'Ola Nordmann', $rendered['body'] );
		$this->assertStringContainsString( 'Festsalen', $rendered['body'] );
		$this->assertStringContainsString( '1234.56.78901', $rendered['body'] );
		$this->assertStringContainsString( '#12345', $rendered['body'] );
		$this->assertStringContainsString( '1 500', $rendered['body'] );
	}
}
