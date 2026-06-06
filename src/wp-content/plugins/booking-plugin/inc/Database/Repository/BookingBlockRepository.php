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

	/**
	 * Save a booking block (insert or update).
	 *
	 * @param array $data
	 * @param int|null $id
	 * @return int|false The ID of the block, or false on failure.
	 */
	public function save( array $data, $id = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_booking_blocks';

		$data['modified_at'] = current_time( 'mysql' );

		if ( $id ) {
			$wpdb->update( $table, $data, array( 'id' => $id ) );
			return $id;
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $data );
			return $wpdb->insert_id;
		}
	}

	/**
	 * Soft delete a booking block.
	 *
	 * @param int $id
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_booking_blocks';
		$result = $wpdb->update( 
			$table, 
			array( 'deleted_at' => current_time( 'mysql' ) ), 
			array( 'id' => $id ) 
		);
		return $result !== false;
	}

	/**
	 * Sync booking objects for a booking block.
	 *
	 * @param int $block_id
	 * @param array $object_ids
	 * @return void
	 */
	public function sync_booking_objects( $block_id, array $object_ids ) {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_booking_object_booking_blocks';
		
		$wpdb->delete( $table, array( 'booking_block_id' => $block_id ) );
		
		foreach ( $object_ids as $object_id ) {
			$wpdb->insert(
				$table,
				array(
					'booking_block_id' => $block_id,
					'booking_object_id' => (int) $object_id,
				)
			);
		}
	}

	/**
	 * Check if there is an overlap with another booking block for the same object and days.
	 *
	 * @param int|null $exclude_id Block ID to exclude from check (when updating).
	 * @param string $start_time e.g., '08:00:00'
	 * @param string $end_time e.g., '16:00:00'
	 * @param array $object_ids
	 * @param string|null $days_of_week Comma separated string of days e.g., '1,2,3'
	 * @return bool
	 */
	public function has_overlap( $exclude_id, $start_time, $end_time, array $object_ids, $days_of_week ) {
		global $wpdb;
		
		if ( empty( $object_ids ) ) {
			return false;
		}
		
		$table_blocks = $wpdb->prefix . 'snippen_booking_blocks';
		$table_junction = $wpdb->prefix . 'snippen_booking_object_booking_blocks';
		
		$placeholders = implode( ',', array_fill( 0, count( $object_ids ), '%d' ) );
		$query = "SELECT b.id, b.days_of_week FROM $table_blocks b 
		          JOIN $table_junction j ON b.id = j.booking_block_id 
		          WHERE j.booking_object_id IN ($placeholders) 
		          AND b.deleted_at IS NULL 
		          AND (
		            (b.start_time < %s AND b.end_time > %s) OR
		            (b.start_time >= %s AND b.start_time < %s)
		          )";
		
		$args = array_merge( 
			$object_ids, 
			array( $end_time, $start_time, $start_time, $end_time ) 
		);
		
		if ( $exclude_id ) {
			$query .= " AND b.id != %d";
			$args[] = $exclude_id;
		}
		
		$overlapping_blocks = $wpdb->get_results( $wpdb->prepare( $query, ...$args ) );
		
		if ( empty( $overlapping_blocks ) ) {
			return false;
		}
		
		// If days_of_week is empty, it applies to all days.
		// We need to check if there's a day overlap.
		$new_days = empty( $days_of_week ) ? array() : explode( ',', $days_of_week );
		
		foreach ( $overlapping_blocks as $block ) {
			$existing_days = empty( $block->days_of_week ) ? array() : explode( ',', $block->days_of_week );
			
			// If either applies to all days (empty array), they overlap.
			if ( empty( $new_days ) || empty( $existing_days ) ) {
				return true;
			}
			
			// If they share any day, they overlap.
			$intersection = array_intersect( $new_days, $existing_days );
			if ( ! empty( $intersection ) ) {
				return true;
			}
		}
		
		return false;
	}
}
