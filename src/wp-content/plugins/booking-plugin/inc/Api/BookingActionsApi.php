<?php

namespace SnippenBooking\Api;

/**
 * Handles AJAX actions for booking management (Approve/Cancel)
 */
class BookingActionsApi {

    /**
     * Register AJAX hooks
     */
    public static function register() {
        add_action( 'wp_ajax_snippen_update_booking_status', array( __CLASS__, 'update_status' ) );
    }

    /**
     * Update booking status
     */
    public static function update_status() {
        check_ajax_referer( 'snippen_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Ingen tilgang.', 'snippen-booking' ) ) );
        }

        $id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
        $status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';

        if ( ! $id || ! in_array( $status, array( 'confirmed', 'cancelled' ) ) ) {
            wp_send_json_error( array( 'message' => __( 'Ugyldig forespørsel.', 'snippen-booking' ) ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'snippen_bookings';

        $updated = $wpdb->update( 
            $table, 
            array( 'status' => $status, 'modified_at' => current_time( 'mysql' ) ), 
            array( 'id' => $id ) 
        );

        if ( $updated !== false ) {
            wp_send_json_success( array( 
                'message' => __( 'Status oppdatert.', 'snippen-booking' ),
                'new_status' => $status,
                'status_label' => self::get_status_label( $status )
            ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Kunne ikke oppdatere status.', 'snippen-booking' ) ) );
        }
    }

    /**
     * Get status label
     */
    private static function get_status_label( $status ) {
        $labels = array(
            'pending'   => __( 'Venter', 'snippen-booking' ),
            'confirmed' => __( 'Bekreftet', 'snippen-booking' ),
            'cancelled' => __( 'Avbrutt', 'snippen-booking' )
        );
        return isset( $labels[$status] ) ? $labels[$status] : $status;
    }
}
