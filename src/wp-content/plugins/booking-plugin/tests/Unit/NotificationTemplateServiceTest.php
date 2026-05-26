<?php
/**
 * Notification Template Tests
 *
 * @package SnippenBooking\Tests\Unit
 */

namespace SnippenBooking\Tests\Unit;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\Notification\NotificationTemplateService;

/**
 * Unit tests for notification templates
 */
class NotificationTemplateServiceTest extends TestCase {

	/**
	 * Template service instance
	 *
	 * @var NotificationTemplateService
	 */
	private $service;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();
		$this->service = new NotificationTemplateService();
	}

	/**
	 * Test that default templates are returned when no custom template exists
	 */
	public function test_get_default_template() {
		$template = $this->service->get_template( 'user_activation', 'sms' );

		$this->assertTrue( $template['is_default'] );
		$this->assertNotEmpty( $template['body'] );
		$this->assertStringContainsString( '{{confirmation_code}}', $template['body'] );
	}

	/**
	 * Test that custom templates override defaults
	 */
	public function test_save_and_get_custom_template() {
		$custom_body = 'Custom SMS: {{confirmation_code}}';
		$this->service->save_template( 'user_activation', 'sms', null, $custom_body );

		$template = $this->service->get_template( 'user_activation', 'sms' );

		$this->assertFalse( $template['is_default'] );
		$this->assertEquals( $custom_body, $template['body'] );
	}

	/**
	 * Test that reset_template_to_default removes custom template
	 */
	public function test_reset_template_to_default() {
		// First, save a custom template
		$custom_body = 'Custom message';
		$this->service->save_template( 'booking_confirmation', 'email', 'Custom Subject', $custom_body );

		$template = $this->service->get_template( 'booking_confirmation', 'email' );
		$this->assertFalse( $template['is_default'] );

		// Now reset it
		$this->service->reset_template_to_default( 'booking_confirmation', 'email' );

		$template = $this->service->get_template( 'booking_confirmation', 'email' );
		$this->assertTrue( $template['is_default'] );
	}

	/**
	 * Test placeholder replacement
	 */
	public function test_render_template_with_placeholders() {
		$context = array(
			'user_name'          => 'John Doe',
			'confirmation_code'  => '123456',
		);

		$rendered = $this->service->render_template( 'user_activation', 'sms', $context );

		$this->assertStringContainsString( 'John Doe', $rendered['body'] );
		$this->assertStringContainsString( '123456', $rendered['body'] );
		$this->assertStringNotContainsString( '{{user_name}}', $rendered['body'] );
		$this->assertStringNotContainsString( '{{confirmation_code}}', $rendered['body'] );
	}

	/**
	 * Test rendering with custom template
	 */
	public function test_render_custom_template_with_placeholders() {
		$custom_body = 'Hello {{user_name}}, your code is {{confirmation_code}}';
		$this->service->save_template( 'user_activation', 'email', 'Test Subject', $custom_body );

		$context = array(
			'user_name'          => 'Jane Smith',
			'confirmation_code'  => '654321',
		);

		$rendered = $this->service->render_template( 'user_activation', 'email', $context );

		$this->assertEquals( 'Hello Jane Smith, your code is 654321', $rendered['body'] );
		$this->assertEquals( 'Test Subject', $rendered['subject'] );
	}

	/**
	 * Test get_all_templates returns all event types and channels
	 */
	public function test_get_all_templates() {
		$all_templates = $this->service->get_all_templates();

		$this->assertArrayHasKey( 'user_activation', $all_templates );
		$this->assertArrayHasKey( 'booking_confirmation', $all_templates );
		$this->assertArrayHasKey( 'admin_booking', $all_templates );

		foreach ( $all_templates as $event_type => $templates_by_channel ) {
			$this->assertArrayHasKey( 'sms', $templates_by_channel );
			$this->assertArrayHasKey( 'email', $templates_by_channel );
		}
	}

	/**
	 * Test get_available_placeholders returns correct placeholders for each event
	 */
	public function test_get_available_placeholders() {
		$placeholders = $this->service->get_available_placeholders( 'user_activation' );

		$this->assertArrayHasKey( 'user_name', $placeholders );
		$this->assertArrayHasKey( 'confirmation_code', $placeholders );

		$booking_placeholders = $this->service->get_available_placeholders( 'booking_confirmation' );
		$this->assertArrayHasKey( 'user_name', $booking_placeholders );
		$this->assertArrayHasKey( 'booking_objects', $booking_placeholders );
		$this->assertArrayHasKey( 'booking_date', $booking_placeholders );
		$this->assertArrayHasKey( 'booking_url', $booking_placeholders );

		$admin_placeholders = $this->service->get_available_placeholders( 'admin_booking' );
		$this->assertArrayHasKey( 'user_email', $admin_placeholders );
		$this->assertArrayHasKey( 'user_phone', $admin_placeholders );
	}

	/**
	 * Test email templates have subject lines
	 */
	public function test_email_templates_have_subjects() {
		$email_template = $this->service->get_template( 'user_activation', 'email' );
		$this->assertNotEmpty( $email_template['subject'] );
		$this->assertStringContainsString( 'Bekreftelse', $email_template['subject'] );

		$booking_email = $this->service->get_template( 'booking_confirmation', 'email' );
		$this->assertNotEmpty( $booking_email['subject'] );
	}

	/**
	 * Test SMS templates have empty subject lines
	 */
	public function test_sms_templates_have_empty_subjects() {
		$sms_template = $this->service->get_template( 'user_activation', 'sms' );
		$this->assertEmpty( $sms_template['subject'] );

		$booking_sms = $this->service->get_template( 'booking_confirmation', 'sms' );
		$this->assertEmpty( $booking_sms['subject'] );
	}

	/**
	 * Test that missing placeholders don't break rendering
	 */
	public function test_render_with_missing_context_values() {
		$context = array(
			'user_name' => 'Test User',
			// Missing confirmation_code
		);

		$rendered = $this->service->render_template( 'user_activation', 'sms', $context );

		// Should still contain user_name replacement, but {{confirmation_code}} remains
		$this->assertStringContainsString( 'Test User', $rendered['body'] );
	}
}
