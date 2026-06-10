<?php

namespace SnippenBooking\Database\Repository;

/**
 * Repository for discount rules.
 */
class DiscountRuleRepository {

	/**
	 * Find a discount rule by ID.
	 *
	 * @param int $id
	 * @return object|null
	 */
	public function find( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_discount_rules';
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE id = %d AND deleted_at IS NULL",
				(int) $id
			)
		);
		return $row ? $row : null;
	}

	/**
	 * Find all discount rules.
	 *
	 * @return array
	 */
	public function find_all() {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_discount_rules';
		
		$query = "SELECT * FROM $table WHERE deleted_at IS NULL ORDER BY priority DESC, name ASC";
		
		return $wpdb->get_results( $query );
	}

	/**
	 * Save a discount rule (insert or update).
	 *
	 * @param array $data
	 * @param int|null $id
	 * @return int|false The ID of the rule, or false on failure.
	 */
	public function save( array $data, $id = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_discount_rules';

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
	 * Soft delete a discount rule.
	 *
	 * @param int $id
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_discount_rules';
		$result = $wpdb->update( 
			$table, 
			array( 'deleted_at' => current_time( 'mysql' ) ), 
			array( 'id' => $id ) 
		);
		return $result !== false;
	}

	/**
	 * Sync booking objects for a discount rule.
	 *
	 * @param int $rule_id
	 * @param array $object_ids
	 * @return void
	 */
	public function sync_booking_objects( $rule_id, array $object_ids ) {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_discount_rule_booking_objects';
		
		$wpdb->delete( $table, array( 'discount_rule_id' => $rule_id ) );
		
		foreach ( $object_ids as $object_id ) {
			$wpdb->insert(
				$table,
				array(
					'discount_rule_id' => $rule_id,
					'booking_object_id' => (int) $object_id,
				)
			);
		}
	}

	/**
	 * Find discount rule booking objects.
	 *
	 * @param int $rule_id
	 * @return array
	 */
	public function get_rule_objects( $rule_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_discount_rule_booking_objects';
		return $wpdb->get_col( $wpdb->prepare( "SELECT booking_object_id FROM $table WHERE discount_rule_id = %d", $rule_id ) );
	}

	/**
	 * Find the best applicable discount rule for the given objects and duration.
	 *
	 * @param array $object_ids List of booking object IDs.
	 * @param float $duration_hours Total duration in hours.
	 * @param string|null $date Date string (Y-m-d)
	 * @return object|null The best rule or null.
	 */
	public function find_applicable_rule( array $object_ids, $duration_hours, $date = null ) {
		global $wpdb;
		if ( empty( $object_ids ) ) {
			return null;
		}

		$object_ids = array_map( 'intval', $object_ids );

		$table_rules        = $wpdb->prefix . 'snippen_discount_rules';
		$table_rule_objects = $wpdb->prefix . 'snippen_discount_rule_booking_objects';
		
		$object_placeholders = implode( ',', array_fill( 0, count( $object_ids ), '%d' ) );

		$query = $wpdb->prepare(
			"SELECT r.*, COUNT(ro.booking_object_id) as matched_objects,
			 (SELECT COUNT(*) FROM $table_rule_objects WHERE discount_rule_id = r.id) as total_required_objects
			 FROM $table_rules r
			 LEFT JOIN $table_rule_objects ro ON r.id = ro.discount_rule_id AND ro.booking_object_id IN ($object_placeholders)
			 WHERE r.deleted_at IS NULL
			   AND (r.min_duration_hours IS NULL OR %f >= r.min_duration_hours)
			   AND (r.max_duration_hours IS NULL OR %f <= r.max_duration_hours)
			 GROUP BY r.id
			 HAVING matched_objects = total_required_objects AND total_required_objects > 0
			 ORDER BY r.priority DESC",
			...array_merge( $object_ids, array( $duration_hours, $duration_hours ) )
		);
		
		$rules = $wpdb->get_results( $query );

		$is_holiday = false;
		$day_of_week = null;

		if ( $date ) {
			$day_of_week = date( 'w', strtotime( $date ) ); // 0 (Sunday) to 6 (Saturday)
			$holiday_service = new \SnippenBooking\Service\HolidayService();
			$is_holiday = $holiday_service->isHoliday( $date );
		}

		foreach ( $rules as $rule ) {
			if ( $date ) {
				if ( (int) $rule->holiday_only === 1 && ! $is_holiday ) {
					continue;
				}
				if ( ! empty( $rule->days_of_week ) ) {
					$allowed_days = explode( ',', $rule->days_of_week );
					if ( ! in_array( (string) $day_of_week, $allowed_days, true ) ) {
						continue;
					}
				}
			}
			return $rule;
		}

		return null;
	}
}
