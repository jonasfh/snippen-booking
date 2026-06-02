<?php

namespace SnippenBooking\Service;

/**
 * Service for identifying Norwegian holidays
 */
class HolidayService {

	/**
	 * Check if a given date is a Norwegian holiday.
	 *
	 * @param string $date YYYY-MM-DD.
	 * @return bool
	 */
	public function isHoliday( $date ) {
		$timestamp = strtotime( $date );
		$year      = (int) date( 'Y', $timestamp );
		$md        = date( 'm-d', $timestamp );

		// Fixed holidays.
		$fixed = array(
			'01-01', // Nyttårsdag.
			'05-01', // 1. mai.
			'05-17', // 17. mai.
			'12-24', // Julaften (treated as holiday for booking purposes).
			'12-25', // 1. juledag.
			'12-26', // 2. juledag.
			'12-31', // Nyttårsaften (treated as holiday for booking purposes).
		);

		if ( in_array( $md, $fixed, true ) ) {
			return true;
		}

		// Moving holidays (Easter based).
		$days   = easter_days( $year );
		$easter = new \DateTimeImmutable( "$year-03-21 UTC" );
		$easter = $easter->modify( "+$days days" );

		$moving = array(
			$easter->modify( '-3 days' )->format( 'm-d' ), // Skjærtorsdag.
			$easter->modify( '-2 days' )->format( 'm-d' ), // Langfredag.
			$easter->format( 'm-d' ),                      // 1. påskedag.
			$easter->modify( '+1 day' )->format( 'm-d' ),  // 2. påskedag.
			$easter->modify( '+39 days' )->format( 'm-d' ), // Kr. Himmelfart.
			$easter->modify( '+49 days' )->format( 'm-d' ), // 1. pinsedag.
			$easter->modify( '+50 days' )->format( 'm-d' ), // 2. pinsedag.
		);

		if ( in_array( $md, $moving, true ) ) {
			return true;
		}

		return false;
	}
}
