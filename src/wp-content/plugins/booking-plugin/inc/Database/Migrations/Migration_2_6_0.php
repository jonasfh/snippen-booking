<?php

namespace SnippenBooking\Database\Migrations;

/**
 * Migration 2.6.0
 * Adds payment statuses table and payment tracking columns to bookings table.
 */
class Migration_2_6_0 {
	public function up() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Create payment statuses table
		$table_payment_statuses = $wpdb->prefix . 'snippen_payment_statuses';
		$sql_payment_statuses   = "CREATE TABLE $table_payment_statuses (
            id INT NOT NULL AUTO_INCREMENT,
            slug VARCHAR(50) NOT NULL,
            name VARCHAR(100) NOT NULL,
            is_settled TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            modified_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_payment_statuses );

		// Seed default payment statuses
		$default_statuses = array(
			array(
				'id'         => 1,
				'slug'       => 'UNPAID',
				'name'       => 'Mangler betaling',
				'is_settled' => 0,
			),
			array(
				'id'         => 2,
				'slug'       => 'PAID',
				'name'       => 'Betalt',
				'is_settled' => 1,
			),
			array(
				'id'         => 3,
				'slug'       => 'EXEMPT',
				'name'       => 'Fritatt / Gratis',
				'is_settled' => 1,
			),
		);

		foreach ( $default_statuses as $st ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_payment_statuses WHERE slug = %s", $st['slug'] ) );
			if ( ! $exists ) {
				$wpdb->insert( $table_payment_statuses, $st );
			} else {
				$wpdb->update( $table_payment_statuses, $st, array( 'slug' => $st['slug'] ) );
			}
		}

		// Delete obsolete PENDING_VERIFICATION status if present
		$wpdb->delete( $table_payment_statuses, array( 'slug' => 'PENDING_VERIFICATION' ) );

		// Add payment columns to bookings table
		$table_bookings = $wpdb->prefix . 'snippen_bookings';

		// Check payment_status_id
		$row = $wpdb->get_results( "SHOW COLUMNS FROM $table_bookings LIKE 'payment_status_id'" );
		if ( empty( $row ) ) {
			$wpdb->query( "ALTER TABLE $table_bookings ADD payment_status_id INT DEFAULT 1 AFTER status" );
		}

		// Check payment_receipt_attachment_id
		$row = $wpdb->get_results( "SHOW COLUMNS FROM $table_bookings LIKE 'payment_receipt_attachment_id'" );
		if ( empty( $row ) ) {
			$wpdb->query( "ALTER TABLE $table_bookings ADD payment_receipt_attachment_id BIGINT UNSIGNED NULL AFTER payment_status_id" );
		}

		// Check payment_notes
		$row = $wpdb->get_results( "SHOW COLUMNS FROM $table_bookings LIKE 'payment_notes'" );
		if ( empty( $row ) ) {
			$wpdb->query( "ALTER TABLE $table_bookings ADD payment_notes TEXT NULL AFTER payment_receipt_attachment_id" );
		}

		// Check payment_updated_at
		$row = $wpdb->get_results( "SHOW COLUMNS FROM $table_bookings LIKE 'payment_updated_at'" );
		if ( empty( $row ) ) {
			$wpdb->query( "ALTER TABLE $table_bookings ADD payment_updated_at DATETIME NULL AFTER payment_notes" );
		}
	}
}
