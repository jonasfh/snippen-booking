<?php
/**
 * Set up KeySMS settings from .env
 */

require_once __DIR__ . '/env-loader.php';
load_env(__DIR__ . '/../.env');

// Bootstrap WordPress
$abspath = getenv('WP_ABSPATH') ?: '/wordpress/';
if (!file_exists($abspath . 'wp-load.php')) {
    echo "Error: WordPress not found at $abspath\n";
    exit(1);
}

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once $abspath . 'wp-load.php';

$username = getenv('KEYSMS_USERNAME');
$api_key = getenv('KEYSMS_API_KEY');
$sender = getenv('SMS_SENDER') ?: 'Snippen';
$booking_enabled = getenv('SMS_BOOKING_CONFIRMATION_ENABLED') ?: 'no';
$account_enabled = getenv('SMS_ACCOUNT_CONFIRMATION_ENABLED') ?: 'yes';

if (!$username || !$api_key) {
    echo "Info: KEYSMS_USERNAME and KEYSMS_API_KEY are not set in .env. Skipping KeySMS setting setup.\n";
    exit(0);
}

update_option('snippen_keysms_username', $username);
update_option('snippen_keysms_api_key', $api_key);
update_option('snippen_sms_sender', $sender);
update_option('snippen_sms_booking_confirmation_enabled', $booking_enabled);
update_option('snippen_sms_account_confirmation_enabled', $account_enabled);

echo "Success: KeySMS settings updated.\n";
echo "Username: $username\n";
echo "Sender: $sender\n";
echo "Booking SMS Enabled: $booking_enabled\n";
echo "Account SMS Enabled: $account_enabled\n";
