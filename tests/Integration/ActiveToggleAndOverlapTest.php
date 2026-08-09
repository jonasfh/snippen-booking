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
		$table_slots = $wpdb->prefix . 'snippen_booking_blocks';

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

	public function test_weekly_preview_overlap_detection() {
		$page = new \SnippenBooking\Admin\Pages\BookingBlocksPage();
		$reflection = new \ReflectionClass( $page );
		$method = $reflection->getMethod( 'render_weekly_preview' );
		$method->setAccessible( true );

		$block_morning = (object) array(
			'id'           => 1,
			'name'         => 'Morgen 08-11',
			'start_time'   => '08:00:00',
			'end_time'     => '11:00:00',
			'days_of_week' => '6,0,7',
			'is_active'    => 1,
		);

		$block_day = (object) array(
			'id'           => 2,
			'name'         => 'Dag 08-16',
			'start_time'   => '08:00:00',
			'end_time'     => '16:00:00',
			'days_of_week' => '6,0,7',
			'is_active'    => 1,
		);

		$block_evening = (object) array(
			'id'           => 3,
			'name'         => 'Kveld 16-23',
			'start_time'   => '16:00:00',
			'end_time'     => '23:00:00',
			'days_of_week' => '6,0,7',
			'is_active'    => 1,
		);

		global $wpdb;
		$table_junction = $wpdb->prefix . 'snippen_booking_object_booking_blocks';
		$wpdb->insert( $table_junction, array( 'booking_object_id' => 1, 'booking_block_id' => 1 ) );
		$wpdb->insert( $table_junction, array( 'booking_object_id' => 1, 'booking_block_id' => 2 ) );
		$wpdb->insert( $table_junction, array( 'booking_object_id' => 1, 'booking_block_id' => 3 ) );

		ob_start();
		$method->invoke( $page, array( $block_morning, $block_day, $block_evening ) );
		$output = ob_get_clean();

		// Morgen (08-11) and Dag (08-16) overlap -> Morgen must have Overlapp tag
		$this->assertStringContainsString( 'Morgen 08-11</strong> <span class="snippen-badge snippen-status-pending"', $output );

		// Dag (08-16) and Morgen (08-11) overlap -> Dag must have Overlapp tag
		$this->assertStringContainsString( 'Dag 08-16</strong> <span class="snippen-badge snippen-status-pending"', $output );

		// Kveld (16-23) starts at 16:00, Dag ends at 16:00 (adjacent, not overlapping) -> Kveld must NOT have Overlapp tag
		$this->assertStringNotContainsString( 'Kveld 16-23</strong> <span class="snippen-badge snippen-status-pending"', $output );
	}
}
