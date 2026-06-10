<?php

namespace SnippenBooking\Service;

use SnippenBooking\Database\Repository\PricingRuleRepository;

/**
 * Service for calculating prices based on booking objects, blocks, and pricing rules
 */
class PricingService {

	/**
	 * @var PricingRuleRepository
	 */
	private $pricing_rule_repository;

	/**
	 * @var HolidayService
	 */
	private $holiday_service;

	public function __construct() {
		$this->pricing_rule_repository = new PricingRuleRepository();
		$this->holiday_service         = new HolidayService();
	}

	/**
	 * Get total price for a combination of objects, blocks, and date
	 *
	 * @param array  $objectIds List of booking object IDs
	 * @param array  $blockIds  List of booking block IDs
	 * @param string $date      YYYY-MM-DD
	 * @return float Total price
	 */
	public function getPrice( array $objectIds, array $blockIds, $date ) {
		if ( empty( $objectIds ) || empty( $blockIds ) || empty( $date ) ) {
			return 0.0;
		}

		global $wpdb;

		// Fetch all potentially applicable pricing rules
		$rules = $this->pricing_rule_repository->find_applicable_rules( $objectIds, $blockIds );
		if ( empty( $rules ) ) {
			// Legacy fallback for backward compatibility
			$table_prices   = $wpdb->prefix . 'snippen_prices';
			$table_slots    = $wpdb->prefix . 'snippen_time_slots';
			$slot_in_clause = implode( ',', array_fill( 0, count( $blockIds ), '%d' ) );
			$query          = $wpdb->prepare(
				"SELECT p.price 
				 FROM $table_prices p
				 JOIN $table_slots s ON s.price_id = p.id
				 WHERE s.id IN ($slot_in_clause)
				 ORDER BY p.priority DESC LIMIT 1",
				...$blockIds
			);
			$legacy_price   = $wpdb->get_var( $query );
			if ( $legacy_price !== null ) {
				return (float) $legacy_price;
			}
			return 0.0;
		}

		$is_holiday  = $this->holiday_service->isHoliday( $date );
		$day_of_week = date( 'w', strtotime( $date ) );

		// Filter the rules that match the date constraints
		$applicable_rules = array();
		foreach ( $rules as $rule ) {
			// Check date range
			if ( ! empty( $rule->date_start ) && $date < $rule->date_start ) {
				continue;
			}
			if ( ! empty( $rule->date_end ) && $date > $rule->date_end ) {
				continue;
			}

			// Check holiday constraint
			if ( $rule->holiday_only && ! $is_holiday ) {
				continue;
			}

			// Check days of week constraint
			if ( ! empty( $rule->days_of_week ) ) {
				$allowed_days = explode( ',', $rule->days_of_week );
				$match_day    = false;
				if ( $is_holiday ) {
					// 7 represents holiday
					$match_day = in_array( '7', $allowed_days );
				} else {
					$match_day = in_array( (string) $day_of_week, $allowed_days );
				}
				if ( ! $match_day ) {
					continue;
				}
			}

			$applicable_rules[] = $rule;
		}

		// Now, for each object and each block, find the rule with the highest priority
		$total_price = 0.0;

		$table_rule_blocks  = $wpdb->prefix . 'snippen_pricing_rule_booking_blocks';
		$table_rule_objects = $wpdb->prefix . 'snippen_pricing_rule_booking_objects';

		foreach ( $objectIds as $obj_id ) {
			foreach ( $blockIds as $block_id ) {
				$best_rule    = null;
				$max_priority = -1;

				foreach ( $applicable_rules as $rule ) {
					// Check if this rule is explicitly linked to this block and object
					$is_linked_block = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(*) FROM $table_rule_blocks WHERE pricing_rule_id = %d AND booking_block_id = %d",
							$rule->id,
							$block_id
						)
					);

					$is_linked_object = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(*) FROM $table_rule_objects WHERE pricing_rule_id = %d AND booking_object_id = %d",
							$rule->id,
							$obj_id
						)
					);

					if ( $is_linked_block && $is_linked_object ) {
						if ( (int) $rule->priority > $max_priority ) {
							$max_priority = (int) $rule->priority;
							$best_rule    = $rule;
						}
					}
				}

				if ( $best_rule ) {
					$total_price += (float) $best_rule->price;
				}
			}
		}

		return $total_price;
	}
}
