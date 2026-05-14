<?php

namespace SnippenBooking\Service;

/**
 * Service for calculating prices based on objects and slots
 */
class PricingService {

    /**
     * Get price for a specific combination of objects and slot name
     * 
     * @param array $objectIds
     * @param string $slotName
     * @return float|null Price or null if no price found
     */
    public function getPrice($objectIds, $slotName) {
        global $wpdb;

        if (empty($objectIds)) return 0;

        $table_prices = $wpdb->prefix . 'snippen_prices';
        $table_price_objects = $wpdb->prefix . 'snippen_price_booking_objects';

        // Find prices that match the slot name
        $object_count = count($objectIds);
        $in_clause = implode(',', array_fill(0, $object_count, '%d'));

        $sql = "SELECT p.price 
             FROM $table_prices p
             JOIN (
                SELECT price_id 
                FROM $table_price_objects 
                GROUP BY price_id 
                HAVING COUNT(*) = %d
             ) count_check ON p.id = count_check.price_id
             WHERE p.slot_name = %s
             AND p.id IN (
                SELECT price_id 
                FROM $table_price_objects 
                WHERE booking_object_id IN ($in_clause)
                GROUP BY price_id 
                HAVING COUNT(*) = %d
             )
             LIMIT 1";

        $params = array_merge([$object_count, $slotName], $objectIds, [$object_count]);
        $query = $wpdb->prepare($sql, ...$params);

        $price = $wpdb->get_var($query);

        return $price !== null ? (float)$price : null;
    }
}
