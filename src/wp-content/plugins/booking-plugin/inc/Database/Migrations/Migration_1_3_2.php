<?php

namespace SnippenBooking\Database\Migrations;

/**
 * Migration for version 1.3.2
 * Adds unique uuid column to bookings and generates values for existing records
 */
class Migration_1_3_2 {

	/**
	 * Run the migration
	 */
	public function up() {
		global $wpdb;
		$table_bookings = $wpdb->prefix . 'snippen_bookings';

		// 1. Add uuid column if it doesn't exist
		$row = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_bookings' AND COLUMN_NAME = 'uuid'" );

		if ( empty( $row ) ) {
			// Add as NULL to allow existing rows and tests to work seamlessly without forcing NOT NULL constraint issues.
			$wpdb->query( "ALTER TABLE $table_bookings ADD COLUMN uuid VARCHAR(36) NULL AFTER id" );
		}

		// 2. Data Migration: Backfill UUIDs for any existing bookings
		$existing_bookings = $wpdb->get_results( "SELECT id FROM $table_bookings WHERE uuid IS NULL OR uuid = ''" );

		if ( ! empty( $existing_bookings ) ) {
			foreach ( $existing_bookings as $booking ) {
				$uuid = wp_generate_uuid4();
				$wpdb->update(
					$table_bookings,
					array( 'uuid' => $uuid ),
					array( 'id' => $booking->id )
				);
			}
		}

		// 3. Add a unique index on uuid column if it doesn't exist already
		$index_exists = $wpdb->get_results( "SHOW INDEX FROM $table_bookings WHERE Key_name = 'uuid'" );
		if ( empty( $index_exists ) ) {
			$wpdb->query( "ALTER TABLE $table_bookings ADD UNIQUE KEY uuid (uuid)" );
		}
	}
}
