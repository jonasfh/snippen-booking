<?php

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Admin\AdminBar;
use SnippenBooking\Database\Repository\BookingRepository;
use SnippenBooking\Helper\Capabilities;
use SnippenBooking\Tests\TestCase;

class AdminBarTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		if ( ! class_exists( '\WP_Admin_Bar' ) ) {
			require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';
		}
		AdminBar::delete_count_cache();
	}

	protected function tearDown(): void {
		AdminBar::delete_count_cache();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	public function test_admin_bar_node_not_added_for_unauthenticated_or_unauthorized_users() {
		// 1. Logged out user
		wp_set_current_user( 0 );
		$admin_bar = new \WP_Admin_Bar();
		AdminBar::add_admin_bar_node( $admin_bar );
		$this->assertNull( $admin_bar->get_node( AdminBar::NODE_ID ) );

		// 2. Regular subscriber without manage_snippen_bookings or manage_options
		$subscriber_id = wp_insert_user(
			array(
				'user_login' => 'sub_user_' . wp_generate_password( 6, false ),
				'user_pass'  => 'password',
				'role'       => 'subscriber',
			)
		);
		wp_set_current_user( $subscriber_id );
		$admin_bar = new \WP_Admin_Bar();
		AdminBar::add_admin_bar_node( $admin_bar );
		$this->assertNull( $admin_bar->get_node( AdminBar::NODE_ID ) );
	}

	public function test_admin_bar_node_added_for_administrator_without_badge_when_no_pending_bookings() {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_bookings" );

		$admin_id = wp_insert_user(
			array(
				'user_login' => 'admin_user_' . wp_generate_password( 6, false ),
				'user_pass'  => 'password',
				'role'       => 'administrator',
			)
		);
		wp_set_current_user( $admin_id );

		$admin_bar = new \WP_Admin_Bar();
		AdminBar::add_admin_bar_node( $admin_bar );

		$node = $admin_bar->get_node( AdminBar::NODE_ID );
		$this->assertNotNull( $node );
		$this->assertSame( AdminBar::NODE_ID, $node->id );
		$this->assertStringContainsString( '📅', $node->title );
		$this->assertStringContainsString( 'Reservasjoner', $node->title );
		$this->assertStringNotContainsString( 'update-plugins', $node->title );
		$this->assertStringContainsString( 'page=snippen-booking', $node->href );
		$this->assertSame( 'Gå direkte til reservasjonslisten', $node->meta['title'] );
	}

	public function test_admin_bar_node_added_for_user_with_manage_snippen_bookings_capability() {
		$custom_user_id = wp_insert_user(
			array(
				'user_login' => 'booking_manager_' . wp_generate_password( 6, false ),
				'user_pass'  => 'password',
				'role'       => 'subscriber',
			)
		);
		$user           = get_user_by( 'id', $custom_user_id );
		$user->add_cap( Capabilities::MANAGE_BOOKINGS );
		wp_set_current_user( $custom_user_id );

		$admin_bar = new \WP_Admin_Bar();
		AdminBar::add_admin_bar_node( $admin_bar );

		$node = $admin_bar->get_node( AdminBar::NODE_ID );
		$this->assertNotNull( $node );
		$this->assertSame( AdminBar::NODE_ID, $node->id );
	}

	public function test_admin_bar_node_displays_badge_when_pending_bookings_exist() {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_bookings" );

		$admin_id = wp_insert_user(
			array(
				'user_login' => 'admin_user2_' . wp_generate_password( 6, false ),
				'user_pass'  => 'password',
				'role'       => 'administrator',
			)
		);
		wp_set_current_user( $admin_id );

		// Insert 2 pending bookings and 1 confirmed booking
		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'uuid'         => wp_generate_uuid4(),
				'user_id'      => $admin_id,
				'booking_date' => gmdate( 'Y-m-d' ),
				'status'       => 'pending',
				'created_at'   => current_time( 'mysql' ),
				'modified_at'  => current_time( 'mysql' ),
			)
		);
		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'uuid'         => wp_generate_uuid4(),
				'user_id'      => $admin_id,
				'booking_date' => gmdate( 'Y-m-d', strtotime( '+1 day' ) ),
				'status'       => 'pending',
				'created_at'   => current_time( 'mysql' ),
				'modified_at'  => current_time( 'mysql' ),
			)
		);
		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'uuid'         => wp_generate_uuid4(),
				'user_id'      => $admin_id,
				'booking_date' => gmdate( 'Y-m-d', strtotime( '+2 days' ) ),
				'status'       => 'confirmed',
				'created_at'   => current_time( 'mysql' ),
				'modified_at'  => current_time( 'mysql' ),
			)
		);

		AdminBar::delete_count_cache();

		$admin_bar = new \WP_Admin_Bar();
		AdminBar::add_admin_bar_node( $admin_bar );

		$node = $admin_bar->get_node( AdminBar::NODE_ID );
		$this->assertNotNull( $node );
		$this->assertStringContainsString( 'update-plugins count-2', $node->title );
		$this->assertStringContainsString( '<span class="plugin-count">2</span>', $node->title );
	}

	public function test_transient_caching_and_invalidation() {
		// Pre-populate transient cache
		set_transient( AdminBar::TRANSIENT_KEY, 7, 300 );
		$this->assertSame( 7, AdminBar::get_pending_count() );

		// Invalidate cache
		AdminBar::delete_count_cache();
		$this->assertFalse( get_transient( AdminBar::TRANSIENT_KEY ) );

		// Creating a booking through BookingRepository invalidates transient
		set_transient( AdminBar::TRANSIENT_KEY, 4, 300 );
		$repo       = new BookingRepository();
		$booking_id = $repo->create(
			array(
				'booking_date' => gmdate( 'Y-m-d' ),
				'status'       => 'pending',
				'user_id'      => 1,
			),
			array(),
			array()
		);
		$this->assertNotEmpty( $booking_id );
		$this->assertFalse( get_transient( AdminBar::TRANSIENT_KEY ) );

		// Updating booking through repository also invalidates cache
		set_transient( AdminBar::TRANSIENT_KEY, 9, 300 );
		$repo->update(
			$booking_id,
			array( 'status' => 'confirmed' )
		);
		$this->assertFalse( get_transient( AdminBar::TRANSIENT_KEY ) );

		// Updating booking status through BookingActionsApi also invalidates cache
		set_transient( AdminBar::TRANSIENT_KEY, 5, 300 );
		$admin_id   = wp_insert_user(
			array(
				'user_login' => 'admin_action_' . wp_generate_password( 6, false ),
				'user_pass'  => 'password',
				'role'       => 'administrator',
			)
		);
		$admin_user = get_user_by( 'id', $admin_id );
		$admin_user->add_cap( Capabilities::MANAGE_BOOKINGS );
		wp_set_current_user( $admin_id );
		$_POST['id']       = $booking_id;
		$_POST['status']   = 'cancelled';
		$_POST['nonce']    = wp_create_nonce( 'snippen_admin_nonce' );
		$_REQUEST['nonce'] = $_POST['nonce'];

		$die_handler = function () {
			return function () {
				throw new \Exception( 'ajax_exit' );
			};
		};
		add_filter( 'wp_die_ajax_handler', $die_handler );

		// Suppress json response exit with output buffer
		ob_start();
		try {
			\SnippenBooking\Api\BookingActionsApi::update_status();
		} catch ( \Throwable $e ) {
			// Handled exception from wp_die_ajax_handler.
			unset( $e );
		}
		ob_end_clean();
		remove_filter( 'wp_die_ajax_handler', $die_handler );

		$this->assertFalse( get_transient( AdminBar::TRANSIENT_KEY ) );
		$_POST    = array();
		$_REQUEST = array();
	}

	public function test_legacy_booking_oversikt_redirects_to_snippen_booking() {
		$_GET['page'] = 'booking-oversikt';
		$redirect_url = null;

		add_filter(
			'wp_redirect',
			function ( $location ) use ( &$redirect_url ) {
				$redirect_url = $location;
				return false; // prevent actual header sending in test
			}
		);

		AdminBar::maybe_redirect_legacy_slug();

		$this->assertNotNull( $redirect_url );
		$this->assertStringContainsString( 'page=snippen-booking', $redirect_url );

		unset( $_GET['page'] );
	}
}
