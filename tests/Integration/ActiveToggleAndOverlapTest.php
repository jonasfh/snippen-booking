<?php

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Database\Repository\BookingBlockRepository;
use SnippenBooking\Database\Repository\DiscountRuleRepository;
use SnippenBooking\Api\ToggleStatusApi;

class ActiveToggleAndOverlapTest extends TestCase {

	protected $requires_db        = true;
	protected $requires_seed_data = false;

	public function test_time_slot_overlapping_allowed_when_one_inactive() {
		$repo = new BookingBlockRepository();

		// Create active block 1 (08:00 - 12:00)
		$block1_id = $repo->save(
			array(
				'name'       => 'Active Morning Block',
				'start_time' => '08:00:00',
				'end_time'   => '12:00:00',
				'is_active'  => 1,
			)
		);
		$repo->sync_booking_objects( $block1_id, array( 1 ) );

		// 1. Check overlap for another block (10:00 - 14:00) while block 1 is active -> Should overlap
		$has_overlap = $repo->has_overlap( null, '10:00:00', '14:00:00', array( 1 ), null );
		$this->assertTrue( (bool) $has_overlap, 'Active overlapping block should cause overlap error' );

		// Deactivate block 1
		$repo->save( array( 'name' => 'Deactivated Block 1', 'start_time' => '08:00:00', 'end_time' => '12:00:00', 'is_active' => 0 ), $block1_id );

		// 2. Check overlap when block 1 is inactive -> Should NOT overlap
		$has_overlap_against_inactive = $repo->has_overlap( null, '10:00:00', '14:00:00', array( 1 ), null );
		$this->assertFalse( (bool) $has_overlap_against_inactive, 'Inactive overlapping block should be ignored in overlap checks' );
	}

	public function test_inactive_discount_rule_is_ignored() {
		$repo = new DiscountRuleRepository();

		// Create discount rule (active = 0)
		$rule_id = $repo->save(
			array(
				'name'           => 'Disabled 20% Discount',
				'discount_type'  => 'percentage',
				'discount_value' => 20,
				'priority'       => 10,
				'is_active'      => 0,
			)
		);
		$repo->sync_booking_objects( $rule_id, array( 1 ) );

		$found = $repo->find_applicable_rule( array( 1 ), 5.0, '2026-08-10' );
		$this->assertNull( $found, 'Inactive discount rule should not be returned by find_applicable_rule' );

		// Activate rule
		$repo->save( array( 'is_active' => 1 ), $rule_id );
		$found_active = $repo->find_applicable_rule( array( 1 ), 5.0, '2026-08-10' );
		$this->assertNotNull( $found_active, 'Active discount rule should be found' );
		$this->assertEquals( 'Disabled 20% Discount', $found_active->name );
	}

	public function test_toggle_status_ajax_handler() {
		// Set admin user
		$admin_id = wp_insert_user(
			array(
				'user_login' => 'admin_'       . uniqid(),
				'user_pass'  => 'password',
				'user_email' => 'admin_'       . uniqid() . '@example.com',
				'role'       => 'administrator',
			)
		);
		$user = get_user_by( 'id', $admin_id );
		$user->add_cap( \SnippenBooking\Helper\Capabilities::MANAGE_BOOKINGS );
		wp_set_current_user( $admin_id );

		global $wpdb;
		$table_slots = $wpdb->prefix . 'snippen_time_slots';

		// Insert time slot
		$wpdb->insert(
			$table_slots,
			array(
				'name'       => 'Test Toggle Slot',
				'start_time' => '08:00:00',
				'end_time'   => '10:00:00',
				'is_active'  => 1,
			)
		);
		$slot_id = $wpdb->insert_id;

		$_REQUEST['nonce']    = wp_create_nonce( 'snippen_admin_nonce' );
		$_POST['nonce']       = $_REQUEST['nonce'];
		$_POST['entity_type'] = 'time_slot';
		$_POST['id']          = $slot_id;
		$_POST['is_active']   = 0;

		add_filter( 'wp_die_ajax_handler', function() {
			return function( $message, $title, $args ) {
				throw new \WPAjaxDieContinueException( is_string( $message ) ? $message : wp_json_encode( $message ) );
			};
		} );

		try {
			ToggleStatusApi::handle_toggle();
		} catch ( \WPAjaxDieContinueException $e ) {
			// Expected WP Ajax exit call
		}

		$updated_is_active = (int) $wpdb->get_var( $wpdb->prepare( "SELECT is_active FROM $table_slots WHERE id = %d", $slot_id ) );
		$this->assertEquals( 0, $updated_is_active, 'Status should be toggled to 0 in DB' );
	}
}
