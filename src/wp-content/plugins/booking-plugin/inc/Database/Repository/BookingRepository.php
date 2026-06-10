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

		$table_bookings = $wpdb->prefix . 'snippen_bookings';
		$inserted       = $wpdb->insert( $table_bookings, $data );

		if ( ! $inserted ) {
			return false;
		}

		$booking_id = $wpdb->insert_id;

		// Link objects
		$table_booking_objects = $wpdb->prefix . 'snippen_booking_booking_objects';
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

		return $booking_id;
	}
}
