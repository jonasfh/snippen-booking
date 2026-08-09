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
			$table = $wpdb->prefix . 'snippen_booking_blocks';
		} elseif ( $entity_type === 'discount_rule' ) {
			$table = $wpdb->prefix . 'snippen_discount_rules';
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
