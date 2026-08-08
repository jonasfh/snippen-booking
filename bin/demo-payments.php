<?php
/**
 * Set up demo payment settings and enrich demo bookings with realistic payment statuses & uploaded receipt in custom userdata folders.
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

// Ensure migration has run so payment columns and statuses exist
\SnippenBooking\Database\MigrationManager::run();

global $wpdb;
$table_bookings = $wpdb->prefix . 'snippen_bookings';

// Helper function to create demo attachment in custom user folder
function create_demo_attachment( $booking_uuid ) {
	$source_image = __DIR__ . '/../src/wp-content/plugins/booking-plugin/assets/images/betalt.png';
	if ( ! file_exists( $source_image ) ) {
		return 0;
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$custom_filter = function( $uploads ) use ( $booking_uuid ) {
		$subdir            = '/userdata/booking_uuid_' . $booking_uuid;
		$uploads['subdir'] = $subdir;
		$uploads['path']   = $uploads['basedir'] . $subdir;
		$uploads['url']    = $uploads['baseurl'] . $subdir;
		return $uploads;
	};

	add_filter( 'upload_dir', $custom_filter );
	$upload_dir = wp_upload_dir();

	if ( ! file_exists( $upload_dir['path'] ) ) {
		wp_mkdir_p( $upload_dir['path'] );
	}

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
	}

	remove_filter( 'upload_dir', $custom_filter );

	return is_wp_error( $attachment_id ) ? 0 : $attachment_id;
}

// 2. Create specific demo test cases for explicit testing
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
		'with_receipt'       => false,
	),
	array(
		'uuid'               => wp_generate_uuid4(),
		'user_id'            => 1,
		'slot_id'            => 1,
		'booking_date'       => date( 'Y-m-d', strtotime( '+3 days' ) ),
		'customer_name'      => 'Kari Nordmann (Kvittering opplastet)',
		'customer_email'     => 'kari.nordmann@example.com',
		'customer_phone'     => '90001002',
		'description'        => 'Konfirmasjon - kvittering fra nettbank vedlagt',
		'price'              => 2000.00,
		'status'             => 'pending',
		'payment_status_id'  => 1, // UNPAID (with receipt attachment)
		'payment_notes'      => 'Kunde har lastet opp skjermbilde fra nettbank.',
		'with_receipt'       => true,
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
		'with_receipt'       => false,
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
		'with_receipt'       => false,
	),
);

$created_uuids = array();
foreach ( $demo_test_bookings as $data ) {
	$with_receipt               = $data['with_receipt'];
	unset( $data['with_receipt'] );

	$data['payment_updated_at'] = current_time( 'mysql' );
	$data['created_at']         = current_time( 'mysql' );
	$data['modified_at']        = current_time( 'mysql' );

	$wpdb->insert( $table_bookings, $data );
	$booking_id = $wpdb->insert_id;

	if ( $with_receipt ) {
		$attach_id = create_demo_attachment( $data['uuid'] );
		if ( $attach_id ) {
			$wpdb->update(
				$table_bookings,
				array( 'payment_receipt_attachment_id' => $attach_id ),
				array( 'id' => $booking_id )
			);
		}
	}

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

echo "Success: Configured demo payments and created demo test bookings with isolated userdata upload folders!\n\n";
echo "--- TEST-LENKER FOR DEMO ---\n";
echo "1. Admin Booking-oversikt (Filtrer & Behandle betalinger):\n";
echo "   http://localhost:8080/wp-admin/admin.php?page=snippen-booking\n\n";
echo "2. Admin Betalingsinnstillinger:\n";
echo "   http://localhost:8080/wp-admin/admin.php?page=snippen-booking-settings\n\n";
echo "3. Brukervisning / Kvitteringsopplasting via UUID (Kunde: Kari - Kvittering vedlagt):\n";
echo "   http://localhost:8080/?booking_uuid=" . $created_uuids[1]['uuid'] . "\n\n";
