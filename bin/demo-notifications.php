<?php
/**
 * Set up demo notification templates
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

$template_service = new \SnippenBooking\Service\Notification\NotificationTemplateService();

// Demo: Custom User Activation SMS
$template_service->save_template(
    'user_activation',
    'sms',
    '',
    'Hei! Din kode for Snippen Booking er {{confirmation_code}}. Gyldig i 15 min.'
);

// Demo: Custom Booking Confirmation Email
$template_service->save_template(
    'booking_confirmation',
    'email',
    'Din forespørsel er mottatt (DEMO)',
    "Hei {{user_name}},\n\nVi har mottatt din forespørsel for {{booking_objects}} på datoen {{booking_date}}.\nSe detaljer her: {{booking_url}}\n\nDette er en demo-mal for e-post.\n\nMvh,\nSnippen"
);

echo "Success: Demo notification templates have been configured.\n";
