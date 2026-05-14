<?php

namespace SnippenBooking\Service;

/**
 * Service for identifying Norwegian holidays
 */
class HolidayService {

    /**
     * Check if a given date is a Norwegian holiday
     * 
     * @param string $date YYYY-MM-DD
     * @return bool
     */
    public function isHoliday($date) {
        $timestamp = strtotime($date);
        $year = (int)date('Y', $timestamp);
        $md = date('m-d', $timestamp);

        // Fixed holidays
        $fixed = [
            '01-01', // Nyttårsdag
            '05-01', // 1. mai
            '05-17', // 17. mai
            '12-25', // 1. juledag
            '12-26', // 2. juledag
        ];

        if (in_array($md, $fixed)) return true;

        // Moving holidays (Easter based)
        $easter = easter_date($year);
        
        $moving = [
            date('m-d', $easter - 3 * 86400), // Skjærtorsdag
            date('m-d', $easter - 2 * 86400), // Langfredag
            date('m-d', $easter),             // 1. påskedag
            date('m-d', $easter + 1 * 86400), // 2. påskedag
            date('m-d', $easter + 39 * 86400), // Kr. Himmelfart
            date('m-d', $easter + 49 * 86400), // 1. pinsedag
            date('m-d', $easter + 50 * 86400), // 2. pinsedag
        ];

        if (in_array($md, $moving)) return true;

        return false;
    }
}
