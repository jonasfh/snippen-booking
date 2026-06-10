<?php

namespace SnippenBooking\Database\Migrations;

/**
 * Migration 1.26.0
 * Adds discount rules tables and adds discount columns to bookings.
 */
class Migration_1_26_0 {
	public function up() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Create discount rules table
		$table_discount_rules = $wpdb->prefix . 'snippen_discount_rules';
		$sql_discount_rules   = "CREATE TABLE $table_discount_rules (
            id INT NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            discount_type VARCHAR(20) NOT NULL,
            discount_value DECIMAL(10,2) NOT NULL,
            min_duration_hours DECIMAL(10,2) NULL,
            max_duration_hours DECIMAL(10,2) NULL,
            days_of_week VARCHAR(50) NULL,
            holiday_only TINYINT(1) DEFAULT 0,
            priority INT DEFAULT 10,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            modified_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY priority (priority)
        ) $charset_collate;";

		// Create discount rule booking objects junction table
		$table_discount_rule_objects = $wpdb->prefix . 'snippen_discount_rule_booking_objects';
		$sql_discount_rule_objects   = "CREATE TABLE $table_discount_rule_objects (
            discount_rule_id INT NOT NULL,
            booking_object_id INT NOT NULL,
            PRIMARY KEY  (discount_rule_id, booking_object_id),
            KEY discount_rule_id (discount_rule_id),
            KEY booking_object_id (booking_object_id)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_discount_rules );
		dbDelta( $sql_discount_rule_objects );

		// Add discount_amount and discount_rule_id to bookings table
		$table_bookings = $wpdb->prefix . 'snippen_bookings';

		// Check if discount_amount column exists
		$row = $wpdb->get_results( "SHOW COLUMNS FROM $table_bookings LIKE 'discount_amount'" );
		if ( empty( $row ) ) {
			$wpdb->query( "ALTER TABLE $table_bookings ADD discount_amount DECIMAL(10,2) DEFAULT 0 AFTER price" );
		}

		// Check if discount_rule_id column exists
		$row = $wpdb->get_results( "SHOW COLUMNS FROM $table_bookings LIKE 'discount_rule_id'" );
		if ( empty( $row ) ) {
			$wpdb->query( "ALTER TABLE $table_bookings ADD discount_rule_id INT NULL AFTER discount_amount" );
		}
	}
}
