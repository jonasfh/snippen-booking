<?php
/**
 * SMS Notification Provider Interface
 *
 * @package SnippenBooking\Service\Notification
 */

namespace SnippenBooking\Service\Notification;

/**
 * Interface SmsProviderInterface
 */
interface SmsProviderInterface extends NotificationProviderInterface {

	/**
	 * Send an SMS message.
	 *
	 * @param string $to      Recipient phone number.
	 * @param string $message Message content.
	 * @return bool True on success, false on failure.
	 */
	public function send_sms( string $to, string $message ): bool;
}
