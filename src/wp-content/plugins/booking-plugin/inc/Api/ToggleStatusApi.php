<?php

namespace SnippenBooking\Api;

use SnippenBooking\Helper\Capabilities;

/**
 * AJAX endpoint for toggling entity active status (Pricing rules, Time slots, Discount rules)
 */
class ToggleStatusApi {

	public static function register() {
		add_action( 'wp_ajax_snippen_toggle_entity_status', array( __CLASS__, 'handle_toggle' ) );
	}

	public static function handle_toggle() {
		if ( ! Capabilities::can_manage_bookings() ) {
			wp_send_json_error( array( 'message' => __( 'Du har ikke tilgang til å utføre denne handlingen.', 'snippen-booking' ) ) );
		}

		if ( ! check_ajax_referer( 'snippen_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Ugyldig sikkerhets-token.', 'snippen-booking' ) ) );
		}

		$entity_type = isset( $_POST['entity_type'] ) ? sanitize_text_field( $_POST['entity_type'] ) : '';
		$id          = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		$is_active   = isset( $_POST['is_active'] ) ? intval( $_POST['is_active'] ) : 0;

		if ( ! $id || ! in_array( $entity_type, array( 'pricing_rule', 'time_slot', 'discount_rule' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Ugyldige parametere.', 'snippen-booking' ) ) );
		}

		global $wpdb;

		if ( $entity_type === 'pricing_rule' ) {
			$table = $wpdb->prefix . 'snippen_pricing_rules';
		} elseif ( $entity_type === 'time_slot' ) {
			$table = $wpdb->prefix . 'snippen_time_slots';
		} elseif ( $entity_type === 'discount_rule' ) {
			$table = $wpdb->prefix . 'snippen_discount_rules';
		}

		// Validation when activating a time slot to prevent overlaps
		if ( $entity_type === 'time_slot' && $is_active === 1 ) {
			$slot = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d AND deleted_at IS NULL", $id ) );
			if ( $slot ) {
				$object_ids = $wpdb->get_col( $wpdb->prepare( "SELECT booking_object_id FROM {$wpdb->prefix}snippen_time_slot_booking_objects WHERE time_slot_id = %d", $id ) );
				if ( ! empty( $object_ids ) ) {
					// Check if activating this slot causes an overlap with another active slot
					$placeholders = implode( ',', array_fill( 0, count( $object_ids ), '%d' ) );
					$query        = "SELECT s.id FROM $table s
					          JOIN {$wpdb->prefix}snippen_time_slot_booking_objects tso ON s.id = tso.time_slot_id
					          WHERE tso.booking_object_id IN ($placeholders)
					          AND s.deleted_at IS NULL
					          AND s.id != %d
					          AND (s.is_active IS NULL OR s.is_active = 1)
					          AND (
					            (s.start_time < %s AND s.end_time > %s) OR
					            (s.start_time >= %s AND s.start_time < %s)
					          )";
					$args         = array_merge( $object_ids, array( $id, $slot->end_time, $slot->start_time, $slot->start_time, $slot->end_time ) );
					$overlapping  = $wpdb->get_results( $wpdb->prepare( $query, ...$args ) );

					if ( ! empty( $overlapping ) ) {
						// Check days overlap
						$slot_days    = empty( $slot->days_of_week ) ? array() : explode( ',', $slot->days_of_week );
						$has_conflict = false;
						foreach ( $overlapping as $ov ) {
							$ov_slot = $wpdb->get_row( $wpdb->prepare( "SELECT days_of_week FROM $table WHERE id = %d", $ov->id ) );
							$ov_days = empty( $ov_slot->days_of_week ) ? array() : explode( ',', $ov_slot->days_of_week );

							if ( empty( $slot_days ) || empty( $ov_days ) || ! empty( array_intersect( $slot_days, $ov_days ) ) ) {
								$has_conflict = true;
								break;
							}
						}

						if ( $has_conflict ) {
							wp_send_json_error( array( 'message' => __( 'Kan ikke aktivere tidsluke: Den overlapper med en annen aktiv tidsluke.', 'snippen-booking' ) ) );
						}
					}
				}
			}
		}

		$updated = $wpdb->update(
			$table,
			array(
				'is_active'   => $is_active,
				'modified_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);

		if ( $updated === false ) {
			wp_send_json_error( array( 'message' => __( 'Klarte ikke å oppdatere status.', 'snippen-booking' ) ) );
		}

		wp_send_json_success(
			array(
				'message'   => __( 'Status oppdatert.', 'snippen-booking' ),
				'is_active' => $is_active,
			)
		);
	}
}
