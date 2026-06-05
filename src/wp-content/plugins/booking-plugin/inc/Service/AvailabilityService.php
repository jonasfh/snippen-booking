<?php

namespace SnippenBooking\Service;

use SnippenBooking\Database\Repository\BookingBlockRepository;
use SnippenBooking\Database\Repository\BookingRepository;

/**
 * Service for calculating availability and detecting overlaps for booking blocks
 */
class AvailabilityService {

	/**
	 * @var BookingBlockRepository
	 */
	private $block_repository;

	/**
	 * @var BookingRepository
	 */
	private $booking_repository;

	public function __construct() {
		$this->block_repository   = new BookingBlockRepository();
		$this->booking_repository = new BookingRepository();
	}

	/**
	 * Check if a single block is available for a given date and object.
	 *
	 * @param int    $objectId
	 * @param string $date YYYY-MM-DD
	 * @param int    $blockId
	 * @return bool
	 */
	public function isBlockAvailable( $objectId, $date, $blockId ) {
		return $this->areBlocksAvailable( $objectId, $date, array( $blockId ) );
	}

	/**
	 * Check if a set of blocks are available for a given date and object.
	 *
	 * @param int    $objectId
	 * @param string $date YYYY-MM-DD
	 * @param array  $blockIds
	 * @return bool
	 */
	public function areBlocksAvailable( $objectId, $date, array $blockIds ) {
		if ( empty( $blockIds ) ) {
			return true;
		}

		$proposed_blocks = $this->block_repository->find_by_ids( $blockIds );
		if ( count( $proposed_blocks ) !== count( $blockIds ) ) {
			return false; // Some requested blocks do not exist
		}

		// Fetch existing bookings for this object and date
		$existing_bookings = $this->booking_repository->find_by_object_and_date_range( $objectId, $date, $date );

		foreach ( $proposed_blocks as $proposed ) {
			$proposed_start = new \DateTime( $date . ' ' . $proposed->start_time );
			$proposed_end   = new \DateTime( $date . ' ' . $proposed->end_time );

			// Adjust end time if block wraps past midnight (e.g., end_time is less than start_time)
			if ( $proposed_end <= $proposed_start ) {
				$proposed_end->modify( '+1 day' );
			}

			// Subtract 1 second to avoid edge-to-edge overlap conflicts (e.g., 08:00-09:00 and 09:00-10:00)
			$proposed_end->modify( '-1 second' );

			foreach ( $existing_bookings as $booking ) {
				$booked_blocks = $this->block_repository->find_by_ids( $booking->booking_block_ids );
				foreach ( $booked_blocks as $booked ) {
					$booked_start = new \DateTime( $date . ' ' . $booked->start_time );
					$booked_end   = new \DateTime( $date . ' ' . $booked->end_time );

					if ( $booked_end <= $booked_start ) {
						$booked_end->modify( '+1 day' );
					}
					$booked_end->modify( '-1 second' );

					// Overlap condition: (start1 < end2) && (start2 < end1)
					if ( ( $proposed_start < $booked_end ) && ( $booked_start < $proposed_end ) ) {
						return false; // Time conflict
					}
				}
			}
		}

		return true;
	}

	/**
	 * Get a list of unavailable block IDs for a date range.
	 *
	 * @param int    $objectId
	 * @param string $startDate YYYY-MM-DD
	 * @param string $endDate YYYY-MM-DD
	 * @return array Array of block IDs indexed by date string
	 */
	public function getUnavailableBlocks( $objectId, $startDate, $endDate ) {
		$all_blocks = $this->block_repository->find_all();
		$bookings   = $this->booking_repository->find_by_object_and_date_range( $objectId, $startDate, $endDate );

		$unavailable = array();

		$current = new \DateTime( $startDate );
		$last    = new \DateTime( $endDate );

		while ( $current <= $last ) {
			$date_str                 = $current->format( 'Y-m-d' );
			$unavailable[ $date_str ] = array();

			// Filter bookings for this day
			$day_bookings = array_filter(
				$bookings,
				function ( $b ) use ( $date_str ) {
					return $b->booking_date === $date_str;
				}
			);

			foreach ( $all_blocks as $proposed ) {
				$proposed_start = new \DateTime( $date_str . ' ' . $proposed->start_time );
				$proposed_end   = new \DateTime( $date_str . ' ' . $proposed->end_time );

				if ( $proposed_end <= $proposed_start ) {
					$proposed_end->modify( '+1 day' );
				}
				$proposed_end->modify( '-1 second' );

				$has_overlap = false;
				foreach ( $day_bookings as $booking ) {
					$booked_blocks = $this->block_repository->find_by_ids( $booking->booking_block_ids );
					foreach ( $booked_blocks as $booked ) {
						$booked_start = new \DateTime( $date_str . ' ' . $booked->start_time );
						$booked_end   = new \DateTime( $date_str . ' ' . $booked->end_time );

						if ( $booked_end <= $booked_start ) {
							$booked_end->modify( '+1 day' );
						}
						$booked_end->modify( '-1 second' );

						if ( ( $proposed_start < $booked_end ) && ( $booked_start < $proposed_end ) ) {
							$has_overlap = true;
							break 2;
						}
					}
				}

				if ( $has_overlap ) {
					$unavailable[ $date_str ][] = (int) $proposed->id;
				}
			}

			$current->modify( '+1 day' );
		}

		return $unavailable;
	}

	/**
	 * Check if a booking block is applicable for a given date.
	 *
	 * @param object $block DB block object
	 * @param string $date_str YYYY-MM-DD
	 * @param bool   $is_holiday
	 * @return bool
	 */
	public function isBlockApplicable( $block, $date_str, $is_holiday ) {
		$day_of_week = date( 'w', strtotime( $date_str ) );

		if ( $block->days_of_week !== null && $block->days_of_week !== '' ) {
			$allowed_days = explode( ',', $block->days_of_week );
			if ( $is_holiday ) {
				// Holiday day code is 7
				return in_array( '7', $allowed_days );
			}
			return in_array( (string) $day_of_week, $allowed_days );
		}

		return true;
	}
}
