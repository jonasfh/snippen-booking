<?php
/**
 * Migration 2.44.0
 *
 * @package SnippenBooking\Database\Migrations
 */

namespace SnippenBooking\Database\Migrations;

/**
 * Migration 2.44.0
 * Create snippen_booking_snapshots table and backfill revision 1 for existing bookings.
 */
class Migration_2_44_0 {

	/**
	 * Run migration
	 */
	public function up() {
		global $wpdb;

		$table           = $wpdb->prefix . 'snippen_booking_snapshots';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table (
			id BIGINT NOT NULL AUTO_INCREMENT,
			booking_id BIGINT NOT NULL,
			revision INT NOT NULL DEFAULT 1,
			snapshot LONGTEXT NOT NULL,
			changes_summary TEXT NULL,
			modified_by_user_id BIGINT UNSIGNED NOT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			modified_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY booking_id (booking_id),
			KEY modified_by_user_id (modified_by_user_id)
		) $charset_collate;";

		if ( file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
		}

		// Backfill existing bookings with initial snapshot (revision 1) if not already recorded
		$table_bookings = $wpdb->prefix . 'snippen_bookings';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_bookings'" ) === $table_bookings ) {
			$unmigrated_bookings = $wpdb->get_results(
				"SELECT b.* FROM $table_bookings b
				 LEFT JOIN $table s ON b.id = s.booking_id
				 WHERE s.id IS NULL"
			);

			if ( ! empty( $unmigrated_bookings ) ) {
				$booking_repo = new \SnippenBooking\Database\Repository\BookingRepository();
				foreach ( $unmigrated_bookings as $b ) {
					$snapshot_str = $b->booking_snapshot;
					if ( empty( $snapshot_str ) ) {
						$booking_obj  = $booking_repo->find( $b->id );
						$obj_ids      = $booking_obj ? $booking_obj->booking_object_ids : array();
						$block_ids    = $booking_obj ? $booking_obj->booking_block_ids : array();
						$snapshot     = $booking_repo->build_snapshot( (array) $b, $obj_ids, $block_ids );
						$snapshot_str = wp_json_encode( $snapshot );
					}

					$user_id = ! empty( $b->user_id ) ? (int) $b->user_id : 1;

					$wpdb->insert(
						$table,
						array(
							'booking_id'          => (int) $b->id,
							'revision'            => 1,
							'snapshot'            => $snapshot_str,
							'changes_summary'     => __( 'Opprinnelig booking / migrert', 'snippen-booking' ),
							'modified_by_user_id' => $user_id,
							'created_at'          => ! empty( $b->created_at ) ? $b->created_at : current_time( 'mysql' ),
							'modified_at'         => ! empty( $b->modified_at ) ? $b->modified_at : current_time( 'mysql' ),
						)
					);
				}
			}
		}
	}
}
