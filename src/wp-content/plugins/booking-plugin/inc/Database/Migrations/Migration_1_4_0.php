<?php

namespace SnippenBooking\Database\Migrations;

/**
 * Migration for version 1.4.0
 * Adds door_code to booking_objects and bookings tables
 */
class Migration_1_4_0 {

	/**
	 * Run the migration
	 */
	public function up() {
		global $wpdb;
		$table_objects  = $wpdb->prefix . 'snippen_booking_objects';
		$table_bookings = $wpdb->prefix . 'snippen_bookings';

		// 1. Add door_code to snippen_booking_objects table if it doesn't exist
		$row_objects = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_objects' AND COLUMN_NAME = 'door_code'" );
		if ( empty( $row_objects ) ) {
			$wpdb->query( "ALTER TABLE $table_objects ADD COLUMN door_code VARCHAR(255) NULL DEFAULT NULL AFTER info_link" );
		}

		// 2. Add door_code to snippen_bookings table if it doesn't exist
		$row_bookings = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_bookings' AND COLUMN_NAME = 'door_code'" );
		if ( empty( $row_bookings ) ) {
			$wpdb->query( "ALTER TABLE $table_bookings ADD COLUMN door_code VARCHAR(255) NULL DEFAULT NULL AFTER status" );
		}
	}
}
