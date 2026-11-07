<?php

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Admin\Pages\DoorCodesPage;

class DoorCodesPageTest extends TestCase {

	public function setUp(): void {
		parent::setUp();

		$admin_id = wp_insert_user(
			array(
				'user_login' => 'doorcodes_admin_test',
				'user_pass'  => 'password',
				'role'       => 'administrator',
			)
		);
		$user_obj = get_userdata( $admin_id );
		$user_obj->add_cap( 'manage_snippen_bookings' );
		wp_set_current_user( $admin_id );

		update_option( 'snippen_enable_door_code', 'yes' );
	}

	public function test_door_codes_page_renders_empty_state() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}snippen_bookings" );

		$page = new DoorCodesPage();

		ob_start();
		$page->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Dørkoder &amp; Adgangskontroll', $output );
		$this->assertStringContainsString( 'Ingen kommende reservasjoner funnet for valgt filter.', $output );
	}

	public function test_door_codes_page_renders_bookings_and_inputs() {
		global $wpdb;

		$booking_date = date( 'Y-m-d', strtotime( '+1 day' ) );

		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'user_id'          => 1,
				'booking_date'     => $booking_date,
				'customer_name'    => 'Kari Nordmann',
				'customer_email'   => 'kari@example.com',
				'status'           => 'confirmed',
				'door_code'        => '1234',
				'booking_snapshot' => wp_json_encode(
					array(
						'start_time' => '10:00:00',
						'end_time'   => '14:00:00',
					)
				),
			)
		);

		$page = new DoorCodesPage();

		ob_start();
		$page->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Kari Nordmann', $output );
		$this->assertStringContainsString( 'value="1234"', $output );
		$this->assertStringContainsString( 'Lagre alle endrede koder', $output );
		$this->assertStringContainsString( 'Varsle', $output );
		$this->assertStringContainsString( 'Administrer dørkoder', $output );
		$this->assertStringContainsString( 'Status i dørene &amp; Doorman', $output );
	}

	public function test_door_codes_page_renders_status_tab_with_locks_and_pins() {
		// Set mock status snapshot from Doorman
		$mock_status = array(
			'generated_at'        => '2026-11-07T13:15:10+00:00',
			'expired_window_days' => 7,
			'locks'               => array(
				array(
					'name'        => 'Inngangsdør 1 (Hoveddør)',
					'address'     => '98:1B:B5:0B:BB:35',
					'lock_id'     => '1',
					'battery'     => 85,
					'lock_status' => 'LOCKED',
					'door_status' => 'CLOSED',
					'last_seen'   => '2026-11-07T13:15:08+00:00',
					'slots'       => array(
						'3' => '101',
						'4' => null,
						'5' => null,
						'6' => null,
						'7' => null,
						'8' => null,
						'9' => null,
					),
				),
			),
			'provisioned_on_locks' => array(
				array(
					'booking_id'   => '101',
					'lock_id'      => '1',
					'lock_name'    => 'Inngangsdør 1 (Hoveddør)',
					'lock_address' => '98:1B:B5:0B:BB:35',
					'slot'         => 3,
					'pin'          => '123456',
					'valid_from'   => '2026-11-07T15:00:00+00:00',
					'valid_to'     => '2026-11-09T11:00:00+00:00',
					'status'       => 'PROVISIONED',
				),
			),
			'reservations_in_db' => array(
				array(
					'booking_id'             => '101',
					'pin'                    => '123456',
					'valid_from'             => '2026-11-07T15:00:00+00:00',
					'valid_to'               => '2026-11-09T11:00:00+00:00',
					'status'                 => 'PROVISIONED',
					'all_locks_provisioned'  => true,
					'physical_locks'         => array(
						array(
							'lock_name'    => 'Inngangsdør 1 (Hoveddør)',
							'lock_address' => '98:1B:B5:0B:BB:35',
							'slot'         => 3,
							'status'       => 'PROVISIONED',
						),
					),
				),
			),
			'recently_expired'   => array(),
		);

		update_option( 'snippen_doorman_last_status', $mock_status );
		update_option( 'snippen_doorman_last_received_at', '2026-11-07T13:15:11Z' );

		$_GET['tab'] = 'status';

		$page = new DoorCodesPage();

		ob_start();
		$page->render();
		$output = ob_get_clean();

		unset( $_GET['tab'] );

		$this->assertStringContainsString( 'Yale Doorman-tjeneste tilkoblet', $output );
		$this->assertStringContainsString( 'Inngangsdør 1 (Hoveddør)', $output );
		$this->assertStringContainsString( '98:1B:B5:0B:BB:35', $output );
		$this->assertStringContainsString( '85%', $output );
		$this->assertStringContainsString( '123456', $output );
		$this->assertStringContainsString( 'PROVISIONED', $output );
		$this->assertStringContainsString( 'I alle dører', $output );
	}

	public function test_door_codes_page_renders_doorman_badges_in_manage_tab() {
		$mock_status = array(
			'reservations_in_db' => array(
				array(
					'booking_id'     => '201',
					'pin'            => '123456',
					'physical_locks' => array(
						array(
							'lock_name' => 'Inngangsdør 1',
							'slot'      => 3,
							'status'    => 'PROVISIONED',
						),
						array(
							'lock_name' => 'Inngangsdør 2',
							'slot'      => 3,
							'status'    => 'PROVISIONED',
						),
					),
				),
			),
		);

		$badges = DoorCodesPage::get_doorman_badges_for_chain( array( 201 ), '123456', $mock_status );

		$this->assertCount( 2, $badges );
		$this->assertStringContainsString( 'Inngangsdør 1: I lås (Spor 3)', $badges[0] );
		$this->assertStringContainsString( 'Inngangsdør 2: I lås (Spor 3)', $badges[1] );

		// Test code mismatch badge
		$mismatch_badges = DoorCodesPage::get_doorman_badges_for_chain( array( 201 ), '999999', $mock_status );
		$this->assertCount( 2, $mismatch_badges );
		$this->assertStringContainsString( 'Kode-avvik!', $mismatch_badges[0] );
	}
}
