<?php
/**
 * Set up SMS Gateway demo settings, token, and seed recognizable test booking data.
 *
 * @package SnippenBooking
 */

require_once __DIR__ . '/env-loader.php';
load_env( __DIR__ . '/../.env' );

// Bootstrap WordPress
$abspath = getenv( 'WP_ABSPATH' ) ?: '/wordpress/';
if ( ! file_exists( $abspath . 'wp-load.php' ) ) {
	echo "Error: WordPress not found at $abspath\n";
	exit( 1 );
}

$_SERVER['HTTP_HOST']      = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once $abspath . 'wp-load.php';

echo "Setting up SMS Gateway demo configuration...\n";

// Ensure database tables & migrations are up to date
if ( class_exists( '\SnippenBooking\Database\MigrationManager' ) ) {
	\SnippenBooking\Database\MigrationManager::run();
}

// 1. Configure SMS Gateway & Provider settings
$api_token   = getenv( 'SNIPPEN_SMS_API_TOKEN' ) ?: 'test-integration-token';
$sender_name = getenv( 'SNIPPEN_SMS_SENDER' ) ?: ( getenv( 'SMS_SENDER' ) ?: 'Snippen' );

update_option( 'snippen_sms_service_api_token', $api_token );
update_option( 'snippen_sms_service_sender', $sender_name );
update_option( 'snippen_sms_provider', 'snippen_sms_service' );
update_option( 'snippen_active_notification_provider', 'snippen_sms_service' );

update_option( 'snippen_sms_booking_confirmation_enabled', 'yes' );
update_option( 'snippen_sms_admin_booking_enabled', 'yes' );
update_option( 'snippen_sms_user_activation_enabled', 'yes' );
update_option( 'snippen_sms_payment_reminder_enabled', 'yes' );
update_option( 'snippen_sms_payment_receipt_uploaded_enabled', 'yes' );

echo "Configured SMS Gateway settings:\n";
echo " - Active Provider: snippen_sms_service\n";
echo " - API Token: $api_token\n";
echo " - Sender: $sender_name\n";

// 2. Ensure starter booking objects and time slots exist
global $wpdb;
$table_objects = $wpdb->prefix . 'snippen_booking_objects';
$objects       = $wpdb->get_results( "SELECT id, name FROM {$table_objects} WHERE deleted_at IS NULL" );

if ( empty( $objects ) ) {
	echo "No booking objects found. Creating starter setup...\n";
	if ( class_exists( '\SnippenBooking\Admin\SetupWizard' ) ) {
		\SnippenBooking\Admin\SetupWizard::create_starter_setup();
		$objects = $wpdb->get_results( "SELECT id, name FROM {$table_objects} WHERE deleted_at IS NULL" );
	}
}

$first_object_id = ! empty( $objects ) ? (int) $objects[0]->id : 1;

// 3. Seed test resident user
$test_phone    = '+4799887766';
$test_email    = 'test.guest@example.no';
$test_username = 'test.guest';
$test_name     = 'Ola Nordmann (E2E Test)';

$user = get_user_by( 'login', $test_username );
if ( ! $user ) {
	$user_id = wp_insert_user(
		array(
			'user_login'   => $test_username,
			'user_pass'    => 'demo123',
			'user_email'   => $test_email,
			'display_name' => $test_name,
			'role'         => 'snippen_resident',
		)
	);
	if ( is_wp_error( $user_id ) ) {
		echo 'Error creating test user: ' . $user_id->get_error_message() . "\n";
		exit( 1 );
	}
} else {
	$user_id = $user->ID;
	wp_update_user(
		array(
			'ID'           => $user_id,
			'user_email'   => $test_email,
			'display_name' => $test_name,
		)
	);
}

update_user_meta( $user_id, 'snippen_phone', $test_phone );
echo "Test user configured (ID: $user_id, Phone: $test_phone)\n";

// 4. Seed test booking data for this phone number
$table_bookings            = $wpdb->prefix . 'snippen_bookings';
$table_booking_objects     = $wpdb->prefix . 'snippen_bookings_booking_objects';
$table_new_booking_objects = $wpdb->prefix . 'snippen_booking_booking_objects';
$table_booking_blocks      = $wpdb->prefix . 'snippen_booking_booking_blocks';
$table_blocks              = $wpdb->prefix . 'snippen_booking_blocks';

$existing_booking = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT id, uuid FROM {$table_bookings} WHERE customer_phone = %s AND deleted_at IS NULL LIMIT 1",
		$test_phone
	)
);

$now = current_time( 'mysql' );
if ( ! $existing_booking ) {
	$booking_date = date( 'Y-m-d', strtotime( '+1 day' ) );
	$uuid         = wp_generate_uuid4();

	$wpdb->insert(
		$table_bookings,
		array(
			'uuid'              => $uuid,
			'user_id'           => $user_id,
			'slot_id'           => 1,
			'booking_date'      => $booking_date,
			'customer_name'     => $test_name,
			'customer_email'    => $test_email,
			'customer_phone'    => $test_phone,
			'price'             => 1500.00,
			'description'       => 'E2E Test Booking for SMS Gateway',
			'status'            => 'confirmed',
			'payment_status_id' => 2, // PAID
			'created_at'        => $now,
			'modified_at'       => $now,
		)
	);
	$booking_id = $wpdb->insert_id;

	$wpdb->insert(
		$table_booking_objects,
		array(
			'booking_id'        => $booking_id,
			'booking_object_id' => $first_object_id,
		)
	);

	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_new_booking_objects ) ) === $table_new_booking_objects ) {
		$wpdb->insert(
			$table_new_booking_objects,
			array(
				'booking_id'        => $booking_id,
				'booking_object_id' => $first_object_id,
			)
		);
	}

	// Link booking block if blocks table exists
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_blocks ) ) === $table_blocks ) {
		$block_ids = $wpdb->get_col( "SELECT id FROM {$table_blocks} WHERE deleted_at IS NULL LIMIT 1" );
		if ( ! empty( $block_ids ) && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_booking_blocks ) ) === $table_booking_blocks ) {
			$wpdb->insert(
				$table_booking_blocks,
				array(
					'booking_id'       => $booking_id,
					'booking_block_id' => (int) $block_ids[0],
				)
			);
		}
	}

	echo "Created test booking ID: $booking_id (UUID: $uuid, Date: $booking_date)\n";
} else {
	$booking_id = (int) $existing_booking->id;
	echo "Existing test booking found (ID: $booking_id, UUID: {$existing_booking->uuid})\n";
}

// 5. Seed a queued outbound test message for the gateway outbox
$table_messages = $wpdb->prefix . 'snippen_messages';
$pending_outbox = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$table_messages} WHERE channel = 'sms' AND status = 'queued' AND recipient = %s",
		$test_phone
	)
);

if ( 0 === $pending_outbox ) {
	\SnippenBooking\Service\Notification\MessageLoggerService::log_message(
		$booking_id,
		$user_id,
		'sms',
		$test_phone,
		null,
		'Din adgangskode til Snippen er 4821',
		'booking_confirmation',
		'queued',
		array(
			'provider' => 'snippen_sms_service',
			'sender'   => $sender_name,
		)
	);
	echo "Queued outbound SMS message created for $test_phone in outbox.\n";
} else {
	echo "Pending outbound SMS message already queued in outbox.\n";
}

echo "Success: SMS Gateway demo setup complete!\n";
