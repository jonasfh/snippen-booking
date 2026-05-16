<?php
/**
 * Generate/Clear Demo Data for Snippen Booking (Booking Objects Version)
 * 
 * Usage:
 * php bin/demo-data.php generate
 * php bin/demo-data.php clear
 */

// Bootstrap WordPress
$abspath = getenv( 'WP_ABSPATH' ) ?: '/wordpress/';
if ( ! file_exists( $abspath . 'wp-load.php' ) ) {
    echo "Error: WordPress not found at $abspath\n";
    exit(1);
}

// Set up globals that WP expects
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once $abspath . 'wp-load.php';

global $wpdb;

$table_objects = $wpdb->prefix . 'snippen_booking_objects';
$table_slots = $wpdb->prefix . 'snippen_time_slots';
$table_bookings = $wpdb->prefix . 'snippen_bookings';
$table_booking_objects = $wpdb->prefix . 'snippen_bookings_booking_objects';

$action = $argv[1] ?? 'bookings';

if ($action === 'generate' || $action === 'bookings') {
    $action = 'generate'; // Keep internal logic using 'generate'
}

if ($action === 'clear') {
    echo "Clearing all bookings...\n";
    $wpdb->query("DELETE FROM $table_booking_objects");
    $wpdb->query("DELETE FROM $table_bookings");
    echo "Success: All bookings cleared.\n";

    echo "Clearing demo pages...\n";
    $existing_pages = get_pages();
    foreach ($existing_pages as $page) {
        if (strpos($page->post_title, 'Booking Demo') !== false) {
            wp_delete_post($page->ID, true);
        }
    }
    echo "Success: Demo pages cleared.\n";

    echo "Clearing demo users...\n";
    require_once(ABSPATH . 'wp-admin/includes/user.php');
    $subscribers = get_users(['role' => 'subscriber']);
    foreach ($subscribers as $sub) {
        if (strpos($sub->user_email, '@example.no') !== false) {
            wp_delete_user($sub->ID);
        }
    }
    echo "Success: Demo users cleared.\n";

    exit(0);
}

if ($action === 'users') {
    echo "Generating demo subscriber users...\n";
    $count = 0;
    $first_names = ['Lars', 'Erik', 'Morten', 'Kari', 'Ingrid', 'Solveig', 'Anders', 'Stian', 'Mette', 'Heidi'];
    $last_names = ['Hansen', 'Johansen', 'Olsen', 'Larsen', 'Andersen', 'Nilsen', 'Pedersen', 'Kristiansen', 'Jensen', 'Karlsen'];

    for ($i = 0; $i < 50; $i++) {
        $first = $first_names[array_rand($first_names)];
        $last = $last_names[array_rand($last_names)];
        $username = strtolower($first . '.' . $last . rand(10, 99));
        $email = $username . '@example.no';

        if (!username_exists($username) && !email_exists($email)) {
            $user_id = wp_insert_user([
                'user_login' => $username,
                'user_pass'  => 'demo',
                'user_email' => $email,
                'display_name' => $first . ' ' . $last,
                'role'       => 'subscriber'
            ]);
            if (!is_wp_error($user_id)) {
                $phone = '+47' . (rand(0, 1) ? '4' : '9') . rand(1000000, 9999999);
                update_user_meta($user_id, 'snippen_phone', $phone);
                $count++;
            }
        }
    }
    echo "Success: Generated $count demo subscriber users.\n";
    exit(0);
}

if ($action === 'generate') {
    echo "Generating demo bookings...\n";

    $objects = $wpdb->get_results("SELECT id FROM $table_objects WHERE deleted_at IS NULL");

    if (empty($objects)) {
        echo "Error: No booking objects found. Please activate the plugin first.\n";
        exit(1);
    }

    $count = 0;
    $today = new DateTime();
    
    // Generate bookings for the next 30 days
    for ($i = 0; $i < 30; $i++) {
        $date = clone $today;
        $date->modify("+$i days");
        $date_str = $date->format('Y-m-d');
        
        $service = new \SnippenBooking\Service\AvailabilityService();
        $pricing_service = new \SnippenBooking\Service\PricingService();
        
        // Fetch subscriber users to link bookings
        $subscriber_users = get_users(['role' => 'subscriber', 'fields' => ['ID', 'display_name', 'user_email']]);
        $default_admin = get_users(['role' => 'administrator', 'number' => 1, 'fields' => ['ID', 'display_name', 'user_email']])[0];

        // Multi-object booking chance (20%) - Book all objects for a single slot (e.g. "Hele dagen")
        if (rand(1, 10) <= 2) {
            // Pick a random slot name (e.g. "Hele dagen")
            $slot_names = ['Hele dagen', 'Formiddag', 'Ettermiddag'];
            $target_name = $slot_names[array_rand($slot_names)];
            
            // Find corresponding slot IDs for each object
            $slots_to_book = [];
            $all_available = true;
            
            foreach ($objects as $obj) {
                $slot = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM $table_slots WHERE booking_object_id = %d AND name = %s AND allow_multi_object = 1 AND deleted_at IS NULL",
                    $obj->id, $target_name
                ));
                
                if (!$slot || !$service->isSlotAvailable($obj->id, $date_str, $slot->id)) {
                    $all_available = false;
                    break;
                }
                $slots_to_book[$obj->id] = $slot->id;
            }
            
            if ($all_available && !empty($slots_to_book)) {
                $first_slot_id = reset($slots_to_book);
                $price = $pricing_service->getPrice(array_keys($slots_to_book), array_values($slots_to_book), $date_str) ?: 0;
                
                $user = !empty($subscriber_users) ? $subscriber_users[array_rand($subscriber_users)] : $default_admin;

                // Insert main booking
                $phone = get_user_meta($user->ID, 'snippen_phone', true) ?: '+4799887766';
                $wpdb->insert($table_bookings, array(
                    'user_id' => $user->ID,
                    'slot_id' => (int) $first_slot_id,
                    'booking_date' => $date_str,
                    'customer_name' => $user->display_name,
                    'customer_email' => $user->user_email,
                    'customer_phone' => $phone,
                    'price' => $price,
                    'description' => 'Demo: Booket begge lokaler (' . $target_name . ')'
                ));
                
                $booking_id = $wpdb->insert_id;
                
                // Link all objects
                foreach ($slots_to_book as $obj_id => $sid) {
                    $wpdb->insert($table_booking_objects, array(
                        'booking_id' => $booking_id,
                        'booking_object_id' => (int) $obj_id
                    ));
                }
                
                $count++;
                continue; // Skip individual bookings for this day to keep it simple
            }
        }

        // Individual object bookings
        foreach ($objects as $obj) {
            // 30% chance of bookings for this object on this day
            if (rand(1, 10) <= 3) {
                $slots = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, name FROM $table_slots WHERE booking_object_id = %d AND deleted_at IS NULL",
                    $obj->id
                ));
                
                if (empty($slots)) continue;

                $num_bookings = rand(1, min(2, count($slots)));
                shuffle($slots);
                $selected_slots = array_slice($slots, 0, $num_bookings);
                
                foreach ($selected_slots as $slot) {
                    if (!$service->isSlotAvailable($obj->id, $date_str, $slot->id)) {
                        continue;
                    }

                    $price = $pricing_service->getPrice([$obj->id], [$slot->id], $date_str) ?: 0;
                    $user = !empty($subscriber_users) ? $subscriber_users[array_rand($subscriber_users)] : $default_admin;

                    // Insert booking record
                    $phone = get_user_meta($user->ID, 'snippen_phone', true) ?: '+4712345678';
                    $wpdb->insert($table_bookings, array(
                        'user_id' => $user->ID,
                        'slot_id' => (int) $slot->id,
                        'booking_date' => $date_str,
                        'customer_name' => $user->display_name,
                        'customer_email' => $user->user_email,
                        'customer_phone' => $phone,
                        'price' => $price,
                        'description' => 'Automatisk generert demo-booking for ' . $date_str
                    ));
                    
                    $booking_id = $wpdb->insert_id;
                    
                    // Insert junction table entry
                    $wpdb->insert($table_booking_objects, array(
                        'booking_id' => $booking_id,
                        'booking_object_id' => (int) $obj->id
                    ));
                    
                    $count++;
                }
            }
        }
    }

    echo "Success: Generated $count demo bookings for the next 30 days across " . count($objects) . " objects.\n";
    exit(0);
}

echo "Unknown action: $action. Use 'generate' or 'clear'.\n";
exit(1);
