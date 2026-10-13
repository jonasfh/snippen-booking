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
	}
}
