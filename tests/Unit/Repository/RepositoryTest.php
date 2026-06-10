<?php

namespace SnippenBooking\Tests\Unit\Repository;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Database\Repository\BookingBlockRepository;
use SnippenBooking\Database\Repository\PricingRuleRepository;
use SnippenBooking\Database\Repository\BookingRepository;
use SnippenBooking\Database\Install;

class RepositoryTest extends TestCase {

	private $block_repo;
	private $rule_repo;
	private $booking_repo;

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;

		Install::activate();

		$this->block_repo   = new BookingBlockRepository();
		$this->rule_repo    = new PricingRuleRepository();
		$this->booking_repo = new BookingRepository();

		// Clean up bookings
		$wpdb->query( "DELETE FROM " . $wpdb->prefix . "snippen_bookings" );
		$wpdb->query( "DELETE FROM " . $wpdb->prefix . "snippen_booking_booking_blocks" );
		$wpdb->query( "DELETE FROM " . $wpdb->prefix . "snippen_booking_booking_objects" );
	}

	public function test_booking_block_repository_find_methods() {
		// 1. find_all should return the 17 blocks from starter setup
		$all_blocks = $this->block_repo->find_all();
		$this->assertCount( 17, $all_blocks );

		// 2. find should retrieve one block
		$block_id = $all_blocks[0]->id;
		$block = $this->block_repo->find( $block_id );
		$this->assertNotNull( $block );
		$this->assertEquals( $block_id, $block->id );

		// 3. find_by_ids
		$blocks = $this->block_repo->find_by_ids( array( $block_id ) );
		$this->assertCount( 1, $blocks );
		$this->assertEquals( $block_id, $blocks[0]->id );
	}

	public function test_pricing_rule_repository_find_methods() {
		// 1. find_all should return 10 pricing rules from starter setup
		$all_rules = $this->rule_repo->find_all();
		$this->assertCount( 10, $all_rules );

		// 2. find_applicable_rules
		global $wpdb;
		$object_id = 1;
		$block_id  = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}snippen_booking_blocks LIMIT 1" );

		$rules = $this->rule_repo->find_applicable_rules( array( $object_id ), array( $block_id ) );
		$this->assertNotEmpty( $rules );
	}

	public function test_booking_repository_create_and_find() {
		global $wpdb;

		$object_id = 1;
		$block_id  = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}snippen_booking_blocks LIMIT 1" );

		// Create a booking
		$data = array(
			'booking_date'   => '2026-06-15',
			'user_id'        => 1,
			'customer_name'  => 'Test Customer',
			'customer_email' => 'customer@example.com',
			'customer_phone' => '12345678',
			'status'         => 'confirmed',
			'price'          => 100.00,
		);

		$booking_id = $this->booking_repo->create( $data, array( $object_id ), array( $block_id ) );
		$this->assertNotEmpty( $booking_id );

		// Find by ID
		$booking = $this->booking_repo->find( $booking_id );
		$this->assertNotNull( $booking );
		$this->assertEquals( 'customer@example.com', $booking->customer_email );
		$this->assertContains( $object_id, $booking->booking_object_ids );
		$this->assertContains( $block_id, $booking->booking_block_ids );

		// Find by UUID
		$booking_by_uuid = $this->booking_repo->find_by_uuid( $booking->uuid );
		$this->assertNotNull( $booking_by_uuid );
		$this->assertEquals( $booking_id, $booking_by_uuid->id );

		// Find by object and date range
		$bookings_range = $this->booking_repo->find_by_object_and_date_range( $object_id, '2026-06-15', '2026-06-15' );
		$this->assertCount( 1, $bookings_range );
		$this->assertEquals( $booking_id, $bookings_range[0]->id );
	}
}
