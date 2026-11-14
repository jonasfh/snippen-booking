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
			'generated_at'         => '2026-11-07T13:15:10+00:00',
			'expired_window_days'  => 7,
			'locks'                => array(
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
			'reservations_in_db'   => array(
				array(
					'booking_id'            => '101',
					'pin'                   => '123456',
					'valid_from'            => '2026-11-07T15:00:00+00:00',
					'valid_to'              => '2026-11-09T11:00:00+00:00',
					'status'                => 'PROVISIONED',
					'all_locks_provisioned' => true,
					'physical_locks'        => array(
						array(
							'lock_name'    => 'Inngangsdør 1 (Hoveddør)',
							'lock_address' => '98:1B:B5:0B:BB:35',
							'slot'         => 3,
							'status'       => 'PROVISIONED',
						),
					),
				),
			),
			'recently_expired'     => array(),
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

	public function test_status_tab_multi_service_fleet_and_filtering() {
		$registry = array(
			'grendehus-node' => array(
				'service_id'  => 'grendehus-node',
				'name'        => 'Grendehuset (Hovedbygg)',
				'version'     => '0.19.0',
				'hostname'    => 'snippen-pi-grendehus',
				'ip_address'  => '192.168.1.120',
				'mac_address' => 'd8:3a:dd:12:34:56',
				'lock_ids'    => array( '1' ),
				'last_seen'   => gmdate( 'Y-m-d\TH:i:s\Z', time() - 100 ),
				'lock_count'  => 1,
				'min_battery' => 88,
			),
			'hybel-node'     => array(
				'service_id'  => 'hybel-node',
				'name'        => 'Utleieleilighet (Hybel)',
				'version'     => '0.19.1',
				'hostname'    => 'snippen-pi-hybel',
				'ip_address'  => '192.168.1.125',
				'mac_address' => 'd8:3a:dd:98:76:54',
				'lock_ids'    => array( '2' ),
				'last_seen'   => gmdate( 'Y-m-d\TH:i:s\Z', time() - 200 ),
				'lock_count'  => 1,
				'min_battery' => 95,
			),
		);
		update_option( 'snippen_door_services_registry', $registry );

		$status_grendehus = array(
			'service_id'           => 'grendehus-node',
			'generated_at'         => '2026-11-07T14:30:00Z',
			'expired_window_days'  => 7,
			'locks'                => array(
				array(
					'lock_id' => '1',
					'name'    => 'Grendehusdør',
					'address' => 'AA:BB:CC:DD:EE:01',
					'battery' => 88,
				),
			),
			'reservations_in_db'   => array(),
			'provisioned_on_locks' => array(),
			'recently_expired'     => array(),
		);
		update_option( 'snippen_door_status_grendehus-node', $status_grendehus );

		$status_hybel = array(
			'service_id'           => 'hybel-node',
			'generated_at'         => '2026-11-07T14:35:00Z',
			'expired_window_days'  => 7,
			'locks'                => array(
				array(
					'lock_id' => '2',
					'name'    => 'Hybeldør',
					'address' => 'AA:BB:CC:DD:EE:02',
					'battery' => 95,
				),
			),
			'reservations_in_db'   => array(),
			'provisioned_on_locks' => array(),
			'recently_expired'     => array(),
		);
		update_option( 'snippen_door_status_hybel-node', $status_hybel );

		$_GET['tab'] = 'status';

		// 1. All services (unified view)
		$page = new DoorCodesPage();
		ob_start();
		$page->render();
		$output_all = ob_get_clean();

		$this->assertStringContainsString( 'Tilknyttede Doorman-noder (Flåteoversikt)', $output_all );
		$this->assertStringContainsString( 'Grendehuset (Hovedbygg)', $output_all );
		$this->assertStringContainsString( 'Utleieleilighet (Hybel)', $output_all );
		$this->assertStringContainsString( 'http://192.168.1.120:8080', $output_all );
		$this->assertStringContainsString( 'http://192.168.1.125:8080', $output_all );
		$this->assertStringContainsString( 'Grendehusdør', $output_all );
		$this->assertStringContainsString( 'Hybeldør', $output_all );

		// 2. Filter by hybel-node
		$_GET['service_id'] = 'hybel-node';
		ob_start();
		$page->render();
		$output_filtered = ob_get_clean();

		$this->assertStringContainsString( 'Hybeldør', $output_filtered );
		$this->assertStringNotContainsString( 'Grendehusdør', $output_filtered );

		unset( $_GET['tab'], $_GET['service_id'] );
		delete_option( 'snippen_door_services_registry' );
		delete_option( 'snippen_door_status_grendehus-node' );
		delete_option( 'snippen_door_status_hybel-node' );
	}

	public function test_status_tab_offline_warning() {
		$registry = array(
			'dead-node' => array(
				'service_id'  => 'dead-node',
				'name'        => 'Fjernbygget Node',
				'last_seen'   => gmdate( 'Y-m-d\TH:i:s\Z', time() - 3600 ), // 1 hour ago (> 15m)
				'lock_ids'    => array( '9' ),
				'min_battery' => 10,
			),
		);
		update_option( 'snippen_door_services_registry', $registry );

		$_GET['tab'] = 'status';

		$page = new DoorCodesPage();
		ob_start();
		$page->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Advarsel: Én eller flere Doorman-tjenester er offline!', $output );
		$this->assertStringContainsString( 'Fjernbygget Node', $output );
		$this->assertStringContainsString( 'Offline (&gt; 15m)', $output );

		unset( $_GET['tab'] );
		delete_option( 'snippen_door_services_registry' );
	}

	/**
	 * Verify that all UTC ISO 8601 timestamps in the Status tab are converted to the WordPress site timezone (Issue #388).
	 */
	public function test_status_tab_formats_utc_timestamps_in_site_timezone() {
		$prev_tz = get_option( 'timezone_string' );
		update_option( 'timezone_string', 'Europe/Oslo' );

		$registry = array(
			'oslo-node' => array(
				'service_id'  => 'oslo-node',
				'name'        => 'Oslo Node',
				'last_seen'   => '2026-11-07T13:15:08+00:00', // 14:15:08 CET (UTC+1)
				'lock_ids'    => array( '1' ),
				'min_battery' => 90,
			),
		);
		update_option( 'snippen_door_services_registry', $registry );

		$mock_status = array(
			'generated_at'         => '2026-11-07T13:15:10+00:00', // 14:15:10 CET
			'locks'                => array(),
			'provisioned_on_locks' => array(
				array(
					'booking_id' => '101',
					'lock_id'    => '1',
					'lock_name'  => 'Hoveddør',
					'slot'       => 3,
					'pin'        => '123456',
					'valid_from' => '2026-11-07T15:00:00+00:00', // 16:00 CET
					'valid_to'   => '2026-11-09T11:00:00+00:00', // 12:00 CET
					'status'     => 'PROVISIONED',
				),
			),
			'reservations_in_db'   => array(
				array(
					'booking_id'            => '102',
					'pin'                   => '654321',
					'valid_from'            => '2026-11-07T16:00:00+00:00', // 17:00 CET
					'valid_to'              => '2026-11-09T12:00:00+00:00', // 13:00 CET
					'status'                => 'SCHEDULED',
					'all_locks_provisioned' => false,
					'physical_locks'        => array(),
				),
			),
			'recently_expired'     => array(
				array(
					'booking_id' => '100',
					'pin'        => '111222',
					'valid_from' => '2026-11-01T10:00:00+00:00', // 11:00 CET
					'valid_to'   => '2026-11-01T12:00:00+00:00', // 13:00 CET
				),
			),
		);
		update_option( 'snippen_door_status_oslo-node', $mock_status );

		$_GET['tab'] = 'status';

		try {
			$page = new DoorCodesPage();
			ob_start();
			$page->render();
			$output = ob_get_clean();

			// Fleet header: last_received_at (derived from last_seen 13:15:08 UTC) & generated_at (13:15:10 UTC)
			$this->assertStringContainsString( '07.11.2026 14:15:08', $output );
			$this->assertStringContainsString( '07.11.2026 14:15:10', $output );

			// Provisioned on locks
			$this->assertStringContainsString( '07.11.2026 16:00 – 09.11.2026 12:00', $output );

			// Reservations in DB
			$this->assertStringContainsString( '07.11.2026 17:00 – 09.11.2026 13:00', $output );

			// Recently expired
			$this->assertStringContainsString( '01.11.2026 11:00 – 01.11.2026 13:00', $output );

			// Ensure UTC times are NOT rendered
			$this->assertStringNotContainsString( '07.11.2026 13:15:08', $output );
			$this->assertStringNotContainsString( '07.11.2026 13:15:10', $output );
			$this->assertStringNotContainsString( '07.11.2026 15:00', $output );
		} finally {
			unset( $_GET['tab'] );
			delete_option( 'snippen_door_services_registry' );
			delete_option( 'snippen_door_status_oslo-node' );
			update_option( 'timezone_string', $prev_tz );
		}
	}

	/**
	 * Verify that DoorCodesPage detects when the access window changes (e.g. buffer/grace) after notification (Issue #392).
	 */
	public function test_notification_status_detects_access_window_change_when_buffer_changes() {
		global $wpdb;

		$prev_buffer = get_option( 'snippen_doorman_buffer_minutes_before', 30 );
		$prev_grace  = get_option( 'snippen_doorman_grace_minutes_after', 120 );
		update_option( 'snippen_doorman_buffer_minutes_before', 120 );
		update_option( 'snippen_doorman_grace_minutes_after', 180 );
		update_option( 'snippen_email_door_code_updated_enabled', 'yes' );
		update_option( 'snippen_sms_door_code_updated_enabled', 'no' );

		add_filter( 'pre_wp_mail', '__return_true' );

		$future_date = wp_date( 'Y-m-d', strtotime( '+3 days' ) );
		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'uuid'             => wp_generate_uuid4(),
				'customer_name'    => 'Test Bruker',
				'customer_email'   => 'test_window@example.com',
				'customer_phone'   => '+4790001234',
				'booking_date'     => $future_date,
				'status'           => 'confirmed',
				'door_code'        => '554433',
				'booking_snapshot' => wp_json_encode(
					array(
						'start_time'           => '12:00:00',
						'end_time'             => '16:00:00',
						'time_range_formatted' => '12:00 - 16:00',
					)
				),
			)
		);
		$booking_id = (int) $wpdb->insert_id;

		try {
			$manager = new \SnippenBooking\Service\Notification\NotificationManager();
			$sent    = $manager->send_door_code_updated_notification( $booking_id, '554433' );
			$this->assertTrue( $sent );

			$window = \SnippenBooking\Service\DoorCodeService::get_access_window( $booking_id );
			$status = DoorCodesPage::get_notification_status_for_booking( $booking_id, '554433', $window );
			$this->assertEquals( 'sent', $status['state'] );

			// Change buffer setting -> access window changes.
			update_option( 'snippen_doorman_buffer_minutes_before', 60 );
			$new_window = \SnippenBooking\Service\DoorCodeService::get_access_window( $booking_id );
			$new_status = DoorCodesPage::get_notification_status_for_booking( $booking_id, '554433', $new_window );

			$this->assertEquals( 'changed', $new_status['state'] );
			$this->assertEquals( 'time_changed', $new_status['reason'] );

			// Verify UI renders the warning badge and separate Lagre + Varsle buttons (no checkbox).
			$page = new DoorCodesPage();
			ob_start();
			$page->render();
			$output = ob_get_clean();

			$this->assertStringContainsString( 'Tid endret siden varsel', $output );
			$this->assertStringContainsString( 'btn-notify-row', $output );
			$this->assertStringNotContainsString( 'cb-notify-customer', $output );
		} finally {
			remove_filter( 'pre_wp_mail', '__return_true' );
			update_option( 'snippen_doorman_buffer_minutes_before', $prev_buffer );
			update_option( 'snippen_doorman_grace_minutes_after', $prev_grace );
		}
	}

	/**
	 * Verify that DoorCodesPage detects when the door code differs from what was last notified (Issue #392).
	 */
	public function test_notification_status_detects_code_change() {
		global $wpdb;

		update_option( 'snippen_email_door_code_updated_enabled', 'yes' );
		update_option( 'snippen_sms_door_code_updated_enabled', 'no' );
		add_filter( 'pre_wp_mail', '__return_true' );

		$future_date = wp_date( 'Y-m-d', strtotime( '+4 days' ) );
		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'uuid'             => wp_generate_uuid4(),
				'customer_name'    => 'Kode Endret',
				'customer_email'   => 'kode_endret@example.com',
				'booking_date'     => $future_date,
				'status'           => 'confirmed',
				'door_code'        => '112233',
				'booking_snapshot' => wp_json_encode(
					array(
						'start_time' => '10:00:00',
						'end_time'   => '14:00:00',
					)
				),
			)
		);
		$booking_id = (int) $wpdb->insert_id;

		try {
			$manager = new \SnippenBooking\Service\Notification\NotificationManager();
			$manager->send_door_code_updated_notification( $booking_id, '112233' );

			$window = \SnippenBooking\Service\DoorCodeService::get_access_window( $booking_id );
			$status = DoorCodesPage::get_notification_status_for_booking( $booking_id, '998877', $window );

			$this->assertEquals( 'changed', $status['state'] );
			$this->assertEquals( 'code_changed', $status['reason'] );
		} finally {
			remove_filter( 'pre_wp_mail', '__return_true' );
		}
	}

	/**
	 * Verify that BookingActionsApi::notify_door_code sends notification for existing door code (Issue #392).
	 */
	public function test_notify_door_code_ajax_endpoint_sends_notification() {
		global $wpdb;

		update_option( 'snippen_email_door_code_updated_enabled', 'yes' );
		update_option( 'snippen_sms_door_code_updated_enabled', 'no' );

		$sent_mails = array();
		$mail_cb    = function ( $status, $atts ) use ( &$sent_mails ) {
			$sent_mails[] = $atts;
			return true;
		};
		add_filter( 'pre_wp_mail', $mail_cb, 10, 2 );

		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'uuid'             => wp_generate_uuid4(),
				'customer_name'    => 'Varsle Knapp',
				'customer_email'   => 'varsle_knapp@example.com',
				'booking_date'     => wp_date( 'Y-m-d', strtotime( '+5 days' ) ),
				'status'           => 'confirmed',
				'door_code'        => '776655',
				'booking_snapshot' => wp_json_encode(
					array(
						'start_time' => '12:00:00',
						'end_time'   => '16:00:00',
					)
				),
			)
		);
		$booking_id = (int) $wpdb->insert_id;

		$admin_id = wp_create_user( 'door_admin_' . wp_rand( 1000, 9999 ), 'password123', 'door_admin_' . wp_rand( 1000, 9999 ) . '@example.com' );
		$user     = new \WP_User( $admin_id );
		$user->set_role( 'administrator' );
		$user->add_cap( \SnippenBooking\Helper\Capabilities::MANAGE_BOOKINGS );
		wp_set_current_user( $admin_id );

		$_POST['nonce']    = wp_create_nonce( 'snippen_admin_nonce' );
		$_REQUEST['nonce'] = $_POST['nonce'];
		$_POST['id']       = $booking_id;

		add_filter(
			'wp_die_ajax_handler',
			function () {
				return function ( $message ) {
					throw new \Exception( is_string( $message ) ? $message : (string) $message );
				};
			}
		);

		try {
			ob_start();
			try {
				\SnippenBooking\Api\BookingActionsApi::notify_door_code();
			} catch ( \Exception $e ) {
				// Expected wp_die from wp_send_json_success.
			}
			$json     = ob_get_clean();
			$response = json_decode( $json, true );

			$this->assertIsArray( $response );
			$this->assertTrue( $response['success'], 'notify_door_code failed: ' . $json );
			$this->assertNotEmpty( $sent_mails );
			$this->assertStringContainsString( '776655', $sent_mails[0]['message'] );
		} finally {
			remove_filter( 'pre_wp_mail', $mail_cb, 10 );
		}
	}
}
