<?php

namespace SnippenBooking\Api;

/**
 * Handles booking submission AJAX requests
 */
class BookingApi {

    /**
     * Register AJAX handlers
     */
    public static function register() {
        add_action( 'wp_ajax_snippen_booking_submit', array( __CLASS__, 'submit_booking' ) );
        add_action( 'wp_ajax_nopriv_snippen_booking_submit', array( __CLASS__, 'submit_booking' ) );
    }

    /**
     * Handle booking submission
     */
    public static function submit_booking() {
        global $wpdb;

        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'snippen_booking_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed' ) );
        }

        $booking_object_id = intval( $_POST['booking_object_id'] ?? 0 );
        $booking_date = sanitize_text_field( $_POST['event_date'] ?? '' );
        $slot_id = intval( $_POST['slot_id'] ?? 0 );

        if ( empty( $booking_object_id ) || empty( $booking_date ) || empty( $slot_id ) ) {
            wp_send_json_error( array( 'message' => 'Mangler nødvendige felt.' ) );
        }

        // Check if available (using advanced overlap detection)
        $availability_service = new \SnippenBooking\Service\AvailabilityService();
        if ( ! $availability_service->isSlotAvailable( $booking_object_id, $booking_date, $slot_id ) ) {
            wp_send_json_error( array( 'message' => 'Denne tidsluken er ikke lenger tilgjengelig.' ) );
        }

        // Process booking data
        $booking_data = array(
            'booking_object_id' => $booking_object_id,
            'booking_date' => $booking_date,
            'slot_id' => $slot_id,
            'customer_name' => sanitize_text_field( $_POST['name'] ?? '' ),
            'customer_email' => sanitize_email( $_POST['email'] ?? '' ),
            'customer_phone' => sanitize_text_field( $_POST['phone'] ?? '' ),
            'description' => sanitize_textarea_field( $_POST['description'] ?? '' ),
            'status' => 'pending',
            'created_at' => current_time( 'mysql' )
        );

        $table_bookings = $wpdb->prefix . 'snippen_bookings';
        $result = $wpdb->insert( $table_bookings, $booking_data );

        if ( $result ) {
            // Get object name for notification
            $table_objects = $wpdb->prefix . 'snippen_booking_objects';
            $object_name = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM $table_objects WHERE id = %d", $booking_object_id ) );

            // Send email notification
            $to = get_option( 'admin_email' );
            $subject = 'Ny Bookingforespørsel - ' . $object_name;
            $message = "Ny bookingforespørsel mottatt:\n\n";
            $message .= "Lokale: " . $object_name . "\n";
            $message .= "Dato: " . $booking_date . "\n";
            $message .= "Navn: " . $booking_data['customer_name'] . "\n";
            $message .= "Email: " . $booking_data['customer_email'] . "\n";
            $message .= "Telefon: " . $booking_data['customer_phone'] . "\n";
            $message .= "Beskrivelse: " . $booking_data['description'] . "\n";

            wp_mail( $to, $subject, $message );

            wp_send_json_success( array(
                'message' => 'Bookingforespørsel sendt! Vi kontakter deg snart.'
            ) );
        } else {
            wp_send_json_error( array( 'message' => 'Kunne ikke lagre bookingen. Vennligst prøv igjen.' ) );
        }
    }
}
