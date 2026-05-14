<?php

namespace SnippenBooking\Service;

/**
 * Service for calculating prices based on objects and slots
 */
class PricingService {

    /**
     * Get price for a specific combination of objects, slot IDs and date
     * 
     * @param array $objectIds
     * @param array|int $slotIds One or more slot IDs that represent the time slot for the objects
     * @param string $date YYYY-MM-DD
     * @return float|null Price or null if no price found
     */
    public function getPrice($objectIds, $slotIds, $date = null) {
        global $wpdb;

        if (empty($objectIds)) return 0;
        if (!$date) $date = date('Y-m-d');

        if (!is_array($slotIds)) $slotIds = [$slotIds];
        $slotIds = array_map('intval', $slotIds);

        $holiday_service = new HolidayService();
        $is_holiday = $holiday_service->isHoliday($date);
        $day_of_week = date('w', strtotime($date)); // 0 (Sun) to 6 (Sat)

        $table_prices = $wpdb->prefix . 'snippen_prices';
        $table_price_objects = $wpdb->prefix . 'snippen_price_booking_objects';

        $object_count = count($objectIds);
        $obj_in_clause = implode(',', array_fill(0, $object_count, '%d'));
        $slot_in_clause = implode(',', array_fill(0, count($slotIds), '%d'));

        $params = array_merge([$object_count], $slotIds, $objectIds, [$object_count]);
        
        // Find prices that match EXACTLY or by NAME if multi-object
        // First, get the names of the requested slots
        $slot_names = $wpdb->get_col("SELECT name FROM {$wpdb->prefix}snippen_time_slots WHERE id IN (" . implode(',', $slotIds) . ")");
        $slot_name_clause = "";
        if (!empty($slot_names)) {
            $name_placeholders = implode(',', array_fill(0, count($slot_names), '%s'));
            $slot_name_clause = "OR p.slot_id IN (SELECT id FROM {$wpdb->prefix}snippen_time_slots WHERE name IN ($name_placeholders))";
            $params = array_merge([$object_count], $slotIds, $slot_names, $objectIds, [$object_count]);
        }

        $query = $wpdb->prepare(
            "SELECT p.* 
             FROM $table_prices p
             JOIN (
                SELECT price_id 
                FROM $table_price_objects 
                GROUP BY price_id 
                HAVING COUNT(*) = %d
             ) count_check ON p.id = count_check.price_id
             WHERE (p.slot_id IN ($slot_in_clause) $slot_name_clause)
             AND p.id IN (
                SELECT price_id 
                FROM $table_price_objects 
                WHERE booking_object_id IN ($obj_in_clause)
                GROUP BY price_id 
                HAVING COUNT(*) = %d
             )",
            ...$params
        );

        $prices = $wpdb->get_results($query);
        
        if (empty($prices)) return null;

        // Filter and find the best match based on priority
        $best_price = null;
        $max_priority = -1;

        foreach ($prices as $p) {
            // Check holiday
            if ($p->is_holiday && !$is_holiday) continue;
            
            // Check days of week
            if ($p->days_of_week !== null && $p->days_of_week !== '') {
                $allowed_days = explode(',', $p->days_of_week);
                if (!in_array((string)$day_of_week, $allowed_days)) continue;
            }

            // Check date range
            if ($p->date_start && $date < $p->date_start) continue;
            if ($p->date_end && $date > $p->date_end) continue;

            // If we are here, the price is a candidate
            if ((int)$p->priority > $max_priority) {
                $max_priority = (int)$p->priority;
                $best_price = (float)$p->price;
            }
        }

        return $best_price;
    }
}
