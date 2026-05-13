<?php
/**
 * Generate/Clear Demo Data for Snippen Booking
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

$table_slots = $wpdb->prefix . 'snippen_time_slots';
$table_bookings = $wpdb->prefix . 'snippen_bookings';

$action = $argv[1] ?? 'generate';

if ($action === 'clear') {
    echo "Clearing all bookings...\n";
    $wpdb->query("DELETE FROM $table_bookings");
    echo "Success: All bookings cleared.\n";
    exit(0);
}

if ($action === 'generate') {
    echo "Generating demo bookings...\n";

    $facilities = ['spisestuen', 'peisestuen'];
    $slots = $wpdb->get_results("SELECT id FROM $table_slots WHERE deleted_at IS NULL");

    if (empty($slots)) {
        echo "Error: No time slots found. Is the plugin activated?\n";
        exit(1);
    }

    $count = 0;
    $today = new DateTime();
    
    // Generate bookings for the next 30 days
    for ($i = 0; $i < 30; $i++) {
        $date = clone $today;
        $date->modify("+$i days");
        $date_str = $date->format('Y-m-d');
        
        // Random chance of bookings on this day (60% chance)
        if (rand(1, 10) <= 6) {
            $num_bookings = rand(1, 3);
            
            // Randomly select facility
            $facility = $facilities[rand(0, 1)];
            
            // Shuffle slots and pick some
            $available_slots = (array) $slots;
            shuffle($available_slots);
            $selected_slots = array_slice($available_slots, 0, $num_bookings);
            
            foreach ($selected_slots as $slot) {
                // Check if already booked (simple check)
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_bookings WHERE booking_date = %s AND slot_id = %d AND facility = %s",
                    $date_str, $slot->id, $facility
                ));
                
                if ($exists) continue;

                $wpdb->insert($table_bookings, array(
                    'facility' => $facility,
                    'slot_id' => (int) $slot->id,
                    'booking_date' => $date_str,
                    'customer_name' => 'Demo Bruker ' . rand(100, 999),
                    'customer_email' => 'demo' . rand(1, 100) . '@example.com',
                    'customer_phone' => '12345678',
                    'description' => 'Automatisk generert demo-booking for testing.'
                ));
                $count++;
            }
        }
    }

    echo "Success: Generated $count demo bookings for the next 30 days.\n";
    exit(0);
}

echo "Unknown action: $action. Use 'generate' or 'clear'.\n";
exit(1);
