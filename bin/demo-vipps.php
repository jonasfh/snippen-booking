<?php
/**
 * Set up Vipps ePayment settings from .env
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

$client_id        = getenv( 'VIPPS_CLIENT_ID' );
$client_secret    = getenv( 'VIPPS_CLIENT_SECRET' );
$subscription_key = getenv( 'VIPPS_SUBSCRIPTION_KEY' );
$msn              = getenv( 'VIPPS_MSN' );
$environment      = getenv( 'VIPPS_ENVIRONMENT' ) ?: 'test';
$enabled          = getenv( 'VIPPS_ENABLED' ) ?: 'no';

if ( ! $client_id || ! $client_secret || ! $subscription_key || ! $msn ) {
	echo "Info: VIPPS_* credentials are not fully set in .env. Skipping Vipps demo settings setup.\n";
	exit( 0 );
}

update_option( 'snippen_vipps_enabled', $enabled );
update_option( 'snippen_vipps_environment', $environment );
update_option( 'snippen_vipps_client_id', $client_id );
update_option( 'snippen_vipps_client_secret', $client_secret );
update_option( 'snippen_vipps_subscription_key', $subscription_key );
update_option( 'snippen_vipps_msn', $msn );

echo "Success: Vipps ePayment settings updated from .env.\n";
echo "Enabled: $enabled\n";
echo "Environment: $environment\n";
echo 'Client ID: ' . substr( $client_id, 0, 8 ) . "...\n";
echo "MSN: $msn\n";
