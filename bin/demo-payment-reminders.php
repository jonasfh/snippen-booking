<?php
/**
 * Run payment reminder cron process manually for testing/demo
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

echo "Running payment reminder processor...\n";

$service = new \SnippenBooking\Service\Notification\PaymentReminderService();
$result  = $service->process_reminders();

echo sprintf("Done! Total payment reminders sent: %d\n", $result['total_sent']);
foreach ($result['processed_intervals'] as $days => $info) {
    echo sprintf(" - Interval %d days before booking: %d eligible, %d sent\n", $days, $info['eligible_count'], $info['sent_count']);
}
