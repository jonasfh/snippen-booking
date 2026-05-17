<?php

namespace SnippenBooking\Database\Migrations;

/**
 * Migration for version 1.0.0
 * Adds mandatory user_id to bookings and migrates existing data
 */
class Migration_1_0_0 {

	/**
	 * Run the migration
	 */
	public function up() {
		global $wpdb;
		$table_bookings = $wpdb->prefix . 'snippen_bookings';

		// 1. Add user_id column if it doesn't exist
		$row = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_bookings' AND COLUMN_NAME = 'user_id'" );

		if ( empty( $row ) ) {
			$wpdb->query( "ALTER TABLE $table_bookings ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER slot_id" );
			$wpdb->query( "ALTER TABLE $table_bookings ADD INDEX (user_id)" );
		}

		// 2. Data Migration: Assign existing bookings to the first administrator found
		$admin_id = $this->get_default_admin_id();

		if ( $admin_id ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE $table_bookings SET user_id = %d WHERE user_id IS NULL",
					$admin_id
				)
			);
		}

		// 3. Make user_id NOT NULL
		$wpdb->query( "ALTER TABLE $table_bookings MODIFY COLUMN user_id BIGINT UNSIGNED NOT NULL" );
	}

	/**
	 * Get the ID of the first administrator
	 */
	private function get_default_admin_id() {
		$admins = get_users(
			array(
				'role'    => 'administrator',
				'number'  => 1,
				'orderby' => 'ID',
				'order'   => 'ASC',
				'fields'  => 'ID',
			)
		);

		return ! empty( $admins ) ? $admins[0] : 0;
	}
}
