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

        // Booking objects table
        $table_objects = $wpdb->prefix . 'snippen_booking_objects';
        $sql_objects = "CREATE TABLE $table_objects (
            id INT NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            info_link VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            modified_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta( $sql_objects );

        // Time slots table
        $table_slots = $wpdb->prefix . 'snippen_time_slots';
        $sql_slots = "CREATE TABLE $table_slots (
            id INT NOT NULL AUTO_INCREMENT,
            booking_object_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            start_time TIME DEFAULT '00:00:00',
            end_time TIME DEFAULT '23:59:59',
            cleanup_hours INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            modified_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY booking_object_id (booking_object_id)
        ) $charset_collate;";
        dbDelta( $sql_slots );

        // Bookings table
        $table_bookings = $wpdb->prefix . 'snippen_bookings';
        $sql_bookings = "CREATE TABLE $table_bookings (
            id BIGINT NOT NULL AUTO_INCREMENT,
            facility VARCHAR(50),
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
            PRIMARY KEY  (id),
            KEY booking_date (booking_date),
            KEY slot_id (slot_id)
        ) $charset_collate;";
        dbDelta( $sql_bookings );

        // Booking objects junction table (many-to-many relationship)
        $table_booking_objects = $wpdb->prefix . 'snippen_bookings_booking_objects';
        $sql_booking_objects = "CREATE TABLE $table_booking_objects (
            id INT NOT NULL AUTO_INCREMENT,
            booking_id BIGINT NOT NULL,
            booking_object_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            modified_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY booking_id (booking_id),
            KEY booking_object_id (booking_object_id),
            UNIQUE KEY unique_booking_object (booking_id, booking_object_id)
        ) $charset_collate;";
        dbDelta( $sql_booking_objects );

        // Seed data if empty
        $object_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_objects" );
        if ( $object_count == 0 ) {
            // 1. Festsalen
            $wpdb->insert( $table_objects, array(
                'name' => 'Festsalen',
                'description' => 'Vårt største lokale med plass til mange gjester.'
            ) );
            $festsalen_id = $wpdb->insert_id;

            // 2. Peisestuen
            $wpdb->insert( $table_objects, array(
                'name' => 'Peisestuen',
                'description' => 'Koselig lokale med peis, perfekt for mindre samlinger.'
            ) );
            $peisestuen_id = $wpdb->insert_id;

            // Seed slots for Festsalen
            $this_slots = array(
                array(
                    'name' => 'Hele dagen',
                    'description' => 'Du booker rommet fra kl 11 til 23, og har til kl 11 neste dag til å rydde og vaske ut.',
                    'start_time' => '11:00:00',
                    'end_time' => '23:00:00',
                    'cleanup_hours' => 12
                ),
                array(
                    'name' => 'Formiddag',
                    'description' => 'Fra kl 08:00 til 16:00. Du må vaske og ryddet lokalet når du forlater det.',
                    'start_time' => '08:00:00',
                    'end_time' => '16:00:00',
                    'cleanup_hours' => 0
                ),
                array(
                    'name' => 'Ettermiddag',
                    'description' => 'Fra kl 16:00 til 23:00. Du har til kl 08:00 neste dag til å vaske deg ut',
                    'start_time' => '16:00:00',
                    'end_time' => '23:00:00',
                    'cleanup_hours' => 9
                )
            );

            foreach ( array( $festsalen_id, $peisestuen_id ) as $obj_id ) {
                foreach ( $this_slots as $slot ) {
                    $wpdb->insert( $table_slots, array_merge( $slot, array( 'booking_object_id' => $obj_id ) ) );
                }
            }
        }
    }
}

