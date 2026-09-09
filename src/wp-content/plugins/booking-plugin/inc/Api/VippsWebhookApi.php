<?php
/**
 * Vipps REST Webhook API
 *
 * @package SnippenBooking\Api
 */

namespace SnippenBooking\Api;

use SnippenBooking\Database\Repository\BookingRepository;
use SnippenBooking\Service\Vipps\VippsService;
use SnippenBooking\Service\Notification\NotificationManager;

/**
 * Handles incoming webhooks from Vipps ePayment.
 */
class VippsWebhookApi {

	const REST_NAMESPACE = 'snippen/v1';
	const REST_BASE      = 'vipps/webhook';

	/**
	 * Vipps service instance
	 *
	 * @var VippsService|null
	 */
	private static $vipps_service = null;

	/**
	 * Booking repository instance
	 *
	 * @var BookingRepository|null
	 */
	private static $booking_repository = null;

	/**
	 * Notification manager instance
	 *
	 * @var NotificationManager|null
	 */
	private static $notification_manager = null;

	/**
	 * Set dependencies for testing
	 *
	 * @param VippsService|null        $vipps_service
	 * @param BookingRepository|null   $booking_repository
	 * @param NotificationManager|null $notification_manager
	 */
	public static function set_dependencies(
		VippsService $vipps_service = null,
		BookingRepository $booking_repository = null,
		NotificationManager $notification_manager = null
	) {
		self::$vipps_service        = $vipps_service;
		self::$booking_repository   = $booking_repository;
		self::$notification_manager = $notification_manager;
	}

	/**
	 * Register REST API route
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register routes with WordPress REST API
	 */
	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE,
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'handle_webhook' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Handle incoming Vipps webhook request
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public static function handle_webhook( \WP_REST_Request $request ) {
		global $wpdb;

		$params = $request->get_json_params();
		if ( empty( $params ) || ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$reference = ! empty( $params['reference'] ) ? sanitize_text_field( $params['reference'] ) : '';
		if ( empty( $reference ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Missing reference.',
				),
				400
			);
		}

		// Extract booking ID from reference: snippen-{id}-{timestamp}-{rand}
		$booking_id = 0;
		if ( preg_match( '/^snippen-(\d+)-/', $reference, $matches ) ) {
			$booking_id = (int) $matches[1];
		}

		$booking_repo = self::$booking_repository ?: new BookingRepository();
		$booking      = $booking_id > 0 ? $booking_repo->find( $booking_id ) : null;

		if ( ! $booking ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Booking not found.',
				),
				404
			);
		}

		$event_name = ! empty( $params['name'] ) ? $params['name'] : ( ! empty( $params['eventType'] ) ? $params['eventType'] : '' );

		$vipps_service = self::$vipps_service ?: new VippsService();

		// Case 1: Payment authorized (or state AUTHORIZED)
		if ( 'epayments.payment.authorized' === $event_name || ( isset( $params['status'] ) && 'AUTHORIZED' === $params['status'] ) ) {
			// Idempotency check: if already confirmed and paid, do not re-process
			if ( 'confirmed' === $booking->status && 2 === (int) $booking->payment_status_id ) {
				return new \WP_REST_Response(
					array(
						'success' => true,
						'message' => 'Booking already confirmed.',
					),
					200
				);
			}

			// Verify actual payment state directly from Vipps ePayment API
			$payment_details = $vipps_service->get_booking_payment_status( $reference );
			if ( is_wp_error( $payment_details ) ) {
				error_log( 'Vipps webhook verification error: ' . $payment_details->get_error_message() );
				return new \WP_REST_Response(
					array(
						'success' => false,
						'message' => 'Failed to verify payment status with Vipps.',
					),
					500
				);
			}

			$vipps_state = ! empty( $payment_details['state'] ) ? $payment_details['state'] : '';
			if ( 'AUTHORIZED' === $vipps_state ) {
				// Capture the payment
				$capture_res = $vipps_service->capture_booking_payment( $reference, $booking->price );
				if ( is_wp_error( $capture_res ) ) {
					error_log( 'Vipps webhook capture error: ' . $capture_res->get_error_message() );
					return new \WP_REST_Response(
						array(
							'success' => false,
							'message' => 'Payment capture failed.',
						),
						500
					);
				}
			} elseif ( 'CAPTURED' !== $vipps_state ) {
				// Not authorized and not captured
				return new \WP_REST_Response(
					array(
						'success' => false,
						'message' => sprintf( 'Payment in unexpected state: %s', $vipps_state ),
					),
					400
				);
			}

			// Update booking to confirmed and PAID
			$table = $wpdb->prefix . 'snippen_bookings';
			$wpdb->update(
				$table,
				array(
					'status'             => 'confirmed',
					'payment_status_id'  => 2, // PAID
					'payment_notes'      => 'Vipps ref: ' . $reference,
					'payment_updated_at' => current_time( 'mysql' ),
					'modified_at'        => current_time( 'mysql' ),
				),
				array( 'id' => $booking->id )
			);

			// Send confirmation notifications to resident
			$notification_manager = self::$notification_manager ?: new NotificationManager();
			$notification_manager->send_booking_confirmed_notification( (int) $booking->id );

			return new \WP_REST_Response(
				array(
					'success' => true,
					'message' => 'Payment captured and booking confirmed.',
				),
				200
			);
		}

		// Case 2: Payment terminated, cancelled or expired
		if ( in_array( $event_name, array( 'epayments.payment.terminated', 'epayments.payment.cancelled', 'epayments.payment.expired' ), true ) ) {
			if ( 'pending_payment' === $booking->status ) {
				$table = $wpdb->prefix . 'snippen_bookings';
				$wpdb->update(
					$table,
					array(
						'status'           => 'cancelled',
						'rejection_reason' => __( 'Vipps-betaling ble avbrutt eller utløpt.', 'snippen-booking' ),
						'modified_at'      => current_time( 'mysql' ),
					),
					array( 'id' => $booking->id )
				);
			}

			return new \WP_REST_Response(
				array(
					'success' => true,
					'message' => 'Payment cancelled.',
				),
				200
			);
		}

		// Other informational webhook events (e.g. captured, refunded)
		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => 'Webhook received.',
			),
			200
		);
	}
}
