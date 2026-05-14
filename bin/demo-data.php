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

$action = $argv[1] ?? 'generate';

if ($action === 'clear') {
    echo "Clearing all bookings...\n";
    $wpdb->query("DELETE FROM $table_booking_objects");
    $wpdb->query("DELETE FROM $table_bookings");
    echo "Success: All bookings cleared.\n";
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
        
        // Randomly pick a booking object for this day's demo bookings
        $service = new \SnippenBooking\Service\AvailabilityService();

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
                    "SELECT id FROM $table_slots WHERE booking_object_id = %d AND name = %s AND deleted_at IS NULL",
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
                
                // Insert main booking
                $wpdb->insert($table_bookings, array(
                    'slot_id' => (int) $first_slot_id,
                    'booking_date' => $date_str,
                    'customer_name' => 'Multi-Object Demo ' . rand(100, 999),
                    'customer_email' => 'multi' . rand(1, 100) . '@example.com',
                    'customer_phone' => '99887766',
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
                    "SELECT id FROM $table_slots WHERE booking_object_id = %d AND deleted_at IS NULL",
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

                    // Insert booking record
                    $wpdb->insert($table_bookings, array(
                        'slot_id' => (int) $slot->id,
                        'booking_date' => $date_str,
                        'customer_name' => 'Demo Bruker ' . rand(100, 999),
                        'customer_email' => 'demo' . rand(1, 100) . '@example.com',
                        'customer_phone' => '12345678',
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
