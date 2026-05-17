<?php
/**
 * Set up SMTP / Email settings from .env
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

$host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
$port = getenv('SMTP_PORT') ?: '587';
$encryption = getenv('SMTP_ENCRYPTION') ?: 'tls';
$user = getenv('SMTP_USER');
$pass = getenv('SMTP_PASS');
$from_email = getenv('SMTP_FROM_EMAIL');
$from_name = getenv('SMTP_FROM_NAME') ?: 'Snippen Booking';

if (!$user || !$pass) {
    update_option('snippen_smtp_enabled', 'no');
    echo "SMTP settings deactivated (SMTP_USER and/or SMTP_PASS empty in .env).\n";
    exit(0);
}

update_option('snippen_smtp_enabled', 'yes');
update_option('snippen_smtp_host', $host);
update_option('snippen_smtp_port', $port);
update_option('snippen_smtp_encryption', $encryption);
update_option('snippen_smtp_user', $user);
update_option('snippen_smtp_pass', $pass);
update_option('snippen_smtp_from_email', $from_email ?: $user);
update_option('snippen_smtp_from_name', $from_name);

echo "Success: SMTP settings updated and enabled.\n";
echo "Host: $host\n";
echo "Port: $port\n";
echo "Encryption: $encryption\n";
echo "User: $user\n";
echo "From Email: " . ($from_email ?: $user) . "\n";
echo "From Name: $from_name\n";
