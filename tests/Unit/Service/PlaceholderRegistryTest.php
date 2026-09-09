<?php
/**
 * Unit Tests for PlaceholderRegistry
 *
 * @package SnippenBooking\Tests\Unit
 */

namespace SnippenBooking\Tests\Unit;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\Notification\PlaceholderRegistry;
use SnippenBooking\Service\Notification\Exception\UnknownPlaceholderException;
use SnippenBooking\Service\Notification\Exception\DisallowedPlaceholderException;
use SnippenBooking\Service\Notification\Exception\MissingPlaceholderValueException;

/**
 * Class PlaceholderRegistryTest
 */
class PlaceholderRegistryTest extends TestCase {

	/**
	 * Registry instance
	 *
	 * @var PlaceholderRegistry
	 */
	private $registry;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();
		$this->registry = new PlaceholderRegistry();
	}

	/**
	 * Test that all 17 standard placeholders are registered
	 */
	public function test_all_17_standard_placeholders_registered() {
		$placeholders = $this->registry->get_registered_placeholders();

		$this->assertCount( 17, $placeholders );

		$expected_keys = array(
			'user_name',
			'user_email',
			'user_phone',
			'confirmation_code',
			'booking_objects',
			'booking_date',
			'booking_time',
			'booking_description',
			'booking_url',
			'booking_price',
			'bank_account',
			'vipps_number',
			'payment_instructions',
			'reset_link',
			'booking_type',
			'rejection_reason',
			'payment_method',
		);

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $placeholders );
			$this->assertNotEmpty( $placeholders[ $key ]['label'] );
			$this->assertNotEmpty( $placeholders[ $key ]['connected_to'] );
		}
	}

	/**
	 * Test filtering placeholders by connected_to context
	 */
	public function test_get_placeholders_for_context() {
		$user_act_ph = $this->registry->get_placeholders_for_context( 'user_activation' );
		$this->assertArrayHasKey( 'user_name', $user_act_ph );
		$this->assertArrayHasKey( 'confirmation_code', $user_act_ph );
		$this->assertArrayNotHasKey( 'reset_link', $user_act_ph );

		$pass_reset_ph = $this->registry->get_placeholders_for_context( 'password_reset' );
		$this->assertArrayHasKey( 'user_name', $pass_reset_ph );
		$this->assertArrayHasKey( 'reset_link', $pass_reset_ph );
		$this->assertArrayNotHasKey( 'confirmation_code', $pass_reset_ph );
	}

	/**
	 * Test extracting placeholders from template string
	 */
	public function test_extract_placeholders() {
		$text = 'Hello {{user_name}}, date is {{booking_date}} and total is {{booking_price}}';
		$extracted = $this->registry->extract_placeholders( $text );

		$this->assertEquals( array( 'user_name', 'booking_date', 'booking_price' ), $extracted );
	}

	/**
	 * Test validating template strings
	 */
	public function test_validate_template() {
		$valid_text = 'Hello {{user_name}}, code is {{confirmation_code}}';
		$errors     = $this->registry->validate_template( $valid_text, 'user_activation' );
		$this->assertEmpty( $errors );

		$unknown_text = 'Hello {{unknown_token}}';
		$errors       = $this->registry->validate_template( $unknown_text, 'user_activation' );
		$this->assertCount( 1, $errors );
		$this->assertStringContainsString( 'Ukjent placeholder', $errors[0] );

		$disallowed_text = 'Reset password link {{reset_link}}';
		$errors          = $this->registry->validate_template( $disallowed_text, 'user_activation' );
		$this->assertCount( 1, $errors );
		$this->assertStringContainsString( 'ikke tillatt', $errors[0] );
	}

	/**
	 * Test validating template strings with hyphenated context names
	 */
	public function test_validate_template_hyphenated_context() {
		$booking_conf_text = 'Hello {{user_name}}, booking for {{booking_objects}} on {{booking_date}}. Total: {{booking_price}} kr. {{payment_instructions}} {{booking_url}}';
		$errors_hyphen     = $this->registry->validate_template( $booking_conf_text, 'booking-confirmation' );
		$this->assertEmpty( $errors_hyphen );

		$errors_underscore = $this->registry->validate_template( $booking_conf_text, 'booking_confirmation' );
		$this->assertEmpty( $errors_underscore );
	}

	/**
	 * Test resolving values from flat array context
	 */
	public function test_resolve_flat_context() {
		$context = array(
			'user_name'    => 'Alice',
			'booking_date' => '2026-10-15',
		);

		$res = $this->registry->resolve( 'user_name', 'booking_confirmation', $context );
		$this->assertEquals( 'Alice', $res );

		$res_date = $this->registry->resolve( 'booking_date', 'booking_confirmation', $context );
		$this->assertEquals( '2026-10-15', $res_date );
	}

	/**
	 * Test resolving values from nested object/array context
	 */
	public function test_resolve_nested_context() {
		$context = array(
			'user'    => array(
				'display_name' => 'Bob Builder',
			),
			'booking' => array(
				'date' => '2026-12-01',
			),
		);

		$res_name = $this->registry->resolve( 'user_name', 'booking_confirmation', $context );
		$this->assertEquals( 'Bob Builder', $res_name );

		$res_date = $this->registry->resolve( 'booking_date', 'booking_confirmation', $context );
		$this->assertEquals( '2026-12-01', $res_date );
	}

	/**
	 * Test exception when resolving missing value in strict mode
	 */
	public function test_strict_render_missing_value_throws_exception() {
		$this->expectException( MissingPlaceholderValueException::class );

		$text    = 'Hello {{user_name}}';
		$context = array(); // user_name missing

		$this->registry->render_template( $text, 'user_activation', $context, true );
	}

	/**
	 * Test validating template strings for booking-confirmed and payment-received contexts
	 */
	public function test_validate_template_booking_confirmed_and_payment_received_contexts() {
		$confirmed_text = 'Hello {{user_name}}, booking for {{booking_objects}} on {{booking_date}} {{booking_time}} is confirmed: {{booking_url}}';
		$this->assertEmpty( $this->registry->validate_template( $confirmed_text, 'booking-confirmed' ) );
		$this->assertEmpty( $this->registry->validate_template( $confirmed_text, 'booking_confirmed' ) );

		$payment_text = 'Tusen takk for mottatt betaling for reservasjon ({{booking_objects}}, {{booking_date}} {{booking_time}}). Beløp: {{booking_price}} kr. Se: {{booking_url}}';
		$this->assertEmpty( $this->registry->validate_template( $payment_text, 'payment-received' ) );
		$this->assertEmpty( $this->registry->validate_template( $payment_text, 'payment_received' ) );

		// Disallowed placeholder in payment-received context (e.g., reset_link)
		$invalid_payment = 'Paid for booking {{booking_objects}} {{reset_link}}';
		$errors          = $this->registry->validate_template( $invalid_payment, 'payment-received' );
		$this->assertCount( 1, $errors );
		$this->assertStringContainsString( 'ikke tillatt', $errors[0] );
	}

	/**
	 * Test validating template strings for booking-rejected context
	 */
	public function test_validate_template_booking_rejected_context() {
		$rejected_text = 'Hello {{user_name}}, booking for {{booking_objects}} on {{booking_date}} of type {{booking_type}} was rejected: {{rejection_reason}}. Method: {{payment_method}}';
		$this->assertEmpty( $this->registry->validate_template( $rejected_text, 'booking-rejected' ) );
		$this->assertEmpty( $this->registry->validate_template( $rejected_text, 'booking_rejected' ) );

		// Disallowed placeholder in booking-rejected context (e.g., confirmation_code)
		$invalid_rejected = 'Booking rejected with code {{confirmation_code}}';
		$errors           = $this->registry->validate_template( $invalid_rejected, 'booking-rejected' );
		$this->assertCount( 1, $errors );
		$this->assertStringContainsString( 'ikke tillatt', $errors[0] );
	}

	/**
	 * Test resolving booking_type, rejection_reason, and payment_method placeholders
	 */
	public function test_resolve_new_placeholders() {
		// Test booking_type conversion
		$context_open = array( 'booking_type' => 'open' );
		$this->assertEquals( 'Åpent arrangement', $this->registry->resolve( 'booking_type', 'booking_rejected', $context_open ) );

		$context_clean = array( 'booking_type' => 'cleaning' );
		$this->assertEquals( 'Utvask', $this->registry->resolve( 'booking_type', 'booking_rejected', $context_clean ) );

		$context_private = array( 'booking_type' => 'private' );
		$this->assertEquals( 'Privat arrangement', $this->registry->resolve( 'booking_type', 'booking_rejected', $context_private ) );

		// Test rejection_reason
		$context_reason = array( 'rejection_reason' => 'Arrangementet kolliderer med dugnad.' );
		$this->assertEquals( 'Arrangementet kolliderer med dugnad.', $this->registry->resolve( 'rejection_reason', 'booking_rejected', $context_reason ) );

		$context_empty_reason = array();
		$this->assertEquals( 'Ingen begrunnelse oppgitt.', $this->registry->resolve( 'rejection_reason', 'booking_rejected', $context_empty_reason ) );

		// Test payment_method
		$this->assertEquals( 'Vipps', $this->registry->resolve( 'payment_method', 'booking_confirmation', array( 'payment_method' => 'vipps' ) ) );
		$this->assertEquals( 'Bankoverføring', $this->registry->resolve( 'payment_method', 'booking_confirmation', array( 'payment_method' => 'bank' ) ) );
		$this->assertEquals( 'Fritatt', $this->registry->resolve( 'payment_method', 'booking_confirmation', array( 'payment_method' => 'exempt' ) ) );

		// Test fallback detection from booking object
		$booking_vipps = (object) array(
			'price'         => '500',
			'payment_notes' => 'Vipps ref: snippen-123',
			'booking_type'  => 'private',
		);
		$this->assertEquals( 'Vipps', $this->registry->resolve( 'payment_method', 'booking_confirmation', array( 'booking' => $booking_vipps ) ) );

		$booking_free = (object) array(
			'price'         => 0,
			'payment_notes' => null,
			'booking_type'  => 'open',
		);
		$this->assertEquals( 'Fritatt', $this->registry->resolve( 'payment_method', 'booking_confirmation', array( 'booking' => $booking_free ) ) );

		$booking_bank = (object) array(
			'price'         => '1000',
			'payment_notes' => null,
			'booking_type'  => 'private',
		);
		$this->assertEquals( 'Bankoverføring', $this->registry->resolve( 'payment_method', 'booking_confirmation', array( 'booking' => $booking_bank ) ) );
	}
}
