<?php
/**
 * Migration 2.18.0
 *
 * @package SnippenBooking\Database\Migrations
 */

namespace SnippenBooking\Database\Migrations;

/**
 * Migration 2.18.0
 * Create snippen_messages table for tracking sent user communications.
 */
class Migration_2_18_0 {

	/**
	 * Run migration
	 */
	public function up() {
		global $wpdb;

		$table           = $wpdb->prefix . 'snippen_messages';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table (
			id BIGINT NOT NULL AUTO_INCREMENT,
			booking_id BIGINT NULL,
			user_id BIGINT NULL,
			channel VARCHAR(20) NOT NULL,
			recipient VARCHAR(255) NOT NULL,
			subject VARCHAR(255) NULL,
			message TEXT NOT NULL,
			event_type VARCHAR(50) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'sent',
			metadata LONGTEXT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			modified_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY booking_id (booking_id),
			KEY user_id (user_id),
			KEY recipient (recipient)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
