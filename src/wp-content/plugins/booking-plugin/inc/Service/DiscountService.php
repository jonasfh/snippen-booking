<?php

namespace SnippenBooking\Service;

use SnippenBooking\Database\Repository\DiscountRuleRepository;
use SnippenBooking\Database\Repository\BookingBlockRepository;

/**
 * Service for calculating discounts based on duration.
 */
class DiscountService {

	/**
	 * @var DiscountRuleRepository
	 */
	private $discount_repository;

	/**
	 * @var BookingBlockRepository
	 */
	private $block_repository;

	public function __construct() {
		$this->discount_repository = new DiscountRuleRepository();
		$this->block_repository    = new BookingBlockRepository();
	}

	/**
	 * Calculate total duration in hours for a set of blocks.
	 *
	 * Duration is calculated from the earliest start time to the latest end time.
	 *
	 * @param array $block_ids
	 * @return float
	 */
	public function calculateDuration( array $block_ids ) {
		if ( empty( $block_ids ) ) {
			return 0.0;
		}

		$blocks = $this->block_repository->find_by_ids( $block_ids );
		if ( empty( $blocks ) ) {
			return 0.0;
		}

		$min_start = null;
		$max_end   = null;

		foreach ( $blocks as $block ) {
			$start = strtotime( $block->start_time );
			$end   = strtotime( $block->end_time );

			// Handle blocks ending at midnight or later
			if ( $end <= $start ) {
				$end += 24 * 3600; // Add 24 hours
			}

			if ( $min_start === null || $start < $min_start ) {
				$min_start = $start;
			}
			if ( $max_end === null || $end > $max_end ) {
				$max_end = $end;
			}
		}

		if ( $min_start !== null && $max_end !== null && $max_end > $min_start ) {
			return round( ( $max_end - $min_start ) / 3600, 2 );
		}

		return 0.0;
	}

	/**
	 * Calculate discount for a booking.
	 *
	 * @param float       $base_price The price before discount.
	 * @param array       $object_ids Array of object IDs.
	 * @param array       $block_ids Array of block IDs.
	 * @param string|null $date The booking date (Y-m-d).
	 * @return array Associative array with 'final_price', 'discount_amount', and 'discount_rule'.
	 */
	public function applyDiscount( $base_price, array $object_ids, array $block_ids, $date = null ) {
		$repo = new DiscountRuleRepository();

		$duration = $this->calculateDuration( $block_ids );

		$rule = $repo->find_applicable_rule( $object_ids, $duration, $date );

		if ( ! $rule ) {
			return array(
				'final_price'     => $base_price,
				'discount_amount' => 0.0,
				'discount_rule'   => null,
			);
		}

		$discount_amount = 0.0;
		$final_price     = $base_price;

		if ( $rule->discount_type === 'percentage' ) {
			$discount_amount = $base_price * ( (float) $rule->discount_value / 100 );
			if ( $discount_amount > $base_price ) {
				$discount_amount = $base_price;
			}
			$final_price = $base_price - $discount_amount;
		} elseif ( $rule->discount_type === 'fixed_amount' ) {
			$discount_amount = (float) $rule->discount_value;
			if ( $discount_amount > $base_price ) {
				$discount_amount = $base_price;
			}
			$final_price = $base_price - $discount_amount;
		} elseif ( $rule->discount_type === 'fixed_price' ) {
			$target_price    = (float) $rule->discount_value;
			$discount_amount = max( 0.0, $base_price - $target_price );
			$final_price     = $target_price;
		}

		return array(
			'final_price'     => $final_price,
			'discount_amount' => $discount_amount,
			'discount_rule'   => $rule,
		);
	}
}
