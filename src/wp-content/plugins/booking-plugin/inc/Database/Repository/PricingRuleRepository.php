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
	 * @return array
	 */
	public function find_all() {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_pricing_rules';
		return $wpdb->get_results(
			"SELECT * FROM $table WHERE deleted_at IS NULL ORDER BY priority DESC"
		);
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
			 ORDER BY r.priority DESC",
			...array_merge( $block_ids, $object_ids )
		);

		return $wpdb->get_results( $query );
	}
}
