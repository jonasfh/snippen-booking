<?php
/**
 * Migration 2.42.0
 *
 * @package SnippenBooking\Database\Migrations
 */

namespace SnippenBooking\Database\Migrations;

/**
 * Migration 2.42.0
 * Automatically enable snippen_sms_booking_confirmed_enabled if snippen_sms_booking_confirmation_enabled is active.
 */
class Migration_2_42_0 {

	/**
	 * Run migration
	 */
	public function up() {
		if ( 'yes' === get_option( 'snippen_sms_booking_confirmation_enabled', 'no' ) ) {
			update_option( 'snippen_sms_booking_confirmed_enabled', 'yes' );
		}
	}
}
