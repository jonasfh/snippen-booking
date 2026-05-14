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
            allow_multi_object TINYINT(1) DEFAULT 0,
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
            price DECIMAL(10,2) DEFAULT 0,
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

        // Pricing table
        $table_prices = $wpdb->prefix . 'snippen_prices';
        $sql_prices = "CREATE TABLE $table_prices (
            id INT NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            price DECIMAL(10,2) NOT NULL,
            slot_name VARCHAR(50) NOT NULL,
            priority INT DEFAULT 0,
            days_of_week VARCHAR(20) DEFAULT NULL,
            is_holiday TINYINT(1) DEFAULT 0,
            date_start DATE DEFAULT NULL,
            date_end DATE DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            modified_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY slot_name (slot_name),
            KEY priority (priority)
        ) $charset_collate;";
        dbDelta( $sql_prices );

        // Price booking objects junction table
        $table_price_objects = $wpdb->prefix . 'snippen_price_booking_objects';
        $sql_price_objects = "CREATE TABLE $table_price_objects (
            id INT NOT NULL AUTO_INCREMENT,
            price_id INT NOT NULL,
            booking_object_id INT NOT NULL,
            PRIMARY KEY  (id),
            KEY price_id (price_id),
            KEY booking_object_id (booking_object_id),
            UNIQUE KEY unique_price_object (price_id, booking_object_id)
        ) $charset_collate;";
        dbDelta( $sql_price_objects );

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
                    'cleanup_hours' => 12,
                    'allow_multi_object' => 1
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
        
        // Seed prices if empty
        $price_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_prices" );
        if ( $price_count == 0 ) {
            $slots = ['Hele dagen', 'Formiddag', 'Ettermiddag'];
            $base_prices = [
                'Hele dagen' => 2000,
                'Formiddag' => 1000,
                'Ettermiddag' => 1000
            ];

            // 1. Individual prices for each object
            $objects = $wpdb->get_results( "SELECT id, name FROM $table_objects" );
            foreach ( $objects as $obj ) {
                foreach ( $slots as $slot_name ) {
                    $price = $base_prices[$slot_name];
                    if ($obj->name === 'Peisestuen') $price *= 0.8; // Peisestuen is cheaper
                    
                    // Standard Weekday Price (Mon-Thu)
                    $wpdb->insert( $table_prices, [
                        'name' => $obj->name . ' - ' . $slot_name . ' (Hverdag)',
                        'price' => $price,
                        'slot_name' => $slot_name,
                        'days_of_week' => '1,2,3,4',
                        'priority' => 0
                    ] );
                    $price_id = $wpdb->insert_id;
                    $wpdb->insert( $table_price_objects, [
                        'price_id' => $price_id,
                        'booking_object_id' => $obj->id
                    ] );

                    // Weekend Price (Fri-Sun)
                    $wpdb->insert( $table_prices, [
                        'name' => $obj->name . ' - ' . $slot_name . ' (Helg)',
                        'price' => $price * 1.5,
                        'slot_name' => $slot_name,
                        'days_of_week' => '5,6,0',
                        'priority' => 10
                    ] );
                    $price_id = $wpdb->insert_id;
                    $wpdb->insert( $table_price_objects, [
                        'price_id' => $price_id,
                        'booking_object_id' => $obj->id
                    ] );
                }
            }

            // 2. Combined prices (Hele området)
            // Standard
            $wpdb->insert( $table_prices, [
                'name' => 'Hele området - Hele dagen (Hverdag)',
                'price' => 4000,
                'slot_name' => 'Hele dagen',
                'days_of_week' => '1,2,3,4',
                'priority' => 0
            ] );
            $combined_price_id = $wpdb->insert_id;
            foreach ( $objects as $obj ) {
                $wpdb->insert( $table_price_objects, [
                    'price_id' => $combined_price_id,
                    'booking_object_id' => $obj->id
                ] );
            }

            // Weekend
            $wpdb->insert( $table_prices, [
                'name' => 'Hele området - Hele dagen (Helg)',
                'price' => 6000,
                'slot_name' => 'Hele dagen',
                'days_of_week' => '5,6,0',
                'priority' => 10
            ] );
            $combined_price_id = $wpdb->insert_id;
            foreach ( $objects as $obj ) {
                $wpdb->insert( $table_price_objects, [
                    'price_id' => $combined_price_id,
                    'booking_object_id' => $obj->id
                ] );
            }

            // 3. Special Holiday Price (General)
            $wpdb->insert( $table_prices, [
                'name' => 'Helligdagstillegg (Alle lokaler)',
                'price' => 8000,
                'slot_name' => 'Hele dagen',
                'is_holiday' => 1,
                'priority' => 100
            ] );
            $holiday_price_id = $wpdb->insert_id;
            foreach ( $objects as $obj ) {
                $wpdb->insert( $table_price_objects, [
                    'price_id' => $holiday_price_id,
                    'booking_object_id' => $obj->id
                ] );
            }
        }
    }
}

