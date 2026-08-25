<?php
/**
 * Migration 2.21.0
 *
 * @package SnippenBooking\Database\Migrations
 */

namespace SnippenBooking\Database\Migrations;

use SnippenBooking\Database\Repository\NotificationTemplateRepository;

/**
 * Migration 2.21.0
 * Create snippen_booking_payment_reminders table and seed default payment reminder templates.
 */
class Migration_2_21_0 {

	/**
	 * Run migration
	 */
	public function up() {
		global $wpdb;

		$table           = $wpdb->prefix . 'snippen_booking_payment_reminders';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table (
			id BIGINT NOT NULL AUTO_INCREMENT,
			booking_id BIGINT NOT NULL,
			days_before INT NOT NULL,
			sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			modified_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY booking_days (booking_id, days_before),
			KEY booking_id (booking_id)
		) $charset_collate;";

		if ( file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
		}

		$repository = new NotificationTemplateRepository();
		$repository->seed_defaults();
	}
}
