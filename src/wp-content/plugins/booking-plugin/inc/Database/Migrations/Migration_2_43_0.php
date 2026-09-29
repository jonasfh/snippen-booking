<?php

namespace SnippenBooking\Database\Migrations;

/**
 * Migration 2.43.0
 * Adds a dedicated booking snapshot history table and backfills the initial revision.
 */
class Migration_2_43_0 {

	public function up() {
		global $wpdb;

		$table_bookings  = $wpdb->prefix . 'snippen_bookings';
		$table_history   = $wpdb->prefix . 'snippen_booking_snapshots';
		$charset_collate = $wpdb->get_charset_collate();

		$exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_history'" );
		if ( $exists !== $table_history ) {
			$wpdb->query(
				"CREATE TABLE $table_history (
					id BIGINT NOT NULL AUTO_INCREMENT,
					booking_id BIGINT NOT NULL,
					revision INT NOT NULL DEFAULT 1,
					snapshot LONGTEXT NOT NULL,
					changes_summary TEXT NULL,
					modified_by_user_id BIGINT UNSIGNED NOT NULL,
					created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
					PRIMARY KEY  (id),
					KEY booking_id (booking_id),
					KEY modified_by_user_id (modified_by_user_id)
				) $charset_collate;"
			);
		}

		$bookings = $wpdb->get_results( "SELECT * FROM $table_bookings WHERE deleted_at IS NULL" );
		foreach ( $bookings as $booking ) {
			$existing_revision = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(MAX(revision), 0) FROM $table_history WHERE booking_id = %d",
					(int) $booking->id
				)
			);
			if ( $existing_revision > 0 ) {
				continue;
			}

			$snapshot = $booking->booking_snapshot;
			if ( empty( $snapshot ) ) {
				continue;
			}

			$wpdb->insert(
				$table_history,
				array(
					'booking_id'          => (int) $booking->id,
					'revision'            => 1,
					'snapshot'            => (string) $snapshot,
					'changes_summary'     => 'Initial booking snapshot',
					'modified_by_user_id' => (int) $booking->user_id,
					'created_at'          => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%s', '%d', '%s' )
			);
		}
	}
}
