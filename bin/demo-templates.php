<?php
/**
 * Set up default notification templates in database idempotently
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

$repository = new \SnippenBooking\Database\Repository\NotificationTemplateRepository();
$repository->seed_defaults();

echo "Success: Default notification templates have been seeded in database.\n";
