<?php

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Api\BookingApi;
use SnippenBooking\Api\BookingActionsApi;
use SnippenBooking\Api\AvailabilityApi;
use SnippenBooking\Admin\Pages\BookingsPage;
use SnippenBooking\Admin\Pages\SettingsPage;
use SnippenBooking\Database\Repository\BookingBlockRepository;
use SnippenBooking\Database\Repository\BookingRepository;
use SnippenBooking\Service\AvailabilityService;

class BookingTypesTest extends TestCase {

	public function setUp(): void {
		parent::setUp();
		BookingApi::register();
		BookingActionsApi::register();

		if ( ! defined( 'DOING_AJAX' ) ) {
			define( 'DOING_AJAX', true );
		}

		add_filter(
			'wp_die_ajax_handler',
			function () {
				return function ( $message, $title, $args ) {
					throw new \Exception( is_string( $message ) ? $message : wp_json_encode( $message ) );
				};
			}
		);
	}

	/**
	 * Test that submitting an open booking saves it with 0 price, EXEMPT payment status, and pending status
	 */
	public function test_submit_open_booking_saves_correct_attributes() {
		$login   = 'resident_open_' . uniqid();
		$user_id = wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => 'password',
				'user_email' => $login . '@example.com',
				'role'       => 'subscriber',
			)
		);
		$this->assertIsInt( $user_id, 'Failed to create user: ' . ( is_wp_error( $user_id ) ? $user_id->get_error_message() : '' ) );
		update_user_meta( $user_id, 'snippen_phone', '99887766' );
		wp_set_current_user( $user_id );

		global $wpdb;
		$block_id  = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}snippen_booking_blocks LIMIT 1" );
		$object_id = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}snippen_booking_objects LIMIT 1" );

		$_POST['nonce']             = wp_create_nonce( 'snippen_booking_nonce' );
		$_POST['event_date']        = '2026-10-15';
		$_POST['booking_object_id'] = array( $object_id );
		$_POST['block_ids']         = array( $block_id );
		$_POST['name']              = 'Beboer Test';
		$_POST['email']             = 'resident@example.com';
		$_POST['description']       = 'Felles brettspillkveld';
		$_POST['booking_type']      = 'open';
		$_POST['accept_terms']      = '1';

		ob_start();
		try {
			BookingApi::submit_booking();
		} catch ( \Throwable $e ) {
			unset( $e );
		}
		$output = ob_get_clean();

		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}snippen_bookings WHERE user_id = %d AND booking_date = %s ORDER BY id DESC LIMIT 1",
				$user_id,
				'2026-10-15'
			)
		);

		$this->assertNotNull( $booking, 'Booking was not created. Output: ' . $output );
		$this->assertEquals( 'open', $booking->booking_type );
		$this->assertEquals( 0.0, (float) $booking->price );
		$this->assertEquals( 3, (int) $booking->payment_status_id ); // 3 = EXEMPT
		$this->assertEquals( 'pending', $booking->status );
	}

	/**
	 * Test that submitting a private booking calculates regular pricing
	 */
	public function test_submit_private_booking_calculates_price() {
		$login   = 'resident_priv_' . uniqid();
		$user_id = wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => 'password',
				'user_email' => $login . '@example.com',
				'role'       => 'subscriber',
			)
		);
		$this->assertIsInt( $user_id, 'Failed to create user: ' . ( is_wp_error( $user_id ) ? $user_id->get_error_message() : '' ) );
		update_user_meta( $user_id, 'snippen_phone', '99887766' );
		wp_set_current_user( $user_id );

		global $wpdb;
		$block_id  = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}snippen_booking_blocks LIMIT 1" );
		$object_id = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}snippen_booking_objects LIMIT 1" );

		$_POST['nonce']             = wp_create_nonce( 'snippen_booking_nonce' );
		$_POST['event_date']        = '2026-10-16';
		$_POST['booking_object_id'] = array( $object_id );
		$_POST['block_ids']         = array( $block_id );
		$_POST['name']              = 'Beboer Privat';
		$_POST['email']             = 'privat@example.com';
		$_POST['description']       = 'Privat bursdag';
		$_POST['booking_type']      = 'private';
		$_POST['accept_terms']      = '1';

		ob_start();
		try {
			BookingApi::submit_booking();
		} catch ( \Throwable $e ) {
			unset( $e );
		}
		$output = ob_get_clean();

		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}snippen_bookings WHERE user_id = %d AND booking_date = %s ORDER BY id DESC LIMIT 1",
				$user_id,
				'2026-10-16'
			)
		);

		$this->assertNotNull( $booking, 'Booking was not created. Output: ' . $output );
		$this->assertEquals( 'private', $booking->booking_type );
		$this->assertEquals( 1, (int) $booking->payment_status_id ); // 1 = UNPAID
		$this->assertEquals( 'pending', $booking->status );
	}

	/**
	 * Test admin rejection with a rejection_reason
	 */
	public function test_admin_can_reject_with_reason() {
		$login    = 'admin_rej_' . uniqid();
		$admin_id = wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => 'password',
				'user_email' => $login . '@example.com',
				'role'       => 'administrator',
			)
		);
		$this->assertIsInt( $admin_id, 'Failed to create admin: ' . ( is_wp_error( $admin_id ) ? $admin_id->get_error_message() : '' ) );
		$user = get_user_by( 'id', $admin_id );
		$user->add_cap( 'manage_snippen_bookings' );
		wp_set_current_user( $admin_id );

		global $wpdb;
		$table = $wpdb->prefix . 'snippen_bookings';
		$wpdb->insert(
			$table,
			array(
				'uuid'              => wp_generate_uuid4(),
				'user_id'           => 1,
				'booking_date'      => '2026-10-20',
				'customer_name'     => 'Open Applicant',
				'customer_email'    => 'open@example.com',
				'booking_type'      => 'open',
				'price'             => 0.0,
				'payment_status_id' => 3,
				'status'            => 'pending',
			)
		);
		$booking_id = $wpdb->insert_id;

		$_POST['id']               = $booking_id;
		$_POST['status']           = 'cancelled';
		$_POST['rejection_reason'] = 'Lokalet er reservert for sameiets generalforsamling på dette tidspunktet.';
		$_POST['nonce']            = wp_create_nonce( 'snippen_admin_nonce' );
		$_REQUEST['nonce']         = $_POST['nonce'];

		ob_start();
		try {
			BookingActionsApi::update_status();
		} catch ( \Throwable $e ) {
			unset( $e );
		}
		$output = ob_get_clean();

		$updated = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $booking_id ) );
		$this->assertEquals( 'cancelled', $updated->status );
		$this->assertEquals( 'Lokalet er reservert for sameiets generalforsamling på dette tidspunktet.', $updated->rejection_reason );
	}

	/**
	 * Test that include_cleaning creates a linked cleaning reservation for the next morning
	 */
	public function test_include_cleaning_creates_next_day_reservation() {
		$login   = 'resident_clean_' . uniqid();
		$user_id = wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => 'password',
				'user_email' => $login . '@example.com',
				'role'       => 'subscriber',
			)
		);
		$this->assertIsInt( $user_id, 'Failed to create user: ' . ( is_wp_error( $user_id ) ? $user_id->get_error_message() : '' ) );
		update_user_meta( $user_id, 'snippen_phone', '99887766' );
		wp_set_current_user( $user_id );

		global $wpdb;
		$block_repo = new BookingBlockRepository();
		$object_id  = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}snippen_booking_objects LIMIT 1" );

		// 1. Create an evening block with supports_cleaning = 1 on Saturday (day 6)
		$evening_block_id = $block_repo->save(
			array(
				'name'              => 'Sat Evening',
				'start_time'        => '16:00:00',
				'end_time'          => '23:00:00',
				'days_of_week'      => '6',
				'supports_cleaning' => 1,
				'sort_order'        => 100,
			)
		);
		$block_repo->sync_booking_objects( $evening_block_id, array( $object_id ) );

		// 2. Create a Sunday morning block ending <= 11:00 on Sunday (day 0/7)
		$morning_block_id = $block_repo->save(
			array(
				'name'              => 'Sun Morning',
				'start_time'        => '08:00:00',
				'end_time'          => '11:00:00',
				'days_of_week'      => '0,7',
				'supports_cleaning' => 0,
				'sort_order'        => 10,
			)
		);
		$block_repo->sync_booking_objects( $morning_block_id, array( $object_id ) );

		// 2026-10-17 is a Saturday, 2026-10-18 is Sunday
		$sat_date = '2026-10-17';
		$sun_date = '2026-10-18';

		$_POST['nonce']             = wp_create_nonce( 'snippen_booking_nonce' );
		$_POST['event_date']        = $sat_date;
		$_POST['booking_object_id'] = array( $object_id );
		$_POST['block_ids']         = array( $evening_block_id );
		$_POST['name']              = 'Helgefest';
		$_POST['email']             = 'party@example.com';
		$_POST['description']       = 'Fest på lørdag';
		$_POST['booking_type']      = 'private';
		$_POST['include_cleaning']  = '1';
		$_POST['accept_terms']      = '1';

		ob_start();
		try {
			BookingApi::submit_booking();
		} catch ( \Throwable $e ) {
			unset( $e );
		}
		$output = ob_get_clean();

		// Check Sunday cleaning booking
		$cleaning_booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}snippen_bookings WHERE user_id = %d AND booking_date = %s AND booking_type = 'cleaning' LIMIT 1",
				$user_id,
				$sun_date
			)
		);

		$this->assertNotNull( $cleaning_booking, 'Cleaning booking was not automatically created for Sunday. Output: ' . $output );
		$this->assertEquals( 'cleaning', $cleaning_booking->booking_type );
		$this->assertEquals( 0.0, (float) $cleaning_booking->price );
		$this->assertEquals( 3, (int) $cleaning_booking->payment_status_id );
	}

	/**
	 * Test BookingsPage render_type_badge output
	 */
	public function test_bookings_page_render_type_badge() {
		$page       = new BookingsPage();
		$reflection = new \ReflectionClass( BookingsPage::class );
		$method     = $reflection->getMethod( 'render_type_badge' );
		$method->setAccessible( true );

		$private_badge  = $method->invoke( $page, 'private' );
		$open_badge     = $method->invoke( $page, 'open' );
		$cleaning_badge = $method->invoke( $page, 'cleaning' );

		$this->assertStringContainsString( 'Privat', $private_badge );
		$this->assertStringContainsString( 'snippen-type-private', $private_badge );

		$this->assertStringContainsString( 'Åpen for sameiet', $open_badge );
		$this->assertStringContainsString( 'snippen-type-open', $open_badge );

		$this->assertStringContainsString( 'Utvask', $cleaning_badge );
		$this->assertStringContainsString( 'snippen-type-cleaning', $cleaning_badge );
	}

	/**
	 * Test BookingsPage render_list with booking_type filter
	 */
	public function test_bookings_page_filtering_by_booking_type() {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_bookings';

		// Insert one open booking, one private booking, one cleaning booking
		$wpdb->insert(
			$table,
			array(
				'uuid'              => wp_generate_uuid4(),
				'user_id'           => 1,
				'booking_date'      => '2026-11-01',
				'customer_name'     => 'Open Filter Customer',
				'customer_email'    => 'openfilter@example.com',
				'booking_type'      => 'open',
				'status'            => 'pending',
				'payment_status_id' => 3,
			)
		);
		$wpdb->insert(
			$table,
			array(
				'uuid'              => wp_generate_uuid4(),
				'user_id'           => 1,
				'booking_date'      => '2026-11-02',
				'customer_name'     => 'Private Filter Customer',
				'customer_email'    => 'privatefilter@example.com',
				'booking_type'      => 'private',
				'status'            => 'pending',
				'payment_status_id' => 1,
			)
		);
		$wpdb->insert(
			$table,
			array(
				'uuid'              => wp_generate_uuid4(),
				'user_id'           => 1,
				'booking_date'      => '2026-11-03',
				'customer_name'     => 'Cleaning Filter Customer',
				'customer_email'    => 'cleaningfilter@example.com',
				'booking_type'      => 'cleaning',
				'status'            => 'confirmed',
				'payment_status_id' => 3,
			)
		);

		$page       = new BookingsPage();
		$reflection = new \ReflectionClass( BookingsPage::class );
		$method     = $reflection->getMethod( 'render_list' );
		$method->setAccessible( true );

		// Filter: open
		ob_start();
		$method->invoke( $page, '', '', 0, '', 'booking_date', 'ASC', true, '', 'open' );
		$output_open = ob_get_clean();

		$this->assertStringContainsString( 'Open Filter Customer', $output_open );
		$this->assertStringNotContainsString( 'Private Filter Customer', $output_open );
		$this->assertStringNotContainsString( 'Cleaning Filter Customer', $output_open );

		// Filter: cleaning
		ob_start();
		$method->invoke( $page, '', '', 0, '', 'booking_date', 'ASC', true, '', 'cleaning' );
		$output_cleaning = ob_get_clean();

		$this->assertStringContainsString( 'Cleaning Filter Customer', $output_cleaning );
		$this->assertStringNotContainsString( 'Open Filter Customer', $output_cleaning );
		$this->assertStringNotContainsString( 'Private Filter Customer', $output_cleaning );

		// Filter: private
		ob_start();
		$method->invoke( $page, '', '', 0, '', 'booking_date', 'ASC', true, '', 'private' );
		$output_private = ob_get_clean();

		$this->assertStringContainsString( 'Private Filter Customer', $output_private );
		$this->assertStringNotContainsString( 'Open Filter Customer', $output_private );
		$this->assertStringNotContainsString( 'Cleaning Filter Customer', $output_private );
	}

	/**
	 * Test that configurable cleaning_end_time dynamically determines cleaning blocks and API response
	 */
	public function test_configurable_cleaning_end_time() {
		update_option( 'snippen_cleaning_end_time', '12:00' );

		global $wpdb;
		$block_repo = new BookingBlockRepository();
		$object_id  = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}snippen_booking_objects LIMIT 1" );

		// 1. Create Saturday evening block
		$sat_block_id = $block_repo->save(
			array(
				'name'              => 'Sat Eve Custom',
				'start_time'        => '16:00:00',
				'end_time'          => '23:00:00',
				'days_of_week'      => '6',
				'supports_cleaning' => 1,
				'sort_order'        => 100,
			)
		);
		$block_repo->sync_booking_objects( $sat_block_id, array( $object_id ) );

		// 2. Create Sunday 08:00-11:00 block
		$sun_block_1 = $block_repo->save(
			array(
				'name'              => 'Sun Morning Part 1',
				'start_time'        => '08:00:00',
				'end_time'          => '11:00:00',
				'days_of_week'      => '0,7',
				'supports_cleaning' => 0,
				'sort_order'        => 10,
			)
		);
		$block_repo->sync_booking_objects( $sun_block_1, array( $object_id ) );

		// 3. Create Sunday 11:00-12:00 block (should be included because cleaning_end_time is 12:00)
		$sun_block_2 = $block_repo->save(
			array(
				'name'              => 'Sun Morning Part 2',
				'start_time'        => '11:00:00',
				'end_time'          => '12:00:00',
				'days_of_week'      => '0,7',
				'supports_cleaning' => 0,
				'sort_order'        => 20,
			)
		);
		$block_repo->sync_booking_objects( $sun_block_2, array( $object_id ) );

		// 4. Create Sunday 12:00-16:00 block (should NOT be included)
		$sun_block_3 = $block_repo->save(
			array(
				'name'              => 'Sun Afternoon',
				'start_time'        => '12:00:00',
				'end_time'          => '16:00:00',
				'days_of_week'      => '0,7',
				'supports_cleaning' => 0,
				'sort_order'        => 30,
			)
		);
		$block_repo->sync_booking_objects( $sun_block_3, array( $object_id ) );

		$sat_date = '2026-11-21'; // Saturday
		$sun_date = '2026-11-22'; // Sunday

		$availability_service = new AvailabilityService();
		$cleaning_block_ids   = $availability_service->getCleaningBlockIds( $sun_date );

		$this->assertContains( (int) $sun_block_1, $cleaning_block_ids );
		$this->assertContains( (int) $sun_block_2, $cleaning_block_ids );
		$this->assertNotContains( (int) $sun_block_3, $cleaning_block_ids );

		// Check AvailabilityApi returns cleaning_end_time = '12:00'
		$_GET['nonce']               = wp_create_nonce( 'snippen_booking_nonce' );
		$_GET['event_date']          = $sat_date;
		$_GET['object_id']           = array( $object_id );
		$_GET['selected_object_ids'] = array( $object_id );
		$_GET['block_ids']           = array( $sat_block_id );

		ob_start();
		try {
			AvailabilityApi::get_objects_availability();
		} catch ( \Throwable $e ) {
			unset( $e );
		}
		$avail_out  = ob_get_clean();
		$avail_data = json_decode( $avail_out, true );

		$this->assertTrue( $avail_data['success'] );
		$this->assertTrue( $avail_data['data']['supports_cleaning'] );
		$this->assertTrue( $avail_data['data']['cleaning_available'] );
		$this->assertEquals( '12:00', $avail_data['data']['cleaning_end_time'] );

		// Submit booking with include_cleaning
		$login   = 'resident_12clean_' . uniqid();
		$user_id = wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => 'password',
				'user_email' => $login . '@example.com',
				'role'       => 'subscriber',
			)
		);
		update_user_meta( $user_id, 'snippen_phone', '99887766' );
		wp_set_current_user( $user_id );

		$_POST['nonce']             = wp_create_nonce( 'snippen_booking_nonce' );
		$_POST['event_date']        = $sat_date;
		$_POST['booking_object_id'] = array( $object_id );
		$_POST['block_ids']         = array( $sat_block_id );
		$_POST['name']              = 'Helgefest 12';
		$_POST['email']             = 'party12@example.com';
		$_POST['description']       = 'Fest på lørdag med 12 utvask';
		$_POST['booking_type']      = 'private';
		$_POST['include_cleaning']  = '1';
		$_POST['accept_terms']      = '1';

		ob_start();
		try {
			BookingApi::submit_booking();
		} catch ( \Throwable $e ) {
			unset( $e );
		}
		ob_get_clean();

		$cleaning_booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}snippen_bookings WHERE user_id = %d AND booking_date = %s AND booking_type = 'cleaning' LIMIT 1",
				$user_id,
				$sun_date
			)
		);

		$this->assertNotNull( $cleaning_booking );
		$attached_blocks = array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare(
					"SELECT booking_block_id FROM {$wpdb->prefix}snippen_booking_booking_blocks WHERE booking_id = %d",
					(int) $cleaning_booking->id
				)
			)
		);
		$this->assertContains( (int) $sun_block_1, $attached_blocks );
		$this->assertContains( (int) $sun_block_2, $attached_blocks );
		$this->assertNotContains( (int) $sun_block_3, $attached_blocks );
	}

	/**
	 * Test SettingsPage handles saving snippen_cleaning_end_time
	 */
	public function test_settings_page_saves_cleaning_end_time() {
		$settings_page = new SettingsPage();
		$reflection    = new \ReflectionClass( SettingsPage::class );
		$method        = $reflection->getMethod( 'handle_request' );
		$method->setAccessible( true );

		$_POST['snippen_settings_nonce']     = wp_create_nonce( 'snippen_save_settings' );
		$_POST['snippen_cleaning_end_time'] = '10:30';

		ob_start();
		$method->invoke( $settings_page );
		ob_get_clean();

		$this->assertEquals( '10:30', get_option( 'snippen_cleaning_end_time' ) );

		// Invalid format falls back to default 11:00
		$_POST['snippen_cleaning_end_time'] = 'invalid-time';
		ob_start();
		$method->invoke( $settings_page );
		ob_get_clean();

		$this->assertEquals( '11:00', get_option( 'snippen_cleaning_end_time' ) );
	}
}
