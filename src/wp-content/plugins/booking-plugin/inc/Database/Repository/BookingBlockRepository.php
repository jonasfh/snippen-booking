<?php

namespace SnippenBooking\Database\Repository;

/**
 * Repository for booking blocks.
 */
class BookingBlockRepository {

	/**
	 * Find a booking block by ID.
	 *
	 * @param int $id
	 * @return object|null
	 */
	public function find( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_booking_blocks';
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE id = %d AND deleted_at IS NULL",
				(int) $id
			)
		);
		return $row ? $row : null;
	}

	/**
	 * Find all active booking blocks.
	 *
	 * @return array
	 */
	public function find_all() {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_booking_blocks';
		return $wpdb->get_results(
			"SELECT * FROM $table WHERE deleted_at IS NULL ORDER BY sort_order ASC"
		);
	}

	/**
	 * Find booking blocks by a list of IDs.
	 *
	 * @param array $ids
	 * @return array
	 */
	public function find_by_ids( array $ids ) {
		global $wpdb;
		if ( empty( $ids ) ) {
			return array();
		}
		$ids          = array_map( 'intval', $ids );
		$table        = $wpdb->prefix . 'snippen_booking_blocks';
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE id IN ($placeholders) AND deleted_at IS NULL ORDER BY sort_order ASC",
				...$ids
			)
		);
	}

	/**
	 * Find booking blocks mapped to a specific booking object.
	 *
	 * @param int $object_id
	 * @return array
	 */
	public function find_by_booking_object( $object_id ) {
		global $wpdb;
		$table_blocks   = $wpdb->prefix . 'snippen_booking_blocks';
		$table_junction = $wpdb->prefix . 'snippen_booking_object_booking_blocks';
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.* FROM $table_blocks b
				 JOIN $table_junction j ON b.id = j.booking_block_id
				 WHERE j.booking_object_id = %d AND b.deleted_at IS NULL
				 ORDER BY b.sort_order ASC",
				(int) $object_id
			)
		);
	}
}
