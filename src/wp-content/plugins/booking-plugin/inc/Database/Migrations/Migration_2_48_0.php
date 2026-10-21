<?php
/**
 * Migration 2.48.0
 *
 * @package SnippenBooking\Database\Migrations
 */

namespace SnippenBooking\Database\Migrations;

/**
 * Migration 2.48.0
 * Adds wp_snippen_door_locks table and door_lock_id column to wp_snippen_booking_objects.
 */
class Migration_2_48_0 {

	/**
	 * Run migration
	 */
	public function up() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_locks     = $wpdb->prefix . 'snippen_door_locks';
		$table_objects   = $wpdb->prefix . 'snippen_booking_objects';

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// 1. Create door locks table if not exists
		$sql_locks = "CREATE TABLE $table_locks (
            id INT NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            modified_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
		dbDelta( $sql_locks );

		// 2. Add door_lock_id to booking_objects if not exists
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$col_door_lock_id = $wpdb->get_results(
			"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_objects' AND COLUMN_NAME = 'door_lock_id'"
		);
		if ( empty( $col_door_lock_id ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( "ALTER TABLE $table_objects ADD COLUMN door_lock_id INT NULL AFTER description" );
		}

		// 3. Seed default lock and assign existing objects if empty
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count_locks = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_locks" );
		if ( 0 === $count_locks ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->insert(
				$table_locks,
				array(
					'id'          => 1,
					'name'        => 'Hovedinngang',
					'description' => 'Felles ytterdør',
					'created_at'  => current_time( 'mysql' ),
					'modified_at' => current_time( 'mysql' ),
				)
			);
			$default_lock_id = (int) $wpdb->insert_id ?: 1;

			// Assign existing booking objects to default lock
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE $table_objects SET door_lock_id = %d WHERE door_lock_id IS NULL AND deleted_at IS NULL",
					$default_lock_id
				)
			);
		}
	}
}
