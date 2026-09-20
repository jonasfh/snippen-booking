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
$booking_enabled = getenv('SMS_BOOKING_CONFIRMATION_ENABLED') ?: 'yes';
$account_enabled = getenv('SMS_ACCOUNT_CONFIRMATION_ENABLED') ?: 'yes';

$provider = strtolower(trim(getenv('SMS_PROVIDER') ?: ''));
$snippen_token = getenv('SNIPPEN_SMS_API_TOKEN') ?: 'test-integration-token';
$snippen_sender = getenv('SNIPPEN_SMS_SENDER') ?: $sender;

// Ensure database tables and migrations are initialized
if (class_exists('\SnippenBooking\Database\Install')) {
    \SnippenBooking\Database\Install::activate();
}
if (class_exists('\SnippenBooking\Database\MigrationManager')) {
    \SnippenBooking\Database\MigrationManager::run();
}

$configured = false;

// 1. Configure KeySMS if credentials exist
if ($username && $api_key) {
    update_option('snippen_keysms_username', $username);
    update_option('snippen_keysms_api_key', $api_key);
    update_option('snippen_sms_sender', $sender);
    echo "Configured KeySMS settings (Username: $username, Sender: $sender)\n";
    $configured = true;
}

// 2. Configure Snippen SMS Service if token exists
if ($snippen_token) {
    update_option('snippen_sms_service_api_token', $snippen_token);
    update_option('snippen_sms_service_sender', $snippen_sender);
    echo "Configured Snippen SMS Service settings (Token: $snippen_token, Sender: $snippen_sender)\n";
    $configured = true;
}

// 3. Notification triggers
update_option('snippen_sms_booking_confirmation_enabled', $booking_enabled);
update_option('snippen_sms_account_confirmation_enabled', $account_enabled);
update_option('snippen_sms_admin_booking_enabled', 'yes');
update_option('snippen_sms_payment_reminder_enabled', 'yes');
update_option('snippen_sms_payment_receipt_uploaded_enabled', 'yes');

// 4. Select active SMS provider
if ($provider === 'snippen_sms_service') {
    update_option('snippen_sms_provider', 'snippen_sms_service');
    update_option('snippen_active_notification_provider', 'snippen_sms_service');
    echo "Active SMS provider set to: snippen_sms_service\n";
} elseif ($provider === 'keysms' || (!empty($username) && !empty($api_key) && empty($provider))) {
    update_option('snippen_sms_provider', 'keysms');
    update_option('snippen_active_notification_provider', 'keysms');
    echo "Active SMS provider set to: keysms\n";
} elseif (!empty($snippen_token)) {
    update_option('snippen_sms_provider', 'snippen_sms_service');
    update_option('snippen_active_notification_provider', 'snippen_sms_service');
    echo "Active SMS provider set to: snippen_sms_service\n";
}

if (!$configured) {
    echo "Notice: Neither KEYSMS nor SNIPPEN_SMS_API_TOKEN set in .env. Default fallback applied.\n";
} else {
    echo "Success: SMS configuration updated.\n";
}
