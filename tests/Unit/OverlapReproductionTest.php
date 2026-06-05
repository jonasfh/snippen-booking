<?php

namespace SnippenBooking\Tests\Unit;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\AvailabilityService;

class OverlapReproductionTest extends TestCase {

	public function testConsecutiveWholeDayBookingsDoNotOverlap() {
		$service = new AvailabilityService();

		// Proposed block 08:00:00 - 09:00:00.
		// Next block 09:00:00 - 10:00:00.
		// They should be compatible and not overlap.
		$proposed_start = new \DateTime( '2026-05-20 08:00:00' );
		$proposed_end   = new \DateTime( '2026-05-20 09:00:00' );
		$proposed_end->modify( '-1 second' );

		$booked_start = new \DateTime( '2026-05-20 09:00:00' );
		$booked_end   = new \DateTime( '2026-05-20 10:00:00' );
		$booked_end->modify( '-1 second' );

		$isOverlapping = ( $proposed_start < $booked_end ) && ( $booked_start < $proposed_end );

		$this->assertFalse( $isOverlapping, 'Consecutive blocks should NOT overlap' );
	}
}
