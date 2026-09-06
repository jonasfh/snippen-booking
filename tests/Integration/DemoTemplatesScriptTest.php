<?php
/**
 * Integration Tests for Demo Templates Setup Script
 *
 * @package SnippenBooking\Tests\Integration
 */

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Database\Repository\NotificationTemplateRepository;

/**
 * Class DemoTemplatesScriptTest
 */
class DemoTemplatesScriptTest extends TestCase {

	/**
	 * Repository instance
	 *
	 * @var NotificationTemplateRepository
	 */
	private $repository;

	/**
	 * Set up test case
	 */
	public function setUp(): void {
		parent::setUp();
		$this->repository = new NotificationTemplateRepository();
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_notification_templates';
		$wpdb->query( "TRUNCATE TABLE {$table}" );
	}

	/**
	 * Test that demo templates script can be executed idempotently
	 */
	public function test_demo_templates_script_seeds_all_default_templates() {
		// Run repository seed_defaults
		$this->repository->seed_defaults();

		$all_templates = $this->repository->get_all();
		$this->assertCount( 16, $all_templates );

		$connected_values = array();
		foreach ( $all_templates as $tpl ) {
			$connected_values[] = $tpl->connected_to . ':' . $tpl->type;
		}

		$this->assertContains( 'account-activation:sms', $connected_values );
		$this->assertContains( 'account-activation:email', $connected_values );
		$this->assertContains( 'booking-confirmation:sms', $connected_values );
		$this->assertContains( 'booking-confirmation:email', $connected_values );
		$this->assertContains( 'admin-booking-alert:sms', $connected_values );
		$this->assertContains( 'admin-booking-alert:email', $connected_values );
		$this->assertContains( 'password-reset:sms', $connected_values );
		$this->assertContains( 'password-reset:email', $connected_values );
		$this->assertContains( 'payment-reminder:sms', $connected_values );
		$this->assertContains( 'payment-reminder:email', $connected_values );
		$this->assertContains( 'payment-receipt-uploaded:sms', $connected_values );
		$this->assertContains( 'payment-receipt-uploaded:email', $connected_values );
		$this->assertContains( 'booking-confirmed:sms', $connected_values );
		$this->assertContains( 'booking-confirmed:email', $connected_values );
		$this->assertContains( 'payment-received:sms', $connected_values );
		$this->assertContains( 'payment-received:email', $connected_values );
	}
}
