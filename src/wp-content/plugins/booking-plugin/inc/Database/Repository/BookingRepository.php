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

		// Record initial snapshot (revision 1) in dedicated snapshots table
		$user_id = get_current_user_id();
		if ( ! $user_id && ! empty( $data['user_id'] ) ) {
			$user_id = (int) $data['user_id'];
		}
		if ( ! $user_id ) {
			$user_id = 1;
		}

		$table_snapshots = $wpdb->prefix . 'snippen_booking_snapshots';
		$wpdb->insert(
			$table_snapshots,
			array(
				'booking_id'          => $booking_id,
				'revision'            => 1,
				'snapshot'            => $data['booking_snapshot'],
				'changes_summary'     => ! empty( $data['changes_summary'] ) ? sanitize_text_field( $data['changes_summary'] ) : __( 'Opprinnelig booking', 'snippen-booking' ),
				'modified_by_user_id' => $user_id,
				'created_at'          => current_time( 'mysql' ),
				'modified_at'         => current_time( 'mysql' ),
			)
		);

		return $booking_id;
	}

	/**
	 * Get all snapshots / revision history for a booking.
	 *
	 * @param int $booking_id
	 * @return array Array of snapshot objects
	 */
	public function get_snapshots( $booking_id ) {
		global $wpdb;
		$table_snapshots = $wpdb->prefix . 'snippen_booking_snapshots';

		$snapshots = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.*, u.display_name as modifier_name, u.user_login as modifier_login\n" .
				" FROM $table_snapshots s\n" .
				" LEFT JOIN {$wpdb->users} u ON s.modified_by_user_id = u.ID\n" .
				" WHERE s.booking_id = %d\n" .
				' ORDER BY s.revision ASC',
				(int) $booking_id
			)
		);

		if ( ! is_array( $snapshots ) ) {
			return array();
		}

		foreach ( $snapshots as $snap ) {
			$snap->decoded_snapshot = ! empty( $snap->snapshot ) ? json_decode( $snap->snapshot, true ) : array();
			if ( empty( $snap->modifier_name ) ) {
				$snap->modifier_name = sprintf( __( 'Bruker #%d', 'snippen-booking' ), $snap->modified_by_user_id );
			}
		}

		return $snapshots;
	}

	/**
	 * Get the latest snapshot for a booking.
	 *
	 * @param int $booking_id
	 * @return object|null
	 */
	public function get_latest_snapshot( $booking_id ) {
		global $wpdb;
		$table_snapshots = $wpdb->prefix . 'snippen_booking_snapshots';

		$snap = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT s.*, u.display_name as modifier_name, u.user_login as modifier_login\n" .
				" FROM $table_snapshots s\n" .
				" LEFT JOIN {$wpdb->users} u ON s.modified_by_user_id = u.ID\n" .
				" WHERE s.booking_id = %d\n" .
				" ORDER BY s.revision DESC\n" .
				' LIMIT 1',
				(int) $booking_id
			)
		);

		if ( $snap ) {
			$snap->decoded_snapshot = ! empty( $snap->snapshot ) ? json_decode( $snap->snapshot, true ) : array();
			if ( empty( $snap->modifier_name ) ) {
				$snap->modifier_name = sprintf( __( 'Bruker #%d', 'snippen-booking' ), $snap->modified_by_user_id );
			}
			return $snap;
		}

		return null;
	}

	/**
	 * Update an existing booking with conflict detection and snapshot history tracking.
	 *
	 * @param int        $booking_id
	 * @param array      $data
	 * @param array|null $object_ids
	 * @param array|null $block_ids
	 * @param array      $modifier_info Metadata about the edit (user ID, reason/changes_summary)
	 * @return object|\WP_Error Updated booking object or WP_Error on conflict/failure
	 */
	public function update( $booking_id, array $data, array $object_ids = null, array $block_ids = null, array $modifier_info = array() ) {
		global $wpdb;
		$booking_id = (int) $booking_id;
		$existing   = $this->find( $booking_id );
		if ( ! $existing ) {
			return new \WP_Error( 'not_found', __( 'Booking ble ikke funnet.', 'snippen-booking' ) );
		}

		$target_date    = isset( $data['booking_date'] ) ? sanitize_text_field( $data['booking_date'] ) : $existing->booking_date;
		$target_objects = $object_ids !== null ? array_values( array_map( 'intval', $object_ids ) ) : $existing->booking_object_ids;
		$target_blocks  = $block_ids !== null ? array_values( array_map( 'intval', $block_ids ) ) : $existing->booking_block_ids;
		$target_status  = isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : $existing->status;

		// Perform availability and conflict check if booking is not cancelled
		if ( 'cancelled' !== $target_status && ! empty( $target_objects ) && ! empty( $target_blocks ) ) {
			$availability_service = new \SnippenBooking\Service\AvailabilityService();
			foreach ( $target_objects as $obj_id ) {
				if ( ! $availability_service->areBlocksAvailable( $obj_id, $target_date, $target_blocks, $booking_id ) ) {
					return new \WP_Error(
						'booking_conflict',
						sprintf(
							__( 'Tidskonflikt: Ett eller flere valgte lokaler er allerede booket i dette tidsrommet på datoen %s.', 'snippen-booking' ),
							$target_date
						)
					);
				}
			}
		}

		// Build updated active snapshot
		$snapshot_merged          = array_merge( (array) $existing, $data );
		$snapshot_array           = $this->build_snapshot( $snapshot_merged, $target_objects, $target_blocks );
		$data['booking_snapshot'] = wp_json_encode( $snapshot_array );

		// Determine next revision number
		$table_snapshots = $wpdb->prefix . 'snippen_booking_snapshots';
		$max_revision    = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT MAX(revision) FROM $table_snapshots WHERE booking_id = %d", $booking_id )
		);

		// If booking exists without any prior snapshots in the table, backfill revision 1 first
		if ( 0 === $max_revision && ! empty( $existing->booking_snapshot ) ) {
			$wpdb->insert(
				$table_snapshots,
				array(
					'booking_id'          => $booking_id,
					'revision'            => 1,
					'snapshot'            => $existing->booking_snapshot,
					'changes_summary'     => __( 'Opprinnelig booking', 'snippen-booking' ),
					'modified_by_user_id' => ! empty( $existing->user_id ) ? (int) $existing->user_id : 1,
					'created_at'          => ! empty( $existing->created_at ) ? $existing->created_at : current_time( 'mysql' ),
					'modified_at'         => ! empty( $existing->created_at ) ? $existing->created_at : current_time( 'mysql' ),
				)
			);
			$max_revision = 1;
		}

		$next_revision = $max_revision > 0 ? $max_revision + 1 : 1;

		// Modifier user ID
		$modifier_user_id = ! empty( $modifier_info['modified_by_user_id'] ) ? (int) $modifier_info['modified_by_user_id'] : get_current_user_id();
		if ( ! $modifier_user_id ) {
			$modifier_user_id = 1;
		}

		// Changes summary / reason
		$changes_summary = '';
		if ( ! empty( $modifier_info['changes_summary'] ) ) {
			$changes_summary = sanitize_textarea_field( $modifier_info['changes_summary'] );
		} elseif ( ! empty( $modifier_info['reason'] ) ) {
			$changes_summary = sanitize_textarea_field( $modifier_info['reason'] );
		} else {
			$changes_summary = __( 'Oppdatert av administrator', 'snippen-booking' );
		}

		// Insert new revision record into wp_snippen_booking_snapshots
		$wpdb->insert(
			$table_snapshots,
			array(
				'booking_id'          => $booking_id,
				'revision'            => $next_revision,
				'snapshot'            => $data['booking_snapshot'],
				'changes_summary'     => $changes_summary,
				'modified_by_user_id' => $modifier_user_id,
				'created_at'          => current_time( 'mysql' ),
				'modified_at'         => current_time( 'mysql' ),
			)
		);

		// Update wp_snippen_bookings
		$table_bookings      = $wpdb->prefix . 'snippen_bookings';
		$data['modified_at'] = current_time( 'mysql' );
		unset( $data['id'], $data['uuid'] );

		$wpdb->update(
			$table_bookings,
			$data,
			array( 'id' => $booking_id )
		);

		// Update junction tables
		if ( $object_ids !== null ) {
			$table_bo  = $wpdb->prefix . 'snippen_booking_booking_objects';
			$table_bbo = $wpdb->prefix . 'snippen_bookings_booking_objects';
			$wpdb->delete( $table_bo, array( 'booking_id' => $booking_id ) );
			$wpdb->delete( $table_bbo, array( 'booking_id' => $booking_id ) );
			foreach ( $target_objects as $obj_id ) {
				$wpdb->insert(
					$table_bo,
					array(
						'booking_id'        => $booking_id,
						'booking_object_id' => (int) $obj_id,
					)
				);
				$wpdb->insert(
					$table_bbo,
					array(
						'booking_id'        => $booking_id,
						'booking_object_id' => (int) $obj_id,
					)
				);
			}
		}

		if ( $block_ids !== null ) {
			$table_bb = $wpdb->prefix . 'snippen_booking_booking_blocks';
			$wpdb->delete( $table_bb, array( 'booking_id' => $booking_id ) );
			foreach ( $target_blocks as $block_id ) {
				$wpdb->insert(
					$table_bb,
					array(
						'booking_id'       => $booking_id,
						'booking_block_id' => (int) $block_id,
					)
				);
			}
		}

		return $this->find( $booking_id );
	}
}
