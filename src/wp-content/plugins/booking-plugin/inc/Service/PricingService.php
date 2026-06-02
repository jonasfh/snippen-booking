<?php

namespace SnippenBooking\Service;

/**
 * Service for calculating prices based on objects and slots
 */
class PricingService {

	/**
	 * Get price for a specific combination of objects, slot IDs and date
	 *
	 * @param array     $objectIds
	 * @param array|int $slotIds One or more slot IDs that represent the time slot for the objects
	 * @param string    $date YYYY-MM-DD
	 * @return float|null Price or null if no price found
	 */
	public function getPrice( $objectIds, $slotIds, $date = null ) {
		global $wpdb;

		if ( empty( $slotIds ) ) {
			return 0;
		}

		if ( ! is_array( $slotIds ) ) {
			$slotIds = array( $slotIds );
		}
		$slotIds        = array_map( 'intval', $slotIds );
		$slot_in_clause = implode( ',', array_fill( 0, count( $slotIds ), '%d' ) );

		$table_prices = $wpdb->prefix . 'snippen_prices';

		$table_slots  = $wpdb->prefix . 'snippen_time_slots';

		$query = $wpdb->prepare(
			"SELECT p.* 
             FROM $table_prices p
             JOIN $table_slots s ON s.price_id = p.id
             WHERE s.id IN ($slot_in_clause)",
			...$slotIds
		);

		$prices = $wpdb->get_results( $query );

		if ( empty( $prices ) ) {

			return null;
		}

		// Filter and find the best match based on priority
		$best_price   = null;
		$max_priority = -1;

		foreach ( $prices as $p ) {
			// If we are here, the price is a candidate
			if ( (int) $p->priority > $max_priority ) {
				$max_priority = (int) $p->priority;
				$best_price   = (float) $p->price;
			}
		}

		return $best_price;
	}
}
