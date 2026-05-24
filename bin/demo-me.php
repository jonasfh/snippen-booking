<?php
/**
 * Create a test user from .env
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

$email = getenv('TEST_USER_EMAIL');
$phone = getenv('TEST_USER_PHONE');
$name = getenv('TEST_USER_NAME') ?: 'Test User';
$pass = getenv('TEST_USER_PASS') ?: 'passord123';

if (!$email || !$phone) {
    echo "Info: TEST_USER_EMAIL and TEST_USER_PHONE are not set in .env. Skipping test user creation.\n";
    exit(0);
}

$username = strtolower(str_replace(' ', '.', $name));

if (username_exists($username)) {
    echo "User $username already exists. Updating...\n";
    $user = get_user_by('login', $username);
    $user_id = $user->ID;
    wp_update_user([
        'ID' => $user_id,
        'user_email' => $email,
        'user_pass' => $pass,
        'display_name' => $name,
        'role' => 'snippen_resident'
    ]);
} else {
    $user_id = wp_insert_user([
        'user_login' => $username,
        'user_pass'  => $pass,
        'user_email' => $email,
        'display_name' => $name,
        'role'       => 'snippen_resident'
    ]);
    
    if (is_wp_error($user_id)) {
        echo "Error creating user: " . $user_id->get_error_message() . "\n";
        exit(1);
    }
    echo "User $username created.\n";
}

update_user_meta($user_id, 'snippen_phone', $phone);

echo "Success: Test user set up.\n";
echo "Username: $username\n";
echo "Email: $email\n";
echo "Phone: $phone\n";
echo "Role: snippen_resident\n";
