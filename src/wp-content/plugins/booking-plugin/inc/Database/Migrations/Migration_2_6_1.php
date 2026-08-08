<?php

namespace SnippenBooking\Database\Migrations;

/**
 * Migration 2.6.1
 * Removes obsolete PENDING_VERIFICATION payment status and cleans up database references.
 */
class Migration_2_6_1 {

	public function up() {
		global $wpdb;

		$table_payment_statuses = $wpdb->prefix . 'snippen_payment_statuses';
		$table_bookings         = $wpdb->prefix . 'snippen_bookings';

		// 1. Find ID of PENDING_VERIFICATION if it exists
		$pending_id = $wpdb->get_var( "SELECT id FROM $table_payment_statuses WHERE slug = 'PENDING_VERIFICATION'" );

		if ( $pending_id ) {
			// Update any bookings using this status back to UNPAID (1)
			$wpdb->query( $wpdb->prepare( "UPDATE $table_bookings SET payment_status_id = 1 WHERE payment_status_id = %d", (int) $pending_id ) );

			// Delete PENDING_VERIFICATION row
			$wpdb->delete( $table_payment_statuses, array( 'slug' => 'PENDING_VERIFICATION' ) );
		}

		// 2. Ensure canonical status IDs (1 = UNPAID, 2 = PAID, 3 = EXEMPT)
		$statuses = array(
			array( 'id' => 1, 'slug' => 'UNPAID', 'name' => 'Mangler betaling', 'is_settled' => 0 ),
			array( 'id' => 2, 'slug' => 'PAID', 'name' => 'Betalt', 'is_settled' => 1 ),
			array( 'id' => 3, 'slug' => 'EXEMPT', 'name' => 'Fritatt / Gratis', 'is_settled' => 1 ),
		);

		foreach ( $statuses as $st ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_payment_statuses WHERE slug = %s", $st['slug'] ) );
			if ( ! $exists ) {
				$wpdb->insert( $table_payment_statuses, $st );
			} else {
				$wpdb->update( $table_payment_statuses, array( 'id' => $st['id'], 'name' => $st['name'], 'is_settled' => $st['is_settled'] ), array( 'slug' => $st['slug'] ) );
			}
		}

		// 3. Remove any left-over rows with IDs higher than 3 that aren't valid
		$wpdb->query( "DELETE FROM $table_payment_statuses WHERE id > 3 AND slug NOT IN ('UNPAID', 'PAID', 'EXEMPT')" );
	}
}
