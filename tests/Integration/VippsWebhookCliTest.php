<?php
/**
 * Integration tests for Vipps Webhook CLI tool (bin/vipps-webhook.php)
 *
 * @package SnippenBooking\Tests\Integration
 */

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;

/**
 * VippsWebhookCliTest
 */
class VippsWebhookCliTest extends TestCase {

	/**
	 * Test that running help command outputs usage instructions including status.
	 */
	public function test_cli_help_command() {
		$script = escapeshellarg( dirname( __DIR__, 2 ) . '/bin/vipps-webhook.php' );
		$output = shell_exec( "php $script help 2>&1" );

		$this->assertIsString( $output );
		$this->assertStringContainsString( 'Vipps Webhooks Management CLI', $output );
		$this->assertStringContainsString( 'vipps-webhook.php status', $output );
		$this->assertStringContainsString( 'vipps-webhook.php list', $output );
		$this->assertStringContainsString( 'vipps-webhook.php register', $output );
	}

	/**
	 * Test that status command executes and outputs configuration sections.
	 */
	public function test_cli_status_command() {
		$script = escapeshellarg( dirname( __DIR__, 2 ) . '/bin/vipps-webhook.php' );
		$output = shell_exec( "php $script status 2>&1" );

		$this->assertIsString( $output );
		$this->assertStringContainsString( 'Vipps MobilePay ePayment & Webhook Status', $output );
		$this->assertStringContainsString( '[1] Miljø / Endepunkt:', $output );
		$this->assertStringContainsString( '[2] API-Nøkler & Konfigurasjonskilder:', $output );
		$this->assertStringContainsString( '[3] Nettverk & Sikkerhet:', $output );
		$this->assertStringContainsString( '[4] Aktive Webhooks hos Vipps:', $output );
		$this->assertStringContainsString( 'Client ID', $output );
		$this->assertStringContainsString( 'Client Secret', $output );
		$this->assertStringContainsString( 'Subscription Key', $output );
		$this->assertStringContainsString( 'MSN (Salgssted)', $output );
		$this->assertStringContainsString( 'TLS 1.2+ støtte', $output );
	}

	/**
	 * Test that registering an insecure HTTP webhook URL in production is blocked.
	 */
	public function test_cli_prod_blocks_http_url() {
		$script = escapeshellarg( dirname( __DIR__, 2 ) . '/bin/vipps-webhook.php' );
		// Execute with VIPPS_ENVIRONMENT=prod and credentials so it runs identically in CI and local
		$cmd    = "VIPPS_ENVIRONMENT=prod VIPPS_CLIENT_ID=dummy-id VIPPS_CLIENT_SECRET=dummy-secret VIPPS_SUBSCRIPTION_KEY=dummy-sub VIPPS_MSN=123456 php $script register http://example.com/wp-json/snippen/v1/vipps/webhook 2>&1";
		$output = array();
		$code   = 0;
		exec( $cmd, $output, $code );

		$full_output = implode( "\n", $output );
		$this->assertNotEquals( 0, $code );
		$this->assertStringContainsString( 'Vipps produksjon (api.vipps.no) krever gyldig HTTPS-adresse', $full_output );
	}
}
