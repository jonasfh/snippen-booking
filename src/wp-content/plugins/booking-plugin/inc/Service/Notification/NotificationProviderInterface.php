<?php
/**
 * Base Notification Provider Interface
 *
 * @package SnippenBooking\Service\Notification
 */

namespace SnippenBooking\Service\Notification;

/**
 * Interface NotificationProviderInterface
 */
interface NotificationProviderInterface {

	/**
	 * Get the unique provider identifier.
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Get the human-readable provider name.
	 *
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * Get the dynamic settings schema.
	 *
	 * @return array
	 */
	public function get_settings_schema(): array;

	/**
	 * Check if the provider has all required configuration parameters.
	 *
	 * @return bool
	 */
	public function is_configured(): bool;
}
