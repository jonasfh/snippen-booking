<?php
/**
 * Vipps Service Business Logic Layer
 *
 * @package SnippenBooking\Service\Vipps
 */

namespace SnippenBooking\Service\Vipps;

/**
 * VippsService handles domain logic, reference generation, payment amount conversions,
 * and high-level operations on top of VippsClient.
 */
class VippsService {

	/**
	 * Vipps API Client
	 *
	 * @var VippsClient
	 */
	private $client;

	/**
	 * Constructor
	 *
	 * @param VippsClient|null $client Optional injected VippsClient instance.
	 */
	public function __construct( VippsClient $client = null ) {
		$this->client = $client ?: new VippsClient();
	}

	/**
	 * Get the underlying VippsClient instance.
	 *
	 * @return VippsClient
	 */
	public function get_client() {
		return $this->client;
	}

	/**
	 * Check if Vipps ePayment is enabled and fully configured.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		$enabled = get_option( 'snippen_vipps_enabled', 'no' );
		return ( 'yes' === $enabled ) && $this->client->is_configured();
	}

	/**
	 * Convert amount in NOK to øre (minor currency units).
	 *
	 * @param float|int|string $amount_nok Amount in NOK.
	 * @return int Amount in øre.
	 */
	public static function format_amount_to_ore( $amount_nok ) {
		return (int) round( (float) $amount_nok * 100 );
	}

	/**
	 * Generate a unique Vipps payment reference for a booking.
	 *
	 * Must match the regex ^[a-zA-Z0-9_-]{1,50}$
	 *
	 * @param int $booking_id Booking ID.
	 * @return string Unique reference string.
	 */
	public static function generate_reference( $booking_id ) {
		return sprintf( 'snippen-%d-%d-%d', (int) $booking_id, time(), wp_rand( 100, 999 ) );
	}

	/**
	 * Build return URL for customer after completing Vipps flow.
	 *
	 * @param string      $uuid     Booking UUID.
	 * @param string|null $base_url Optional base URL (defaults to home_url('/')).
	 * @return string Full return URL.
	 */
	public static function build_return_url( $uuid, $base_url = null ) {
		$base = ! empty( $base_url ) ? $base_url : home_url( '/' );
		return add_query_arg(
			array(
				'booking_uuid'     => sanitize_text_field( $uuid ),
				'payment_provider' => 'vipps',
			),
			$base
		);
	}

	/**
	 * Create and initiate a booking payment via Vipps ePayment v1.
	 *
	 * @param object           $booking     Booking object containing id and uuid.
	 * @param float|int|string $amount_nok  Amount in NOK.
	 * @param string|null      $phone       Optional customer phone number.
	 * @param string|null      $description Optional payment description.
	 * @param string|null      $return_url  Optional custom return URL.
	 * @return array|\WP_Error Result containing reference and redirectUrl or \WP_Error on failure.
	 */
	public function create_booking_payment( $booking, $amount_nok, $phone = null, $description = null, $return_url = null ) {
		if ( empty( $booking ) || empty( $booking->id ) || empty( $booking->uuid ) ) {
			return new \WP_Error( 'invalid_booking', __( 'Ugyldig bookingobjekt for Vipps-betaling.', 'snippen-booking' ) );
		}

		$amount_ore = self::format_amount_to_ore( $amount_nok );
		if ( $amount_ore <= 0 ) {
			return new \WP_Error( 'invalid_amount', __( 'Beløpet må være større enn 0 for Vipps-betaling.', 'snippen-booking' ) );
		}

		$reference = self::generate_reference( (int) $booking->id );

		$payload = array(
			'amount'             => array(
				'value'    => $amount_ore,
				'currency' => 'NOK',
			),
			'paymentMethod'      => array(
				'type' => 'WALLET',
			),
			'reference'          => $reference,
			'userFlow'           => 'WEB_REDIRECT',
			'returnUrl'          => $return_url ?: self::build_return_url( $booking->uuid ),
			'paymentDescription' => $description ?: sprintf(
				/* translators: %d: Booking ID */
				__( 'Booking #%d Snippen Samfunnshus', 'snippen-booking' ),
				(int) $booking->id
			),
		);

		if ( ! empty( $phone ) ) {
			// Strip spaces, dashes and plus sign for customer phone payload
			$clean_phone = preg_replace( '/[^0-9]/', '', (string) $phone );
			if ( strlen( $clean_phone ) === 8 ) {
				$clean_phone = '47' . $clean_phone;
			}
			if ( ! empty( $clean_phone ) ) {
				$payload['customer'] = array(
					'phoneNumber' => $clean_phone,
				);
			}
		}

		return $this->client->create_payment( $payload );
	}

	/**
	 * Get payment status for a reference.
	 *
	 * @param string $reference Vipps payment reference.
	 * @return array|\WP_Error
	 */
	public function get_booking_payment_status( $reference ) {
		return $this->client->get_payment( $reference );
	}

	/**
	 * Capture payment for a reference.
	 *
	 * @param string           $reference  Vipps payment reference.
	 * @param float|int|string $amount_nok Amount to capture in NOK.
	 * @return array|\WP_Error
	 */
	public function capture_booking_payment( $reference, $amount_nok ) {
		$amount_ore = self::format_amount_to_ore( $amount_nok );
		return $this->client->capture_payment( $reference, $amount_ore );
	}

	/**
	 * Cancel payment for a reference.
	 *
	 * @param string $reference Vipps payment reference.
	 * @return array|\WP_Error
	 */
	public function cancel_booking_payment( $reference ) {
		return $this->client->cancel_payment( $reference );
	}

	/**
	 * Cancel bookings that have had status 'pending_payment' for longer than specified minutes.
	 *
	 * @param int $older_than_minutes Time limit in minutes (default 30).
	 * @return int Number of cancelled bookings.
	 */
	public function cleanup_expired_pending_bookings( int $older_than_minutes = 30 ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_bookings';

		// Compare using MySQL datetime format
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $older_than_minutes * 60 ) );

		$expired = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, uuid, payment_notes FROM $table WHERE status = 'pending_payment' AND created_at < %s AND deleted_at IS NULL",
				$cutoff
			)
		);

		if ( empty( $expired ) ) {
			return 0;
		}

		$cancelled_count  = 0;
		$rejection_reason = __( 'Utløpt: Vipps-betaling ble ikke fullført innen 30 minutter.', 'snippen-booking' );

		foreach ( $expired as $booking ) {
			if ( ! empty( $booking->payment_notes ) && preg_match( '/snippen-\d+-\d+-\d+/', $booking->payment_notes, $matches ) ) {
				$this->cancel_booking_payment( $matches[0] );
			}

			$updated = $wpdb->update(
				$table,
				array(
					'status'           => 'cancelled',
					'rejection_reason' => $rejection_reason,
					'modified_at'      => current_time( 'mysql' ),
				),
				array( 'id' => $booking->id )
			);

			if ( false !== $updated ) {
				++$cancelled_count;
			}
		}

		return $cancelled_count;
	}
}
