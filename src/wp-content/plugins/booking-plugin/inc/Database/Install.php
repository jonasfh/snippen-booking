<?php

namespace SnippenBooking\Database;

use SnippenBooking\Helper\Capabilities;

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

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Booking objects table
		$table_objects = $wpdb->prefix . 'snippen_booking_objects';
		$sql_objects   = "CREATE TABLE $table_objects (
            id INT NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            info_link VARCHAR(255),
            door_code VARCHAR(255) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            modified_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
		dbDelta( $sql_objects );

		// Time slots table
		$table_slots = $wpdb->prefix . 'snippen_time_slots';
		$sql_slots   = "CREATE TABLE $table_slots (
            id INT NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            start_time TIME DEFAULT '00:00:00',
            end_time TIME DEFAULT '23:59:59',
            cleanup_hours INT DEFAULT 0,
            allow_multi_object TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            modified_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
		dbDelta( $sql_slots );

		// Bookings table
		$table_bookings = $wpdb->prefix . 'snippen_bookings';
		$sql_bookings   = "CREATE TABLE $table_bookings (
            id BIGINT NOT NULL AUTO_INCREMENT,
            uuid VARCHAR(36) NULL,
            facility VARCHAR(50),
            user_id BIGINT UNSIGNED NOT NULL,
            slot_id INT NOT NULL,
            booking_date DATE NOT NULL,
            customer_name VARCHAR(255) NOT NULL,
            customer_email VARCHAR(255) NOT NULL,
            customer_phone VARCHAR(50) DEFAULT '',
            description TEXT,
            price DECIMAL(10,2) DEFAULT 0,
            status VARCHAR(20) DEFAULT 'pending',
            door_code VARCHAR(255) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            modified_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uuid (uuid),
            KEY booking_date (booking_date),
            KEY slot_id (slot_id),
            KEY user_id (user_id)
        ) $charset_collate;";
		dbDelta( $sql_bookings );

		// Booking objects junction table (many-to-many relationship)
		$table_booking_objects = $wpdb->prefix . 'snippen_bookings_booking_objects';
		$sql_booking_objects   = "CREATE TABLE $table_booking_objects (
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
		$sql_prices   = "CREATE TABLE $table_prices (
            id INT NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            price DECIMAL(10,2) NOT NULL,
            slot_id INT NOT NULL,
            priority INT DEFAULT 0,
            days_of_week VARCHAR(20) DEFAULT NULL,
            is_holiday TINYINT(1) DEFAULT 0,
            date_start DATE DEFAULT NULL,
            date_end DATE DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            modified_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY slot_id (slot_id),
            KEY priority (priority)
        ) $charset_collate;";
		dbDelta( $sql_prices );

		// Price booking objects junction table
		$table_price_objects = $wpdb->prefix . 'snippen_price_booking_objects';
		$sql_price_objects   = "CREATE TABLE $table_price_objects (
            id INT NOT NULL AUTO_INCREMENT,
            price_id INT NOT NULL,
            booking_object_id INT NOT NULL,
            PRIMARY KEY  (id),
            KEY price_id (price_id),
            KEY booking_object_id (booking_object_id),
            UNIQUE KEY unique_price_object (price_id, booking_object_id)
        ) $charset_collate;";
		dbDelta( $sql_price_objects );

		// Run migrations
		MigrationManager::run();

		// Register plugin capabilities
		// NOTE: Capabilities are NOT automatically assigned to any role.
		// Site administrators must manually assign them to roles via admin UI.
		self::register_capabilities();

		// Register custom resident role (Issue #37)
		$subscriber   = get_role( 'subscriber' );
		$capabilities = $subscriber ? $subscriber->capabilities : array( 'read' => true );
		add_role( 'holmen_resident', __( 'Holmen Sameie Beboer', 'snippen-booking' ), $capabilities );
	}

	/**
	 * Register plugin-specific capabilities.
	 *
	 * This method makes plugin capabilities available in WordPress.
	 * Capabilities are NOT automatically assigned to any role - site administrators
	 * must manually assign them to roles via the admin UI or programmatically.
	 *
	 * @return void
	 */
	private static function register_capabilities() {
		// Plugin capabilities are defined in the Capabilities helper class.
		// They can be assigned to roles via WordPress admin UI or programmatically.
		// This is a documentation point - capabilities are defined in Capabilities::get_all_capabilities()
	}
}
