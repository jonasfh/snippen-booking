<?php
/**
 * SMS Service Interface
 *
 * @package SnippenBooking\Service
 */

namespace SnippenBooking\Service;

/**
 * Interface for SMS services
 */
interface SmsServiceInterface {

	/**
	 * Send an SMS message
	 *
	 * @param string $to      The recipient phone number (E.164 format).
	 * @param string $message The message content.
	 * @return bool True on success, false on failure.
	 */
	public function send( string $to, string $message ): bool;
}
