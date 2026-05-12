<?php

namespace SnippenBooking\Database;

/**
 * Handles plugin activation and database setup
 */
class Install {

    /**
     * Run activation tasks
     */
    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        // Time slots table
        $table_slots = $wpdb->prefix . 'snippen_time_slots';
        $sql_slots = "CREATE TABLE $table_slots (
            id INT NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            start_time TIME DEFAULT '00:00:00',
            end_time TIME DEFAULT '23:59:59',
            cleanup_hours INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            modified_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";
        dbDelta( $sql_slots );

        // Bookings table
        $table_bookings = $wpdb->prefix . 'snippen_bookings';
        $sql_bookings = "CREATE TABLE $table_bookings (
            id BIGINT NOT NULL AUTO_INCREMENT,
            facility VARCHAR(50) NOT NULL,
            slot_id INT NOT NULL,
            booking_date DATE NOT NULL,
            customer_name VARCHAR(255) NOT NULL,
            customer_email VARCHAR(255) NOT NULL,
            customer_phone VARCHAR(50) DEFAULT '',
            description TEXT,
            status VARCHAR(20) DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            modified_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            PRIMARY KEY (id),
            INDEX (booking_date),
            INDEX (facility),
            INDEX (slot_id)
        ) $charset_collate;";
        dbDelta( $sql_bookings );

        // Insert default slot if not exists
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_slots WHERE name = %s", 'Hele dagen' ) );
        if ( ! $exists ) {
            $wpdb->insert( $table_slots, array(
                'name' => 'Hele dagen',
                'description' => 'Du booker rommet for hele dagen, og har til kl 12 neste dag til å vaske deg ut.',
                'start_time' => '00:00:00',
                'end_time' => '23:59:59',
                'cleanup_hours' => 12
            ) );
        }
    }
}
