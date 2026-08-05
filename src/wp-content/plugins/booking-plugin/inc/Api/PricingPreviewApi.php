<?php

namespace SnippenBooking\Api;

use SnippenBooking\Helper\Capabilities;
use SnippenBooking\Database\Repository\PricingRuleRepository;
use SnippenBooking\Service\DiscountService;

/**
 * API for pricing preview in Admin
 */
class PricingPreviewApi {

	/**
	 * Register the API hooks
	 */
	public static function register() {
		add_action( 'wp_ajax_snippen_pricing_preview', array( __CLASS__, 'handle_preview' ) );
	}

	/**
	 * Handle the pricing preview request
	 */
	public static function handle_preview() {
		// Only admins can use the preview
		if ( ! Capabilities::can_manage_bookings() ) {
			wp_send_json_error( array( 'message' => __( 'Ingen tilgang', 'snippen-booking' ) ) );
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'snippen_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Ugyldig forespørsel', 'snippen-booking' ) ) );
		}

		$date      = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$object_id = isset( $_POST['object_id'] ) ? intval( $_POST['object_id'] ) : 0;
		$block_id  = isset( $_POST['block_id'] ) ? intval( $_POST['block_id'] ) : 0;

		if ( empty( $date ) || empty( $object_id ) || empty( $block_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Mangler påkrevde parametere', 'snippen-booking' ) ) );
		}

		$repository = new PricingRuleRepository();
		$rule       = $repository->find_matching_rule( $date, $object_id, $block_id );

		if ( $rule ) {
			$base_price = floatval( $rule->price );
			$discount_service = new DiscountService();
			$discount_info = $discount_service->applyDiscount( $base_price, array( $object_id ), array( $block_id ), $date );

			$final_price = $discount_info['final_price'];
			$discount_amount = $discount_info['discount_amount'];
			$discount_name = '';
			if ( $discount_info['discount_rule'] ) {
				$discount_name = $discount_info['discount_rule']->name;
				if ( $discount_info['discount_rule']->discount_type === 'percentage' ) {
					$discount_name .= ' (' . floatval( $discount_info['discount_rule']->discount_value ) . '%)';
				} elseif ( $discount_info['discount_rule']->discount_type === 'fixed_price' ) {
					$discount_name .= ' (' . sprintf( __( 'Fast pris %s kr', 'snippen-booking' ), floatval( $discount_info['discount_rule']->discount_value ) ) . ')';
				}
			}

			wp_send_json_success(
				array(
					'found'           => true,
					'rule_name'       => $rule->name,
					'rule_price'      => $base_price,
					'final_price'     => $final_price,
					'discount_amount' => $discount_amount,
					'discount_name'   => $discount_name,
					'priority'        => intval( $rule->priority ),
					'description'     => $rule->description,
				)
			);
		} else {
			wp_send_json_success(
				array(
					'found'   => false,
					'message' => __( 'Ingen prisregel funnet for denne kombinasjonen.', 'snippen-booking' ),
				)
			);
		}
	}
}
