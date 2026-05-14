<?php

namespace SnippenBooking\Api;

use SnippenBooking\Service\AvailabilityService;

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
            "SELECT id, booking_object_id, name, description, start_time, end_time, cleanup_hours 
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
                    'object_count' => 0
                ];
            }
            $grouped_slots[$key]['id'][] = $slot->id;
            $grouped_slots[$key]['object_count']++;
        }

        // Only keep slots that exist for ALL requested objects
        $slots = [];
        foreach ($grouped_slots as $group) {
            if ($group['object_count'] === count($object_ids)) {
                $group['id'] = implode(',', $group['id']);
                unset($group['object_count']);
                $slots[] = (object) $group;
            }
        }

        // Get all bookings for the range with slot details (for UI display)
        $query_args = array_merge($object_ids, [$start_date, $end_date]);
        $bookings = $wpdb->get_results( $wpdb->prepare(
            "SELECT b.booking_date, b.slot_id, s.name as slot_name, s.start_time, s.end_time, s.cleanup_hours 
             FROM $table_bookings b
             JOIN $table_slots s ON b.slot_id = s.id
             WHERE b.booking_object_id IN ($in_clause) 
             AND b.booking_date BETWEEN %s AND %s 
             AND b.deleted_at IS NULL",
            ...$query_args
        ) );

        // Organize bookings by date
        $booked_details = array();
        foreach ( $bookings as $booking ) {
            $booked_details[ $booking->booking_date ][] = array(
                'slot_id' => (int) $booking->slot_id,
                'slot_name' => $booking->slot_name,
                'start_time' => $booking->start_time,
                'end_time' => $booking->end_time,
                'cleanup_hours' => (int) $booking->cleanup_hours
            );
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

        wp_send_json_success( array(
            'slots' => $slots,
            'booked' => $booked_details,
            'unavailable' => $unavailable_slots,
            'offset_days' => $offset_days
        ) );
    }
}
