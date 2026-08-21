<?php

namespace SnippenBooking\Tests\Unit;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Database\Repository\BookingBlockRepository;
use SnippenBooking\Database\Install;

class WashTimeTest extends TestCase {

	private $block_repo;

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;

		Install::activate();
		$this->block_repo = new BookingBlockRepository();
	}

	public function test_booking_block_includes_wash_time_saving_and_retrieval() {
		// Create a new block with includes_wash_time enabled
		$data = array(
			'name'               => 'Helge kveld med utvask',
			'start_time'         => '18:00:00',
			'end_time'           => '23:59:59',
			'days_of_week'       => '5,6,0',
			'is_active'          => 1,
			'includes_wash_time' => 1,
		);

		$block_id = $this->block_repo->save( $data );
		$this->assertNotEmpty( $block_id );

		$block = $this->block_repo->find( $block_id );
		$this->assertNotNull( $block );
		$this->assertEquals( 1, (int) $block->includes_wash_time );
		$this->assertEquals( 'Helge kveld med utvask', $block->name );
	}
}
