<?php

namespace SnippenBooking\Api;

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
     * Get availability for a given facility and week
     */
    public static function get_availability() {
        global $wpdb;

        $facility = sanitize_text_field( $_GET['facility'] ?? '' );
        $start_date = sanitize_text_field( $_GET['start_date'] ?? '' ); // YYYY-MM-DD

        if ( empty( $facility ) || empty( $start_date ) ) {
            wp_send_json_error( array( 'message' => 'Missing required parameters' ) );
        }

        // Lead time (N days) - hardcoded for now, could be an option
        $offset_days = 0;

        // Calculate end date (7 days)
        $end_date = date( 'Y-m-d', strtotime( $start_date . ' + 6 days' ) );

        $table_slots = $wpdb->prefix . 'snippen_time_slots';
        $table_bookings = $wpdb->prefix . 'snippen_bookings';

        // Get all active slots
        $slots = $wpdb->get_results( "SELECT id, name, description, start_time, end_time FROM $table_slots WHERE deleted_at IS NULL" );

        // Get all bookings for the range
        $bookings = $wpdb->get_results( $wpdb->prepare(
            "SELECT booking_date, slot_id FROM $table_bookings 
             WHERE facility = %s 
             AND booking_date BETWEEN %s AND %s 
             AND deleted_at IS NULL",
            $facility, $start_date, $end_date
        ) );

        // Organize bookings by date and slot
        $booked_slots = array();
        foreach ( $bookings as $booking ) {
            $booked_slots[ $booking->booking_date ][] = (int) $booking->slot_id;
        }

        wp_send_json_success( array(
            'slots' => $slots,
            'booked' => $booked_slots,
            'offset_days' => $offset_days
        ) );
    }
}
