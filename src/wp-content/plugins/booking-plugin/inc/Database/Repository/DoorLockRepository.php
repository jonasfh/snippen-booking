<?php
/**
 * Repository for Door Locks
 *
 * @package SnippenBooking\Database\Repository
 */

namespace SnippenBooking\Database\Repository;

/**
 * Class DoorLockRepository
 */
class DoorLockRepository {

	/**
	 * Find a door lock by ID.
	 *
	 * @param int $id Door lock ID.
	 * @return object|null Lock object or null if not found.
	 */
	public function find( int $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_door_locks';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE id = %d AND deleted_at IS NULL",
				$id
			)
		);

		return $row ? $row : null;
	}

	/**
	 * Find all active door locks.
	 *
	 * @param bool $include_deleted Whether to include soft-deleted locks.
	 * @return array Array of lock objects.
	 */
	public function find_all( bool $include_deleted = false ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_door_locks';

		$where = $include_deleted ? '' : 'WHERE deleted_at IS NULL';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results( "SELECT * FROM $table $where ORDER BY id ASC" );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Find all active door locks with their associated booking objects (venues).
	 *
	 * @return array Array of locks formatted with 'handles_objects'.
	 */
	public function find_all_with_objects(): array {
		global $wpdb;
		$table_locks   = $wpdb->prefix . 'snippen_door_locks';
		$table_objects = $wpdb->prefix . 'snippen_booking_objects';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$locks = $wpdb->get_results(
			"SELECT * FROM $table_locks WHERE deleted_at IS NULL ORDER BY id ASC"
		);

		if ( empty( $locks ) ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$objects = $wpdb->get_results(
			"SELECT id, name, door_lock_id FROM $table_objects WHERE deleted_at IS NULL AND door_lock_id IS NOT NULL ORDER BY name ASC"
		);

		$objects_by_lock = array();
		if ( is_array( $objects ) ) {
			foreach ( $objects as $obj ) {
				$lock_id = (int) $obj->door_lock_id;
				if ( ! isset( $objects_by_lock[ $lock_id ] ) ) {
					$objects_by_lock[ $lock_id ] = array();
				}
				$objects_by_lock[ $lock_id ][] = array(
					'id'   => (int) $obj->id,
					'name' => $obj->name,
				);
			}
		}

		$formatted = array();
		foreach ( $locks as $lock ) {
			$lock_id     = (int) $lock->id;
			$formatted[] = array(
				'id'              => $lock_id,
				'name'            => $lock->name,
				'handles_objects' => $objects_by_lock[ $lock_id ] ?? array(),
			);
		}

		return $formatted;
	}

	/**
	 * Create a new door lock.
	 *
	 * @param array $data Lock data (name, description).
	 * @return int Inserted lock ID or 0 on failure.
	 */
	public function create( array $data ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_door_locks';

		$name        = sanitize_text_field( $data['name'] ?? '' );
		$description = isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : null;

		if ( empty( $name ) ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$table,
			array(
				'name'        => $name,
				'description' => $description,
				'created_at'  => current_time( 'mysql' ),
				'modified_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Update an existing door lock.
	 *
	 * @param int   $id Lock ID.
	 * @param array $data Lock data to update.
	 * @return bool True on success, false otherwise.
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_door_locks';

		$update_data = array(
			'modified_at' => current_time( 'mysql' ),
		);

		if ( isset( $data['name'] ) ) {
			$update_data['name'] = sanitize_text_field( $data['name'] );
		}
		if ( array_key_exists( 'description', $data ) ) {
			$update_data['description'] = ! is_null( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->update(
			$table,
			$update_data,
			array( 'id' => $id ),
			null,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Soft delete a door lock.
	 *
	 * @param int $id Lock ID.
	 * @return bool True on success, false otherwise.
	 */
	public function delete( int $id ): bool {
		global $wpdb;
		$table_locks   = $wpdb->prefix . 'snippen_door_locks';
		$table_objects = $wpdb->prefix . 'snippen_booking_objects';

		// Unassign venues pointing to this lock
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update(
			$table_objects,
			array( 'door_lock_id' => null ),
			array( 'door_lock_id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		// Soft delete lock
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->update(
			$table_locks,
			array(
				'deleted_at'  => current_time( 'mysql' ),
				'modified_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}
}
