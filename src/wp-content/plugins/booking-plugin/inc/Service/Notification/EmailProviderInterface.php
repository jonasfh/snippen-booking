<?php
/**
 * Email Notification Provider Interface
 *
 * @package SnippenBooking\Service\Notification
 */

namespace SnippenBooking\Service\Notification;

/**
 * Interface EmailProviderInterface
 */
interface EmailProviderInterface extends NotificationProviderInterface {

	/**
	 * Send an email.
	 *
	 * @param string $to      Recipient email address.
	 * @param string $subject Subject.
	 * @param string $message HTML or plain text message body.
	 * @return bool True on success, false on failure.
	 */
	public function send_email( string $to, string $subject, string $message ): bool;
}
