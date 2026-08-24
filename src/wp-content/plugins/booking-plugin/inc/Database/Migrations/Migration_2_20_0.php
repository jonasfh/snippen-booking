<?php
/**
 * Migration 2.20.0
 *
 * @package SnippenBooking\Database\Migrations
 */

namespace SnippenBooking\Database\Migrations;

use SnippenBooking\Database\Repository\NotificationTemplateRepository;

/**
 * Migration 2.20.0
 * Create snippen_notification_templates table, seed defaults, and migrate legacy wp_options templates.
 */
class Migration_2_20_0 {

	/**
	 * Run migration
	 */
	public function up() {
		global $wpdb;

		$table           = $wpdb->prefix . 'snippen_notification_templates';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table (
			id BIGINT NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL,
			type VARCHAR(20) NOT NULL,
			title VARCHAR(255) NULL,
			message TEXT NOT NULL,
			connected_to VARCHAR(50) NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			modified_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY connected_type (connected_to, type)
		) $charset_collate;";

		if ( file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
		}

		$repository = new NotificationTemplateRepository();
		$repository->seed_defaults();

		// Migrate legacy options if present
		$legacy_options = array(
			'user_activation'      => array(
				'sms'   => 'snippen_template_user_activation_sms',
				'email' => 'snippen_template_user_activation_email',
			),
			'booking_confirmation' => array(
				'sms'   => 'snippen_template_booking_confirmation_sms',
				'email' => 'snippen_template_booking_confirmation_email',
			),
			'admin_booking'        => array(
				'sms'   => 'snippen_template_admin_booking_sms',
				'email' => 'snippen_template_admin_booking_email',
			),
			'password_reset'       => array(
				'sms'   => 'snippen_template_password_reset_sms',
				'email' => 'snippen_template_password_reset_email',
			),
		);

		foreach ( $legacy_options as $event => $channels ) {
			foreach ( $channels as $channel => $option_name ) {
				$custom_value = get_option( $option_name );
				if ( ! empty( $custom_value ) ) {
					$existing = $repository->find_by_connected_and_type( $event, $channel );
					if ( $existing ) {
						$repository->update(
							(int) $existing->id,
							array(
								'message' => $custom_value,
							)
						);
					}
					delete_option( $option_name );
				}
			}
		}
	}
}
