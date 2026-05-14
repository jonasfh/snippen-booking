<?php

namespace SnippenBooking\Api;

use SnippenBooking\Service\AvailabilityService;
use SnippenBooking\Service\PricingService;

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

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'Du må være innlogget for å booke.' ) );
        }

        $object_ids_raw = isset($_POST['booking_object_id']) ? $_POST['booking_object_id'] : [];
        if (!is_array($object_ids_raw)) {
            $decoded = json_decode(stripslashes($object_ids_raw), true);
            if (is_array($decoded)) {
                $object_ids_raw = $decoded;
            } else {
                $object_ids_raw = explode(',', $object_ids_raw);
            }
        }
        
        $booking_object_ids = array_map('intval', $object_ids_raw);
        $booking_object_ids = array_filter($booking_object_ids);

        $booking_date = sanitize_text_field( $_POST['event_date'] ?? '' );
        
        $slot_ids_raw = isset($_POST['slot_id']) ? $_POST['slot_id'] : '';
        $slot_ids = array_map('intval', explode(',', $slot_ids_raw));
        $slot_ids = array_filter($slot_ids);

        if ( empty( $booking_object_ids ) || empty( $booking_date ) || empty( $slot_ids ) ) {
            wp_send_json_error( array( 'message' => 'Mangler nødvendige felt.' ) );
        }

        // Check if available (using advanced overlap detection) for all requested slots
        $availability_service = new \SnippenBooking\Service\AvailabilityService();
        $slots_to_book = [];
        
        // Match slot IDs to their respective booking objects
        foreach ($booking_object_ids as $obj_id) {
            $matched_slot_id = 0;
            // Find which of the submitted slot_ids belongs to this object
            foreach ($slot_ids as $sid) {
                $slot_check = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}snippen_time_slots WHERE id = %d AND booking_object_id = %d", $sid, $obj_id));
                if ($slot_check) {
                    $matched_slot_id = $sid;
                    break;
                }
            }
            
            if (!$matched_slot_id || !$availability_service->isSlotAvailable( $obj_id, $booking_date, $matched_slot_id )) {
                wp_send_json_error( array( 'message' => 'En eller flere tidsluker er ikke lenger tilgjengelig.' ) );
            }
            $slots_to_book[$obj_id] = $matched_slot_id;
        }

        // Process booking data
        $table_bookings = $wpdb->prefix . 'snippen_bookings';
        $table_booking_objects = $wpdb->prefix . 'snippen_bookings_booking_objects';
        $customer_name = sanitize_text_field( $_POST['name'] ?? '' );
        $customer_email = sanitize_email( $_POST['email'] ?? '' );
        $customer_phone = sanitize_text_field( $_POST['phone'] ?? '' );
        $description = sanitize_textarea_field( $_POST['description'] ?? '' );
        
        // Get slot info for price lookup and restrictions
        $slot_info = $wpdb->get_row($wpdb->prepare(
            "SELECT name, allow_multi_object FROM $table_slots WHERE id = %d",
            $first_slot_id
        ));
        $slot_name = $slot_info ? $slot_info->name : '';

        // RESTRICTION: For multi-object bookings, only allow slots flagged as such
        if ( count( $slots_to_book ) > 1 && (!$slot_info || !$slot_info->allow_multi_object) ) {
            wp_send_json_error( array( 'message' => 'Tidsluken er ikke tilgjengelig for fellesbooking.' ) );
        }

        // Calculate price
        $pricing_service = new PricingService();
        $price = $pricing_service->getPrice(array_keys($slots_to_book), array_values($slots_to_book), $booking_date);
        
        if ($price === null) {
            // Fallback if no price defined, but in a real scenario we might want to block this
            $price = 0;
        }

        // Insert single booking record
        $booking_data = array(
            'booking_date' => $booking_date,
            'slot_id' => $first_slot_id,
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'customer_phone' => $customer_phone,
            'description' => $description,
            'price' => $price,
            'status' => 'pending',
            'created_at' => current_time( 'mysql' )
        );

        $booking_inserted = $wpdb->insert( $table_bookings, $booking_data );
        
        if ( ! $booking_inserted ) {
            wp_send_json_error( array( 'message' => 'Kunne ikke lagre booking. Vennligst prøv igjen.' ) );
        }

        $booking_id = $wpdb->insert_id;
        $success_count = 0;

        // Insert relationships in junction table
        foreach ($slots_to_book as $obj_id => $sid) {
            $junction_data = array(
                'booking_id' => $booking_id,
                'booking_object_id' => $obj_id,
                'created_at' => current_time( 'mysql' )
            );
            
            $result = $wpdb->insert( $table_booking_objects, $junction_data );
            if ($result) {
                $success_count++;
            }
        }

        if ( $success_count == count($booking_object_ids) ) {
            // Get object names for notification
            $table_objects = $wpdb->prefix . 'snippen_booking_objects';
            $in_clause = implode(',', array_fill(0, count($booking_object_ids), '%d'));
            $objects = $wpdb->get_results( $wpdb->prepare( "SELECT name FROM $table_objects WHERE id IN ($in_clause)", ...$booking_object_ids ) );
            $object_names = implode(' og ', wp_list_pluck($objects, 'name'));

            // Send email notification
            $to = get_option( 'admin_email' );
            $subject = 'Ny Bookingforespørsel - ' . $object_names;
            $message = "Ny bookingforespørsel mottatt:\n\n";
            $message .= "Lokale: " . $object_names . "\n";
            $message .= "Dato: " . $booking_date . "\n";
            $message .= "Navn: " . $customer_name . "\n";
            $message .= "Email: " . $customer_email . "\n";
            $message .= "Telefon: " . $customer_phone . "\n";
            $message .= "Beskrivelse: " . $description . "\n";

            wp_mail( $to, $subject, $message );

            wp_send_json_success( array(
                'message' => 'Bookingforespørsel sendt! Vi kontakter deg snart.'
            ) );
        } else {
            // Clean up the booking if junction inserts failed
            $wpdb->delete( $table_bookings, array( 'id' => $booking_id ) );
            wp_send_json_error( array( 'message' => 'Kunne ikke lagre alle bookinger. Vennligst prøv igjen.' ) );
        }
    }
}
