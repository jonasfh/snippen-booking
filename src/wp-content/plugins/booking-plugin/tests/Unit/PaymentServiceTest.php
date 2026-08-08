<?php

namespace SnippenBooking\Tests\Unit;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\PaymentService;

/**
 * Tests for PaymentService
 */
class PaymentServiceTest extends TestCase {

	/**
	 * Test get_statuses returns non-empty list of payment statuses
	 */
	public function test_get_statuses() {
		$statuses = PaymentService::get_statuses();
		$this->assertNotEmpty( $statuses );

		$slugs = array_map( function( $s ) { return $s->slug; }, $statuses );
		$this->assertContains( 'UNPAID', $slugs );
		$this->assertContains( 'PENDING_VERIFICATION', $slugs );
		$this->assertContains( 'PAID', $slugs );
		$this->assertContains( 'EXEMPT', $slugs );
	}

	/**
	 * Test get_status_by_slug
	 */
	public function test_get_status_by_slug() {
		$status = PaymentService::get_status_by_slug( 'PAID' );
		$this->assertNotNull( $status );
		$this->assertEquals( 'PAID', $status->slug );
		$this->assertEquals( 1, (int) $status->is_settled );
	}

	/**
	 * Test get_booking_payment_status fallback for empty booking
	 */
	public function test_get_booking_payment_status_fallback() {
		$booking = (object) array();
		$status  = PaymentService::get_booking_payment_status( $booking );
		$this->assertNotNull( $status );
		$this->assertEquals( 'UNPAID', $status->slug );
		$this->assertEquals( 0, (int) $status->is_settled );
	}
}
