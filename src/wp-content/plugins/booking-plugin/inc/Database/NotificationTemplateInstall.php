<?php
/**
 * Database Install and Migration for Notification Templates
 *
 * @package SnippenBooking\Database
 */

namespace SnippenBooking\Database;

/**
 * Installation helpers for notification templates
 */
class NotificationTemplateInstall {

	/**
	 * Initialize default templates on plugin activation
	 *
	 * This creates reasonable default templates in WordPress options
	 * if they don't already exist.
	 *
	 * @return void
	 */
	public static function initialize_defaults() {
		$template_service = new \SnippenBooking\Service\Notification\NotificationTemplateService();

		$event_types = array( 'user_activation', 'booking_confirmation', 'admin_booking' );
		$channels    = array( 'sms', 'email' );

		foreach ( $event_types as $event_type ) {
			foreach ( $channels as $channel ) {
				// Only create if not already present
				$option_key = "snippen_template_{$event_type}_{$channel}";
				if ( false === get_option( $option_key ) ) {
					$default = $template_service->get_default_template( $event_type, $channel );
					update_option( $option_key, $default );
				}
			}
		}
	}
}
