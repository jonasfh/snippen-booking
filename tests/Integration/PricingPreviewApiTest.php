<?php

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Api\PricingPreviewApi;

class PricingPreviewApiTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
	}

	public function test_api_is_registered() {
		$this->assertTrue( has_action( 'wp_ajax_snippen_pricing_preview', array( PricingPreviewApi::class, 'handle_preview' ) ) !== false );
	}
}
