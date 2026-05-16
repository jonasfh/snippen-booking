<?php

namespace SnippenBooking\Api;

/**
 * Handles user-related AJAX requests
 */
class UserApi {

    /**
     * Register AJAX handlers
     */
    public static function register() {
        add_action( 'wp_ajax_snippen_search_users', array( __CLASS__, 'search_users' ) );
    }

    /**
     * Search users by name, login or email
     */
    public static function search_users() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Ingen tilgang.' ) );
        }

        $search = isset( $_GET['term'] ) ? sanitize_text_field( $_GET['term'] ) : '';

        if ( strlen( $search ) < 2 ) {
            wp_send_json_success( array() );
        }

        $users = get_users( array(
            'search'         => '*' . $search . '*',
            'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
            'number'         => 10,
            'fields'         => array( 'ID', 'display_name', 'user_email' ),
        ) );

        $results = array();
        foreach ( $users as $user ) {
            $results[] = array(
                'id'    => $user->ID,
                'name'  => $user->display_name,
                'email' => $user->user_email,
                'phone' => get_user_meta( $user->ID, 'snippen_phone', true ),
            );
        }

        wp_send_json_success( $results );
    }
}
