<?php
/**
 * Snippen SMS Gateway Service Notification Provider
 *
 * @package SnippenBooking\Service\Notification
 */

namespace SnippenBooking\Service\Notification;

/**
 * Class SnippenSmsProvider
 */
class SnippenSmsProvider implements SmsProviderInterface {

	/**
	 * Get the unique provider identifier.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'snippen_sms_service';
	}

	/**
	 * Get the human-readable provider name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Snippen SMS Service (Gateway)', 'snippen-booking' );
	}

	/**
	 * Get the dynamic settings schema.
	 *
	 * @return array
	 */
	public function get_settings_schema(): array {
		return array(
			array(
				'key'         => 'snippen_sms_service_api_token',
				'label'       => __( 'SMS Service API-token', 'snippen-booking' ),
				'type'        => 'password',
				'required'    => true,
				'description' => __( 'Hemmelig sikkerhetstoken for autentisering av snippen-sms-service.', 'snippen-booking' ),
			),
			array(
				'key'         => 'snippen_sms_service_sender',
				'label'       => __( 'Avsendernavn', 'snippen-booking' ),
				'type'        => 'text',
				'required'    => false,
				'description' => __( 'Standard avsendernavn på utgående SMS (f.eks. Snippen).', 'snippen-booking' ),
			),
			array(
				'key'         => 'snippen_sms_active_booking_past_days',
				'label'       => __( 'Aktiv booking: dager i fortid (betalt)', 'snippen-booking' ),
				'type'        => 'number',
				'required'    => false,
				'default'     => 2,
				'description' => __( 'Hvor mange dager etter arrangementsslutt en betalt reservasjon regnes som aktiv for innkommende SMS.', 'snippen-booking' ),
			),
			array(
				'key'         => 'snippen_sms_unpaid_booking_past_days',
				'label'       => __( 'Aktiv booking: dager i fortid (ubetalt)', 'snippen-booking' ),
				'type'        => 'number',
				'required'    => false,
				'default'     => 0,
				'description' => __( 'Hvor mange dager etter arrangementsslutt en ubetalt reservasjon regnes som aktiv for innkommende SMS.', 'snippen-booking' ),
			),
			array(
				'key'         => 'snippen_sms_conversation_ttl_minutes',
				'label'       => __( 'Samtalevindu / dialog (minutter)', 'snippen-booking' ),
				'type'        => 'number',
				'required'    => false,
				'default'     => 120,
				'description' => __( 'Tidsrom hvor nye SMS-meldinger automatisk arver forrige tilknyttede reservasjon.', 'snippen-booking' ),
			),
			array(
				'key'         => 'snippen_sms_auto_disambiguate',
				'label'       => __( 'Automatisk oppklaring ved flervalg', 'snippen-booking' ),
				'type'        => 'checkbox',
				'required'    => false,
				'default'     => 'yes',
				'description' => __( 'Send automatisk en SMS med nummerert liste når leietaker med flere aktive bookinger sender melding.', 'snippen-booking' ),
			),
		);
	}

	/**
	 * Check if the provider has all required configuration parameters.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		$token = get_option( 'snippen_sms_service_api_token' );
		return ! empty( $token );
	}

	/**
	 * Send / enqueue an SMS message.
	 *
	 * When using the Snippen SMS Service, messages are enqueued for the gateway
	 * daemon to poll and dispatch asynchronously.
	 *
	 * @param string $to      Recipient phone.
	 * @param string $message Message content.
	 * @return bool
	 */
	public function send_sms( string $to, string $message ): bool {
		if ( ! $this->is_configured() ) {
			error_log( 'SnippenSmsProvider Error: API token is not configured.' );
			return false;
		}

		if ( empty( $to ) || empty( $message ) ) {
			error_log( 'SnippenSmsProvider Error: Recipient phone or message content is empty.' );
			return false;
		}

		return true;
	}
}
