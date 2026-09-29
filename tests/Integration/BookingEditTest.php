<?php
/**
 * Integration Test for Booking Editing and Snapshot History Tracking (Issue #211)
 *
 * @package SnippenBooking\Tests\Integration
 */

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Database\Repository\BookingRepository;
use SnippenBooking\Database\Repository\BookingBlockRepository;
use SnippenBooking\Api\BookingEditApi;

/**
 * Class BookingEditTest
 */
class BookingEditTest extends TestCase {

	/**
	 * Setup AJAX environment.
	 */
	public function setUp(): void {
		parent::setUp();
		BookingEditApi::register();

		if ( ! defined( 'DOING_AJAX' ) ) {
			define( 'DOING_AJAX', true );
		}

		add_filter(
			'wp_die_ajax_handler',
			function() {
				return function( $message, $title, $args ) {
					throw new \Exception( is_string( $message ) ? $message : wp_json_encode( $message ) );
				};
			}
		);
	}

	/**
	 * Helper to get active objects.
	 */
	private function get_objects() {
		global $wpdb;
		return $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}snippen_booking_objects WHERE deleted_at IS NULL" );
	}

	/**
	 * Test that creating a booking automatically records revision 1 snapshot.
	 */
	public function test_booking_creation_records_initial_snapshot_revision_1() {
		$block_repo = new BookingBlockRepository();
		$blocks     = $block_repo->find_all();
		$objects    = $this->get_objects();

		$this->assertNotEmpty( $blocks );
		$this->assertNotEmpty( $objects );

		$booking_repo = new BookingRepository();
		$data         = array(
			'user_id'        => 1,
			'booking_date'   => '2026-11-10',
			'customer_name'  => 'Snapshot Test',
			'customer_email' => 'snap@test.com',
			'customer_phone' => '11223344',
			'price'          => 1500,
			'status'         => 'pending',
		);

		$booking_id = $booking_repo->create( $data, array( $objects[0]->id ), array( $blocks[0]->id ) );
		$this->assertNotFalse( $booking_id );

		$snapshots = $booking_repo->get_snapshots( $booking_id );
		$this->assertCount( 1, $snapshots );
		$this->assertEquals( 1, (int) $snapshots[0]->revision );
		$this->assertNotEmpty( $snapshots[0]->snapshot );
		$this->assertEquals( 'Opprinnelig booking', $snapshots[0]->changes_summary );

		$latest = $booking_repo->get_latest_snapshot( $booking_id );
		$this->assertNotNull( $latest );
		$this->assertEquals( 1, (int) $latest->revision );
	}

	/**
	 * Test that updating a booking increments revision and records changes summary and modifier.
	 */
	public function test_booking_update_increments_revision_and_tracks_changes() {
		$block_repo   = new BookingBlockRepository();
		$blocks       = $block_repo->find_all();
		$objects      = $this->get_objects();
		$booking_repo = new BookingRepository();

		$booking_id = $booking_repo->create(
			array(
				'user_id'        => 1,
				'booking_date'   => '2026-11-12',
				'customer_name'  => 'Før Endring',
				'customer_email' => 'forendring@test.com',
				'customer_phone' => '11223344',
				'price'          => 1000,
				'status'         => 'pending',
			),
			array( $objects[0]->id ),
			array( $blocks[0]->id )
		);

		$update_data = array(
			'customer_name' => 'Etter Endring',
			'price'         => 2000,
			'door_code'     => '9876',
			'description'   => 'Oppdatert beskrivelse',
		);

		$modifier_info = array(
			'modified_by_user_id' => 1,
			'changes_summary'     => 'Kunde oppgraderte og fikk tildelt dørkode',
		);

		$updated = $booking_repo->update( $booking_id, $update_data, null, null, $modifier_info );
		$this->assertFalse( is_wp_error( $updated ) );
		$this->assertEquals( 'Etter Endring', $updated->customer_name );
		$this->assertEquals( 2000, (float) $updated->price );
		$this->assertEquals( '9876', $updated->door_code );

		// Verify 2 revisions in snapshot history
		$snapshots = $booking_repo->get_snapshots( $booking_id );
		$this->assertCount( 2, $snapshots );
		$this->assertEquals( 1, (int) $snapshots[0]->revision );
		$this->assertEquals( 2, (int) $snapshots[1]->revision );
		$this->assertEquals( 'Kunde oppgraderte og fikk tildelt dørkode', $snapshots[1]->changes_summary );

		// Verify latest snapshot matches updated values
		$latest = $booking_repo->get_latest_snapshot( $booking_id );
		$this->assertNotNull( $latest );
		$this->assertEquals( 2, (int) $latest->revision );
		$this->assertEquals( 2000, (float) $latest->decoded_snapshot['price'] );
	}

	/**
	 * Test that updating booking blocks/dates detects conflict against other bookings,
	 * but allows updates that do not conflict with others.
	 */
	public function test_booking_update_detects_conflicts_and_allows_self_updates() {
		$block_repo   = new BookingBlockRepository();
		$blocks       = $block_repo->find_all();
		$objects      = $this->get_objects();
		$booking_repo = new BookingRepository();

		// Booking 1 on 2026-12-01 with block 0
		$booking_1 = $booking_repo->create(
			array(
				'user_id'        => 1,
				'booking_date'   => '2026-12-01',
				'customer_name'  => 'Booking En',
				'customer_email' => 'en@test.com',
				'status'         => 'confirmed',
			),
			array( $objects[0]->id ),
			array( $blocks[0]->id )
		);

		// Booking 2 on 2026-12-02 with block 0
		$booking_2 = $booking_repo->create(
			array(
				'user_id'        => 1,
				'booking_date'   => '2026-12-02',
				'customer_name'  => 'Booking To',
				'customer_email' => 'to@test.com',
				'status'         => 'pending',
			),
			array( $objects[0]->id ),
			array( $blocks[0]->id )
		);

		// Attempt to update Booking 2 to 2026-12-01 on object 0 and block 0 (occupied by Booking 1)
		$conflict_result = $booking_repo->update(
			$booking_2,
			array( 'booking_date' => '2026-12-01' ),
			array( $objects[0]->id ),
			array( $blocks[0]->id )
		);

		$this->assertTrue( is_wp_error( $conflict_result ) );
		$this->assertEquals( 'booking_conflict', $conflict_result->get_error_code() );

		// Update Booking 2 keeping its own date 2026-12-02 (must not conflict with itself)
		$self_update_result = $booking_repo->update(
			$booking_2,
			array( 'customer_name' => 'Booking To Endret' ),
			array( $objects[0]->id ),
			array( $blocks[0]->id )
		);

		$this->assertFalse( is_wp_error( $self_update_result ) );
		$this->assertEquals( 'Booking To Endret', $self_update_result->customer_name );
	}

	/**
	 * Test AJAX get_booking_edit_data rejects non-admins and accepts admins.
	 */
	public function test_ajax_get_booking_edit_data_permissions_and_response() {
		$block_repo   = new BookingBlockRepository();
		$blocks       = $block_repo->find_all();
		$objects      = $this->get_objects();
		$booking_repo = new BookingRepository();

		$booking_id = $booking_repo->create(
			array(
				'user_id'        => 1,
				'booking_date'   => '2026-11-20',
				'customer_name'  => 'AJAX Test',
				'customer_email' => 'ajax@test.com',
				'status'         => 'pending',
			),
			array( $objects[0]->id ),
			array( $blocks[0]->id )
		);

		// Non-admin user (subscriber)
		$sub_id = wp_insert_user(
			array(
				'user_login' => 'sub_tester_211',
				'user_pass'  => 'password',
				'role'       => 'subscriber',
			)
		);
		wp_set_current_user( $sub_id );

		$_POST['id']       = $booking_id;
		$_POST['nonce']    = wp_create_nonce( 'snippen_admin_nonce' );
		$_REQUEST['id']    = $booking_id;
		$_REQUEST['nonce'] = $_POST['nonce'];

		ob_start();
		try {
			BookingEditApi::get_edit_data();
		} catch ( \Throwable $e ) {
		}
		$sub_output = ob_get_clean();
		$sub_json   = json_decode( $sub_output, true );

		$this->assertFalse( $sub_json['success'] );
		$this->assertEquals( 'Ingen tilgang.', $sub_json['data']['message'] );

		// Admin user
		$admin_id = wp_insert_user(
			array(
				'user_login' => 'admin_tester_211',
				'user_pass'  => 'password',
				'role'       => 'administrator',
			)
		);
		$admin = get_user_by( 'id', $admin_id );
		$admin->add_cap( 'manage_snippen_bookings' );
		wp_set_current_user( $admin_id );

		$_POST['id']       = $booking_id;
		$_POST['nonce']    = wp_create_nonce( 'snippen_admin_nonce' );
		$_REQUEST['id']    = $booking_id;
		$_REQUEST['nonce'] = $_POST['nonce'];

		ob_start();
		try {
			BookingEditApi::get_edit_data();
		} catch ( \Throwable $e ) {
		}
		$admin_output = ob_get_clean();
		$admin_json   = json_decode( $admin_output, true );

		$this->assertTrue( $admin_json['success'] );
		$this->assertEquals( $booking_id, (int) $admin_json['data']['booking']['id'] );
		$this->assertNotEmpty( $admin_json['data']['objects'] );
		$this->assertNotEmpty( $admin_json['data']['blocks'] );
		$this->assertNotEmpty( $admin_json['data']['snapshots'] );
	}

	/**
	 * Test AJAX edit_booking successfully updates booking and logs snapshot revision.
	 */
	public function test_ajax_edit_booking_flow() {
		$block_repo   = new BookingBlockRepository();
		$blocks       = $block_repo->find_all();
		$objects      = $this->get_objects();
		$booking_repo = new BookingRepository();

		$booking_id = $booking_repo->create(
			array(
				'user_id'        => 1,
				'booking_date'   => '2026-11-25',
				'customer_name'  => 'Før AJAX Edit',
				'customer_email' => 'ajaxedit@test.com',
				'price'          => 500,
				'status'         => 'pending',
			),
			array( $objects[0]->id ),
			array( $blocks[0]->id )
		);

		$admin_id = wp_insert_user(
			array(
				'user_login' => 'admin_ajax_editor_211',
				'user_pass'  => 'password',
				'role'       => 'administrator',
			)
		);
		$admin = get_user_by( 'id', $admin_id );
		$admin->add_cap( 'manage_snippen_bookings' );
		wp_set_current_user( $admin_id );

		$_POST = array(
			'nonce'             => wp_create_nonce( 'snippen_admin_nonce' ),
			'booking_id'        => $booking_id,
			'booking_date'      => '2026-11-26',
			'customer_name'     => 'Etter AJAX Edit',
			'customer_email'    => 'ajaxedit_updated@test.com',
			'customer_phone'    => '44556677',
			'booking_type'      => 'open',
			'status'            => 'confirmed',
			'price'             => 1200,
			'discount_amount'   => 200,
			'payment_status_id' => 2, // PAID
			'door_code'         => '4321',
			'description'       => 'Ny beskrivelse via AJAX',
			'changes_summary'   => 'Admin flyttet dato og bekreftet booking',
			'object_ids'        => array( $objects[0]->id ),
			'block_ids'         => array( $blocks[0]->id ),
		);
		$_REQUEST = $_POST;

		ob_start();
		try {
			BookingEditApi::edit_booking();
		} catch ( \Throwable $e ) {
		}
		$output = ob_get_clean();
		$json   = json_decode( $output, true );

		$this->assertTrue( $json['success'] );

		// Re-fetch booking from DB
		$updated = $booking_repo->find( $booking_id );
		$this->assertEquals( '2026-11-26', $updated->booking_date );
		$this->assertEquals( 'Etter AJAX Edit', $updated->customer_name );
		$this->assertEquals( 'open', $updated->booking_type );
		$this->assertEquals( 'confirmed', $updated->status );
		$this->assertEquals( 1200, (float) $updated->price );
		$this->assertEquals( '4321', $updated->door_code );

		// Check snapshot history
		$snapshots = $booking_repo->get_snapshots( $booking_id );
		$this->assertCount( 2, $snapshots );
		$this->assertEquals( 2, (int) $snapshots[1]->revision );
		$this->assertEquals( 'Admin flyttet dato og bekreftet booking', $snapshots[1]->changes_summary );
		$this->assertEquals( $admin_id, (int) $snapshots[1]->modified_by_user_id );
	}
}
