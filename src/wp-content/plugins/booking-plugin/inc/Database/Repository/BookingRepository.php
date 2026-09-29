<?php

namespace SnippenBooking\Database\Repository;

/**
 * Repository for bookings.
 */
class BookingRepository {

	/**
	 * Find a booking by ID.
	 *
	 * @param int $id
	 * @return object|null
	 */
	public function find( $id ) {
		global $wpdb;
		$table   = $wpdb->prefix . 'snippen_bookings';
		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE id = %d AND deleted_at IS NULL",
				(int) $id
			)
		);
		if ( ! $booking ) {
			return null;
		}
		$this->hydrate_relations( $booking );
		return $booking;
	}

	/**
	 * Find a booking by UUID.
	 *
	 * @param string $uuid
	 * @return object|null
	 */
	public function find_by_uuid( $uuid ) {
		global $wpdb;
		$table   = $wpdb->prefix . 'snippen_bookings';
		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE uuid = %s AND deleted_at IS NULL",
				$uuid
			)
		);
		if ( ! $booking ) {
			return null;
		}
		$this->hydrate_relations( $booking );
		return $booking;
	}

	/**
	 * Find bookings within a date range for a specific object.
	 *
	 * @param int    $object_id
	 * @param string $start_date YYYY-MM-DD
	 * @param string $end_date YYYY-MM-DD
	 * @return array
	 */
	public function find_by_object_and_date_range( $object_id, $start_date, $end_date ) {
		global $wpdb;
		$table_bookings = $wpdb->prefix . 'snippen_bookings';
		$table_junction = $wpdb->prefix . 'snippen_booking_booking_objects';

		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.*
				 FROM $table_bookings b
				 JOIN $table_junction j ON b.id = j.booking_id
				 WHERE j.booking_object_id = %d
				   AND b.booking_date BETWEEN %s AND %s
				   AND b.deleted_at IS NULL
				   AND b.status != 'cancelled'",
				(int) $object_id,
				$start_date,
				$end_date
			)
		);

		foreach ( $bookings as $booking ) {
			$this->hydrate_relations( $booking );
		}

		return $bookings;
	}

	/**
	 * Hydrate a booking with its blocks and objects.
	 *
	 * @param object $booking
	 */
	private function hydrate_relations( $booking ) {
		global $wpdb;
		$table_booking_blocks  = $wpdb->prefix . 'snippen_booking_booking_blocks';
		$table_booking_objects = $wpdb->prefix . 'snippen_booking_booking_objects';

		// Decode snapshot if present
		if ( ! empty( $booking->booking_snapshot ) ) {
			$booking->snapshot = json_decode( $booking->booking_snapshot, true );
		} else {
			$booking->snapshot = null;
		}

		// Get block IDs
		$booking->booking_block_ids = array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare(
					"SELECT booking_block_id FROM $table_booking_blocks WHERE booking_id = %d",
					$booking->id
				)
			)
		);

		// Get object IDs
		$booking->booking_object_ids = array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare(
					"SELECT booking_object_id FROM $table_booking_objects WHERE booking_id = %d",
					$booking->id
				)
			)
		);
	}

	/**
	 * Build a snapshot array for a booking
	 */
	public function build_snapshot( array $data, array $object_ids, array $block_ids ) {
		global $wpdb;

		$objects = array();
		if ( ! empty( $object_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $object_ids ), '%d' ) );
			$rows         = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, name FROM {$wpdb->prefix}snippen_booking_objects WHERE id IN ($placeholders)",
					...$object_ids
				)
			);
			foreach ( $rows as $r ) {
				$objects[] = array(
					'id'   => (int) $r->id,
					'name' => $r->name,
				);
			}
		}

		$blocks    = array();
		$min_start = null;
		$max_end   = null;
		if ( ! empty( $block_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $block_ids ), '%d' ) );
			$rows         = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, name, start_time, end_time FROM {$wpdb->prefix}snippen_booking_blocks WHERE id IN ($placeholders)",
					...$block_ids
				)
			);
			foreach ( $rows as $r ) {
				$blocks[] = array(
					'id'         => (int) $r->id,
					'name'       => $r->name,
					'start_time' => $r->start_time,
					'end_time'   => $r->end_time,
				);
				if ( $min_start === null || $r->start_time < $min_start ) {
					$min_start = $r->start_time;
				}
				if ( $max_end === null || $r->end_time > $max_end ) {
					$max_end = $r->end_time;
				}
			}
		}

		$time_range_formatted = '';
		if ( $min_start && $max_end ) {
			$time_range_formatted = date_i18n( 'H:i', strtotime( $min_start ) ) . ' - ' . date_i18n( 'H:i', strtotime( $max_end ) );
		}

		return array(
			'start_time'           => $min_start ?: '',
			'end_time'             => $max_end ?: '',
			'time_range_formatted' => $time_range_formatted,
			'objects'              => $objects,
			'blocks'               => $blocks,
			'price'                => isset( $data['price'] ) ? (float) $data['price'] : 0.0,
			'discount_amount'      => isset( $data['discount_amount'] ) ? (float) $data['discount_amount'] : 0.0,
			'booking_type'         => isset( $data['booking_type'] ) ? $data['booking_type'] : 'private',
			'created_at'           => current_time( 'mysql' ),
		);
	}

	/**
	 * Create a new booking.
	 *
	 * @param array $data
	 * @param array $object_ids
	 * @param array $block_ids
	 * @return int|bool Inserted booking ID or false
	 */
	public function create( array $data, array $object_ids, array $block_ids ) {
		global $wpdb;

		// Automatically assign uuid if not provided
		if ( empty( $data['uuid'] ) && function_exists( 'wp_generate_uuid4' ) ) {
			$data['uuid'] = wp_generate_uuid4();
		}

		if ( empty( $data['booking_snapshot'] ) ) {
			$snapshot                 = $this->build_snapshot( $data, $object_ids, $block_ids );
			$data['booking_snapshot'] = wp_json_encode( $snapshot );
		}

		$table_bookings = $wpdb->prefix . 'snippen_bookings';
		$inserted       = $wpdb->insert( $table_bookings, $data );

		if ( ! $inserted ) {
			return false;
		}

		$booking_id = $wpdb->insert_id;

		// Link objects
		$table_booking_objects          = $wpdb->prefix . 'snippen_booking_booking_objects';
		$table_bookings_booking_objects = $wpdb->prefix . 'snippen_bookings_booking_objects';
		foreach ( $object_ids as $obj_id ) {
			$wpdb->insert(
				$table_booking_objects,
				array(
					'booking_id'        => $booking_id,
					'booking_object_id' => (int) $obj_id,
				)
			);
			$wpdb->insert(
				$table_bookings_booking_objects,
				array(
					'booking_id'        => $booking_id,
					'booking_object_id' => (int) $obj_id,
				)
			);
		}

		// Link blocks
		$table_booking_blocks = $wpdb->prefix . 'snippen_booking_booking_blocks';
		foreach ( $block_ids as $block_id ) {
			$wpdb->insert(
				$table_booking_blocks,
				array(
					'booking_id'       => $booking_id,
					'booking_block_id' => (int) $block_id,
				)
			);
		}

		$this->record_snapshot_history( $booking_id, $data['booking_snapshot'], ! empty( $data['user_id'] ) ? (int) $data['user_id'] : get_current_user_id(), 1 );

		return $booking_id;
	}

	/**
	 * Update a booking and record a new revision snapshot.
	 *
	 * @param int   $id
	 * @param array $data
	 * @param array|null $object_ids
	 * @param array|null $block_ids
	 * @param int|null $modified_by_user_id
	 * @return bool
	 */
	public function update( $id, array $data, array $object_ids = null, array $block_ids = null, $modified_by_user_id = null ) {
		global $wpdb;

		$booking = $this->find( (int) $id );
		if ( ! $booking ) {
			return false;
		}

		if ( null === $object_ids ) {
			$object_ids = $booking->booking_object_ids;
		}
		if ( null === $block_ids ) {
			$block_ids = $booking->booking_block_ids;
		}

		$object_ids = array_values( array_unique( array_map( 'intval', $object_ids ) ) );
		$block_ids  = array_values( array_unique( array_map( 'intval', $block_ids ) ) );

		$booking_data = array_merge( (array) $booking, $data );
		unset( $booking_data['snapshot'], $booking_data['booking_block_ids'], $booking_data['booking_object_ids'] );
		$booking_data['modified_at']      = current_time( 'mysql' );
		$booking_data['booking_snapshot'] = wp_json_encode( $this->build_snapshot( $booking_data, $object_ids, $block_ids ) );

		$updated = $wpdb->update(
			$wpdb->prefix . 'snippen_bookings',
			$booking_data,
			array( 'id' => (int) $id )
		);
		if ( false === $updated ) {
			return false;
		}

		$table_booking_objects          = $wpdb->prefix . 'snippen_booking_booking_objects';
		$table_bookings_booking_objects = $wpdb->prefix . 'snippen_bookings_booking_objects';
		$table_booking_blocks           = $wpdb->prefix . 'snippen_booking_booking_blocks';

		$wpdb->delete( $table_booking_objects, array( 'booking_id' => (int) $id ) );
		$wpdb->delete( $table_bookings_booking_objects, array( 'booking_id' => (int) $id ) );
		$wpdb->delete( $table_booking_blocks, array( 'booking_id' => (int) $id ) );

		foreach ( $object_ids as $obj_id ) {
			$wpdb->insert(
				$table_booking_objects,
				array(
					'booking_id'        => (int) $id,
					'booking_object_id' => (int) $obj_id,
				)
			);
			$wpdb->insert(
				$table_bookings_booking_objects,
				array(
					'booking_id'        => (int) $id,
					'booking_object_id' => (int) $obj_id,
				)
			);
		}

		foreach ( $block_ids as $block_id ) {
			$wpdb->insert(
				$table_booking_blocks,
				array(
					'booking_id'       => (int) $id,
					'booking_block_id' => (int) $block_id,
				)
			);
		}

		$modifier_id = null !== $modified_by_user_id ? (int) $modified_by_user_id : get_current_user_id();
		if ( ! $modifier_id ) {
			$modifier_id = (int) $booking->user_id;
		}

		$history_snapshot = $booking_data['booking_snapshot'];
		if ( is_string( $history_snapshot ) ) {
			$history_snapshot = json_decode( $history_snapshot, true );
		}
		if ( ! is_array( $history_snapshot ) ) {
			$history_snapshot = $this->build_snapshot( $booking_data, $object_ids, $block_ids );
		}

		$revision = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(MAX(revision), 0) FROM {$wpdb->prefix}snippen_booking_snapshots WHERE booking_id = %d",
				(int) $id
			)
		);
		$this->record_snapshot_history( (int) $id, wp_json_encode( $history_snapshot ), $modifier_id, $revision + 1 );

		return true;
	}

	/**
	 * Record a booking snapshot revision.
	 *
	 * @param int    $booking_id
	 * @param string $snapshot
	 * @param int    $modified_by_user_id
	 * @param int    $revision
	 * @return void
	 */
	private function record_snapshot_history( $booking_id, $snapshot, $modified_by_user_id, $revision ) {
		global $wpdb;
		$table_history = $wpdb->prefix . 'snippen_booking_snapshots';

		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_history'" ) !== $table_history ) {
			$wpdb->query(
				"CREATE TABLE $table_history (
					id BIGINT NOT NULL AUTO_INCREMENT,
					booking_id BIGINT NOT NULL,
					revision INT NOT NULL DEFAULT 1,
					snapshot LONGTEXT NOT NULL,
					changes_summary TEXT NULL,
					modified_by_user_id BIGINT UNSIGNED NOT NULL,
					created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
					PRIMARY KEY (id),
					KEY booking_id (booking_id),
					KEY modified_by_user_id (modified_by_user_id)
				)"
			);
		}

		$wpdb->insert(
			$table_history,
			array(
				'booking_id'          => (int) $booking_id,
				'revision'            => (int) $revision,
				'snapshot'            => (string) $snapshot,
				'changes_summary'     => 'Updated booking',
				'modified_by_user_id' => (int) $modified_by_user_id,
				'created_at'          => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%s' )
		);
	}
}
