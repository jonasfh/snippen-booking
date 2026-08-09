<?php

namespace SnippenBooking\Database\Repository;

/**
 * Repository for pricing rules.
 */
class PricingRuleRepository {

	/**
	 * Find a pricing rule by ID.
	 *
	 * @param int $id
	 * @return object|null
	 */
	public function find( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_pricing_rules';
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE id = %d AND deleted_at IS NULL",
				(int) $id
			)
		);
		return $row ? $row : null;
	}

	/**
	 * Find all active pricing rules.
	 *
	 * @param bool $include_inactive Whether to include inactive rules.
	 * @return array
	 */
	public function find_all( $include_inactive = false ) {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_pricing_rules';

		$query = "SELECT * FROM $table WHERE deleted_at IS NULL";
		if ( ! $include_inactive ) {
			$query .= ' AND is_active = 1';
		}
		$query .= ' ORDER BY priority DESC, name ASC';

		return $wpdb->get_results( $query );
	}

	/**
	 * Save a pricing rule (insert or update).
	 *
	 * @param array    $data
	 * @param int|null $id
	 * @return int|false The ID of the rule, or false on failure.
	 */
	public function save( array $data, $id = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_pricing_rules';

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
	 * Soft delete a pricing rule.
	 *
	 * @param int $id
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;
		$table  = $wpdb->prefix . 'snippen_pricing_rules';
		$result = $wpdb->update(
			$table,
			array( 'deleted_at' => current_time( 'mysql' ) ),
			array( 'id' => $id )
		);
		return $result !== false;
	}

	/**
	 * Sync booking objects for a pricing rule.
	 *
	 * @param int   $rule_id
	 * @param array $object_ids
	 * @return void
	 */
	public function sync_booking_objects( $rule_id, array $object_ids ) {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_pricing_rule_booking_objects';

		$wpdb->delete( $table, array( 'pricing_rule_id' => $rule_id ) );

		foreach ( $object_ids as $object_id ) {
			$wpdb->insert(
				$table,
				array(
					'pricing_rule_id'   => $rule_id,
					'booking_object_id' => (int) $object_id,
				)
			);
		}
	}

	/**
	 * Sync booking blocks for a pricing rule.
	 *
	 * @param int   $rule_id
	 * @param array $block_ids
	 * @return void
	 */
	public function sync_booking_blocks( $rule_id, array $block_ids ) {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_pricing_rule_booking_blocks';

		$wpdb->delete( $table, array( 'pricing_rule_id' => $rule_id ) );

		foreach ( $block_ids as $block_id ) {
			$wpdb->insert(
				$table,
				array(
					'pricing_rule_id'  => $rule_id,
					'booking_block_id' => (int) $block_id,
				)
			);
		}
	}

	/**
	 * Find pricing rule booking objects.
	 *
	 * @param int $rule_id
	 * @return array
	 */
	public function get_rule_objects( $rule_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_pricing_rule_booking_objects';
		return $wpdb->get_col( $wpdb->prepare( "SELECT booking_object_id FROM $table WHERE pricing_rule_id = %d", $rule_id ) );
	}

	/**
	 * Find pricing rule booking blocks.
	 *
	 * @param int $rule_id
	 * @return array
	 */
	public function get_rule_blocks( $rule_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_pricing_rule_booking_blocks';
		return $wpdb->get_col( $wpdb->prepare( "SELECT booking_block_id FROM $table WHERE pricing_rule_id = %d", $rule_id ) );
	}

	/**
	 * Find all pricing rules applicable to a set of objects and blocks.
	 *
	 * @param array $object_ids
	 * @param array $block_ids
	 * @return array
	 */
	public function find_applicable_rules( array $object_ids, array $block_ids ) {
		global $wpdb;
		if ( empty( $object_ids ) || empty( $block_ids ) ) {
			return array();
		}

		$object_ids = array_map( 'intval', $object_ids );
		$block_ids  = array_map( 'intval', $block_ids );

		$table_rules        = $wpdb->prefix . 'snippen_pricing_rules';
		$table_rule_blocks  = $wpdb->prefix . 'snippen_pricing_rule_booking_blocks';
		$table_rule_objects = $wpdb->prefix . 'snippen_pricing_rule_booking_objects';

		$block_placeholders  = implode( ',', array_fill( 0, count( $block_ids ), '%d' ) );
		$object_placeholders = implode( ',', array_fill( 0, count( $object_ids ), '%d' ) );

		$query = $wpdb->prepare(
			"SELECT DISTINCT r.* 
			 FROM $table_rules r
			 JOIN $table_rule_blocks rb ON r.id = rb.pricing_rule_id
			 JOIN $table_rule_objects ro ON r.id = ro.pricing_rule_id
			 WHERE rb.booking_block_id IN ($block_placeholders)
			   AND ro.booking_object_id IN ($object_placeholders)
			   AND r.deleted_at IS NULL
			   AND r.is_active = 1
			 ORDER BY r.priority DESC",
			...array_merge( $block_ids, $object_ids )
		);

		return $wpdb->get_results( $query );
	}

	/**
	 * Find the matching pricing rule for a specific date, object and block.
	 * Rule resolution logic:
	 * 1. Must be active and not deleted
	 * 2. Must match the given booking object and block
	 * 3. Must fall within date constraints (if any)
	 * 4. Must match day constraints (if any)
	 * 5. Must match holiday constraint (if applicable)
	 * 6. From matching rules, select the one with highest priority.
	 *
	 * @param string $date YYYY-MM-DD
	 * @param int    $object_id
	 * @param int    $block_id
	 * @return object|null The matching rule object, or null if none found
	 */
	public function find_matching_rule( $date, $object_id, $block_id ) {
		$applicable_rules = $this->find_applicable_rules( array( $object_id ), array( $block_id ) );

		if ( empty( $applicable_rules ) ) {
			return null;
		}

		$timestamp   = strtotime( $date );
		$day_of_week = (int) date( 'N', $timestamp ); // 1 (Mon) to 7 (Sun)

		// In a real scenario, we might have a service checking for holidays.
		// For simplicity, we assume we might check a holiday API, but let's
		// assume no holidays for now, unless passed from outside.
		// We'll just implement the logic based on the schema fields.
		$is_holiday = false; // Could be extended to check actual holidays

		foreach ( $applicable_rules as $rule ) {
			// Check date constraints
			if ( ! empty( $rule->date_start ) && $date < $rule->date_start ) {
				continue;
			}
			if ( ! empty( $rule->date_end ) && $date > $rule->date_end ) {
				continue;
			}

			// Check day constraints
			if ( ! empty( $rule->days_of_week ) ) {
				$days = explode( ',', $rule->days_of_week );
				if ( ! in_array( (string) $day_of_week, $days, true ) ) {
					continue;
				}
			}

			// Check holiday constraint
			if ( (int) $rule->holiday_only === 1 && ! $is_holiday ) {
				continue; // Requires holiday, but it's not
			}

			// The rules are already sorted by priority DESC, so the first match is the winner
			return $rule;
		}

		return null;
	}
}
