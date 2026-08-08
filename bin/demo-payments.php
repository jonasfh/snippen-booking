<?php
/**
 * Set up demo payment settings and enrich demo bookings with realistic payment statuses & uploaded receipt.
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

echo "Setting up demo payment data...\n";

// 1. Configure Payment Settings
update_option( 'snippen_payment_bank_account', '1234.56.78901' );
update_option( 'snippen_payment_vipps_number', '#12345 (Snippen Samfunnshus)' );
update_option( 'snippen_payment_instructions', "Vennligst overfør leiebeløpet innen 3 dager fra booking. Merk betalingen med ditt navn eller booking-ID." );
update_option( 'snippen_payment_admin_emails', 'kasserer@snippen.com, admin@example.com' );
update_option( 'snippen_payment_notify_admin', 'yes' );

// 2. Import demo receipt image into WordPress Uploads media library
$source_image  = __DIR__ . '/../src/wp-content/plugins/booking-plugin/assets/images/betalt.png';
$attachment_id = 0;

if ( file_exists( $source_image ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$upload_dir  = wp_upload_dir();
	$filename    = 'demo-betaling-kvittering-' . time() . '.png';
	$target_file = $upload_dir['path'] . '/' . $filename;

	copy( $source_image, $target_file );

	$filetype   = wp_check_filetype( $filename, null );
	$attachment = array(
		'post_mime_type' => $filetype['type'],
		'post_title'     => 'Demo Betalingskvittering (Nettbank)',
		'post_content'   => '',
		'post_status'    => 'inherit',
	);

	$attachment_id = wp_insert_attachment( $attachment, $target_file );
	if ( ! is_wp_error( $attachment_id ) ) {
		$attach_data = wp_generate_attachment_metadata( $attachment_id, $target_file );
		wp_update_attachment_metadata( $attachment_id, $attach_data );
		echo "Uploaded demo receipt image to Media Library (ID: $attachment_id).\n";
	}
}

// Ensure migration has run so payment columns and statuses exist
\SnippenBooking\Database\MigrationManager::run();

global $wpdb;
$table_bookings = $wpdb->prefix . 'snippen_bookings';

// 3. Enrich existing generated bookings from composer demo:bookings
$existing_bookings = $wpdb->get_results( "SELECT id, status FROM $table_bookings WHERE deleted_at IS NULL ORDER BY id ASC" );

if ( ! empty( $existing_bookings ) ) {
	$idx = 0;
	foreach ( $existing_bookings as $b ) {
		$idx++;
		if ( $idx % 3 === 1 ) {
			// Unpaid & pending
			$wpdb->update(
				$table_bookings,
				array(
					'status'                        => 'pending',
					'payment_status_id'             => 1, // UNPAID
					'payment_receipt_attachment_id' => ( $idx === 1 && $attachment_id ) ? $attachment_id : null,
					'payment_notes'                 => ( $idx === 1 ) ? 'Kvittering sist opplastet via nettbank' : null,
				),
				array( 'id' => $b->id )
			);
		} elseif ( $idx % 3 === 2 ) {
			// Paid & confirmed
			$wpdb->update(
				$table_bookings,
				array(
					'status'            => 'confirmed',
					'payment_status_id' => 2, // PAID
					'payment_notes'     => 'Innbetaling registrert via Vipps',
				),
				array( 'id' => $b->id )
			);
		} else {
			// Exempt & confirmed
			$wpdb->update(
				$table_bookings,
				array(
					'status'            => 'confirmed',
					'payment_status_id' => 3, // EXEMPT
					'payment_notes'     => 'Fritatt for leie (Internt arrangement)',
				),
				array( 'id' => $b->id )
			);
		}
	}
	echo "Enriched " . count( $existing_bookings ) . " existing demo bookings with realistic payment statuses & receipt attachments.\n";
}

// 4. Create specific demo test cases for explicit testing
$demo_test_bookings = array(
	array(
		'uuid'               => wp_generate_uuid4(),
		'user_id'            => 1,
		'slot_id'            => 1,
		'booking_date'       => date( 'Y-m-d', strtotime( '+2 days' ) ),
		'customer_name'      => 'Ola Nordmann (Mangler betaling)',
		'customer_email'     => 'ola.nordmann@example.com',
		'customer_phone'     => '90001001',
		'description'        => 'Bursdagsfeiring i Festsalen',
		'price'              => 1500.00,
		'status'             => 'pending',
		'payment_status_id'  => 1, // UNPAID
		'payment_notes'      => 'Venter på betaling eller kvittering.',
	),
	array(
		'uuid'                          => wp_generate_uuid4(),
		'user_id'                       => 1,
		'slot_id'                       => 1,
		'booking_date'                  => date( 'Y-m-d', strtotime( '+3 days' ) ),
		'customer_name'                 => 'Kari Nordmann (Kvittering opplastet)',
		'customer_email'                => 'kari.nordmann@example.com',
		'customer_phone'                => '90001002',
		'description'                   => 'Konfirmasjon - kvittering fra nettbank vedlagt',
		'price'                         => 2000.00,
		'status'                        => 'pending',
		'payment_status_id'             => 1, // UNPAID (with receipt attachment)
		'payment_receipt_attachment_id' => $attachment_id,
		'payment_notes'                 => 'Kunde har lastet opp skjermbilde fra nettbank.',
	),
	array(
		'uuid'               => wp_generate_uuid4(),
		'user_id'            => 1,
		'slot_id'            => 1,
		'booking_date'       => date( 'Y-m-d', strtotime( '+5 days' ) ),
		'customer_name'      => 'Per Hansen (Betalt & Bekreftet)',
		'customer_email'     => 'per.hansen@example.com',
		'customer_phone'     => '90001003',
		'description'        => 'Møte i Velferden',
		'price'              => 800.00,
		'status'             => 'confirmed',
		'payment_status_id'  => 2, // PAID
		'payment_notes'      => 'Betalt med Vipps ref #987654. Registrert av kasserer.',
	),
	array(
		'uuid'               => wp_generate_uuid4(),
		'user_id'            => 1,
		'slot_id'            => 1,
		'booking_date'       => date( 'Y-m-d', strtotime( '+7 days' ) ),
		'customer_name'      => 'Snippen Vel (Fritatt)',
		'customer_email'     => 'styret@snippen.com',
		'customer_phone'     => '90001004',
		'description'        => 'Årsmøte for vel veilag - Åpent arrangement',
		'price'              => 0.00,
		'status'             => 'confirmed',
		'payment_status_id'  => 3, // EXEMPT
		'payment_notes'      => 'Fritatt for leie (Internt arrangement).',
	),
);

$created_uuids = array();
foreach ( $demo_test_bookings as $data ) {
	$data['payment_updated_at'] = current_time( 'mysql' );
	$data['created_at']         = current_time( 'mysql' );
	$data['modified_at']        = current_time( 'mysql' );

	$wpdb->insert( $table_bookings, $data );
	$booking_id                                  = $wpdb->insert_id;
	$created_uuids[ $data['payment_status_id'] ] = array(
		'id'   => $booking_id,
		'uuid' => $data['uuid'],
		'name' => $data['customer_name'],
	);

	// Link to object 1 (Festsalen)
	$wpdb->insert(
		$wpdb->prefix . 'snippen_bookings_booking_objects',
		array(
			'booking_id'        => $booking_id,
			'booking_object_id' => 1,
		)
	);
}

echo "Success: Configured demo payments and created demo test bookings!\n\n";
echo "--- TEST-LENKER FOR DEMO ---\n";
echo "1. Admin Booking-oversikt (Filtrer & Behandle betalinger):\n";
echo "   http://localhost:8080/wp-admin/admin.php?page=snippen-booking\n\n";
echo "2. Admin Betalingsinnstillinger:\n";
echo "   http://localhost:8080/wp-admin/admin.php?page=snippen-booking-settings\n\n";
echo "3. Brukervisning / Kvitteringsopplasting via UUID (Kunde: Kari - Kvittering vedlagt):\n";
echo "   http://localhost:8080/?booking_uuid=" . $created_uuids[1]['uuid'] . "\n\n";
