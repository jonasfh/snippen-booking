<?php
/**
 * Booking Assistant Manual Dispatch Integration Tests
 *
 * @package SnippenBooking\Tests\Integration
 */

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Api\BookingActionsApi;
use SnippenBooking\Helper\Capabilities;

class BookingAssistantTest extends TestCase {

	private static $sent_mails = array();

	public function setUp(): void {
		parent::setUp();
		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}
		self::$sent_mails = array();
		add_filter( 'pre_wp_mail', array( $this, 'catch_mail' ), 10, 2 );
		BookingActionsApi::register();

		if ( ! defined( 'DOING_AJAX' ) ) {
			define( 'DOING_AJAX', true );
		}

		add_filter( 'wp_die_ajax_handler', function() {
			return function( $message, $title, $args ) {
				throw new \Exception( is_string( $message ) ? $message : wp_json_encode( $message ) );
			};
		} );
	}

	public function tearDown(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'catch_mail' ), 10 );
		parent::tearDown();
	}

	public function catch_mail( $send_status, $atts ) {
		self::$sent_mails[] = $atts;
		return true;
	}

	/**
	 * Helper to setup mock POST and trigger update/dispatch
	 */
	private function trigger_dispatch( $booking_id, $channel ) {
		$_POST['nonce']    = wp_create_nonce( 'snippen_admin_nonce' );
		$_REQUEST['nonce'] = $_POST['nonce'];
		$_POST['id']       = $booking_id;
		$_POST['channel']  = $channel;

		ob_start();
		try {
			BookingActionsApi::dispatch_notification_manually();
		} catch ( \Throwable $e ) {
			// Catch AJAX exit
		}
		$output = ob_get_clean();

		$output = trim( $output );
		if ( preg_match( '/(\{.*\})/', $output, $matches ) ) {
			$output = $matches[1];
		}

		return json_decode( $output, true );
	}

	public function test_dispatch_requires_authentication() {
		// Set current user as subscriber (no manage capability)
		$user_id = wp_insert_user( array(
			'user_login' => 'sub_test_' . uniqid(),
			'user_pass'  => 'password123',
			'role'       => 'subscriber',
		) );
		wp_set_current_user( $user_id );

		$response = $this->trigger_dispatch( 123, 'email_customer' );
		$this->assertIsArray( $response );
		$this->assertFalse( $response['success'] );
		$this->assertEquals( 'Ingen tilgang.', $response['data']['message'] );

		wp_delete_user( $user_id );
	}

	public function test_dispatch_admin_email_success() {
		global $wpdb;

		// Make current user booking manager
		$user_id = wp_insert_user( array(
			'user_login' => 'adm_test_' . uniqid(),
			'user_pass'  => 'password123',
			'role'       => 'administrator',
		) );
		$user = get_userdata( $user_id );
		$user->add_cap( Capabilities::MANAGE_BOOKINGS );
		wp_set_current_user( $user_id );

		// Create a mock booking
		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'uuid'           => wp_generate_uuid4(),
				'booking_date'   => '2026-06-20',
				'user_id'        => $user_id,
				'slot_id'        => 1,
				'customer_name'  => 'Test Customer',
				'customer_email' => 'customer@example.com',
				'customer_phone' => '+4790000000',
				'status'         => 'confirmed',
				'created_at'     => current_time( 'mysql' ),
			)
		);
		$booking_id = $wpdb->insert_id;

		$response = $this->trigger_dispatch( $booking_id, 'email_admin' );
		$this->assertIsArray( $response );
		$this->assertTrue( $response['success'] );
		$this->assertEquals( 'Varsel sendt til administrator(er).', $response['data']['message'] );

		// Verify intercepted mail
		$this->assertCount( 1, self::$sent_mails );
		$mail = self::$sent_mails[0];
		$this->assertEquals( 'admin@example.com', $mail['to'] );
		$this->assertStringContainsString( 'Test Customer', $mail['message'] );

		wp_delete_user( $user_id );
	}

	public function test_dispatch_customer_email_success() {
		global $wpdb;

		// Make current user booking manager
		$user_id = wp_insert_user( array(
			'user_login' => 'adm_test_' . uniqid(),
			'user_pass'  => 'password123',
			'role'       => 'administrator',
		) );
		$user = get_userdata( $user_id );
		$user->add_cap( Capabilities::MANAGE_BOOKINGS );
		wp_set_current_user( $user_id );

		// Create a mock booking
		$wpdb->insert(
			$wpdb->prefix . 'snippen_bookings',
			array(
				'uuid'           => wp_generate_uuid4(),
				'booking_date'   => '2026-06-20',
				'user_id'        => $user_id,
				'slot_id'        => 1,
				'customer_name'  => 'Test Customer',
				'customer_email' => 'customer@example.com',
				'customer_phone' => '+4790000000',
				'status'         => 'confirmed',
				'created_at'     => current_time( 'mysql' ),
			)
		);
		$booking_id = $wpdb->insert_id;

		$response = $this->trigger_dispatch( $booking_id, 'email_customer' );
		$this->assertIsArray( $response );
		$this->assertTrue( $response['success'] );
		$this->assertEquals( 'Bekreftelses-e-post sendt til kunden.', $response['data']['message'] );

		// Verify intercepted mail
		$this->assertCount( 1, self::$sent_mails );
		$mail = self::$sent_mails[0];
		$this->assertEquals( 'customer@example.com', $mail['to'] );
		$this->assertStringContainsString( 'Bekreftelse på din bookingforespørsel', $mail['subject'] );

		wp_delete_user( $user_id );
	}
}
