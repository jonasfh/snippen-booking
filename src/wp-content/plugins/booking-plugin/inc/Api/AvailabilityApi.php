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

        $object_id = (int) ( $_GET['object_id'] ?? 0 );
        $start_date = sanitize_text_field( $_GET['start_date'] ?? '' ); // YYYY-MM-DD

        if ( empty( $object_id ) || empty( $start_date ) ) {
            wp_send_json_error( array( 'message' => 'Manglende påkrevde parametere' ) );
        }

        // Lead time (N days)
        $offset_days = 0;

        // Calculate end date (7 days)
        $end_date = date( 'Y-m-d', strtotime( $start_date . ' + 6 days' ) );

        $table_slots = $wpdb->prefix . 'snippen_time_slots';
        $table_bookings = $wpdb->prefix . 'snippen_bookings';

        // Get slots for this specific object
        $slots = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, name, description, start_time, end_time, cleanup_hours 
             FROM $table_slots 
             WHERE booking_object_id = %d AND deleted_at IS NULL",
            $object_id
        ) );

        // Get all bookings for the range with slot details (for UI display)
        $bookings = $wpdb->get_results( $wpdb->prepare(
            "SELECT b.booking_date, b.slot_id, s.name as slot_name, s.start_time, s.end_time, s.cleanup_hours 
             FROM $table_bookings b
             JOIN $table_slots s ON b.slot_id = s.id
             WHERE b.booking_object_id = %d 
             AND b.booking_date BETWEEN %s AND %s 
             AND b.deleted_at IS NULL",
            $object_id, $start_date, $end_date
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
        $unavailable_slots = $availability_service->getUnavailableSlots($object_id, $start_date, $end_date);

        wp_send_json_success( array(
            'slots' => $slots,
            'booked' => $booked_details,
            'unavailable' => $unavailable_slots,
            'offset_days' => $offset_days
        ) );
    }
}
