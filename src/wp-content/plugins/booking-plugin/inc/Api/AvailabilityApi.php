<?php

namespace SnippenBooking\Api;

use SnippenBooking\Service\AvailabilityService;
use SnippenBooking\Service\PricingService;

/**
 * Handles AJAX availability requests
 */
class AvailabilityApi {

    /**
     * Register AJAX handlers
     */
    public static function register() {
        add_action( 'wp_ajax_snippen_get_availability', array( __CLASS__, 'get_availability' ) );
        add_action( 'wp_ajax_nopriv_snippen_get_availability', array( __CLASS__, 'get_availability' ) );
    }

    /**
     * Get availability for a given object and week
     */
    public static function get_availability() {
        global $wpdb;

        $object_ids_raw = isset($_GET['object_id']) ? $_GET['object_id'] : [];
        if (!is_array($object_ids_raw)) {
            $decoded = json_decode(stripslashes($object_ids_raw), true);
            if (is_array($decoded)) {
                $object_ids_raw = $decoded;
            } else {
                $object_ids_raw = explode(',', $object_ids_raw);
            }
        }
        
        $object_ids = array_map('intval', $object_ids_raw);
        $object_ids = array_filter($object_ids);

        $start_date = sanitize_text_field( $_GET['start_date'] ?? '' ); // YYYY-MM-DD

        if ( empty( $object_ids ) || empty( $start_date ) ) {
            wp_send_json_error( array( 'message' => 'Manglende påkrevde parametere' ) );
        }

        // Lead time (N days)
        $offset_days = 0;

        // Calculate end date (7 days)
        $end_date = date( 'Y-m-d', strtotime( $start_date . ' + 6 days' ) );

        $table_slots = $wpdb->prefix . 'snippen_time_slots';
        $table_bookings = $wpdb->prefix . 'snippen_bookings';

        // Get slots for all specified objects
        $in_clause = implode(',', array_fill(0, count($object_ids), '%d'));
        $all_slots = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, booking_object_id, name, description, start_time, end_time, cleanup_hours, allow_multi_object 
             FROM $table_slots 
             WHERE booking_object_id IN ($in_clause) AND deleted_at IS NULL",
            ...$object_ids
        ) );

        // Group slots by common characteristics
        $grouped_slots = [];
        foreach ($all_slots as $slot) {
            $key = $slot->name . '|' . $slot->start_time . '|' . $slot->end_time;
            if (!isset($grouped_slots[$key])) {
                $grouped_slots[$key] = [
                    'id' => [], 
                    'name' => $slot->name,
                    'description' => $slot->description,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'cleanup_hours' => $slot->cleanup_hours,
                    'allow_multi_object' => (int)$slot->allow_multi_object,
                    'object_count' => 0
                ];
            }
            $grouped_slots[$key]['id'][] = $slot->id;
            // For multi-object to be allowed, ALL objects must allow it for this slot type
            if (!(int)$slot->allow_multi_object) {
                $grouped_slots[$key]['allow_multi_object'] = 0;
            }
            $grouped_slots[$key]['object_count']++;
        }

        // Only keep slots that exist for ALL requested objects
        $slots = [];
        $is_multi_object = count($object_ids) > 1;
        
        foreach ($grouped_slots as $group) {
            if ($group['object_count'] === count($object_ids)) {
                // RESTRICTION: For multi-object bookings, only allow slots flagged as such
                if ($is_multi_object && !$group['allow_multi_object']) {
                    continue;
                }
                
                $group['id'] = implode(',', $group['id']);
                unset($group['object_count']);
                unset($group['allow_multi_object']);
                $slots[] = (object) $group;
            }
        }

        // Get all bookings for the range with slot details (for UI display)
        $query_args = array_merge($object_ids, [$start_date, $end_date]);
        $in_clause = implode(',', array_fill(0, count($object_ids), '%d'));
        $table_booking_objects = $wpdb->prefix . 'snippen_bookings_booking_objects';
        $table_objects = $wpdb->prefix . 'snippen_booking_objects';
        
        $is_admin = current_user_can( 'manage_options' );
        $select_fields = "b.booking_date, b.slot_id, s.name as slot_name, s.start_time, s.end_time, s.cleanup_hours";
        if ( $is_admin ) {
            $select_fields .= ", b.customer_name, b.customer_email, b.customer_phone, b.description as booking_description";
            $select_fields .= ", (SELECT GROUP_CONCAT(o.name SEPARATOR ', ') FROM $table_booking_objects bo JOIN $table_objects o ON bo.booking_object_id = o.id WHERE bo.booking_id = b.id) as object_names";
        }

        $bookings = $wpdb->get_results( $wpdb->prepare(
            "SELECT $select_fields
             FROM $table_bookings b
             JOIN $table_slots s ON b.slot_id = s.id
             WHERE b.id IN (SELECT booking_id FROM $table_booking_objects WHERE booking_object_id IN ($in_clause))
             AND b.booking_date BETWEEN %s AND %s 
             AND b.deleted_at IS NULL",
            ...$query_args
        ) );

        // Organize bookings by date
        $booked_details = array();
        foreach ( $bookings as $booking ) {
            $details = array(
                'slot_id' => (int) $booking->slot_id,
                'slot_name' => $booking->slot_name,
                'start_time' => $booking->start_time,
                'end_time' => $booking->end_time,
                'cleanup_hours' => (int) $booking->cleanup_hours
            );

            if ( $is_admin ) {
                $details['customer_name'] = $booking->customer_name;
                $details['customer_email'] = $booking->customer_email;
                $details['customer_phone'] = $booking->customer_phone;
                $details['description'] = $booking->booking_description;
                $details['object_names'] = $booking->object_names;
            }

            $booked_details[ $booking->booking_date ][] = $details;
        }

        // Get advanced availability (blocked by cleanup)
        $availability_service = new AvailabilityService();
        $unavailable_slots = [];
        
        foreach ($object_ids as $obj_id) {
            $unavail = $availability_service->getUnavailableSlots($obj_id, $start_date, $end_date);
            // Merge into overall unavailable map
            foreach ($unavail as $date => $slot_ids) {
                if (!isset($unavailable_slots[$date])) {
                    $unavailable_slots[$date] = [];
                }
                $unavailable_slots[$date] = array_merge($unavailable_slots[$date], $slot_ids);
            }
        }

        // Calculate prices for each slot on each day
        $pricing_service = new PricingService();
        $prices_by_date = [];
        
        $current = new \DateTime($start_date);
        $last = new \DateTime($end_date);
        
        while ($current <= $last) {
            $date_str = $current->format('Y-m-d');
            $prices_by_date[$date_str] = [];
            
            foreach ($slots as $slot) {
                // For grouped slots, we can use the slot IDs (comma separated string in $slot->id)
                $slot_ids = explode(',', $slot->id);
                $price = $pricing_service->getPrice($object_ids, $slot_ids, $date_str);
                if ($price !== null) {
                    $prices_by_date[$date_str][$slot->name] = $price;
                }
            }
            $current->modify('+1 day');
        }

        wp_send_json_success( array(
            'slots' => $slots,
            'booked' => $booked_details,
            'unavailable' => $unavailable_slots,
            'prices' => $prices_by_date,
            'offset_days' => $offset_days
        ) );
    }
}
